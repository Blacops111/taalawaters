@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <div class="text-muted small">Goods Receiving</div>
            <h2 class="mb-1">{{ $purchaseRequest->reference }}</h2>
            <p class="text-muted mb-0">
                Supplier: {{ $purchaseRequest->supplier?->name ?: '—' }}
            </p>
        </div>

        <a href="{{ route('purchase-requests.edit', $purchaseRequest) }}" class="btn btn-outline-secondary">
            Back to Purchase Request
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->has('purchase_request'))
        <div class="alert alert-danger">{{ $errors->first('purchase_request') }}</div>
    @endif

    @error('quantities')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    @php
        $canReceive = in_array($purchaseRequest->status, ['approved', 'partially_received'], true);
    @endphp

    @if($canReceive)
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="mb-3">Record Received Goods</h5>

                <form method="POST" action="{{ route('purchase-requests.receipts.store', $purchaseRequest) }}">
                    @csrf

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="received_at" class="form-label">Received At</label>
                            <input
                                type="datetime-local"
                                id="received_at"
                                name="received_at"
                                value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"
                                class="form-control @error('received_at') is-invalid @enderror"
                                required
                            >
                            @error('received_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="supplier_delivery_reference" class="form-label">
                                Supplier Delivery Reference
                            </label>
                            <input
                                type="text"
                                id="supplier_delivery_reference"
                                name="supplier_delivery_reference"
                                maxlength="150"
                                value="{{ old('supplier_delivery_reference') }}"
                                class="form-control @error('supplier_delivery_reference') is-invalid @enderror"
                                placeholder="e.g. DN-1045"
                            >
                            @error('supplier_delivery_reference')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="notes" class="form-label">Notes</label>
                            <input
                                type="text"
                                id="notes"
                                name="notes"
                                maxlength="2000"
                                value="{{ old('notes') }}"
                                class="form-control @error('notes') is-invalid @enderror"
                                placeholder="Optional receiving notes"
                            >
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>SKU</th>
                                    <th>Material</th>
                                    <th>Unit</th>
                                    <th class="text-end">Approved Qty</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Received Before</th>
                                    <th class="text-end">Remaining</th>
                                    <th style="min-width: 170px;">Receive Now</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($purchaseRequest->items as $requestItem)
                                    @php
                                        $approved = (float) $requestItem->quantity;
                                        $received = (float) ($requestItem->received_quantity ?? 0);
                                        $remaining = max(0, $approved - $received);
                                    @endphp

                                    <tr>
                                        <td>{{ $requestItem->inventoryItem->sku }}</td>
                                        <td>{{ $requestItem->inventoryItem->name }}</td>
                                        <td>{{ $requestItem->inventoryItem->unit }}</td>
                                        <td class="text-end">{{ number_format($approved, 3) }}</td>
                                        <td class="text-end">
                                            @if($requestItem->approved_unit_cost !== null)
                                                KES {{ number_format((float) $requestItem->approved_unit_cost, 2) }}
                                            @else
                                                <span class="text-danger">Missing</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ number_format($received, 3) }}</td>
                                        <td class="text-end"><strong>{{ number_format($remaining, 3) }}</strong></td>
                                        <td>
                                            @if($remaining > 0)
                                                <input
                                                    type="number"
                                                    name="quantities[{{ $requestItem->id }}]"
                                                    min="0.001"
                                                    step="0.001"
                                                    max="{{ number_format($remaining, 3, '.', '') }}"
                                                    value="{{ old('quantities.'.$requestItem->id) }}"
                                                    class="form-control @error('quantities.'.$requestItem->id) is-invalid @enderror"
                                                    placeholder="0"
                                                >
                                                @error('quantities.'.$requestItem->id)
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            @else
                                                <span class="badge bg-success">Fully received</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-success">
                            Record Goods Receipt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="alert alert-success">
            This purchase request has been fully received.
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3">
            <h5 class="mb-0">Goods Receipt History</h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>GRN</th>
                            <th>Received At</th>
                            <th>Received By</th>
                            <th>Supplier Ref</th>
                            <th>Items</th>
                            <th class="text-end">Receipt Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchaseRequest->receipts as $receipt)
                            @php
                                $receiptValue = $receipt->items->sum(
                                    fn ($receiptItem) => (float) $receiptItem->quantity
                                        * (float) ($receiptItem->purchaseRequestItem?->approved_unit_cost ?? 0)
                                );
                            @endphp
                            <tr>
                                <td><strong>{{ $receipt->reference }}</strong></td>
                                <td>{{ optional($receipt->received_at)->format('Y-m-d H:i') }}</td>
                                <td>{{ $receipt->receiver?->name ?: '—' }}</td>
                                <td>{{ $receipt->supplier_delivery_reference ?: '—' }}</td>
                                <td>
                                    @foreach($receipt->items as $receiptItem)
                                        <div>
                                            {{ $receiptItem->inventoryItem->sku }}:
                                            {{ number_format((float) $receiptItem->quantity, 3) }}
                                            {{ $receiptItem->inventoryItem->unit }}
                                            @if($receiptItem->purchaseRequestItem?->approved_unit_cost !== null)
                                                @ KES {{ number_format((float) $receiptItem->purchaseRequestItem->approved_unit_cost, 2) }}
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                                <td class="text-end fw-semibold">
                                    KES {{ number_format($receiptValue, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No goods receipts recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
