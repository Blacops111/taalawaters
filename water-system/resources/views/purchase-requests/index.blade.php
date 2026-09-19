@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">Purchase Requests</h2>
            <p class="text-muted mb-0">
                Internal requests for materials. Creating a request does not change inventory.
            </p>
        </div>

        <a href="{{ route('purchase-requests.create') }}" class="btn btn-primary">
            New Purchase Request
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Requested At</th>
                            <th>Requested By</th>
                            <th>Supplier</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchaseRequests as $purchaseRequest)
                            <tr>
                                <td><strong>{{ $purchaseRequest->reference ?: 'Pending' }}</strong></td>
                                <td>{{ optional($purchaseRequest->requested_at)->format('Y-m-d H:i') }}</td>
                                <td>{{ $purchaseRequest->requester?->name ?: '—' }}</td>
                                <td>{{ $purchaseRequest->supplier?->name ?: 'Not selected yet' }}</td>
                                <td>{{ number_format($purchaseRequest->items_count) }}</td>
                                <td>
                                    @switch($purchaseRequest->status)
                                        @case('draft')
                                            <span class="badge bg-secondary">Draft</span>
                                            @break
                                        @case('submitted')
                                            <span class="badge bg-info text-dark">Submitted</span>
                                            @break
                                        @case('approved')
                                            <span class="badge bg-success">Approved</span>
                                            @break
                                        @case('rejected')
                                            <span class="badge bg-danger">Rejected</span>
                                            @break
                                        @default
                                            <span class="badge bg-light text-dark">{{ ucfirst($purchaseRequest->status) }}</span>
                                    @endswitch
                                </td>
                                <td>{{ $purchaseRequest->notes ?: '—' }}</td>
                                <td class="text-end">
                                    <a
                                        href="{{ route('purchase-requests.edit', $purchaseRequest) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        {{ $purchaseRequest->status === AppModelsPurchaseRequest::STATUS_DRAFT ? 'Manage Items' : 'View' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    No purchase requests have been created yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $purchaseRequests->links() }}
    </div>
</div>
@endsection
