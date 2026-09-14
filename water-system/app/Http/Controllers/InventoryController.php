<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function index()
    {
        $items = InventoryItem::query()
            ->where('is_active', true)
            ->withSum('stockMovements as stock_balance', 'quantity_delta')
            ->orderBy('category')
            ->orderBy('name')
            ->paginate(50);

        return view('inventory.index', compact('items'));
    }

    public function createReceipt()
    {
        $items = InventoryItem::query()
            ->where('is_active', true)
            ->where('category', '!=', 'raw_water')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('inventory.receive', compact('items'));
    }

    public function storeReceipt(Request $request)
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'movement_type' => ['required', Rule::in(['opening_balance', 'stock_received'])],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],
            'occurred_at' => ['required', 'date'],
            'reference' => [
                'nullable',
                'string',
                'max:150',
                Rule::requiredIf(fn () => $request->input('movement_type') === 'stock_received'),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($validated) {
            $item = InventoryItem::query()
                ->whereKey($validated['inventory_item_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$item || $item->category === 'raw_water') {
                throw ValidationException::withMessages([
                    'inventory_item_id' => 'Raw borehole water is updated automatically from the meter and cannot be entered manually.',
                ]);
            }

            if (
                $validated['movement_type'] === 'stock_received'
                && $item->category === 'finished_product'
            ) {
                throw ValidationException::withMessages([
                    'movement_type' => 'Finished-product stock must come from Production. Use Opening Balance only for existing starting stock.',
                ]);
            }

            if ($validated['movement_type'] === 'opening_balance') {
                $openingBalanceExists = StockMovement::query()
                    ->where('inventory_item_id', $item->id)
                    ->where('movement_type', 'opening_balance')
                    ->exists();

                if ($openingBalanceExists) {
                    throw ValidationException::withMessages([
                        'movement_type' => 'This item already has an opening balance. Record later deliveries as Stock Received.',
                    ]);
                }
            }

            $reference = isset($validated['reference'])
                ? trim($validated['reference'])
                : null;

            if ($validated['movement_type'] === 'stock_received') {
                $duplicateReference = StockMovement::query()
                    ->where('inventory_item_id', $item->id)
                    ->where('movement_type', 'stock_received')
                    ->where('reference', $reference)
                    ->exists();

                if ($duplicateReference) {
                    throw ValidationException::withMessages([
                        'reference' => 'This stock-receipt reference has already been recorded for the selected item.',
                    ]);
                }
            }

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type' => $validated['movement_type'],
                'quantity_delta' => $validated['quantity'],
                'created_by' => auth()->id(),
                'occurred_at' => $validated['occurred_at'],
                'reference' => $reference,
                'notes' => $validated['notes'] ?? null,
            ]);
        }, 3);

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Inventory stock entry recorded successfully.');
    }
}
