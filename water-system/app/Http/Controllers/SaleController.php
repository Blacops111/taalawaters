<?php

namespace App\Http\Controllers;

use App\Exports\SalesExport;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with('product')->get();

        return view('sales.index', compact('sales'));
    }

    public function create()
    {
        $products = Product::all();

        return view('sales.create', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity_sold' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_date' => ['required', 'date'],
        ]);

        $saleRecorded = DB::transaction(function () use ($validated) {
            $stocks = Stock::where('product_id', $validated['product_id'])
                ->where('quantity_remaining', '>', 0)
                ->orderBy('date_added')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $availableStock = $stocks->sum('quantity_remaining');

            if ($availableStock < $validated['quantity_sold']) {
                return false;
            }

            $total = $validated['quantity_sold'] * $validated['price'];

            Sale::create([
                'product_id' => $validated['product_id'],
                'quantity_sold' => $validated['quantity_sold'],
                'price' => $validated['price'],
                'total_amount' => $total,
                'sale_date' => $validated['sale_date'],
            ]);

            $quantityToDeduct = $validated['quantity_sold'];

            foreach ($stocks as $stock) {
                if ($quantityToDeduct <= 0) {
                    break;
                }

                $deduction = min($stock->quantity_remaining, $quantityToDeduct);

                $stock->quantity_remaining -= $deduction;
                $stock->save();

                $quantityToDeduct -= $deduction;
            }

            return true;
        }, 3);

        if (!$saleRecorded) {
            return back()
                ->withInput()
                ->with('error', 'Not enough stock available');
        }

        return redirect()
            ->route('sales.index')
            ->with('success', 'Sale recorded successfully');
    }

    public function exportSales()
    {
        return Excel::download(new SalesExport, 'sales.xlsx');
    }

    public function export()
    {
        return Excel::download(
            new SalesExport,
            'sales_report.xlsx'
        );
    }

    public function exportPdf()
    {
        $sales = Sale::with('product')->get();

        $pdf = Pdf::loadView('sales.pdf', compact('sales'));

        return $pdf->download('sales_report.pdf');
    }

    public function exportExcel()
    {
        $export = new SalesExport();
        $fileName = $export->export();

        return response()->download(
            storage_path('app/public/' . $fileName)
        );
    }
}
