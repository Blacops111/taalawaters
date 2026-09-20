<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        if (request()->user()?->role === 'driver') {
            return redirect()->route('driver.deliveries.index');
        }

        $activeInventoryItems = InventoryItem::query()
            ->where('is_active', true)
            ->count();

        $rawWaterBalance = (float) InventoryItem::query()
            ->where('is_active', true)
            ->where('category', 'raw_water')
            ->withSum('stockMovements as stock_balance', 'quantity_delta')
            ->get()
            ->sum(fn ($item) => (float) ($item->stock_balance ?? 0));

        $finishedProducts = InventoryItem::query()
            ->where('is_active', true)
            ->where('category', 'finished_product')
            ->withSum('stockMovements as stock_balance', 'quantity_delta')
            ->orderBy('name')
            ->get();

        $finishedUnitsOnHand = $finishedProducts
            ->sum(fn ($item) => (float) ($item->stock_balance ?? 0));

        $completedSalesQuery = SalesOrder::query()
            ->where('status', SalesOrder::STATUS_COMPLETED);

        $completedSales = (clone $completedSalesQuery)->count();
        $salesRevenue = (float) (clone $completedSalesQuery)->sum('total_amount');

        $lowStockItems = InventoryItem::query()
            ->where('is_active', true)
            ->where('category', '!=', 'raw_water')
            ->where('reorder_level', '>', 0)
            ->withSum('stockMovements as stock_balance', 'quantity_delta')
            ->whereRaw(
                '(SELECT COALESCE(SUM(stock_movements.quantity_delta), 0) '
                .'FROM stock_movements '
                .'WHERE stock_movements.inventory_item_id = inventory_items.id) '
                .'<= inventory_items.reorder_level'
            )
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $salesTrend = SalesOrder::query()
            ->where('status', SalesOrder::STATUS_COMPLETED)
            ->selectRaw('DATE(sale_at) as sale_date')
            ->selectRaw('SUM(total_amount) as total_revenue')
            ->groupByRaw('DATE(sale_at)')
            ->orderByRaw('DATE(sale_at)')
            ->get();

        $salesDates = $salesTrend->pluck('sale_date')->values();
        $salesTotals = $salesTrend
            ->map(fn ($row) => (float) $row->total_revenue)
            ->values();

        $productSales = SalesOrderItem::query()
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('inventory_items', 'sales_order_items.inventory_item_id', '=', 'inventory_items.id')
            ->where('sales_orders.status', SalesOrder::STATUS_COMPLETED)
            ->select([
                'inventory_items.id',
                'inventory_items.name',
            ])
            ->selectRaw('SUM(sales_order_items.quantity) as units_sold')
            ->groupBy('inventory_items.id', 'inventory_items.name')
            ->orderByDesc('units_sold')
            ->orderBy('inventory_items.name')
            ->get();

        $productNames = $productSales->pluck('name')->values();
        $productQuantities = $productSales
            ->map(fn ($row) => (float) $row->units_sold)
            ->values();

        $finishedStockLabels = $finishedProducts->pluck('name')->values();
        $finishedStockData = $finishedProducts
            ->map(fn ($item) => (float) ($item->stock_balance ?? 0))
            ->values();

        return view('dashboard', compact(
            'activeInventoryItems',
            'rawWaterBalance',
            'finishedUnitsOnHand',
            'completedSales',
            'salesRevenue',
            'lowStockItems',
            'salesDates',
            'salesTotals',
            'productNames',
            'productQuantities',
            'finishedStockLabels',
            'finishedStockData',
        ));
    }
}
