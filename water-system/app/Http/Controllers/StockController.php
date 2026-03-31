<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stock;
use App\Models\Product;

class StockController extends Controller
{

    // Show stock list
    public function index()
    {
        $stocks = Stock::with('product')->get();

        return view('stocks.index',
            compact('stocks'));
    }


    // Show add stock form
    public function create()
    {
        $products = Product::all();

        return view('stocks.create',
            compact('products'));
    }


    // Store new stock
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required',
            'quantity_added' => 'required|numeric',
            'date_added' => 'required|date'
        ]);

        Stock::create([
            'product_id' => $request->product_id,
            'quantity_added' => $request->quantity_added,
            'quantity_remaining' => $request->quantity_added,
            'date_added' => $request->date_added
        ]);

        return redirect()->route('stocks.index')
                         ->with('success',
                         'Stock added successfully');
    }

}
