@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 760px;">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">Confirm Delivery</h2>
            <p class="text-muted mb-0">
                Delivery {{ $deliveryNote->reference }}
            </p>
        </div>

        <a href="{{ route('driver.deliveries.index') }}" class="btn btn-outline-secondary">
            Back to My Deliveries
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
        <div class="card-header bg-white">
            <strong>Delivery Details</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Recipient</div>
                    <strong>{{ $deliveryNote->recipient_name }}</strong>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Phone</div>
                    <strong>{{ $deliveryNote->recipient_phone ?: '—' }}</strong>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Vehicle</div>
                    <strong>
                        {{ $deliveryNote->vehicleAssignment?->vehicle?->registration_number ?? '—' }}
                    </strong>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Dispatched</div>
                    <strong>{{ $deliveryNote->dispatched_at?->format('Y-m-d H:i') }}</strong>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Delivery Address</div>
                    <strong>{{ $deliveryNote->delivery_address ?: '—' }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info">
        Ask the recipient for the 6-digit confirmation code only after the delivery has been received and checked.
        Do not ask office staff to reveal the code.
    </div>

    <form
        method="POST"
        action="{{ route('driver.deliveries.confirm.store', $deliveryNote) }}"
        class="card border-0 shadow-sm"
    >
        @csrf

        <div class="card-body">
            <label for="confirmation_code" class="form-label fw-semibold">
                6-Digit Confirmation Code
            </label>

            <input
                type="text"
                id="confirmation_code"
                name="confirmation_code"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                pattern="[0-9]{6}"
                value="{{ old('confirmation_code') }}"
                class="form-control form-control-lg text-center @error('confirmation_code') is-invalid @enderror"
                style="letter-spacing: .35rem; font-size: 1.5rem;"
                required
                autofocus
            >

            @error('confirmation_code')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror

            <div class="form-text mt-2">
                Too many incorrect attempts will lock this confirmation code.
            </div>
        </div>

        <div class="card-footer bg-white d-flex gap-2">
            <button type="submit" class="btn btn-success">
                Verify & Mark Delivered
            </button>
            <a href="{{ route('driver.deliveries.index') }}" class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
