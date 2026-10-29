@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <div class="small text-muted">Accounting</div>
        <h2>{{ $isCustomer ? 'Customer Statements' : 'Supplier Statements' }}</h2>
        <p class="text-muted">Choose an account to view its opening balance, posted activity and closing balance. Inactive accounts remain available.</p>
    </div>
    <form method="GET" action="{{ route($isCustomer ? 'accounting.customer-statements' : 'accounting.supplier-statements') }}" class="card mb-4">
        <div class="card-body d-flex gap-3 align-items-end flex-wrap">
            <div class="flex-grow-1">
                <label for="search" class="form-label">Search by name, phone or email</label>
                <input id="search" name="search" type="search" maxlength="150" class="form-control" value="{{ $search }}">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <a class="btn btn-outline-secondary" href="{{ route($isCustomer ? 'accounting.customer-statements' : 'accounting.supplier-statements') }}">Clear</a>
        </div>
    </form>
    <div class="card table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th scope="col">Name</th><th scope="col">Phone</th><th scope="col">Email</th><th scope="col">Status</th><th scope="col">Statement</th></tr></thead>
            <tbody>
                @forelse($parties as $party)
                    <tr>
                        <td>{{ $party->name }}</td><td>{{ $party->phone ?: '—' }}</td><td>{{ $party->email ?: '—' }}</td>
                        <td>{{ $party->is_active ? 'Active' : 'Inactive' }}</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route($isCustomer ? 'accounting.customer-statements.show' : 'accounting.supplier-statements.show', $party) }}">View statement</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No matching accounts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $parties->links() }}</div>
</div>
@endsection
