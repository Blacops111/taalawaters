@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Low Stock</h2>
            <p class="text-muted mb-0">
                Items appear here automatically when their live balance reaches or drops below the configured reorder level.
            </p>
        </div>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">
            Back to Inventory
        </a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Unit</th>
                            <th class="text-end">Live Balance</th>
                            <th class="text-end">Reorder Level</th>
                            <th class="text-end">Shortfall</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            @php
                                $balance = (float) ($item->stock_balance ?? 0);
                                $reorderLevel = (float) $item->reorder_level;
                                $shortfall = max(0, $reorderLevel - $balance);
                            @endphp
                            <tr>
                                <td><strong>{{ $item->sku }}</strong></td>
                                <td>{{ $item->name }}</td>
                                <td>{{ \Illuminate\Support\Str::headline($item->category) }}</td>
                                <td>{{ $item->unit === 'litre' ? 'Litre' : \Illuminate\Support\Str::headline($item->unit) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($balance, 3) }}</td>
                                <td class="text-end">{{ number_format($reorderLevel, 3) }}</td>
                                <td class="text-end">{{ number_format($shortfall, 3) }}</td>
                                <td>
                                    @if($balance <= 0)
                                        <span class="badge bg-danger">Out of Stock</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Low Stock</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No configured items are currently low on stock.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($items->hasPages())
        <div class="mt-3">
            {{ $items->links() }}
        </div>
    @endif
</div>
@endsection
