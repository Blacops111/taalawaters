@csrf
@if(isset($driver))
    @method('PUT')
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Driver Name</label>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="255"
            value="{{ old('name', $driver->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label">Phone <span class="text-muted">(optional)</span></label>
        <input
            type="text"
            id="phone"
            name="phone"
            maxlength="50"
            value="{{ old('phone', $driver->phone ?? '') }}"
            class="form-control @error('phone') is-invalid @enderror"
            placeholder="+254..."
        >
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="license_number" class="form-label">Driving Licence Number <span class="text-muted">(optional)</span></label>
        <input
            type="text"
            id="license_number"
            name="license_number"
            maxlength="100"
            value="{{ old('license_number', $driver->license_number ?? '') }}"
            class="form-control @error('license_number') is-invalid @enderror"
        >
        @error('license_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="license_expiry" class="form-label">Licence Expiry <span class="text-muted">(optional)</span></label>
        <input
            type="date"
            id="license_expiry"
            name="license_expiry"
            value="{{ old('license_expiry', isset($driver) && $driver->license_expiry ? $driver->license_expiry->format('Y-m-d') : '') }}"
            class="form-control @error('license_expiry') is-invalid @enderror"
        >
        @error('license_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">Notes <span class="text-muted">(optional)</span></label>
        <textarea
            id="notes"
            name="notes"
            rows="3"
            maxlength="2000"
            class="form-control @error('notes') is-invalid @enderror"
        >{{ old('notes', $driver->notes ?? '') }}</textarea>
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
                @checked((bool) old('is_active', $driver->is_active ?? true))
            >
            <label class="form-check-label" for="is_active">Active driver</label>
        </div>
        <div class="form-text">
            Inactive drivers remain in historical logistics records but will not be available for new delivery assignments.
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        {{ isset($driver) ? 'Update Driver' : 'Save Driver' }}
    </button>
    <a href="{{ route('drivers.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
