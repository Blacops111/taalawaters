@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">New V2 Sale</h2>
        <p class="text-muted mb-0">Start a walk-in or business customer sale. This step creates a draft only; it does not deduct stock yet.</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('sales-orders.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="sale_type" class="form-label">Sale Type</label>
                        <select id="sale_type" name="sale_type" class="form-select" required>
                            <option value="walk_in" @selected(old('sale_type', 'walk_in') === 'walk_in')>Walk-in Sale</option>
                            <option value="business" @selected(old('sale_type') === 'business')>Business Customer Sale</option>
                        </select>
                    </div>

                    <div class="col-md-6" id="business-customer-group">
                        <label for="customer_id" class="form-label">Business Customer</label>
                        <select id="customer_id" name="customer_id" class="form-select">
                            <option value="">-- Select Business Customer --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                                    {{ $customer->name }} — {{ ucwords(str_replace('_', ' ', $customer->customer_type)) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Required only for Business Customer Sale.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="sale_at" class="form-label">Sale Date & Time</label>
                        <input
                            type="datetime-local"
                            id="sale_at"
                            name="sale_at"
                            value="{{ old('sale_at', now()->format('Y-m-d\\TH:i')) }}"
                            class="form-control"
                            required
                        >
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes <span class="text-muted">(optional)</span></label>
                        <textarea id="notes" name="notes" rows="3" maxlength="2000" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Create Sale Draft</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Recent Draft Sales</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Draft</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Sale Date</th>
                            <th>Items</th>
                            <th>Recorded By</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentDrafts as $draft)
                            <tr>
                                <td>#{{ $draft->id }}</td>
                                <td>{{ $draft->sale_type === 'business' ? 'Business Customer' : 'Walk-in' }}</td>
                                <td>{{ $draft->customer?->name ?? 'Walk-in' }}</td>
                                <td>{{ $draft->sale_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $draft->items_count }}</td>
                                <td>{{ $draft->creator?->name ?? 'System' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('sales-orders.edit', $draft) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No V2 sale drafts yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const saleType = document.getElementById('sale_type');
        const customer = document.getElementById('customer_id');

        function syncCustomerRequirement() {
            const isBusiness = saleType.value === 'business';
            customer.required = isBusiness;

            if (!isBusiness) {
                customer.value = '';
            }
        }

        saleType.addEventListener('change', syncCustomerRequirement);
        syncCustomerRequirement();
    });
</script>
@endsection
