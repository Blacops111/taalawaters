<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\SalesOrder;
use App\Models\SalesOrderReversal;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SalesAccountingService
{
    public const CASH_ON_HAND_CODE = '1100';
    public const ACCOUNTS_RECEIVABLE_CODE = '1200';
    public const SALES_REVENUE_CODE = '4100';

    public function postCompletedSale(SalesOrder $salesOrder, User $user): JournalEntry
    {
        if ($salesOrder->status !== SalesOrder::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'accounting' => 'Only completed sales can be posted to accounting.',
            ]);
        }

        $amount = (float) $salesOrder->total_amount;

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'accounting' => 'A completed sale must have a positive total before accounting can be posted.',
            ]);
        }

        $existing = JournalEntry::query()
            ->where('source_type', $salesOrder->getMorphClass())
            ->where('source_id', $salesOrder->id)
            ->first();

        if ($existing) {
            if ($existing->status === JournalEntry::STATUS_POSTED) {
                return $existing->load([
                    'lines.account',
                    'postedBy',
                    'source',
                ]);
            }

            throw ValidationException::withMessages([
                'accounting' => 'This sale already has an unposted accounting journal.',
            ]);
        }

        $debitCode = $salesOrder->sale_type === SalesOrder::TYPE_BUSINESS
            ? self::ACCOUNTS_RECEIVABLE_CODE
            : self::CASH_ON_HAND_CODE;

        $accounts = AccountingAccount::query()
            ->whereIn('code', [
                $debitCode,
                self::SALES_REVENUE_CODE,
            ])
            ->get()
            ->keyBy('code');

        $debitAccount = $accounts->get($debitCode);
        $revenueAccount = $accounts->get(self::SALES_REVENUE_CODE);

        if (! $debitAccount || ! $revenueAccount) {
            throw ValidationException::withMessages([
                'accounting' => 'Required sales accounting accounts are missing. Seed the Chart of Accounts before completing sales.',
            ]);
        }

        if (! $debitAccount->is_active || ! $revenueAccount->is_active) {
            throw ValidationException::withMessages([
                'accounting' => 'Required sales accounting accounts must be active before completing sales.',
            ]);
        }

        $debitMemo = $salesOrder->sale_type === SalesOrder::TYPE_BUSINESS
            ? 'Business customer receivable for '.$salesOrder->reference
            : 'Walk-in cash received for '.$salesOrder->reference;

        return app(JournalPostingService::class)->post(
            $user,
            $salesOrder->sale_at,
            'Revenue posting for '.$salesOrder->reference.'.',
            [
                [
                    'account_id' => $debitAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => $debitMemo,
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Water sales revenue for '.$salesOrder->reference,
                ],
            ],
            $salesOrder,
        );
    }
    public function postSaleReversal(
        SalesOrderReversal $reversal,
        User $user,
    ): JournalEntry {
        if (! $reversal->exists) {
            throw ValidationException::withMessages([
                'accounting' => 'The sale reversal must exist before accounting can be reversed.',
            ]);
        }

        $salesOrder = $reversal->salesOrder()->firstOrFail();

        $existing = JournalEntry::query()
            ->where('source_type', $reversal->getMorphClass())
            ->where('source_id', $reversal->id)
            ->first();

        if ($existing) {
            if ($existing->status === JournalEntry::STATUS_POSTED) {
                return $existing->load([
                    'lines.account',
                    'postedBy',
                    'source',
                    'reversalOf',
                ]);
            }

            throw ValidationException::withMessages([
                'accounting' => 'This sale reversal already has an unposted accounting journal.',
            ]);
        }

        $originalJournals = JournalEntry::query()
            ->where('source_type', $salesOrder->getMorphClass())
            ->where('source_id', $salesOrder->id)
            ->where('status', JournalEntry::STATUS_POSTED)
            ->with('lines')
            ->get();

        if ($originalJournals->count() !== 1) {
            throw ValidationException::withMessages([
                'accounting' => 'The original posted sales journal is missing or ambiguous, so the sale cannot be reversed safely.',
            ]);
        }

        $original = $originalJournals->first();

        if ($original->lines->count() < 2) {
            throw ValidationException::withMessages([
                'accounting' => 'The original sales journal is incomplete and cannot be reversed safely.',
            ]);
        }

        $lines = $original->lines
            ->map(fn ($line) => [
                'account_id' => $line->accounting_account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'memo' => 'Reversal of '.$original->reference
                    .($line->memo ? ': '.$line->memo : ''),
            ])
            ->all();

        return app(JournalPostingService::class)->post(
            $user,
            $reversal->reversed_at,
            'Accounting reversal for '.$salesOrder->reference
                .'. Reason: '.$reversal->reason,
            $lines,
            $reversal,
            $original,
        );
    }

}
