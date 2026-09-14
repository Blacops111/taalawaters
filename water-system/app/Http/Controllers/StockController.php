<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index()
    {
        $stocks = Stock::with('product')->get();

        return view('stocks.index', compact('stocks'));
    }

    public function create()
    {
        $products = Product::all();

        return view('stocks.create', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity_added' => ['required', 'integer', 'min:1'],
            'date_added' => ['required', 'date'],
        ]);

        Stock::create([
            'product_id' => $validated['product_id'],
            'quantity_added' => $validated['quantity_added'],
            'quantity_remaining' => $validated['quantity_added'],
            'date_added' => $validated['date_added'],
        ]);

        return redirect()
            ->route('stocks.index')
            ->with('success', 'Stock added successfully');
    }
}
