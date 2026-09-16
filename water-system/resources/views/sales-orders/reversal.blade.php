@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">Reverse Sale {{ $salesOrder->reference }}</h2>
            <p class="text-muted mb-0">Create an audit-safe reversal and restore the finished stock deducted by this completed sale.</p>
        </div>
        <a href="{{ route('sales-orders.show', $salesOrder) }}" class="btn btn-outline-secondary">
            Back to Sale
        </a>
    </div>

    <div class="alert alert-warning">
        <strong>This action does not delete or edit the original sale.</strong>
        It creates a permanent reversal audit record, restores the exact original stock deduction, and marks the sale as reversed.
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-header"><strong>Sale Being Reversed</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Reference</div>
                    <strong>{{ $salesOrder->reference }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Sale Type</div>
                    <strong>{{ $salesOrder->sale_type === 'business' ? 'Business Customer' : 'Walk-in' }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Customer</div>
                    <strong>{{ $salesOrder->customer?->name ?? 'Walk-in' }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Sale Date</div>
                    <strong>{{ $salesOrder->sale_at?->format('Y-m-d H:i') }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Recorded By</div>
                    <strong>{{ $salesOrder->creator?->name ?? 'System' }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Grand Total</div>
                    <strong>KES {{ number_format((float) $salesOrder->total_amount, 2) }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Products to Restore</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th class="text-end">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($salesOrder->items as $item)
                            <tr>
                                <td>{{ $item->inventoryItem?->sku ?? 'Unavailable' }}</td>
                                <td>{{ $item->inventoryItem?->name ?? 'Unavailable Item' }}</td>
                                <td class="text-end">{{ number_format((float) $item->quantity, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-danger">
        <div class="card-header text-danger"><strong>Reversal Reason</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('sales-orders.reverse', $salesOrder) }}">
                @csrf

                <label for="reason" class="form-label">Reason for reversing this sale</label>
                <textarea
                    id="reason"
                    name="reason"
                    rows="4"
                    maxlength="2000"
                    class="form-control @error('reason') is-invalid @enderror"
                    required
                    placeholder="Explain why this completed sale must be reversed..."
                >{{ old('reason') }}</textarea>
                <div class="form-text">The reason becomes part of the permanent audit trail.</div>

                <div class="mt-4 d-flex gap-2">
                    <button
                        type="submit"
                        class="btn btn-danger"
                        onclick="return confirm('Reverse {{ $salesOrder->reference }} and restore its deducted finished stock? This creates a permanent audit record.')"
                    >
                        Confirm Sale Reversal
                    </button>
                    <a href="{{ route('sales-orders.show', $salesOrder) }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
