<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\SalesOrder;
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
}
