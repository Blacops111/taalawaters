<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SalesPricingController extends Controller
{
    public function index()
    {
        $products = InventoryItem::query()
            ->where('is_active', true)
            ->where('is_sellable', true)
            ->where('category', 'finished_product')
            ->orderBy('name')
            ->get();

        return view('inventory.pricing', compact('products'));
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        if (
            ! $inventoryItem->is_active
            || ! $inventoryItem->is_sellable
            || $inventoryItem->category !== 'finished_product'
        ) {
            throw ValidationException::withMessages([
                'inventory_item' => 'Sales pricing can only be set for active sellable finished products.',
            ]);
        }

        $validated = $request->validate([
            'retail_price' => ['required', 'numeric', 'min:0', 'max:99999999999999.99'],
            'wholesale_price' => ['required', 'numeric', 'min:0', 'max:99999999999999.99', 'lte:retail_price'],
        ], [
            'wholesale_price.lte' => 'Wholesale price cannot be higher than the retail price.',
        ]);

        $inventoryItem->update([
            'retail_price' => $validated['retail_price'],
            'wholesale_price' => $validated['wholesale_price'],
        ]);

        return back()->with(
            'success',
            'Sales prices updated for '.$inventoryItem->name.'.'
        );
    }
}
