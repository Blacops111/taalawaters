@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Business Customers</h2>
            <p class="text-muted mb-0">Manage supermarkets, hotels, offices, distributors and other bulk/repeat customers. Walk-in sales do not require a customer account.</p>
        </div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">Add Business Customer</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Business / Account Name</th>
                            <th>Type</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                            <tr>
                                <td><strong>{{ $customer->name }}</strong></td>
                                <td>{{ ucwords(str_replace('_', ' ', $customer->customer_type ?: 'other')) }}</td>
                                <td>{{ $customer->contact_person ?: '—' }}</td>
                                <td>{{ $customer->phone ?: '—' }}</td>
                                <td>{{ $customer->email ?: '—' }}</td>
                                <td>{{ $customer->address ?: '—' }}</td>
                                <td>
                                    @if($customer->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No business customers registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $customers->links() }}
    </div>
</div>
@endsection
