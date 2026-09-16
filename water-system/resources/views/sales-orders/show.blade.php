@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">Completed Sale {{ $salesOrder->reference }}</h2>
            <p class="text-muted mb-0">Read-only sale record and inventory deduction audit trail.</p>
        </div>
        <a href="{{ route('sales-orders.history') }}" class="btn btn-outline-secondary">
            Back to Completed Sales
        </a>
    </div>

    <div class="alert alert-info">
        This sale is completed and read-only. Its original sale values and inventory movements are preserved for audit history.
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Sale Summary</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Reference</div>
                    <strong>{{ $salesOrder->reference }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <span class="badge bg-success">Completed</span>
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
                <div class="col-md-12">
                    <div class="text-muted small">Notes</div>
                    <div>{{ filled($salesOrder->notes) ? $salesOrder->notes : 'No notes recorded.' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Sale Items</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($salesOrder->items as $item)
                            <tr>
                                <td>{{ $item->inventoryItem?->sku ?? 'Unavailable' }}</td>
                                <td>{{ $item->inventoryItem?->name ?? 'Unavailable Item' }}</td>
                                <td class="text-end">{{ number_format((float) $item->quantity, 0) }}</td>
                                <td class="text-end">KES {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="text-end fw-semibold">KES {{ number_format((float) $item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Grand Total</th>
                            <th class="text-end">KES {{ number_format((float) $salesOrder->total_amount, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Inventory Deduction Audit</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Product</th>
                            <th class="text-end">Inventory Change</th>
                            <th>Occurred At</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesOrder->stockMovements as $movement)
                            <tr>
                                <td><strong>{{ $movement->reference ?? $salesOrder->reference }}</strong></td>
                                <td>{{ $movement->inventoryItem?->name ?? 'Unavailable Item' }}</td>
                                <td class="text-end">{{ number_format((float) $movement->quantity_delta, 3) }}</td>
                                <td>{{ $movement->occurred_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $movement->creator?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No inventory movements are linked to this sale.
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
