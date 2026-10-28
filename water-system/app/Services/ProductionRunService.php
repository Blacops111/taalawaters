<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\ProductionRecipe;
use App\Models\ProductionRun;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProductionRunService
{
    public function complete(
        ProductionRecipe $recipe,
        float $quantityProduced,
        ?User $user = null,
        ?CarbonInterface $occurredAt = null,
        ?string $notes = null,
        array $expenseAllocations = [],
        ?string $idempotencyKey = null,
    ): ProductionRun {
        if ($quantityProduced <= 0) {
            throw new InvalidArgumentException('Production quantity must be greater than zero.');
        }

        if (abs($quantityProduced - round($quantityProduced)) > 0.000001) {
            throw ValidationException::withMessages([
                'quantity_produced' => 'Production quantity must be a whole number of finished units.',
            ]);
        }

        $occurredAtUtc = $occurredAt
            ? CarbonImmutable::instance($occurredAt)->utc()
            : now()->utc();

        $allocations = app(ProductionCostAllocationService::class)->normalize($expenseAllocations);
        \Illuminate\Support\Facades\Validator::make(['completion_key' => $idempotencyKey], [
            'completion_key' => [$allocations !== [] ? 'required' : 'nullable', 'uuid'],
        ])->validate();
        $idempotencyKey = $idempotencyKey ? strtolower($idempotencyKey) : null;
        if ($allocations !== [] || $idempotencyKey !== null) {
            abort_unless($user && $user->role === 'admin', 403);
        }
        $fingerprint = hash('sha256', json_encode([
            'recipe' => $recipe->id, 'quantity' => (string) round($quantityProduced),
            'occurred_at' => $occurredAtUtc->format('Y-m-d H:i:s'), 'notes' => $notes,
            'allocations' => $allocations, 'user' => $user?->id,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $recipe,
            $quantityProduced,
            $user,
            $occurredAtUtc,
            $notes,
            $allocations,
            $idempotencyKey,
            $fingerprint,
        ) {
            InventoryCostingService::lock();
            if ($idempotencyKey !== null) {
                $existing = ProductionRun::where('completion_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->completion_fingerprint !== $fingerprint || $existing->status !== 'completed' || $existing->reversal()->exists()) {
                        throw ValidationException::withMessages(['completion_key' => 'This submission already recorded a different or reversed production run. Reload the form.']);
                    }
                    return $existing->load(['recipe', 'finishedProduct', 'creator', 'stockMovements', 'costAllocations']);
                }
            }
            $lockedRecipe = ProductionRecipe::query()
                ->whereKey($recipe->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedRecipe->is_active) {
                throw ValidationException::withMessages([
                    'production_recipe_id' => 'This production recipe is inactive.',
                ]);
            }

            $lockedRecipe->load(['components', 'finishedProduct']);

            if (
                !$lockedRecipe->finishedProduct
                || !$lockedRecipe->finishedProduct->is_active
                || $lockedRecipe->finishedProduct->category !== 'finished_product'
            ) {
                throw ValidationException::withMessages([
                    'production_recipe_id' => 'The recipe finished product is invalid or inactive.',
                ]);
            }

            if ($lockedRecipe->components->isEmpty()) {
                throw ValidationException::withMessages([
                    'production_recipe_id' => 'Configure at least one recipe component before production.',
                ]);
            }

            $recipeOutputQuantity = (float) $lockedRecipe->output_quantity;

            if ($recipeOutputQuantity <= 0) {
                throw ValidationException::withMessages([
                    'production_recipe_id' => 'The recipe output quantity must be greater than zero.',
                ]);
            }

            $componentIds = $lockedRecipe->components
                ->pluck('inventory_item_id')
                ->push($lockedRecipe->finished_product_id)
                ->unique()
                ->sort()
                ->values();

            $lockedItems = InventoryItem::query()
                ->whereIn('id', $componentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $multiplier = $quantityProduced / $recipeOutputQuantity;
            $requirements = [];

            foreach ($lockedRecipe->components as $component) {
                $item = $lockedItems->get($component->inventory_item_id);

                if (!$item || !$item->is_active || $item->category === 'finished_product') {
                    throw ValidationException::withMessages([
                        'production_recipe_id' => 'One or more recipe components are invalid or inactive.',
                    ]);
                }

                $requiredQuantity = round(
                    (float) $component->quantity_required * $multiplier,
                    3
                );

                if ($requiredQuantity <= 0) {
                    throw ValidationException::withMessages([
                        'production_recipe_id' => 'A recipe component has an invalid required quantity.',
                    ]);
                }

                $balance = (float) StockMovement::query()
                    ->where('inventory_item_id', $item->id)
                    ->sum('quantity_delta');

                if ($balance + 0.0005 < $requiredQuantity) {
                    throw ValidationException::withMessages([
                        'quantity_produced' => sprintf(
                            'Insufficient %s stock. Required %.3f %s, available %.3f %s.',
                            $item->name,
                            $requiredQuantity,
                            $item->unit,
                            $balance,
                            $item->unit
                        ),
                    ]);
                }

                $requirements[] = [
                    'item' => $item,
                    'quantity' => $requiredQuantity,
                ];
            }

            $run = ProductionRun::create([
                'production_recipe_id' => $lockedRecipe->id,
                'finished_product_id' => $lockedRecipe->finished_product_id,
                'quantity_produced' => round($quantityProduced),
                'recipe_output_quantity' => $recipeOutputQuantity,
                'status' => 'completed',
                'occurred_at' => $occurredAtUtc,
                'created_by' => $user?->id,
                'notes' => $notes,
                'completion_key' => $idempotencyKey,
                'completion_fingerprint' => $idempotencyKey !== null ? $fingerprint : null,
            ]);

            $run->reference = 'PROD-'.str_pad((string) $run->id, 8, '0', STR_PAD_LEFT);
            $run->save();

            if ($allocations !== []) {
                app(ProductionCostAllocationService::class)->allocate($run, $user, $allocations);
            }

            foreach ($requirements as $requirement) {
                StockMovement::create([
                    'inventory_item_id' => $requirement['item']->id,
                    'movement_type' => 'production_consumption',
                    'quantity_delta' => -$requirement['quantity'],
                    'source_type' => ProductionRun::class,
                    'source_id' => $run->id,
                    'created_by' => $user?->id,
                    'occurred_at' => $occurredAtUtc,
                    'reference' => $run->reference,
                    'notes' => 'Consumed by production run '.$run->reference.'.',
                ]);
            }

            StockMovement::create([
                'inventory_item_id' => $lockedRecipe->finished_product_id,
                'movement_type' => 'production_output',
                'quantity_delta' => round($quantityProduced),
                'source_type' => ProductionRun::class,
                'source_id' => $run->id,
                'created_by' => $user?->id,
                'occurred_at' => $occurredAtUtc,
                'reference' => $run->reference,
                'notes' => 'Finished stock created by production run '.$run->reference.'.',
            ]);

            return $run->fresh(['recipe', 'finishedProduct', 'creator', 'stockMovements']);
        }, 3);
    }
}
