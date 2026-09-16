@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">Completed Sales History</h2>
            <p class="text-muted mb-0">Read-only record of V2 sales that have already deducted finished-product inventory.</p>
        </div>
        <a href="{{ route('sales-orders.create') }}" class="btn btn-outline-secondary">
            Back to V2 Sales
        </a>
    </div>

    <div class="alert alert-info">
        Completed sales are read-only. Corrections must not overwrite the original sale or its inventory movements.
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Sale Date</th>
                            <th>Items</th>
                            <th>Recorded By</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($completedSales as $sale)
                            <tr>
                                <td><strong>{{ $sale->reference }}</strong></td>
                                <td>{{ $sale->sale_type === 'business' ? 'Business Customer' : 'Walk-in' }}</td>
                                <td>{{ $sale->customer?->name ?? 'Walk-in' }}</td>
                                <td>{{ $sale->sale_at?->format('Y-m-d H:i') }}</td>
                                <td style="min-width: 260px;">
                                    @foreach($sale->items as $item)
                                        <div>
                                            {{ number_format((float) $item->quantity, 0) }} × {{ $item->inventoryItem?->name ?? 'Unavailable Item' }}
                                        </div>
                                    @endforeach
                                </td>
                                <td>{{ $sale->creator?->name ?? 'System' }}</td>
                                <td class="text-end fw-semibold">KES {{ number_format((float) $sale->total_amount, 2) }}</td>
                                <td><span class="badge bg-success">Completed</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No completed V2 sales yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($completedSales->hasPages())
        <div class="mt-3">
            {{ $completedSales->links() }}
        </div>
    @endif
</div>
@endsection
