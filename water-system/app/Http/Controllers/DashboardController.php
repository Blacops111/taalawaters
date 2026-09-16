<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts = Product::count();
        $totalStock = Stock::sum('quantity_remaining');
        $totalSales = Sale::count();
        $totalRevenue = Sale::sum('total_amount');

        $profitSummary = DB::table('sales')
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->selectRaw(
                'COALESCE(SUM((sales.price - products.cost_price) * sales.quantity_sold), 0) as total_profit'
            )
            ->first();

        $totalProfit = (float) ($profitSummary->total_profit ?? 0);

        // SUBSTR works with both the MySQL production database and the
        // SQLite in-memory database used by the automated test suite.
        $monthlyProfitRows = DB::table('sales')
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->selectRaw('SUBSTR(sales.sale_date, 1, 7) as month_key')
            ->selectRaw(
                'SUM((sales.price - products.cost_price) * sales.quantity_sold) as total_profit'
            )
            ->groupByRaw('SUBSTR(sales.sale_date, 1, 7)')
            ->orderByRaw('SUBSTR(sales.sale_date, 1, 7)')
            ->get();

        $profitLabels = $monthlyProfitRows->map(function ($row) {
            return Carbon::createFromFormat('Y-m-d', $row->month_key.'-01')
                ->format('M Y');
        });

        $profitData = $monthlyProfitRows->map(function ($row) {
            return (float) $row->total_profit;
        });

        $salesData = Sale::select(
                'sale_date as date',
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        $dates = $salesData->pluck('date');
        $totals = $salesData->pluck('total');

        $productSales = DB::table('sales')
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->select('products.id', 'products.name')
            ->selectRaw('SUM(sales.quantity_sold) as total_qty')
            ->groupBy('products.id', 'products.name')
            ->orderBy('products.name')
            ->get();

        $productNames = $productSales->pluck('name');
        $productQuantities = $productSales->pluck('total_qty');

        $stocks = Stock::with('product')
            ->select('product_id')
            ->selectRaw('SUM(quantity_remaining) as quantity_remaining')
            ->groupBy('product_id')
            ->get();

        $stockLabels = [];
        $stockData = [];
        $stockColors = [];

        foreach ($stocks as $stock) {
            $stockLabels[] = $stock->product->name ?? 'Unknown';
            $stockData[] = (int) $stock->quantity_remaining;

            if ($stock->quantity_remaining < 10) {
                $stockColors[] = 'rgba(255, 99, 132, 0.8)';
            } else {
                $stockColors[] = 'rgba(54, 162, 235, 0.8)';
            }
        }

        $monthlyProfits = $monthlyProfitRows;
        $months = $profitLabels->values()->all();
        $profits = $profitData->values()->all();

        $lowStockProducts = $stocks
            ->filter(function ($stock) {
                return $stock->quantity_remaining < 10;
            })
            ->values();

        return view('dashboard', compact(
            'totalProducts',
            'totalStock',
            'totalSales',
            'totalRevenue',
            'totalProfit',
            'profitLabels',
            'profitData',
            'dates',
            'totals',
            'productNames',
            'productQuantities',
            'stockLabels',
            'stockData',
            'stockColors',
            'lowStockProducts',
            'monthlyProfits',
            'months',
            'profits'
        ));
    }
}
