<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\ProductionRecipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionRecipeController extends Controller
{
    public function index()
    {
        $finishedProducts = InventoryItem::query()
            ->where('is_active', true)
            ->where('category', 'finished_product')
            ->with([
                'productionRecipe' => fn ($query) => $query->withCount('components'),
            ])
            ->orderBy('name')
            ->get();

        return view('production.recipes.index', compact('finishedProducts'));
    }

    public function edit(InventoryItem $finishedProduct)
    {
        $this->ensureFinishedProduct($finishedProduct);

        $finishedProduct->load('productionRecipe.components');

        $materials = InventoryItem::query()
            ->where('is_active', true)
            ->where('category', '!=', 'finished_product')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $componentQuantities = $finishedProduct->productionRecipe
            ? $finishedProduct->productionRecipe->components
                ->pluck('quantity_required', 'inventory_item_id')
            : collect();

        return view('production.recipes.edit', compact(
            'finishedProduct',
            'materials',
            'componentQuantities'
        ));
    }

    public function update(Request $request, InventoryItem $finishedProduct)
    {
        $this->ensureFinishedProduct($finishedProduct);

        $validated = $request->validate([
            'output_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'components' => ['required', 'array'],
            'components.*' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.999'],
        ]);

        $components = collect($validated['components'])
            ->filter(fn ($quantity) => $quantity !== null && $quantity !== '')
            ->all();

        if ($components === []) {
            throw ValidationException::withMessages([
                'components' => 'Add at least one raw material or packaging component to the recipe.',
            ]);
        }

        $componentIds = array_map('intval', array_keys($components));

        $allowedComponentCount = InventoryItem::query()
            ->whereIn('id', $componentIds)
            ->where('is_active', true)
            ->where('category', '!=', 'finished_product')
            ->count();

        if ($allowedComponentCount !== count($componentIds)) {
            throw ValidationException::withMessages([
                'components' => 'One or more selected recipe components are invalid or inactive.',
            ]);
        }

        DB::transaction(function () use ($finishedProduct, $validated, $components) {
            $recipe = ProductionRecipe::query()->updateOrCreate(
                ['finished_product_id' => $finishedProduct->id],
                [
                    'name' => $finishedProduct->name.' Recipe',
                    'output_quantity' => $validated['output_quantity'],
                    'is_active' => true,
                ]
            );

            $recipe->components()->delete();

            $recipe->components()->createMany(
                collect($components)
                    ->map(fn ($quantity, $inventoryItemId) => [
                        'inventory_item_id' => (int) $inventoryItemId,
                        'quantity_required' => $quantity,
                    ])
                    ->values()
                    ->all()
            );
        }, 3);

        return redirect()
            ->route('production.recipes.index')
            ->with('success', 'Production recipe saved for '.$finishedProduct->name.'.');
    }

    private function ensureFinishedProduct(InventoryItem $finishedProduct): void
    {
        if (!$finishedProduct->is_active || $finishedProduct->category !== 'finished_product') {
            abort(404);
        }
    }
}
