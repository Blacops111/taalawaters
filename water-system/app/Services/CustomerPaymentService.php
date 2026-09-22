<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\CustomerPayment;
use App\Models\JournalEntry;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerPaymentService
{
    public const ACCOUNTS_RECEIVABLE_CODE = '1200';
    public const CASH_ON_HAND_CODE = '1100';
    public const BANK_ACCOUNT_CODE = '1110';

    /** Use the posted receivable, not today's prices or a recalculated sale total. */
    public function outstandingSales(): Builder
    {
        $journals = DB::table('journal_entries')
            ->where('source_type', (new SalesOrder)->getMorphClass())
            ->where('status', JournalEntry::STATUS_POSTED)
            ->selectRaw('source_id, COUNT(*) as journal_count')
            ->groupBy('source_id');

        $receivables = DB::table('journal_entries as entries')
            ->join('journal_lines as lines', 'lines.journal_entry_id', '=', 'entries.id')
            ->join('accounting_accounts as accounts', 'accounts.id', '=', 'lines.accounting_account_id')
            ->where('entries.source_type', (new SalesOrder)->getMorphClass())
            ->where('entries.status', JournalEntry::STATUS_POSTED)
            ->where('accounts.code', self::ACCOUNTS_RECEIVABLE_CODE)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('journal_entries as reversals')
                    ->whereColumn('reversals.reversal_of_id', 'entries.id');
            })
            ->selectRaw('entries.source_id, SUM(lines.debit - lines.credit) as receivable_amount')
            ->groupBy('entries.source_id');

        $payments = DB::table('customer_payments')
            ->selectRaw('sales_order_id, SUM(amount) as paid_amount')
            ->groupBy('sales_order_id');

        return SalesOrder::query()
            ->joinSub($journals, 'sale_journals', 'sale_journals.source_id', '=', 'sales_orders.id')
            ->joinSub($receivables, 'receivables', 'receivables.source_id', '=', 'sales_orders.id')
            ->leftJoinSub($payments, 'payments', 'payments.sales_order_id', '=', 'sales_orders.id')
            ->where('sales_orders.status', SalesOrder::STATUS_COMPLETED)
            ->where('sales_orders.sale_type', SalesOrder::TYPE_BUSINESS)
            ->whereHas('customer')
            ->where('sale_journals.journal_count', 1)
            ->whereRaw('receivables.receivable_amount > COALESCE(payments.paid_amount, 0)')
            ->select('sales_orders.*')
            ->selectRaw('receivables.receivable_amount, COALESCE(payments.paid_amount, 0) as paid_amount')
            ->selectRaw('receivables.receivable_amount - COALESCE(payments.paid_amount, 0) as outstanding_amount');
    }

    public function record(SalesOrder $salesOrder, User $user, array $input): CustomerPayment
    {
        $data = Validator::make($input, [
            'payment_account_id' => ['required', 'integer', 'exists:accounting_accounts,id'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'regex:/^\d{1,13}(\.\d{1,2})?$/', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'uuid'],
            'external_reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $amountCents = $this->toCents($data['amount']);
        $reference = $this->clean($data['external_reference'] ?? null);
        $notes = $this->clean($data['notes'] ?? null);

        return DB::transaction(function () use ($salesOrder, $user, $data, $amountCents, $reference, $notes) {
            // Serializes receipts and sale reversals, including concurrent partial payments.
            $order = SalesOrder::query()->whereKey($salesOrder->id)->lockForUpdate()->firstOrFail();

            $existing = CustomerPayment::query()
                ->where('idempotency_key', $data['idempotency_key'])->first();

            if ($existing) {
                if ($existing->sales_order_id !== $order->id
                    || $existing->created_by !== $user->id
                    || $existing->payment_account_id !== (int) $data['payment_account_id']
                    || $existing->payment_date->toDateString() !== $data['payment_date']
                    || $this->toCents($existing->amount) !== $amountCents
                    || $existing->external_reference !== $reference
                    || $existing->notes !== $notes) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'This form has already recorded a different payment. Reload the page to record a new payment.',
                    ]);
                }

                return $existing;
            }

            if ($order->status !== SalesOrder::STATUS_COMPLETED
                || $order->sale_type !== SalesOrder::TYPE_BUSINESS
                || ! $order->customer()->exists()) {
                throw ValidationException::withMessages([
                    'sales_order_id' => 'Payments require a completed business sale with a customer.',
                ]);
            }

            $accounts = AccountingAccount::query()
                ->where(function ($query) use ($data) {
                    $query->whereKey($data['payment_account_id'])
                        ->orWhere('code', self::ACCOUNTS_RECEIVABLE_CODE);
                })->orderBy('id')->lockForUpdate()->get();
            $paymentAccount = $accounts->firstWhere('id', (int) $data['payment_account_id']);
            $receivableAccount = $accounts->firstWhere('code', self::ACCOUNTS_RECEIVABLE_CODE);

            if (! $paymentAccount || ! $paymentAccount->is_active
                || ! in_array($paymentAccount->code, [self::CASH_ON_HAND_CODE, self::BANK_ACCOUNT_CODE], true)) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'Receive payments into an active Cash on Hand or Bank Account.',
                ]);
            }

            if (! $receivableAccount || ! $receivableAccount->is_active) {
                throw ValidationException::withMessages([
                    'accounting' => 'Accounts Receivable must exist and be active before recording customer payments.',
                ]);
            }

            $journals = $order->journalEntries()->where('status', JournalEntry::STATUS_POSTED)
                ->with('lines')->lockForUpdate()->get();

            if ($journals->count() !== 1 || $journals->first()->reversals()->exists()) {
                throw ValidationException::withMessages([
                    'accounting' => 'The sale requires exactly one posted, unreversed accounting journal before it can be paid.',
                ]);
            }

            $receivableCents = $journals->first()->lines
                ->where('accounting_account_id', $receivableAccount->id)
                ->sum(fn ($line) => $this->toCents($line->debit) - $this->toCents($line->credit));
            $paidCents = $order->customerPayments()->lockForUpdate()->get()
                ->sum(fn ($payment) => $this->toCents($payment->amount));
            $remainingCents = $receivableCents - $paidCents;

            if ($remainingCents <= 0 || $amountCents > $remainingCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment cannot exceed the outstanding customer balance of KES '
                        .number_format(max(0, $remainingCents) / 100, 2).'.',
                ]);
            }

            $payment = CustomerPayment::create([
                'customer_id' => $order->customer_id,
                'sales_order_id' => $order->id,
                'payment_account_id' => $paymentAccount->id,
                'payment_date' => $data['payment_date'],
                'amount' => $this->fromCents($amountCents),
                'idempotency_key' => $data['idempotency_key'],
                'external_reference' => $reference,
                'created_by' => $user->id,
                'notes' => $notes,
            ]);
            $payment->update(['reference' => 'CPAY-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT)]);

            app(JournalPostingService::class)->post(
                $user,
                $data['payment_date'],
                'Customer payment '.$payment->reference.' for '.$order->reference.'.',
                [
                    ['account_id' => $paymentAccount->id, 'debit' => $payment->amount, 'credit' => 0,
                        'memo' => 'Customer payment received for '.$order->reference],
                    ['account_id' => $receivableAccount->id, 'debit' => 0, 'credit' => $payment->amount,
                        'memo' => 'Settle receivable for '.$order->reference],
                ],
                $payment,
            );

            return $payment;
        }, 3);
    }

    private function toCents(mixed $amount): int
    {
        $value = (string) $amount;
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '');
        $cents = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function fromCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function clean(?string $value): ?string
    {
        return trim((string) $value) !== '' ? trim($value) : null;
    }
}
