<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SalesOrder;
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
            ->where('status', SalesOrder::STATUS_DRAFT)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('sales-orders.create', compact('customers', 'recentDrafts'));
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
}
