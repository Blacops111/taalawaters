@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="mb-1">V2 Sales Data Report</h2>
            <p class="text-muted mb-0">Summary of completed V2 sales only. Draft sales are excluded.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('sales-orders.history') }}" class="btn btn-outline-primary">Completed Sales History</a>
            <a href="{{ route('sales-orders.create') }}" class="btn btn-outline-secondary">Back to V2 Sales</a>
        </div>
    </div>

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
        <div class="card-header"><strong>Report Date Range</strong></div>
        <div class="card-body">
            <form method="GET" action="{{ route('sales-orders.report') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="date_from" class="form-label">From Date</label>
                        <input
                            type="date"
                            id="date_from"
                            name="date_from"
                            value="{{ $filters['date_from'] ?? '' }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="date_to" class="form-label">To Date</label>
                        <input
                            type="date"
                            id="date_to"
                            name="date_to"
                            value="{{ $filters['date_to'] ?? '' }}"
                            class="form-control"
                        >
                    </div>

                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Run Report</button>
                        <a href="{{ route('sales-orders.report') }}" class="btn btn-outline-secondary">All Dates</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Completed Sales</div>
                    <div class="fs-4 fw-bold">{{ number_format((int) $summary->completed_sales_count) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Sales Value</div>
                    <div class="fs-4 fw-bold">KES {{ number_format((float) $summary->total_sales_value, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Units Sold</div>
                    <div class="fs-4 fw-bold">{{ number_format($totalUnitsSold, 0) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Walk-in Sales</div>
                    <div class="fs-4 fw-bold">KES {{ number_format((float) $summary->walk_in_total, 2) }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Business Sales</div>
                    <div class="fs-4 fw-bold">KES {{ number_format((float) $summary->business_total, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Product Sales Breakdown</strong>
            <span class="text-muted small">
                {{ filled($filters['date_from'] ?? null) ? $filters['date_from'] : 'Beginning' }}
                to
                {{ filled($filters['date_to'] ?? null) ? $filters['date_to'] : 'Latest' }}
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Finished Product</th>
                            <th class="text-end">Units Sold</th>
                            <th class="text-end">Sales Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productBreakdown as $product)
                            <tr>
                                <td>{{ $product->sku }}</td>
                                <td>{{ $product->name }}</td>
                                <td class="text-end">{{ number_format((float) $product->units_sold, 0) }}</td>
                                <td class="text-end fw-semibold">KES {{ number_format((float) $product->sales_value, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No completed V2 sales exist for the selected date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0">
        PDF and Excel exports will be added after this report screen is verified.
    </div>
</div>
@endsection
