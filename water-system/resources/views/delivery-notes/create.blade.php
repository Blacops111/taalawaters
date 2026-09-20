@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">Create Delivery Note</h2>
            <p class="text-muted mb-0">
                Create a draft delivery note from completed sale {{ $salesOrder->reference }}.
            </p>
        </div>

        <a href="{{ route('sales-orders.show', $salesOrder) }}" class="btn btn-outline-secondary">
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
        <div class="card-header bg-white"><strong>Sale Summary</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Sale Reference</div>
                    <strong>{{ $salesOrder->reference }}</strong>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Customer</div>
                    <strong>{{ $salesOrder->customer?->name ?? 'Walk-in' }}</strong>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Sale Date</div>
                    <strong>{{ $salesOrder->sale_at?->format('Y-m-d H:i') }}</strong>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('delivery-notes.store', $salesOrder) }}">
        @csrf

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Delivery Details</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="vehicle_assignment_id" class="form-label">
                            Driver / Vehicle Assignment <span class="text-muted">(optional for draft)</span>
                        </label>
                        <select
                            id="vehicle_assignment_id"
                            name="vehicle_assignment_id"
                            class="form-select @error('vehicle_assignment_id') is-invalid @enderror"
                        >
                            <option value="">Assign later</option>
                            @foreach($activeAssignments as $assignment)
                                <option
                                    value="{{ $assignment->id }}"
                                    @selected((string) old('vehicle_assignment_id') === (string) $assignment->id)
                                >
                                    {{ $assignment->driver->name }} — {{ $assignment->vehicle->registration_number }}
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_assignment_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="scheduled_at" class="form-label">
                            Scheduled Delivery <span class="text-muted">(optional)</span>
                        </label>
                        <input
                            type="datetime-local"
                            id="scheduled_at"
                            name="scheduled_at"
                            value="{{ old('scheduled_at') }}"
                            class="form-control @error('scheduled_at') is-invalid @enderror"
                        >
                        @error('scheduled_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="recipient_name" class="form-label">Recipient Name</label>
                        <input
                            type="text"
                            id="recipient_name"
                            name="recipient_name"
                            maxlength="255"
                            value="{{ old('recipient_name', $salesOrder->customer?->contact_person ?? $salesOrder->customer?->name ?? '') }}"
                            class="form-control @error('recipient_name') is-invalid @enderror"
                            required
                        >
                        @error('recipient_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="recipient_phone" class="form-label">
                            Recipient Phone <span class="text-muted">(SMS)</span>
                        </label>
                        <input
                            type="tel"
                            id="recipient_phone"
                            name="recipient_phone"
                            maxlength="50"
                            value="{{ old('recipient_phone', $salesOrder->customer?->phone ?? '') }}"
                            class="form-control @error('recipient_phone') is-invalid @enderror"
                        >
                        @error('recipient_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="recipient_email" class="form-label">
                            Recipient Email <span class="text-muted">(Email)</span>
                        </label>
                        <input
                            type="email"
                            id="recipient_email"
                            name="recipient_email"
                            maxlength="255"
                            value="{{ old('recipient_email', $salesOrder->customer?->email ?? '') }}"
                            class="form-control @error('recipient_email') is-invalid @enderror"
                        >
                        @error('recipient_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <div class="form-text">
                            Enter a phone number, an email address, or both. If both are provided, the confirmation code will be sent through both channels.
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="delivery_address" class="form-label">
                            Delivery Address <span class="text-muted">(optional)</span>
                        </label>
                        <textarea
                            id="delivery_address"
                            name="delivery_address"
                            rows="2"
                            maxlength="2000"
                            class="form-control @error('delivery_address') is-invalid @enderror"
                        >{{ old('delivery_address', $salesOrder->customer?->address ?? '') }}</textarea>
                        @error('delivery_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">
                            Notes <span class="text-muted">(optional)</span>
                        </label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="2"
                            maxlength="2000"
                            class="form-control @error('notes') is-invalid @enderror"
                        >{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <strong>Delivery Quantities</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Product</th>
                                <th class="text-end">Sold</th>
                                <th class="text-end">Remaining</th>
                                <th style="width: 180px;">Deliver Now</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($salesOrder->items as $item)
                                @php
                                    $remaining = (float) ($remainingQuantities[$item->id] ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ $item->inventoryItem?->sku ?? 'Unavailable' }}</td>
                                    <td>{{ $item->inventoryItem?->name ?? 'Unavailable Item' }}</td>
                                    <td class="text-end">{{ number_format((float) $item->quantity, 0) }}</td>
                                    <td class="text-end">{{ number_format($remaining, 0) }}</td>
                                    <td>
                                        <input
                                            type="number"
                                            name="quantities[{{ $item->id }}]"
                                            min="0"
                                            max="{{ (int) $remaining }}"
                                            step="1"
                                            value="{{ old('quantities.'.$item->id, $remaining > 0 ? (int) $remaining : 0) }}"
                                            class="form-control @error('quantities.'.$item->id) is-invalid @enderror"
                                            @disabled($remaining <= 0)
                                        >
                                        @error('quantities.'.$item->id)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-white d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    Create Draft Delivery Note
                </button>
                <a href="{{ route('sales-orders.show', $salesOrder) }}" class="btn btn-outline-secondary">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
