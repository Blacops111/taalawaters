@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">Sales History</h2>
            <p class="text-muted mb-0">Read-only record of completed and reversed V2 sales with their original sale values preserved.</p>
        </div>
        <a href="{{ route('sales-orders.create') }}" class="btn btn-outline-secondary">
            Back to V2 Sales
        </a>
    </div>

    <div class="alert alert-info">
        Completed sales are read-only. Reversed sales remain in this history for audit purposes and are excluded from active completed-sales reports.
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <strong>Filter Sales History</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('sales-orders.history') }}">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="reference" class="form-label">Sale Reference</label>
                        <input
                            type="text"
                            id="reference"
                            name="reference"
                            value="{{ $filters['reference'] ?? '' }}"
                            maxlength="150"
                            class="form-control"
                            placeholder="e.g. SALE-00000001"
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="sale_type" class="form-label">Sale Type</label>
                        <select id="sale_type" name="sale_type" class="form-select">
                            <option value="">All Sale Types</option>
                            <option value="walk_in" @selected(($filters['sale_type'] ?? '') === 'walk_in')>Walk-in</option>
                            <option value="business" @selected(($filters['sale_type'] ?? '') === 'business')>Business Customer</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="customer_id" class="form-label">Business Customer</label>
                        <select id="customer_id" name="customer_id" class="form-select">
                            <option value="">All Business Customers</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string) ($filters['customer_id'] ?? '') === (string) $customer->id)>
                                    {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="date_from" class="form-label">From Date</label>
                        <input
                            type="date"
                            id="date_from"
                            name="date_from"
                            value="{{ $filters['date_from'] ?? '' }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-3">
                        <label for="date_to" class="form-label">To Date</label>
                        <input
                            type="date"
                            id="date_to"
                            name="date_to"
                            value="{{ $filters['date_to'] ?? '' }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="{{ route('sales-orders.history') }}" class="btn btn-outline-secondary">Clear Filters</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Sale Date</th>
                            <th>Items</th>
                            <th>Recorded By</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($completedSales as $sale)
                            <tr>
                                <td><strong>{{ $sale->reference }}</strong></td>
                                <td>{{ $sale->sale_type === 'business' ? 'Business Customer' : 'Walk-in' }}</td>
                                <td>{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                                <td>{{ $sale->sale_at?->format('Y-m-d H:i') }}</td>
                                <td style="min-width: 260px;">
                                    @foreach($sale->items as $item)
                                        <div>
                                            {{ number_format((float) $item->quantity, 0) }} × {{ $item->inventoryItem?->name ?? 'Unavailable Item' }}
                                        </div>
                                    @endforeach
                                </td>
                                <td>{{ $sale->creator?->name ?? 'System' }}</td>
                                <td class="text-end fw-semibold">KES {{ number_format((float) $sale->total_amount, 2) }}</td>
                                <td>
                                    @if($sale->status === \App\Models\SalesOrder::STATUS_REVERSED)
                                        <span class="badge bg-warning text-dark">Reversed</span>
                                    @else
                                        <span class="badge bg-success">Completed</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('sales-orders.show', $sale) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    No completed or reversed V2 sales match the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($completedSales->hasPages())
        <div class="mt-3">
            {{ $completedSales->links() }}
        </div>
    @endif
</div>
@endsection
