<?php

namespace App\Http\Controllers;

use App\Models\PurchaseRequest;
use App\Services\PurchaseReceiptService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseReceiptController extends Controller
{
    public function create(PurchaseRequest $purchaseRequest)
    {
        if (! in_array($purchaseRequest->status, [
            PurchaseRequest::STATUS_APPROVED,
            PurchaseRequest::STATUS_PARTIALLY_RECEIVED,
            PurchaseRequest::STATUS_RECEIVED,
        ], true)) {
            abort(404);
        }

        $purchaseRequest->load([
            'supplier:id,name',
            'items' => fn ($query) => $query
                ->with('inventoryItem:id,sku,name,unit')
                ->withSum('receiptItems as received_quantity', 'quantity')
                ->orderBy('id'),
            'receipts' => fn ($query) => $query
                ->with(['receiver:id,name', 'items.inventoryItem:id,sku,name,unit'])
                ->orderByDesc('received_at')
                ->orderByDesc('id'),
        ]);

        return view('purchase-requests.receive', compact('purchaseRequest'));
    }

    public function store(
        Request $request,
        PurchaseRequest $purchaseRequest,
        PurchaseReceiptService $receiptService,
    ) {
        if (! in_array($purchaseRequest->status, [
            PurchaseRequest::STATUS_APPROVED,
            PurchaseRequest::STATUS_PARTIALLY_RECEIVED,
        ], true)) {
            throw ValidationException::withMessages([
                'purchase_request' => 'This purchase request has no receivable approved quantities.',
            ]);
        }

        $validated = $request->validate([
            'received_at' => ['required', 'date'],
            'supplier_delivery_reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999'],
        ]);

        $receivedAt = CarbonImmutable::parse(
            $validated['received_at'],
            config('app.timezone')
        )->utc();

        $receipt = $receiptService->receive(
            $purchaseRequest,
            $request->user(),
            $receivedAt,
            $validated['quantities'],
            $validated['supplier_delivery_reference'] ?? null,
            $validated['notes'] ?? null,
        );

        return redirect()
            ->route('purchase-requests.receipts.create', $purchaseRequest)
            ->with(
                'success',
                $receipt->reference.' recorded successfully. Received quantities were added to inventory.'
            );
    }
}
