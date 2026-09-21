<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\PurchaseReceipt;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PurchaseAccountingService
{
    public const INVENTORY_CODE = '1300';
    public const ACCOUNTS_PAYABLE_CODE = '2100';

    public function postReceipt(PurchaseReceipt $receipt, User $user): JournalEntry
    {
        if (! $receipt->exists) {
            throw ValidationException::withMessages([
                'accounting' => 'The goods receipt must exist before accounting can be posted.',
            ]);
        }

        $existing = JournalEntry::query()
            ->where('source_type', $receipt->getMorphClass())
            ->where('source_id', $receipt->id)
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
                'accounting' => 'This goods receipt already has an unposted accounting journal.',
            ]);
        }

        $receipt->loadMissing([
            'items.inventoryItem',
            'items.purchaseRequestItem',
            'purchaseRequest.supplier',
        ]);

        if ($receipt->items->isEmpty()) {
            throw ValidationException::withMessages([
                'accounting' => 'A goods receipt must contain at least one received item before accounting can be posted.',
            ]);
        }

        if (! $receipt->purchaseRequest?->supplier_id) {
            throw ValidationException::withMessages([
                'accounting' => 'The purchase request supplier is required before receipt accounting can be posted.',
            ]);
        }

        $accounts = AccountingAccount::query()
            ->whereIn('code', [
                self::INVENTORY_CODE,
                self::ACCOUNTS_PAYABLE_CODE,
            ])
            ->get()
            ->keyBy('code');

        $inventoryAccount = $accounts->get(self::INVENTORY_CODE);
        $payableAccount = $accounts->get(self::ACCOUNTS_PAYABLE_CODE);

        if (! $inventoryAccount || ! $payableAccount) {
            throw ValidationException::withMessages([
                'accounting' => 'Required purchase accounting accounts are missing. Seed the Chart of Accounts before receiving goods.',
            ]);
        }

        if (! $inventoryAccount->is_active || ! $payableAccount->is_active) {
            throw ValidationException::withMessages([
                'accounting' => 'Required purchase accounting accounts must be active before receiving goods.',
            ]);
        }

        $inventoryLines = [];
        $totalCents = 0;

        foreach ($receipt->items as $receiptItem) {
            $requestItem = $receiptItem->purchaseRequestItem;
            $unitCost = $requestItem?->approved_unit_cost;

            if ($unitCost === null || (float) $unitCost <= 0) {
                throw ValidationException::withMessages([
                    'accounting' => 'Every received material must have an approved unit cost before goods can be received.',
                ]);
            }

            $lineCents = (int) round(
                (float) $receiptItem->quantity
                * (float) $unitCost
                * 100
            );

            if ($lineCents <= 0) {
                throw ValidationException::withMessages([
                    'accounting' => 'Every received material must have a positive accounting value.',
                ]);
            }

            $totalCents += $lineCents;

            $inventoryLines[] = [
                'account_id' => $inventoryAccount->id,
                'debit' => number_format($lineCents / 100, 2, '.', ''),
                'credit' => 0,
                'memo' => sprintf(
                    '%s %s × %s @ KES %s',
                    $receiptItem->inventoryItem?->sku ?? 'Item',
                    rtrim(rtrim(number_format((float) $receiptItem->quantity, 3, '.', ''), '0'), '.'),
                    $receiptItem->inventoryItem?->unit ?? 'unit',
                    number_format((float) $unitCost, 2, '.', '')
                ),
            ];
        }

        if ($totalCents <= 0) {
            throw ValidationException::withMessages([
                'accounting' => 'The goods receipt must have a positive accounting value.',
            ]);
        }

        $supplierName = $receipt->purchaseRequest?->supplier?->name ?? 'Supplier';

        $lines = [
            ...$inventoryLines,
            [
                'account_id' => $payableAccount->id,
                'debit' => 0,
                'credit' => number_format($totalCents / 100, 2, '.', ''),
                'memo' => 'Amount payable to '.$supplierName.' for '.$receipt->reference,
            ],
        ];

        return app(JournalPostingService::class)->post(
            $user,
            $receipt->received_at,
            'Goods receipt '.$receipt->reference.' from '.$supplierName.'.',
            $lines,
            $receipt,
        );
    }
}
