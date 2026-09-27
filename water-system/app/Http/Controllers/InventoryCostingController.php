<?php

namespace App\Http\Controllers;

use App\Models\InventoryCostingSetup;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Services\InventoryCostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryCostingController extends Controller
{
    public function index(InventoryCostingService $cost)
    {
        $setup = InventoryCostingSetup::findOrFail(1);
        $items = InventoryItem::withSum('stockMovements as quantity', 'quantity_delta')->orderBy('sku')->get();
        $balances = DB::table('inventory_cost_balances')->get()->keyBy('inventory_item_id');
        $ledger = $cost->ledgerCents();
        $total = (int) $balances->sum('value_cents');
        $movements = StockMovement::with('inventoryItem')->whereNotNull('value_delta_cents')->latest('id')->paginate(50);
        return view('accounting.inventory-costing', compact('setup', 'items', 'balances', 'ledger', 'total', 'movements'));
    }

    public function preview(Request $request, InventoryCostingService $cost)
    {
        abort_if(InventoryCostingSetup::findOrFail(1)->is_active, 409, 'Costing is already active.');
        $data = $cost->validateSetup($request->all());
        $items = InventoryItem::orderBy('sku')->get();
        $total = array_sum(array_map(fn ($value) => InventoryCostingService::scaled($value, 2), $data['values']));
        $difference = $total - (int) $data['ledger_cents'];
        return view('accounting.inventory-costing-preview', compact('data', 'items', 'total', 'difference'));
    }

    public function activate(Request $request, InventoryCostingService $cost)
    {
        $cost->activate($request->user(), $request->all());
        return redirect()->route('accounting.inventory-costing')->with('success', 'Inventory costing activated. Opening valuation and reconciliation have been recorded.');
    }
}
