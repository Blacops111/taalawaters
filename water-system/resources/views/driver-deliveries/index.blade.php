@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">My Deliveries</h2>
            <p class="text-muted mb-0">
                Signed in as {{ $driver->name }}. Only deliveries assigned to you are shown.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Delivery</th>
                            <th>Status</th>
                            <th>Recipient</th>
                            <th>Destination</th>
                            <th>Vehicle</th>
                            <th>Dispatched</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deliveryNotes as $deliveryNote)
                            <tr>
                                <td>
                                    <strong>{{ $deliveryNote->reference }}</strong>
                                    <div class="small text-muted">
                                        {{ $deliveryNote->salesOrder?->reference }}
                                    </div>
                                </td>
                                <td>
                                    @if($deliveryNote->status === 'dispatched')
                                        <span class="badge bg-info text-dark">Dispatched</span>
                                    @else
                                        <span class="badge bg-success">Delivered</span>
                                        @if($deliveryNote->delivered_at)
                                            <div class="small text-muted mt-1">
                                                {{ $deliveryNote->delivered_at->format('Y-m-d H:i') }}
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    {{ $deliveryNote->recipient_name }}
                                    @if($deliveryNote->recipient_phone)
                                        <div class="small text-muted">
                                            {{ $deliveryNote->recipient_phone }}
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $deliveryNote->delivery_address ?: '—' }}</td>
                                <td>
                                    {{ $deliveryNote->vehicleAssignment?->vehicle?->registration_number ?? '—' }}
                                </td>
                                <td>{{ $deliveryNote->dispatched_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="text-end">
                                    @if($deliveryNote->status === 'dispatched')
                                        @php
                                            $codeActive =
                                                filled($deliveryNote->confirmation_code_hash)
                                                && $deliveryNote->confirmation_code_expires_at
                                                && $deliveryNote->confirmation_code_expires_at->isFuture()
                                                && $deliveryNote->confirmation_code_locked_at === null;
                                        @endphp

                                        @if($codeActive)
                                            <a
                                                href="{{ route('driver.deliveries.confirm', $deliveryNote) }}"
                                                class="btn btn-sm btn-primary"
                                            >
                                                Enter Code
                                            </a>
                                        @else
                                            <form
                                                method="POST"
                                                action="{{ route('driver.deliveries.send-code', $deliveryNote) }}"
                                                class="d-inline"
                                            >
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    {{ $deliveryNote->confirmation_code_generated_at ? 'Send New Code' : 'Send Code' }}
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="text-muted">Completed</span>
                                    @endif
                                </td>
                            </tr>
                            <tr class="table-light">
                                <td colspan="7">
                                    <div class="small text-muted mb-1">Delivery Items</div>
                                    @foreach($deliveryNote->items as $item)
                                        <span class="me-3">
                                            {{ $item->inventoryItem?->name ?? 'Unavailable Item' }}:
                                            <strong>{{ number_format((float) $item->quantity, 0) }}</strong>
                                        </span>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    No dispatched or completed deliveries are assigned to you.
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
