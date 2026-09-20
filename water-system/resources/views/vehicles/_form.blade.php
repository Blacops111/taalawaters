@csrf
@if(isset($vehicle))
    @method('PUT')
@endif

@php
    $currentStatus = old(
        'status',
        isset($vehicle) && in_array($vehicle->status, ['available', 'maintenance'], true)
            ? $vehicle->status
            : 'available'
    );
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="registration_number" class="form-label">Registration Number</label>
        <input
            type="text"
            id="registration_number"
            name="registration_number"
            maxlength="50"
            value="{{ old('registration_number', $vehicle->registration_number ?? '') }}"
            class="form-control @error('registration_number') is-invalid @enderror"
            placeholder="e.g. KME 123A"
            required
        >
        @error('registration_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label">Vehicle Name <span class="text-muted">(optional)</span></label>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="255"
            value="{{ old('name', $vehicle->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="e.g. Bottle Delivery Bike 1"
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="vehicle_type" class="form-label">Vehicle Type</label>
        <select
            id="vehicle_type"
            name="vehicle_type"
            class="form-select @error('vehicle_type') is-invalid @enderror"
            required
        >
            <option value="">Select type</option>
            <option value="motorbike" @selected(old('vehicle_type', $vehicle->vehicle_type ?? '') === 'motorbike')>
                Motorbike
            </option>
            <option value="tanker_truck" @selected(old('vehicle_type', $vehicle->vehicle_type ?? '') === 'tanker_truck')>
                Tanker Truck
            </option>
        </select>
        @error('vehicle_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Operational Status</label>
        <select
            id="status"
            name="status"
            class="form-select @error('status') is-invalid @enderror"
            required
        >
            <option value="available" @selected($currentStatus === 'available')>Available</option>
            <option value="maintenance" @selected($currentStatus === 'maintenance')>Maintenance</option>
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">
            Assigned status will be controlled later by the delivery assignment workflow.
        </div>
    </div>

    <div class="col-md-6">
        <label for="capacity_quantity" class="form-label">Capacity <span class="text-muted">(optional)</span></label>
        <input
            type="number"
            id="capacity_quantity"
            name="capacity_quantity"
            min="0.001"
            step="0.001"
            value="{{ old('capacity_quantity', $vehicle->capacity_quantity ?? '') }}"
            class="form-control @error('capacity_quantity') is-invalid @enderror"
        >
        @error('capacity_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="capacity_unit" class="form-label">Capacity Unit</label>
        <input
            type="text"
            id="capacity_unit"
            name="capacity_unit"
            maxlength="30"
            value="{{ old('capacity_unit', $vehicle->capacity_unit ?? '') }}"
            class="form-control @error('capacity_unit') is-invalid @enderror"
            placeholder="e.g. crates or litre"
        >
        @error('capacity_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notes <span class="text-muted">(optional)</span></label>
        <textarea
            id="notes"
            name="notes"
            rows="3"
            maxlength="2000"
            class="form-control @error('notes') is-invalid @enderror"
        >{{ old('notes', $vehicle->notes ?? '') }}</textarea>
        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input
                class="form-check-input"
                type="checkbox"
                id="is_active"
                name="is_active"
                value="1"
                @checked((bool) old('is_active', $vehicle->is_active ?? true))
            >
            <label class="form-check-label" for="is_active">Active vehicle</label>
        </div>
        <div class="form-text">
            Inactive vehicles remain in logistics history but will not be selectable for new delivery assignments.
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        {{ isset($vehicle) ? 'Update Vehicle' : 'Save Vehicle' }}
    </button>
    <a href="{{ route('vehicles.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
