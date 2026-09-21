<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\PurchaseReceipt;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public const ACCOUNTS_PAYABLE_CODE = '2100';

    public const CASH_ON_HAND_CODE = '1100';

    public const BANK_ACCOUNT_CODE = '1110';

    public function record(
        PurchaseReceipt $purchaseReceipt,
        AccountingAccount $paymentAccount,
        User $user,
        CarbonInterface|string $paymentDate,
        mixed $amount,
        ?string $externalReference = null,
        ?string $notes = null,
    ): SupplierPayment {
        $amountCents = $this->toCents($amount);

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Supplier payment amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $purchaseReceipt,
            $paymentAccount,
            $user,
            $paymentDate,
            $amountCents,
            $externalReference,
            $notes,
        ) {
            $lockedReceipt = PurchaseReceipt::query()
                ->whereKey($purchaseReceipt->id)
                ->with('purchaseRequest.supplier')
                ->lockForUpdate()
                ->firstOrFail();

            $supplier = $lockedReceipt->purchaseRequest?->supplier;

            if (! $supplier) {
                throw ValidationException::withMessages([
                    'purchase_receipt_id' => 'The selected goods receipt does not have a supplier.',
                ]);
            }

            $lockedPaymentAccount = AccountingAccount::query()
                ->whereKey($paymentAccount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array(
                $lockedPaymentAccount->code,
                [self::CASH_ON_HAND_CODE, self::BANK_ACCOUNT_CODE],
                true
            )) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'Supplier payments can only be paid from Cash on Hand or Bank Account.',
                ]);
            }

            if (! $lockedPaymentAccount->is_active) {
                throw ValidationException::withMessages([
                    'payment_account_id' => 'The selected payment account is inactive.',
                ]);
            }

            $payableAccount = AccountingAccount::query()
                ->where('code', self::ACCOUNTS_PAYABLE_CODE)
                ->lockForUpdate()
                ->first();

            if (! $payableAccount || ! $payableAccount->is_active) {
                throw ValidationException::withMessages([
                    'accounting' => 'Accounts Payable must exist and be active before supplier payments can be recorded.',
                ]);
            }

            $receiptJournals = JournalEntry::query()
                ->where('source_type', $lockedReceipt->getMorphClass())
                ->where('source_id', $lockedReceipt->id)
                ->where('status', JournalEntry::STATUS_POSTED)
                ->with('lines')
                ->lockForUpdate()
                ->get();

            if ($receiptJournals->count() !== 1) {
                throw ValidationException::withMessages([
                    'accounting' => 'The goods receipt must have exactly one posted accounting journal before it can be paid.',
                ]);
            }

            $receiptJournal = $receiptJournals->first();

            $payableCents = $receiptJournal->lines
                ->where('accounting_account_id', $payableAccount->id)
                ->sum(fn ($line) => $this->toCents($line->credit) - $this->toCents($line->debit));

            if ($payableCents <= 0) {
                throw ValidationException::withMessages([
                    'accounting' => 'The goods receipt does not contain a payable balance to settle.',
                ]);
            }

            $paidCents = SupplierPayment::query()
                ->where('purchase_receipt_id', $lockedReceipt->id)
                ->lockForUpdate()
                ->get()
                ->sum(fn ($payment) => $this->toCents($payment->amount));

            $remainingCents = $payableCents - $paidCents;

            if ($remainingCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This goods receipt has already been fully paid.',
                ]);
            }

            if ($amountCents > $remainingCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment cannot exceed the outstanding supplier balance of KES '
                        .number_format($remainingCents / 100, 2).'.',
                ]);
            }

            $payment = SupplierPayment::create([
                'reference' => null,
                'supplier_id' => $supplier->id,
                'purchase_receipt_id' => $lockedReceipt->id,
                'payment_account_id' => $lockedPaymentAccount->id,
                'payment_date' => $paymentDate instanceof CarbonInterface
                    ? $paymentDate->toDateString()
                    : $paymentDate,
                'amount' => $this->fromCents($amountCents),
                'external_reference' => $this->clean($externalReference),
                'created_by' => $user->id,
                'notes' => $this->clean($notes),
            ]);

            $payment->update([
                'reference' => 'SPAY-'.str_pad(
                    (string) $payment->id,
                    8,
                    '0',
                    STR_PAD_LEFT
                ),
            ]);

            app(JournalPostingService::class)->post(
                $user,
                $payment->payment_date,
                'Supplier payment '.$payment->reference.' to '.$supplier->name.'.',
                [
                    [
                        'account_id' => $payableAccount->id,
                        'debit' => $this->fromCents($amountCents),
                        'credit' => 0,
                        'memo' => 'Settle '.$lockedReceipt->reference.' payable to '.$supplier->name,
                    ],
                    [
                        'account_id' => $lockedPaymentAccount->id,
                        'debit' => 0,
                        'credit' => $this->fromCents($amountCents),
                        'memo' => 'Supplier payment from '.$lockedPaymentAccount->name,
                    ],
                ],
                $payment,
            );

            return $payment->fresh([
                'supplier',
                'purchaseReceipt',
                'paymentAccount',
                'creator',
                'journalEntries.lines.account',
            ]);
        }, 3);
    }

    private function toCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
