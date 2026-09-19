<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionRecipeController;
use App\Http\Controllers\ProductionRunController;
use App\Http\Controllers\PurchaseRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SalesPricingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
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

    Route::get('/inventory/pricing', [SalesPricingController::class, 'index'])
        ->name('inventory.pricing.index');

    Route::patch('/inventory/{inventoryItem}/pricing', [SalesPricingController::class, 'update'])
        ->name('inventory.pricing.update');

    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])
        ->name('inventory.low-stock');

    Route::patch('/inventory/{inventoryItem}/reorder-level', [InventoryController::class, 'updateReorderLevel'])
        ->name('inventory.reorder-level.update');

    Route::get('/inventory/movements', [InventoryController::class, 'movements'])
        ->name('inventory.movements');

    Route::get('/inventory/receipts/create', [InventoryController::class, 'createReceipt'])
        ->name('inventory.receipts.create');

    Route::post('/inventory/receipts', [InventoryController::class, 'storeReceipt'])
        ->name('inventory.receipts.store');

    Route::get('/inventory/adjustments/create', [InventoryController::class, 'createAdjustment'])
        ->name('inventory.adjustments.create');

    Route::post('/inventory/adjustments', [InventoryController::class, 'storeAdjustment'])
        ->name('inventory.adjustments.store');

    Route::get('/production/runs/create', [ProductionRunController::class, 'create'])
        ->name('production.runs.create');

    Route::post('/production/runs', [ProductionRunController::class, 'store'])
        ->name('production.runs.store');

    Route::get('/production/runs/{productionRun}/reversal', [ProductionRunController::class, 'reversal'])
        ->name('production.runs.reversal');

    Route::post('/production/runs/{productionRun}/reversal', [ProductionRunController::class, 'reverse'])
        ->name('production.runs.reverse');

    Route::get('/production/recipes', [ProductionRecipeController::class, 'index'])
        ->name('production.recipes.index');

    Route::get('/production/recipes/{finishedProduct}/edit', [ProductionRecipeController::class, 'edit'])
        ->name('production.recipes.edit');

    Route::put('/production/recipes/{finishedProduct}', [ProductionRecipeController::class, 'update'])
        ->name('production.recipes.update');

    Route::get('/customers', [CustomerController::class, 'index'])
        ->name('customers.index');

    Route::get('/customers/create', [CustomerController::class, 'create'])
        ->name('customers.create');

    Route::post('/customers', [CustomerController::class, 'store'])
        ->name('customers.store');

    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
        ->name('customers.edit');

    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->name('customers.update');



    Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])
        ->name('purchase-requests.index');

    Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])
        ->name('purchase-requests.create');

    Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])
        ->name('purchase-requests.store');

    Route::get('/purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])
        ->name('purchase-requests.edit');

    Route::post('/purchase-requests/{purchaseRequest}/items', [PurchaseRequestController::class, 'storeItem'])
        ->name('purchase-requests.items.store');

    Route::post('/purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])
        ->name('purchase-requests.submit');

    Route::delete('/purchase-requests/{purchaseRequest}/items/{purchaseRequestItem}', [PurchaseRequestController::class, 'destroyItem'])
        ->name('purchase-requests.items.destroy');

    Route::get('/suppliers', [SupplierController::class, 'index'])
        ->name('suppliers.index');

    Route::get('/suppliers/create', [SupplierController::class, 'create'])
        ->name('suppliers.create');

    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->name('suppliers.store');

    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])
        ->name('suppliers.edit');

    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->name('suppliers.update');

    Route::get('/sales-orders/history', [SalesOrderController::class, 'history'])
        ->name('sales-orders.history');

    Route::get('/sales-orders/report', [SalesOrderController::class, 'report'])
        ->name('sales-orders.report');

    Route::get('/sales-orders/report/pdf', [SalesOrderController::class, 'reportPdf'])
        ->name('sales-orders.report.pdf');

    Route::get('/sales-orders/report/excel', [SalesOrderController::class, 'reportExcel'])
        ->name('sales-orders.report.excel');

    Route::get('/sales-orders/create', [SalesOrderController::class, 'create'])
        ->name('sales-orders.create');

    Route::post('/sales-orders', [SalesOrderController::class, 'store'])
        ->name('sales-orders.store');

    Route::get('/sales-orders/{salesOrder}', [SalesOrderController::class, 'show'])
        ->name('sales-orders.show');

    Route::get('/sales-orders/{salesOrder}/reversal', [SalesOrderController::class, 'reversal'])
        ->name('sales-orders.reversal');

    Route::post('/sales-orders/{salesOrder}/reversal', [SalesOrderController::class, 'reverse'])
        ->name('sales-orders.reverse');

    Route::get('/sales-orders/{salesOrder}/edit', [SalesOrderController::class, 'edit'])
        ->name('sales-orders.edit');

    Route::post('/sales-orders/{salesOrder}/items', [SalesOrderController::class, 'storeItem'])
        ->name('sales-orders.items.store');

    Route::delete('/sales-orders/{salesOrder}/items/{salesOrderItem}', [SalesOrderController::class, 'destroyItem'])
        ->name('sales-orders.items.destroy');

    Route::post('/sales-orders/{salesOrder}/complete', [SalesOrderController::class, 'complete'])
        ->name('sales-orders.complete');

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
