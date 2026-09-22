<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccount;
use App\Models\CustomerPayment;
use App\Models\SalesOrder;
use App\Services\CustomerPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPaymentController extends Controller
{
    public function index(CustomerPaymentService $service): View
    {
        $paymentAccounts = AccountingAccount::query()
            ->whereIn('code', [CustomerPaymentService::CASH_ON_HAND_CODE, CustomerPaymentService::BANK_ACCOUNT_CODE])
            ->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']);

        $outstandingSales = $service->outstandingSales()->with('customer:id,name')
            ->orderBy('sales_orders.sale_at')->orderBy('sales_orders.id')
            ->paginate(25, ['*'], 'sales_page')->withQueryString();

        $payments = CustomerPayment::query()
            ->with(['customer:id,name', 'salesOrder:id,reference', 'paymentAccount:id,code,name', 'creator:id,name'])
            ->latest('payment_date')->latest('id')
            ->paginate(25, ['*'], 'payments_page')->withQueryString();

        return view('accounting.customer-payments', compact('paymentAccounts', 'outstandingSales', 'payments'));
    }

    public function store(Request $request, CustomerPaymentService $service): RedirectResponse
    {
        $request->validate(['sales_order_id' => ['required', 'integer', 'exists:sales_orders,id']]);
        $payment = $service->record(
            SalesOrder::findOrFail($request->input('sales_order_id')),
            $request->user(),
            $request->only(['payment_account_id', 'payment_date', 'amount', 'idempotency_key', 'external_reference', 'notes']),
        );

        return redirect()->route('accounting.customer-payments')
            ->with('success', $payment->reference.' recorded and posted to Accounts Receivable.');
    }
}
