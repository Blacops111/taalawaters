<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\SalesOrder;
use App\Services\SalesOrderCompletionService;
use App\Services\SalesOrderDraftItemService;
use App\Services\SalesOrderDraftService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesOrderController extends Controller
{
    public function create()
    {
        $customers = Customer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $recentDrafts = SalesOrder::query()
            ->with(['customer', 'creator'])
            ->withCount('items')
            ->where('status', SalesOrder::STATUS_DRAFT)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('sales-orders.create', compact('customers', 'recentDrafts'));
    }

    public function history()
    {
        $completedSales = SalesOrder::query()
            ->with([
                'customer',
                'creator',
                'items.inventoryItem',
            ])
            ->where('status', SalesOrder::STATUS_COMPLETED)
            ->orderByDesc('sale_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('sales-orders.history', compact('completedSales'));
    }

    public function store(Request $request, SalesOrderDraftService $draftService)
    {
        $validated = $request->validate([
            'sale_type' => ['required', Rule::in([
                SalesOrder::TYPE_WALK_IN,
                SalesOrder::TYPE_BUSINESS,
            ])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sale_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = isset($validated['customer_id'])
            ? Customer::find($validated['customer_id'])
            : null;

        $order = $draftService->createDraft(
            $validated['sale_type'],
            $customer,
            $request->user(),
            Carbon::parse($validated['sale_at']),
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('sales-orders.create')
            ->with('success', 'Draft sales order #'.$order->id.' was created successfully.');
    }

    public function edit(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== SalesOrder::STATUS_DRAFT) {
            abort(404);
        }

        $salesOrder->load([
            'customer',
            'creator',
            'items.inventoryItem',
        ]);

        $priceColumn = $salesOrder->sale_type === SalesOrder::TYPE_BUSINESS
            ? 'wholesale_price'
            : 'retail_price';

        $finishedProducts = InventoryItem::query()
            ->where('category', 'finished_product')
            ->where('is_sellable', true)
            ->where('is_active', true)
            ->whereNotNull($priceColumn)
            ->orderBy('name')
            ->get();

        return view('sales-orders.edit', compact('salesOrder', 'finishedProducts', 'priceColumn'));
    }

    public function storeItem(
        Request $request,
        SalesOrder $salesOrder,
        SalesOrderDraftItemService $itemService,
    ) {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999999999'],
        ]);

        $inventoryItem = InventoryItem::findOrFail($validated['inventory_item_id']);

        $itemService->addItem(
            $salesOrder,
            $inventoryItem,
            (int) $validated['quantity'],
        );

        return redirect()
            ->route('sales-orders.edit', $salesOrder)
            ->with('success', 'Product was added to draft sales order #'.$salesOrder->id.' using the configured sales price.');
    }

    public function complete(
        Request $request,
        SalesOrder $salesOrder,
        SalesOrderCompletionService $completionService,
    ) {
        $completedOrder = $completionService->complete(
            $salesOrder,
            $request->user(),
        );

        return redirect()
            ->route('sales-orders.create')
            ->with(
                'success',
                'Sale '.$completedOrder->reference.' was completed successfully and inventory was deducted.'
            );
    }
}
