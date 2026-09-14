@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Inventory</h2>
            <p class="text-muted mb-0">Live balances calculated from stock movements.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="{{ route('inventory.low-stock') }}"
               class="btn {{ $lowStockCount > 0 ? 'btn-danger' : 'btn-outline-success' }}">
                Low Stock ({{ $lowStockCount }})
            </a>
            <a href="{{ route('inventory.movements') }}" class="btn btn-outline-secondary">
                Movement History
            </a>
            <a href="{{ route('inventory.adjustments.create') }}" class="btn btn-outline-warning">
                Adjust Stock
            </a>
            <a href="{{ route('inventory.receipts.create') }}" class="btn btn-primary">
                Record Stock
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

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
                            <th>Reorder Level</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            @php
                                $balance = (float) ($item->stock_balance ?? 0);
                                $reorderLevel = (float) $item->reorder_level;
                            @endphp
                            <tr>
                                <td><strong>{{ $item->sku }}</strong></td>
                                <td>{{ $item->name }}</td>
                                <td>{{ \Illuminate\Support\Str::headline($item->category) }}</td>
                                <td>{{ $item->unit === 'litre' ? 'Litre' : \Illuminate\Support\Str::headline($item->unit) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($balance, 3) }}</td>
                                <td style="min-width: 210px;">
                                    @if($item->category === 'raw_water')
                                        <span class="text-muted">Meter managed</span>
                                    @else
                                        <form method="POST"
                                              action="{{ route('inventory.reorder-level.update', $item) }}"
                                              class="d-flex gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="number"
                                                   name="reorder_level"
                                                   class="form-control form-control-sm"
                                                   min="0"
                                                   step="0.001"
                                                   value="{{ number_format($reorderLevel, 3, '.', '') }}"
                                                   aria-label="Reorder level for {{ $item->name }}">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                Save
                                            </button>
                                        </form>
                                    @endif
                                </td>
                                <td>
                                    @if($balance < 0)
                                        <span class="badge bg-danger">Negative Stock</span>
                                    @elseif($balance == 0)
                                        <span class="badge bg-secondary">Out of Stock</span>
                                    @elseif($reorderLevel > 0 && $balance <= $reorderLevel)
                                        <span class="badge bg-warning text-dark">Low Stock</span>
                                    @else
                                        <span class="badge bg-success">In Stock</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No inventory items found.
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
