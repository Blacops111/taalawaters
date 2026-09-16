@csrf
@if(isset($customer))
    @method('PUT')
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Customer Name</label>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="255"
            value="{{ old('name', $customer->name ?? '') }}"
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
            value="{{ old('phone', $customer->phone ?? '') }}"
            class="form-control @error('phone') is-invalid @enderror"
        >
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email <span class="text-muted">(optional)</span></label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="255"
            value="{{ old('email', $customer->email ?? '') }}"
            class="form-control @error('email') is-invalid @enderror"
        >
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="address" class="form-label">Address <span class="text-muted">(optional)</span></label>
        <textarea
            id="address"
            name="address"
            rows="2"
            maxlength="2000"
            class="form-control @error('address') is-invalid @enderror"
        >{{ old('address', $customer->address ?? '') }}</textarea>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                @checked((bool) old('is_active', $customer->is_active ?? true))
            >
            <label class="form-check-label" for="is_active">Active customer</label>
        </div>
        <div class="form-text">Inactive customers remain in historical sales records but will not be offered for new sales later.</div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ isset($customer) ? 'Update Customer' : 'Save Customer' }}</button>
    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
