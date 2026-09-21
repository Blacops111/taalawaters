<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccount;
use App\Models\PurchaseReceipt;
use App\Models\SupplierPayment;
use App\Services\SupplierPaymentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    public function index(): View
    {
        $paymentAccounts = AccountingAccount::query()
            ->whereIn('code', [
                SupplierPaymentService::CASH_ON_HAND_CODE,
                SupplierPaymentService::BANK_ACCOUNT_CODE,
            ])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $outstandingReceipts = PurchaseReceipt::query()
            ->with([
                'purchaseRequest.supplier:id,name',
                'items.purchaseRequestItem:id,approved_unit_cost',
            ])
            ->withSum('supplierPayments as paid_amount', 'amount')
            ->latest('received_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(function (PurchaseReceipt $receipt) {
                $receiptValueCents = $receipt->items->sum(function ($item) {
                    return (int) round(
                        (float) $item->quantity
                        * (float) ($item->purchaseRequestItem?->approved_unit_cost ?? 0)
                        * 100
                    );
                });

                $paidCents = (int) round(((float) ($receipt->paid_amount ?? 0)) * 100);
                $receipt->setAttribute('receipt_value', $receiptValueCents / 100);
                $receipt->setAttribute(
                    'outstanding_amount',
                    max(0, $receiptValueCents - $paidCents) / 100
                );

                return $receipt;
            })
            ->filter(fn (PurchaseReceipt $receipt) => $receipt->outstanding_amount > 0)
            ->values();

        $payments = SupplierPayment::query()
            ->with([
                'supplier:id,name',
                'purchaseReceipt:id,reference',
                'paymentAccount:id,code,name',
                'creator:id,name',
            ])
            ->latest('payment_date')
            ->latest('id')
            ->paginate(25);

        return view(
            'accounting.supplier-payments',
            compact('paymentAccounts', 'outstandingReceipts', 'payments')
        );
    }

    public function store(
        Request $request,
        SupplierPaymentService $paymentService,
    ) {
        $validated = $request->validate([
            'purchase_receipt_id' => [
                'required',
                'integer',
                'exists:purchase_receipts,id',
            ],
            'payment_account_id' => [
                'required',
                'integer',
                'exists:accounting_accounts,id',
            ],
            'payment_date' => ['required', 'date'],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:9999999999999',
            ],
            'external_reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $receipt = PurchaseReceipt::findOrFail($validated['purchase_receipt_id']);
        $paymentAccount = AccountingAccount::findOrFail($validated['payment_account_id']);

        $payment = $paymentService->record(
            $receipt,
            $paymentAccount,
            $request->user(),
            $validated['payment_date'],
            $validated['amount'],
            $validated['external_reference'] ?? null,
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('accounting.supplier-payments')
            ->with(
                'success',
                $payment->reference.' recorded and posted to Accounts Payable.'
            );
    }
}
