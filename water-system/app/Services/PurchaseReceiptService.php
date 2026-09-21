<?php

namespace App\Services;

use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReceiptService
{
    public function receive(
        PurchaseRequest $purchaseRequest,
        User $user,
        CarbonInterface $receivedAt,
        array $quantities,
        ?string $supplierDeliveryReference = null,
        ?string $notes = null,
    ): PurchaseReceipt {
        return DB::transaction(function () use (
            $purchaseRequest,
            $user,
            $receivedAt,
            $quantities,
            $supplierDeliveryReference,
            $notes,
        ) {
            $lockedRequest = PurchaseRequest::query()
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedRequest->status, [
                PurchaseRequest::STATUS_APPROVED,
                PurchaseRequest::STATUS_PARTIALLY_RECEIVED,
            ], true)) {
                throw ValidationException::withMessages([
                    'purchase_request' => 'Goods can only be received against an approved purchase request with outstanding quantities.',
                ]);
            }

            if (! $lockedRequest->supplier_id) {
                throw ValidationException::withMessages([
                    'purchase_request' => 'An approved purchase request must have a supplier before goods can be received.',
                ]);
            }

            $requestItems = PurchaseRequestItem::query()
                ->where('purchase_request_id', $lockedRequest->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($requestItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_request' => 'This purchase request has no items to receive.',
                ]);
            }

            $lines = [];
            $allFullyReceived = true;

            foreach ($requestItems as $requestItem) {
                $receivedBefore = (float) PurchaseReceiptItem::query()
                    ->where('purchase_request_item_id', $requestItem->id)
                    ->sum('quantity');

                $requestedQuantity = (float) $requestItem->quantity;
                $remainingBefore = max(0, $requestedQuantity - $receivedBefore);

                $rawQuantity = $quantities[$requestItem->id]
                    ?? $quantities[(string) $requestItem->id]
                    ?? null;

                $receiveNow = $rawQuantity === null || $rawQuantity === ''
                    ? 0.0
                    : (float) $rawQuantity;

                if ($receiveNow < 0) {
                    throw ValidationException::withMessages([
                        'quantities.'.$requestItem->id => 'Received quantity cannot be negative.',
                    ]);
                }

                if ($receiveNow > $remainingBefore + 0.0005) {
                    throw ValidationException::withMessages([
                        'quantities.'.$requestItem->id => 'Received quantity cannot exceed the remaining approved quantity of '.number_format($remainingBefore, 3).'.',
                    ]);
                }

                if ($receiveNow > 0) {
                    $lines[] = [
                        'request_item' => $requestItem,
                        'quantity' => $receiveNow,
                    ];
                }

                $remainingAfter = max(0, $remainingBefore - $receiveNow);

                if ($remainingAfter > 0.0005) {
                    $allFullyReceived = false;
                }
            }

            if ($lines === []) {
                throw ValidationException::withMessages([
                    'quantities' => 'Enter a received quantity for at least one outstanding item.',
                ]);
            }

            $deliveryReference = trim((string) $supplierDeliveryReference);
            $cleanNotes = trim((string) $notes);

            $receipt = PurchaseReceipt::create([
                'reference' => null,
                'purchase_request_id' => $lockedRequest->id,
                'supplier_delivery_reference' => $deliveryReference !== ''
                    ? $deliveryReference
                    : null,
                'received_by' => $user->id,
                'received_at' => $receivedAt,
                'notes' => $cleanNotes !== '' ? $cleanNotes : null,
            ]);

            $receipt->update([
                'reference' => 'GRN-'.str_pad(
                    (string) $receipt->id,
                    8,
                    '0',
                    STR_PAD_LEFT
                ),
            ]);

            foreach ($lines as $line) {
                /** @var PurchaseRequestItem $requestItem */
                $requestItem = $line['request_item'];
                $quantity = $line['quantity'];

                $receipt->items()->create([
                    'purchase_request_item_id' => $requestItem->id,
                    'inventory_item_id' => $requestItem->inventory_item_id,
                    'quantity' => $quantity,
                ]);

                StockMovement::create([
                    'inventory_item_id' => $requestItem->inventory_item_id,
                    'movement_type' => 'purchase_received',
                    'quantity_delta' => $quantity,
                    'source_type' => PurchaseReceipt::class,
                    'source_id' => $receipt->id,
                    'created_by' => $user->id,
                    'occurred_at' => $receivedAt,
                    'reference' => $receipt->reference,
                    'notes' => 'Received against '.$lockedRequest->reference
                        .($deliveryReference !== '' ? ' / Supplier ref '.$deliveryReference : ''),
                ]);
            }

            app(PurchaseAccountingService::class)->postReceipt(
                $receipt,
                $user,
            );

            $lockedRequest->update([
                'status' => $allFullyReceived
                    ? PurchaseRequest::STATUS_RECEIVED
                    : PurchaseRequest::STATUS_PARTIALLY_RECEIVED,
            ]);

            return $receipt->load([
                'items.inventoryItem',
                'receiver',
                'purchaseRequest',
            ]);
        }, 3);
    }
}
