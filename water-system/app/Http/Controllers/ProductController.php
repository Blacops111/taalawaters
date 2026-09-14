<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{

    // Show all products
    public function index()
    {
        $products = Product::all();

        return view('products.index',
            compact('products'));
    }


    // Show create form
    public function create()
    {
        return view('products.create');
    }


    // Store new product
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'size' => 'required',
            'price' => 'required|numeric',
            'cost_price' => 'required|numeric'
        ]);

        Product::create([
            'name' => $request->name,
            'size' => $request->size,
            'price' => $request->price,
            'cost_price' => $request->cost_price,
        ]);

        return redirect()->route('products.index')
                         ->with('success',
                         'Product added successfully');
    }


    // Show edit form
    public function edit($id)
    {
        $product = Product::findOrFail($id);

        return view('products.edit',
            compact('product'));
    }


    // Update product
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'name' => 'required',
            'size' => 'required',
            'price' => 'required|numeric',
            'cost_price' => 'required|numeric'
        ]);

        $product->update([
            'name' => $request->name,
            'size' => $request->size,
            'price' => $request->price,
            'cost_price' => $request->cost_price,
        ]);

        return redirect()->route('products.index')
                         ->with('success',
                         'Product updated successfully');
    }


    // Delete product
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        $product->delete();

        return redirect()->route('products.index')
                         ->with('success',
                         'Product deleted successfully');
    }

}
