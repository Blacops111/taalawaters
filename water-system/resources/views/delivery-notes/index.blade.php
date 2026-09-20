@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">Delivery History</h2>
            <p class="text-muted mb-0">
                Review delivery notes across sales, drivers, vehicles, and completion status.
            </p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('delivery-notes.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label">Search</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control"
                        maxlength="100"
                        placeholder="Delivery ref, sale ref, or recipient"
                    >
                </div>

                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="dispatched" @selected(request('status') === 'dispatched')>Dispatched</option>
                        <option value="delivered" @selected(request('status') === 'delivered')>Delivered</option>
                        <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('delivery-notes.index') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Delivery</th>
                            <th>Status</th>
                            <th>Sale / Customer</th>
                            <th>Recipient</th>
                            <th>Driver / Vehicle</th>
                            <th>Timeline</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deliveryNotes as $deliveryNote)
                            <tr>
                                <td>
                                    <strong>{{ $deliveryNote->reference }}</strong>
                                    <div class="small text-muted">
                                        Created {{ $deliveryNote->created_at?->format('Y-m-d H:i') }}
                                    </div>
                                </td>
                                <td>
                                    @switch($deliveryNote->status)
                                        @case(AppModelsDeliveryNote::STATUS_DRAFT)
                                            <span class="badge bg-secondary">Draft</span>
                                            @break
                                        @case(AppModelsDeliveryNote::STATUS_DISPATCHED)
                                            <span class="badge bg-info text-dark">Dispatched</span>
                                            @break
                                        @case(AppModelsDeliveryNote::STATUS_DELIVERED)
                                            <span class="badge bg-success">Delivered</span>
                                            @break
                                        @default
                                            <span class="badge bg-warning text-dark">Cancelled</span>
                                    @endswitch
                                </td>
                                <td>
                                    <a
                                        href="{{ route('sales-orders.show', $deliveryNote->sales_order_id) }}"
                                        class="text-decoration-none fw-semibold"
                                    >
                                        {{ $deliveryNote->salesOrder?->reference ?? 'Unavailable Sale' }}
                                    </a>
                                    <div class="small text-muted">
                                        {{ $deliveryNote->salesOrder?->customer?->name ?? 'Walk-in' }}
                                    </div>
                                </td>
                                <td>
                                    {{ $deliveryNote->recipient_name }}
                                    @if($deliveryNote->recipient_phone)
                                        <div class="small text-muted">{{ $deliveryNote->recipient_phone }}</div>
                                    @endif
                                    @if($deliveryNote->recipient_email)
                                        <div class="small text-muted">{{ $deliveryNote->recipient_email }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $deliveryNote->vehicleAssignment?->driver?->name ?? '—' }}
                                    <div class="small text-muted">
                                        {{ $deliveryNote->vehicleAssignment?->vehicle?->registration_number ?? '—' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="text-muted">Dispatched:</span>
                                        {{ $deliveryNote->dispatched_at?->format('Y-m-d H:i') ?? '—' }}
                                    </div>
                                    <div class="small">
                                        <span class="text-muted">Delivered:</span>
                                        {{ $deliveryNote->delivered_at?->format('Y-m-d H:i') ?? '—' }}
                                    </div>
                                    @if($deliveryNote->confirmationVerifier)
                                        <div class="small text-muted">
                                            Verified by {{ $deliveryNote->confirmationVerifier->name }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($deliveryNote->status === AppModelsDeliveryNote::STATUS_DRAFT)
                                        <a
                                            href="{{ route('delivery-notes.dispatch', $deliveryNote) }}"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Dispatch
                                        </a>
                                    @else
                                        <a
                                            href="{{ route('sales-orders.show', $deliveryNote->sales_order_id) }}"
                                            class="btn btn-sm btn-outline-secondary"
                                        >
                                            View Sale
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    No delivery notes match the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($deliveryNotes->hasPages())
            <div class="card-footer bg-white">
                {{ $deliveryNotes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
