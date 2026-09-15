<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\ProductionRun;
use App\Models\ProductionRunReversal;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionRunReversalService
{
    public function reverse(
        ProductionRun $productionRun,
        User $user,
        string $reason,
        ?CarbonInterface $reversedAt = null
    ): ProductionRunReversal {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required to reverse a production run.',
            ]);
        }

        $reversedAtUtc = $reversedAt
            ? CarbonImmutable::instance($reversedAt)->utc()
            : now()->utc();

        return DB::transaction(function () use (
            $productionRun,
            $user,
            $reason,
            $reversedAtUtc
        ) {
            $lockedRun = ProductionRun::query()
                ->whereKey($productionRun->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRun->status !== 'completed') {
                throw ValidationException::withMessages([
                    'production_run_id' => 'Only completed production runs can be reversed.',
                ]);
            }

            if (ProductionRunReversal::query()
                ->where('production_run_id', $lockedRun->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'production_run_id' => 'This production run has already been reversed.',
                ]);
            }

            $originalMovements = StockMovement::query()
                ->where('source_type', ProductionRun::class)
                ->where('source_id', $lockedRun->id)
                ->whereIn('movement_type', [
                    'production_consumption',
                    'production_output',
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $consumptionMovements = $originalMovements
                ->where('movement_type', 'production_consumption');
            $outputMovements = $originalMovements
                ->where('movement_type', 'production_output');

            if ($consumptionMovements->isEmpty() || $outputMovements->isEmpty()) {
                throw ValidationException::withMessages([
                    'production_run_id' => 'The original production stock movements are incomplete and cannot be reversed safely.',
                ]);
            }

            $itemIds = $originalMovements
                ->pluck('inventory_item_id')
                ->unique()
                ->sort()
                ->values();

            $lockedItems = InventoryItem::query()
                ->whereIn('id', $itemIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($lockedItems->count() !== $itemIds->count()) {
                throw ValidationException::withMessages([
                    'production_run_id' => 'One or more inventory items from the original production run are unavailable.',
                ]);
            }

            $finishedOutputQuantity = 0.0;

            foreach ($outputMovements as $movement) {
                $quantity = (float) $movement->quantity_delta;

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'production_run_id' => 'The original production output movement is invalid.',
                    ]);
                }

                $finishedOutputQuantity += $quantity;
            }

            $finishedBalance = (float) StockMovement::query()
                ->where('inventory_item_id', $lockedRun->finished_product_id)
                ->sum('quantity_delta');

            if ($finishedBalance + 0.0005 < $finishedOutputQuantity) {
                throw ValidationException::withMessages([
                    'production_run_id' => sprintf(
                        'Cannot reverse %s because only %.3f finished units remain in stock, but %.3f must be removed.',
                        $lockedRun->reference,
                        $finishedBalance,
                        $finishedOutputQuantity
                    ),
                ]);
            }

            $baseReference = $lockedRun->reference
                ?: 'PROD-'.str_pad((string) $lockedRun->id, 8, '0', STR_PAD_LEFT);

            $reversal = ProductionRunReversal::create([
                'production_run_id' => $lockedRun->id,
                'reversed_by' => $user->id,
                'reversed_at' => $reversedAtUtc,
                'reference' => 'REV-'.$baseReference,
                'reason' => $reason,
            ]);

            foreach ($originalMovements as $movement) {
                $originalQuantity = (float) $movement->quantity_delta;

                if ($movement->movement_type === 'production_consumption') {
                    if ($originalQuantity >= 0) {
                        throw ValidationException::withMessages([
                            'production_run_id' => 'The original production consumption movement is invalid.',
                        ]);
                    }

                    $movementType = 'production_reversal_restore';
                    $quantityDelta = abs($originalQuantity);
                } else {
                    if ($originalQuantity <= 0) {
                        throw ValidationException::withMessages([
                            'production_run_id' => 'The original production output movement is invalid.',
                        ]);
                    }

                    $movementType = 'production_reversal_output';
                    $quantityDelta = -$originalQuantity;
                }

                StockMovement::create([
                    'inventory_item_id' => $movement->inventory_item_id,
                    'movement_type' => $movementType,
                    'quantity_delta' => $quantityDelta,
                    'source_type' => ProductionRunReversal::class,
                    'source_id' => $reversal->id,
                    'created_by' => $user->id,
                    'occurred_at' => $reversedAtUtc,
                    'reference' => $reversal->reference,
                    'notes' => 'Reversal of '.$baseReference.' movement #'.$movement->id.'. Reason: '.$reason,
                ]);
            }

            $lockedRun->status = 'reversed';
            $lockedRun->save();

            return $reversal->fresh([
                'productionRun',
                'reversedBy',
                'stockMovements.inventoryItem',
            ]);
        }, 3);
    }
}
