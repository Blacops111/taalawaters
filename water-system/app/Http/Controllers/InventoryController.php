<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;

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
}
