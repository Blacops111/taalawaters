<?php

namespace App\Services;

use App\Models\{AccountingAccount, Customer, CustomerPayment, PurchaseReceipt, SalesOrder, SalesOrderReversal, Supplier, SupplierPayment};
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PartyStatementService
{
    public function report(Customer|Supplier $party, string $dateFrom, string $dateTo, int $page = 1): array
    {
        Validator::make(['date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $page], [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['integer', 'min:1', 'max:1000000'],
        ])->validate();

        return DB::transaction(function () use ($party, $dateFrom, $dateTo, $page) {
            $isCustomer = $party instanceof Customer;
            $accountCode = $isCustomer ? '1200' : '2100';
            $account = AccountingAccount::where('code', $accountCode)->first();
            $sources = $this->sources($isCustomer);
            $amounts = DB::table('journal_lines as lines')
                ->join('accounting_accounts as accounts', 'accounts.id', '=', 'lines.accounting_account_id')
                ->where('accounts.code', $accountCode)->groupBy('lines.journal_entry_id')
                ->select('lines.journal_entry_id')
                ->selectRaw('SUM(CAST(ROUND(lines.debit * 100, 0) AS SIGNED)) as debit_cents')
                ->selectRaw('SUM(CAST(ROUND(lines.credit * 100, 0) AS SIGNED)) as credit_cents');

            $openingCents = $closingCents = $periodDebitCents = $periodCreditCents = 0;
            $controlBalanceCents = $attributedBalanceCents = $unassignedBalanceCents = $unassignedCount = 0;
            $transactionCount = 0;
            $pageRows = collect();
            $pageOpeningCents = null;
            $offset = ($page - 1) * 25;

            // Chunk the ledger rather than loading every party's history into memory.
            // Totals and running balances span the full range, not just the displayed page.
            $this->metadataQuery($sources)
                ->joinSub($amounts, 'amounts', 'amounts.journal_entry_id', '=', 'e.id')
                ->addSelect('amounts.debit_cents', 'amounts.credit_cents')
                ->where('e.status', 'posted')->whereDate('e.entry_date', '<=', $dateTo)
                ->orderByRaw('DATE(e.entry_date)')->orderBy('e.id')
                ->chunk(500, function ($entries) use ($sources, $party, $isCustomer, $dateFrom, $offset,
                    &$openingCents, &$closingCents, &$periodDebitCents, &$periodCreditCents,
                    &$controlBalanceCents, &$attributedBalanceCents, &$unassignedBalanceCents, &$unassignedCount,
                    &$transactionCount, &$pageOpeningCents, $pageRows) {
                    $metadata = $entries->keyBy('id')->all();
                    $this->loadAncestors($metadata, $sources);
                    foreach ($entries as $entry) {
                        $debit = (int) $entry->debit_cents;
                        $credit = (int) $entry->credit_cents;
                        $change = $isCustomer ? $debit - $credit : $credit - $debit;
                        $controlBalanceCents += $change;
                        $attribution = $this->attribute($entry, $metadata);
                        if ($attribution === null) {
                            $unassignedCount++;
                            $unassignedBalanceCents += $change;
                            continue;
                        }
                        $attributedBalanceCents += $change;
                        if ($attribution['party_id'] !== $party->id) {
                            continue;
                        }
                        $date = substr((string) $entry->entry_date, 0, 10);
                        if ($date < $dateFrom) {
                            $openingCents += $change;
                            $closingCents += $change;
                            continue;
                        }
                        $before = $closingCents;
                        $closingCents += $change;
                        $periodDebitCents += $debit;
                        $periodCreditCents += $credit;
                        if ($transactionCount >= $offset && $transactionCount < $offset + 25) {
                            $pageOpeningCents ??= $before;
                            $pageRows->push((object) [
                                'id' => $entry->id, 'date' => $date, 'journal_reference' => $entry->reference,
                                'document_reference' => $attribution['document_reference'],
                                'kind' => $entry->reversal_of_id !== null && $entry->mapped_source_id === null
                                    ? 'Journal reversal' : $attribution['kind'],
                                'external_reference' => $attribution['external_reference'],
                                'description' => $entry->description, 'debit_cents' => $debit, 'credit_cents' => $credit,
                                'balance_cents' => $closingCents,
                            ]);
                        }
                        $transactionCount++;
                    }
                });

            $pageOpeningCents ??= $closingCents;
            $rows = (new LengthAwarePaginator($pageRows, $transactionCount, 25, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]))->withQueryString();
            $issues = [];
            if (! $account || $account->type !== ($isCustomer ? 'asset' : 'liability')) {
                $issues[] = 'Control account '.$accountCode.' is missing or has an invalid account type. Review the chart of accounts before relying on these balances.';
            }
            if ($unassignedCount > 0) {
                $issues[] = 'Some posted control-account entries cannot be safely linked to a '.($isCustomer ? 'customer' : 'supplier').'. They are excluded from individual statements. Review their source documents and reversal links.';
            }

            return compact('party', 'isCustomer', 'accountCode', 'dateFrom', 'dateTo', 'rows',
                'openingCents', 'closingCents', 'periodDebitCents', 'periodCreditCents', 'pageOpeningCents',
                'controlBalanceCents', 'attributedBalanceCents', 'unassignedBalanceCents', 'unassignedCount', 'issues');
        });
    }

    /** One source row per document. Never infer a party from free-text journal descriptions. */
    private function sources(bool $customer): Builder
    {
        if ($customer) {
            $sales = DB::table('sales_orders as s')->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
                ->where('s.sale_type', SalesOrder::TYPE_BUSINESS)
                ->selectRaw('? as source_type, s.id as source_id, c.id as party_id, s.reference as document_reference, ? as kind, NULL as external_reference', [(new SalesOrder)->getMorphClass(), 'Sale']);
            $payments = DB::table('customer_payments as p')->leftJoin('customers as c', 'c.id', '=', 'p.customer_id')
                ->leftJoin('sales_orders as s', 's.id', '=', 'p.sales_order_id')
                ->selectRaw('? as source_type, p.id as source_id, CASE WHEN s.customer_id = p.customer_id AND s.sale_type = ? THEN c.id ELSE NULL END as party_id, p.reference as document_reference, ? as kind, p.external_reference', [(new CustomerPayment)->getMorphClass(), SalesOrder::TYPE_BUSINESS, 'Customer payment']);
            $reversals = DB::table('sales_order_reversals as r')->leftJoin('sales_orders as s', 's.id', '=', 'r.sales_order_id')
                ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
                ->selectRaw('? as source_type, r.id as source_id, CASE WHEN s.sale_type = ? THEN c.id ELSE NULL END as party_id, r.reference as document_reference, ? as kind, NULL as external_reference', [(new SalesOrderReversal)->getMorphClass(), SalesOrder::TYPE_BUSINESS, 'Sale reversal']);

            return $sales->unionAll($payments)->unionAll($reversals);
        }

        $receipts = DB::table('purchase_receipts as r')->leftJoin('purchase_requests as q', 'q.id', '=', 'r.purchase_request_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'q.supplier_id')
            ->selectRaw('? as source_type, r.id as source_id, s.id as party_id, r.reference as document_reference, ? as kind, r.supplier_delivery_reference as external_reference', [(new PurchaseReceipt)->getMorphClass(), 'Goods receipt']);
        $payments = DB::table('supplier_payments as p')->leftJoin('suppliers as s', 's.id', '=', 'p.supplier_id')
            ->leftJoin('purchase_receipts as r', 'r.id', '=', 'p.purchase_receipt_id')
            ->leftJoin('purchase_requests as q', 'q.id', '=', 'r.purchase_request_id')
            ->selectRaw('? as source_type, p.id as source_id, CASE WHEN q.supplier_id = p.supplier_id THEN s.id ELSE NULL END as party_id, p.reference as document_reference, ? as kind, p.external_reference', [(new SupplierPayment)->getMorphClass(), 'Supplier payment']);

        return $receipts->unionAll($payments);
    }

    private function metadataQuery(Builder $sources): Builder
    {
        return DB::table('journal_entries as e')->leftJoinSub($sources, 'sources', function ($join) {
            $join->on('sources.source_type', '=', 'e.source_type')->on('sources.source_id', '=', 'e.source_id');
        })->select('e.id', 'e.reference', 'e.entry_date', 'e.status', 'e.description', 'e.reversal_of_id',
            'sources.source_id as mapped_source_id', 'sources.party_id', 'sources.document_reference', 'sources.kind', 'sources.external_reference');
    }

    /** Reversal parents may be outside the date range or have no control-account lines. */
    private function loadAncestors(array &$metadata, Builder $sources): void
    {
        $frontier = array_values($metadata);
        while ($frontier !== []) {
            $missing = [];
            foreach ($frontier as $entry) {
                if ($entry->reversal_of_id !== null && ! array_key_exists($entry->reversal_of_id, $metadata)) {
                    $missing[(int) $entry->reversal_of_id] = true;
                }
            }
            if ($missing === []) {
                return;
            }
            $frontier = [];
            foreach (array_chunk(array_keys($missing), 500) as $ids) {
                foreach ($ids as $id) {
                    $metadata[$id] = null; // Missing parents must not cause an endless query loop.
                }
                foreach ($this->metadataQuery($sources)->whereIn('e.id', $ids)->get() as $entry) {
                    $metadata[$entry->id] = $entry;
                    $frontier[] = $entry;
                }
            }
        }
    }

    /** Every known source in a reversal chain must agree; broken/cyclic links stay unassigned. */
    private function attribute(object $entry, array $metadata): ?array
    {
        $seen = [];
        $attribution = null;
        while (true) {
            if (isset($seen[$entry->id]) || $entry->status !== 'posted') {
                return null;
            }
            $seen[$entry->id] = true;
            if ($entry->mapped_source_id !== null) {
                if ($entry->party_id === null || ($attribution !== null && $attribution['party_id'] !== (int) $entry->party_id)) {
                    return null;
                }
                $attribution ??= [
                    'party_id' => (int) $entry->party_id, 'document_reference' => $entry->document_reference,
                    'kind' => $entry->kind, 'external_reference' => $entry->external_reference,
                ];
            }
            if ($entry->reversal_of_id === null) {
                // A known child does not invent an owner for an unlinked original journal.
                return $entry->mapped_source_id !== null ? $attribution : null;
            }
            $entry = $metadata[$entry->reversal_of_id] ?? null;
            if ($entry === null) {
                return null;
            }
        }
    }
}
