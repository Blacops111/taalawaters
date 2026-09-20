@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">Dispatch Delivery Note</h2>
            <p class="text-muted mb-0">
                Confirm the active driver and vehicle before dispatching {{ $deliveryNote->reference }}.
            </p>
        </div>

        <a href="{{ route('sales-orders.show', $deliveryNote->salesOrder) }}" class="btn btn-outline-secondary">
            Back to Sale
        </a>
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

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><strong>Delivery Note Summary</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Delivery Note</div>
                    <strong>{{ $deliveryNote->reference }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Sale</div>
                    <strong>{{ $deliveryNote->salesOrder->reference }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Customer</div>
                    <strong>{{ $deliveryNote->salesOrder->customer?->name ?? 'Walk-in' }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Scheduled</div>
                    <strong>{{ $deliveryNote->scheduled_at?->format('Y-m-d H:i') ?? 'Not scheduled' }}</strong>
                </div>
                <div class="col-md-12">
                    <div class="text-muted small">Delivery Address</div>
                    <div>{{ $deliveryNote->delivery_address ?: 'No delivery address recorded.' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><strong>Delivery Items</strong></div>
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
                        @foreach($deliveryNote->items as $item)
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

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Dispatch Assignment</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('delivery-notes.dispatch.store', $deliveryNote) }}">
                @csrf

                <div class="mb-3">
                    <label for="vehicle_assignment_id" class="form-label">Driver / Vehicle Assignment</label>
                    <select
                        id="vehicle_assignment_id"
                        name="vehicle_assignment_id"
                        class="form-select @error('vehicle_assignment_id') is-invalid @enderror"
                        required
                    >
                        <option value="">Select active assignment</option>
                        @foreach($activeAssignments as $assignment)
                            <option
                                value="{{ $assignment->id }}"
                                @selected(
                                    (string) old(
                                        'vehicle_assignment_id',
                                        $deliveryNote->vehicle_assignment_id
                                    ) === (string) $assignment->id
                                )
                            >
                                {{ $assignment->driver->name }}
                                — {{ $assignment->vehicle->registration_number }}
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle_assignment_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">
                        Only active driver/vehicle assignments with an assigned vehicle are shown.
                    </div>
                </div>

                @if($activeAssignments->isEmpty())
                    <div class="alert alert-warning">
                        No active driver/vehicle assignment is currently available. Create or restore an assignment before dispatch.
                    </div>
                @endif

                <div class="d-flex gap-2">
                    <button
                        type="submit"
                        class="btn btn-primary"
                        @disabled($activeAssignments->isEmpty())
                    >
                        Dispatch Delivery Note
                    </button>
                    <a href="{{ route('sales-orders.show', $deliveryNote->salesOrder) }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
