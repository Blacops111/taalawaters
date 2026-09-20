@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">{{ $salesOrder->status === \App\Models\SalesOrder::STATUS_REVERSED ? 'Reversed Sale' : 'Completed Sale' }} {{ $salesOrder->reference }}</h2>
            <p class="text-muted mb-0">Read-only sale record with inventory and reversal audit history.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            @if($salesOrder->status === \App\Models\SalesOrder::STATUS_COMPLETED)
                <a href="{{ route('delivery-notes.create', $salesOrder) }}" class="btn btn-primary">
                    Create Delivery Note
                </a>
                <a href="{{ route('sales-orders.reversal', $salesOrder) }}" class="btn btn-outline-danger">
                    Reverse Sale
                </a>
            @endif
            <a href="{{ route('sales-orders.history') }}" class="btn btn-outline-secondary">
                Back to Sales History
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($salesOrder->status === \App\Models\SalesOrder::STATUS_REVERSED)
        <div class="alert alert-warning">
            This sale has been reversed. The original sale values remain unchanged for audit history, and the finished stock deduction was restored through separate reversal movements.
        </div>
    @else
        <div class="alert alert-info">
            This sale is completed and read-only. Its original sale values and inventory movements are preserved for audit history.
        </div>
    @endif

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
                    @if($salesOrder->status === \App\Models\SalesOrder::STATUS_REVERSED)
                        <span class="badge bg-warning text-dark">Reversed</span>
                    @else
                        <span class="badge bg-success">Completed</span>
                    @endif
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


    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <strong>Delivery Notes</strong>
            @if($salesOrder->status === \App\Models\SalesOrder::STATUS_COMPLETED)
                <a href="{{ route('delivery-notes.create', $salesOrder) }}" class="btn btn-sm btn-outline-primary">
                    New Delivery Note
                </a>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Driver / Vehicle</th>
                            <th>Scheduled</th>
                            <th>Destination</th>
                            <th>Created By</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesOrder->deliveryNotes->sortByDesc('id') as $deliveryNote)
                            <tr>
                                <td><strong>{{ $deliveryNote->reference }}</strong></td>
                                <td>
                                    @switch($deliveryNote->status)
                                        @case(\App\Models\DeliveryNote::STATUS_DRAFT)
                                            <span class="badge bg-secondary">Draft</span>
                                            @break
                                        @case(\App\Models\DeliveryNote::STATUS_DISPATCHED)
                                            <span class="badge bg-info text-dark">Dispatched</span>
                                            @if($deliveryNote->dispatched_at)
                                                <div class="small text-muted mt-1">
                                                    {{ $deliveryNote->dispatched_at->format('Y-m-d H:i') }}
                                                </div>
                                            @endif
                                            @break
                                        @case(\App\Models\DeliveryNote::STATUS_DELIVERED)
                                            <span class="badge bg-success">Delivered</span>
                                            @if($deliveryNote->delivered_at)
                                                <div class="small text-muted mt-1">
                                                    {{ $deliveryNote->delivered_at->format('Y-m-d H:i') }}
                                                </div>
                                            @endif
                                            @break
                                        @default
                                            <span class="badge bg-warning text-dark">{{ ucfirst($deliveryNote->status) }}</span>
                                    @endswitch
                                </td>
                                <td>
                                    @if($deliveryNote->vehicleAssignment)
                                        {{ $deliveryNote->vehicleAssignment->driver?->name ?? 'Unavailable Driver' }}
                                        <div class="small text-muted">
                                            {{ $deliveryNote->vehicleAssignment->vehicle?->registration_number ?? 'Unavailable Vehicle' }}
                                        </div>
                                    @else
                                        <span class="text-muted">Not assigned yet</span>
                                    @endif
                                </td>
                                <td>{{ $deliveryNote->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>{{ $deliveryNote->delivery_address ?: '—' }}</td>
                                <td>{{ $deliveryNote->creator?->name ?? 'System' }}</td>
                                <td class="text-end">
                                    @if(
                                        $deliveryNote->status === \App\Models\DeliveryNote::STATUS_DRAFT
                                        && $salesOrder->status === \App\Models\SalesOrder::STATUS_COMPLETED
                                    )
                                        <a
                                            href="{{ route('delivery-notes.dispatch', $deliveryNote) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Dispatch
                                        </a>
                                    @elseif(
                                        $deliveryNote->status === \App\Models\DeliveryNote::STATUS_DISPATCHED
                                        && $salesOrder->status === \App\Models\SalesOrder::STATUS_COMPLETED
                                    )
                                        <span class="badge bg-light text-dark border">
                                            Receiver Code Required
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="7">
                                    <div class="small text-muted mb-1">Delivery Items</div>
                                    @foreach($deliveryNote->items as $deliveryItem)
                                        <span class="me-3">
                                            {{ $deliveryItem->inventoryItem?->name ?? 'Unavailable Item' }}:
                                            <strong>{{ number_format((float) $deliveryItem->quantity, 0) }}</strong>
                                        </span>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No delivery notes have been created for this sale.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Original Inventory Deduction Audit</strong></div>
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

    @if($salesOrder->reversal)
        <div class="card border-warning">
            <div class="card-header"><strong>Sale Reversal Audit</strong></div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="text-muted small">Reversal Reference</div>
                        <strong>{{ $salesOrder->reversal->reference }}</strong>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Reversed At</div>
                        <strong>{{ $salesOrder->reversal->reversed_at?->format('Y-m-d H:i') }}</strong>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Reversed By</div>
                        <strong>{{ $salesOrder->reversal->reversedBy?->name ?? 'System' }}</strong>
                    </div>
                    <div class="col-md-12">
                        <div class="text-muted small">Reason</div>
                        <div>{{ $salesOrder->reversal->reason }}</div>
                    </div>
                </div>

                <h6>Inventory Restoration</h6>
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
                            @foreach($salesOrder->reversal->stockMovements as $movement)
                                <tr>
                                    <td><strong>{{ $movement->reference }}</strong></td>
                                    <td>{{ $movement->inventoryItem?->name ?? 'Unavailable Item' }}</td>
                                    <td class="text-end text-success fw-semibold">+{{ number_format((float) $movement->quantity_delta, 3) }}</td>
                                    <td>{{ $movement->occurred_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $movement->creator?->name ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
