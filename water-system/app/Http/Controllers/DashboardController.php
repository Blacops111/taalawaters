<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {

        // Cards Data
        $totalProducts = Product::count();

        $totalStock =
            Stock::sum('quantity_remaining');

        $totalSales = Sale::count();

        $totalRevenue =
            Sale::sum('total_amount');

        // Total Profit

        $totalProfit = Sale::with('product')->get()
            ->sum(function ($sale) {

                $profitPerUnit =
                    $sale->product->price -
                    $sale->product->cost_price;

                return $profitPerUnit *
                       $sale->quantity_sold;
            });

        // Monthly Profit Chart

        $monthlyProfit = Sale::with('product')
            ->get()
            ->groupBy(function ($sale) {
                return Carbon::parse($sale->created_at)
                    ->format('M');
            })
            ->map(function ($sales) {

                return $sales->sum(function ($sale) {

                    $profitPerUnit =
                        $sale->product->price -
                        $sale->product->cost_price;

                    return $profitPerUnit *
                           $sale->quantity_sold;
                });

            });

        // Prepare labels & values

        $profitLabels = $monthlyProfit->keys();
        $profitData = $monthlyProfit->values();


        // Graph Data (Sales per Day)

        $salesData = Sale::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $dates = $salesData->pluck('date');

        $totals = $salesData->pluck('total');

        // Product Sales Pie Chart (Fixed)
        $productSales = Sale::select(
                'product_id',
                DB::raw('SUM(quantity_sold) as total_qty')
            )
            ->groupBy('product_id')
            ->with('product')
            ->get();

        $productNames = $productSales
            ->map(function ($sale) {
                return $sale->product->name ?? 'Unknown';
            });

        $productQuantities = $productSales
            ->pluck('total_qty');

        // Stock Levels Bar Chart

        $stocks = Stock::with('product')->get();

        $stockLabels = [];
        $stockData = [];
        $stockColors = [];

        foreach ($stocks as $stock) {

            $stockLabels[] = $stock->product->name;
            $stockData[] = $stock->quantity_remaining;

            // Color logic
            if ($stock->quantity_remaining < 10) {
                $stockColors[] = 'rgba(255, 99, 132, 0.8)'; // RED (Low Stock)
            } else {
                $stockColors[] = 'rgba(54, 162, 235, 0.8)'; // BLUE (Normal)
            }
        }

        $monthlyProfits = Sale::select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total_amount) as total_profit')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $months = [];
        $profits = [];

        foreach ($monthlyProfits as $data) {
            $months[] = date("F", mktime(0, 0, 0, $data->month, 1));
            $profits[] = $data->total_profit;
        }

        // Low Stock Alert (Correct Logic)
        $lowStockProducts = Stock::with('product')
            ->where('quantity_remaining', '<', 10)
            ->get();


        return view('dashboard',
            compact(
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
