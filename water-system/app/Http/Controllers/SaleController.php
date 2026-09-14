<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Stock;
use App\Exports\SalesExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class SaleController extends Controller
{

    // Show sales list
    public function index()
    {
        $sales = Sale::with('product')->get();

        return view('sales.index',
            compact('sales'));
    }


    // Show create sale form
    public function create()
    {
        $products = Product::all();

        return view('sales.create',
            compact('products'));
    }


    // Store sale & reduce stock
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'quantity_sold' => 'required|numeric',
            'price' => 'required|numeric',
            'sale_date' => 'required|date'
        ]);

        // Get stock record
        $stock = Stock::where('product_id',
                    $request->product_id)
                    ->latest()
                    ->first();

        if (!$stock ||
            $stock->quantity_remaining <
            $request->quantity_sold) {

            return back()->with(
                'error',
                'Not enough stock available'
            );
        }

        // Calculate total
        $total =
            $request->quantity_sold *
            $request->price;

        // Create sale
        Sale::create([

            'product_id' =>
                $request->product_id,

            'quantity_sold' =>
                $request->quantity_sold,

            'price' =>
                $request->price,

            'total_amount' =>
                $total,

            'sale_date' =>
                $request->sale_date

        ]);

        // Reduce stock
        $stock->quantity_remaining -=
            $request->quantity_sold;

        $stock->save();

        return redirect()
            ->route('sales.index')
            ->with('success',
            'Sale recorded successfully');
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
