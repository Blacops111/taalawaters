<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TruckController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin-test', function () {
        return 'Admin Access Granted';
    });

    Route::get('/inventory', [InventoryController::class, 'index'])
        ->name('inventory.index');

    Route::get('/inventory/movements', [InventoryController::class, 'movements'])
        ->name('inventory.movements');

    Route::get('/inventory/receipts/create', [InventoryController::class, 'createReceipt'])
        ->name('inventory.receipts.create');

    Route::post('/inventory/receipts', [InventoryController::class, 'storeReceipt'])
        ->name('inventory.receipts.store');

    Route::get('/products', [ProductController::class, 'index'])
        ->name('products.index');

    Route::get('/products/create', [ProductController::class, 'create'])
        ->name('products.create');

    Route::post('/products', [ProductController::class, 'store'])
        ->name('products.store');

    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->name('products.edit');

    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->name('products.update');

    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->name('products.destroy');

    Route::get('/stocks', [StockController::class, 'index'])
        ->name('stocks.index');

    Route::get('/stocks/create', [StockController::class, 'create'])
        ->name('stocks.create');

    Route::post('/stocks', [StockController::class, 'store'])
        ->name('stocks.store');

    Route::get('/sales', [SaleController::class, 'index'])
        ->name('sales.index');

    Route::get('/sales/create', [SaleController::class, 'create'])
        ->name('sales.create');

    Route::post('/sales', [SaleController::class, 'store'])
        ->name('sales.store');

    Route::get('/sales/export', [SaleController::class, 'export'])
        ->name('sales.export');

    Route::get('/sales/pdf', [SaleController::class, 'exportPdf'])
        ->name('sales.pdf');

    Route::get('/sales/export-excel', [SaleController::class, 'exportExcel'])
        ->name('sales.export.excel');

    Route::resource('trucks', TruckController::class);
});

require __DIR__.'/auth.php';
