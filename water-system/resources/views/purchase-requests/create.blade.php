@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">New Purchase Request</h2>
        <p class="text-muted mb-0">
            Create the request first. Supplier selection is optional at this stage and inventory will not change.
        </p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('purchase-requests.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="supplier_id" class="form-label">
                            Preferred Supplier <span class="text-muted">(optional)</span>
                        </label>
                        <select
                            id="supplier_id"
                            name="supplier_id"
                            class="form-select @error('supplier_id') is-invalid @enderror"
                        >
                            <option value="">Select later during approval</option>
                            @foreach($suppliers as $supplier)
                                <option
                                    value="{{ $supplier->id }}"
                                    @selected((string) old('supplier_id') === (string) $supplier->id)
                                >
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">
                            Only active suppliers are available here. You can leave this blank.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="requested_at" class="form-label">Request Date & Time</label>
                        <input
                            type="datetime-local"
                            id="requested_at"
                            name="requested_at"
                            value="{{ old('requested_at', now()->format('Y-m-d\TH:i')) }}"
                            class="form-control @error('requested_at') is-invalid @enderror"
                            required
                        >
                        @error('requested_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">
                            Request Notes <span class="text-muted">(optional)</span>
                        </label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="4"
                            maxlength="2000"
                            class="form-control @error('notes') is-invalid @enderror"
                            placeholder="Example: Replenish packaging materials for next week's production."
                        >{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="alert alert-info mt-4 mb-0">
                    This creates a <strong>draft</strong> only. No inventory quantity will be added until goods are received later in the purchasing workflow.
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Draft Request</button>
                    <a href="{{ route('purchase-requests.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
