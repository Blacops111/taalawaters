@csrf
@if(isset($customer))
    @method('PUT')
@endif

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Business / Account Name</label>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="255"
            value="{{ old('name', $customer->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="Example: Naivas Westlands"
            required
        >
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="customer_type" class="form-label">Customer Type</label>
        <select
            id="customer_type"
            name="customer_type"
            class="form-select @error('customer_type') is-invalid @enderror"
            required
        >
            <option value="">Select customer type</option>
            @foreach($customerTypes as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(old('customer_type', $customer->customer_type ?? '') === $value)
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('customer_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="contact_person" class="form-label">Contact Person <span class="text-muted">(optional)</span></label>
        <input
            type="text"
            id="contact_person"
            name="contact_person"
            maxlength="255"
            value="{{ old('contact_person', $customer->contact_person ?? '') }}"
            class="form-control @error('contact_person') is-invalid @enderror"
            placeholder="Example: Jane Wanjiku"
        >
        @error('contact_person')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
        <label for="address" class="form-label">Delivery / Business Address <span class="text-muted">(optional)</span></label>
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
            <label class="form-check-label" for="is_active">Active business customer</label>
        </div>
        <div class="form-text">Inactive accounts stay in historical sales records but will not be available for new business-customer sales.</div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ isset($customer) ? 'Update Business Customer' : 'Save Business Customer' }}</button>
    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
