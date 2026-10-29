<?php

namespace App\Services;

use App\Models\AccountingAccount;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PartyAgeingService extends PartyStatementService
{
    public const BUCKETS = [
        'current' => 'Not overdue (includes due today)',
        '1_30' => '1–30 days overdue',
        '31_60' => '31–60 days overdue',
        '61_90' => '61–90 days overdue',
        '91_plus' => '91+ days overdue',
        'unknown' => 'Due date unknown',
    ];

    public function ageing(bool $isCustomer, string $asOf, ?int $partyId = null, int $page = 1): array
    {
        Validator::make(['as_of' => $asOf, 'party_id' => $partyId, 'page' => $page], [
            'as_of' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now()->utc()->toDateString()],
            'party_id' => ['nullable', 'integer', 'min:1', 'exists:'.($isCustomer ? 'customers' : 'suppliers').',id'],
            'page' => ['integer', 'min:1', 'max:1000000'],
        ])->validate();

        return DB::transaction(function () use ($isCustomer, $asOf, $partyId, $page) {
            $accountCode = $isCustomer ? '1200' : '2100';
            $account = AccountingAccount::where('code', $accountCode)->first();
            $sources = $this->sources($isCustomer);
            $amounts = DB::table('journal_lines as l')
                ->join('accounting_accounts as a', 'a.id', '=', 'l.accounting_account_id')
                ->where('a.code', $accountCode)->groupBy('l.journal_entry_id')->select('l.journal_entry_id')
                ->selectRaw('SUM(CAST(ROUND(l.debit * 100, 0) AS SIGNED)) as debit_cents')
                ->selectRaw('SUM(CAST(ROUND(l.credit * 100, 0) AS SIGNED)) as credit_cents');
            $documents = [];
            $controlBalanceCents = $attributedBalanceCents = $unassignedBalanceCents = $unassignedCount = 0;

            // Payments settle their explicitly linked document, never another invoice by FIFO.
            // Only posted control lines through the cutoff count, including reversal chains.
            $this->metadataQuery($sources)
                ->joinSub($amounts, 'amounts', 'amounts.journal_entry_id', '=', 'e.id')
                ->addSelect('amounts.debit_cents', 'amounts.credit_cents')
                ->where('e.status', 'posted')->whereDate('e.entry_date', '<=', $asOf)
                ->orderBy('e.id')->chunk(500, function ($entries) use ($sources, $isCustomer, $partyId,
                    &$documents, &$controlBalanceCents, &$attributedBalanceCents, &$unassignedBalanceCents, &$unassignedCount) {
                    $metadata = $entries->keyBy('id')->all();
                    $this->loadAncestors($metadata, $sources);
                    foreach ($entries as $entry) {
                        $change = $isCustomer
                            ? (int) $entry->debit_cents - (int) $entry->credit_cents
                            : (int) $entry->credit_cents - (int) $entry->debit_cents;
                        $controlBalanceCents += $change;
                        $owner = $this->attribute($entry, $metadata, true);
                        if ($owner === null) {
                            $unassignedCount++;
                            $unassignedBalanceCents += $change;
                            continue;
                        }
                        $attributedBalanceCents += $change;
                        if ($partyId !== null && $owner['party_id'] !== $partyId) {
                            continue;
                        }
                        $key = $owner['document_id'];
                        $documents[$key] ??= $owner + ['balance_cents' => 0];
                        $documents[$key]['balance_cents'] += $change;
                    }
                });

            $bucketLabels = self::BUCKETS;
            $totals = array_fill_keys(array_keys($bucketLabels), 0);
            $creditCents = $outstandingCents = $netCents = 0;
            $cutoff = CarbonImmutable::parse($asOf, 'UTC');
            $allRows = collect();
            foreach ($documents as $document) {
                $balance = $document['balance_cents'];
                if ($balance === 0) {
                    continue;
                }
                $days = $document['due_date'] === null ? null
                    : (int) CarbonImmutable::parse($document['due_date'], 'UTC')->diffInDays($cutoff);
                $bucket = match (true) {
                    $balance < 0 => 'credit',
                    $days === null => 'unknown',
                    $days <= 0 => 'current',
                    $days <= 30 => '1_30',
                    $days <= 60 => '31_60',
                    $days <= 90 => '61_90',
                    default => '91_plus',
                };
                $netCents += $balance;
                if ($balance < 0) {
                    $creditCents += $balance; // Signed credits/debit balances stay separate from overdue debt.
                } else {
                    $outstandingCents += $balance;
                    $totals[$bucket] += $balance;
                }
                $allRows->push((object) ($document + ['bucket' => $bucket, 'days_overdue' => $days === null ? null : max(0, $days)]));
            }
            $allRows = $allRows->sortBy([['party_name', 'asc'], ['party_id', 'asc'], ['document_id', 'asc']])->values();
            $rows = (new LengthAwarePaginator($allRows->slice(($page - 1) * 25, 25)->values(), $allRows->count(), 25, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]))->withQueryString();
            $issues = [];
            if (! $account || $account->type !== ($isCustomer ? 'asset' : 'liability')) {
                $issues[] = 'The control account is missing or has an invalid type. Review the chart of accounts.';
            }
            if ($unassignedCount > 0) {
                $issues[] = 'Some posted control entries have missing or conflicting document links. Their amounts are excluded from ageing and require review.';
            }

            return compact('isCustomer', 'asOf', 'partyId', 'accountCode', 'rows', 'bucketLabels', 'totals',
                'outstandingCents', 'creditCents', 'netCents', 'controlBalanceCents', 'attributedBalanceCents',
                'unassignedBalanceCents', 'unassignedCount', 'issues');
        });
    }
}
