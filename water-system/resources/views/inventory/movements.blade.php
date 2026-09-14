@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Stock Movement History</h2>
            <p class="text-muted mb-0">Read-only audit trail of every inventory increase and decrease.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Back to Inventory</a>
            <a href="{{ route('inventory.receipts.create') }}" class="btn btn-primary">Record Stock</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('inventory.movements') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="inventory_item_id" class="form-label">Item</label>
                    <select name="inventory_item_id" id="inventory_item_id" class="form-select">
                        <option value="">All items</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" @selected((string) request('inventory_item_id') === (string) $item->id)>
                                {{ $item->sku }} — {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="movement_type" class="form-label">Movement Type</label>
                    <select name="movement_type" id="movement_type" class="form-select">
                        <option value="">All types</option>
                        @foreach($movementTypes as $type)
                            <option value="{{ $type }}" @selected(request('movement_type') === $type)>
                                {{ \Illuminate\Support\Str::headline($type) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="from" class="form-label">From</label>
                    <input type="date" name="from" id="from" value="{{ request('from') }}" class="form-control">
                </div>

                <div class="col-md-2">
                    <label for="to" class="form-label">To</label>
                    <input type="date" name="to" id="to" value="{{ request('to') }}" class="form-control">
                </div>

                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>

                @if(request()->hasAny(['inventory_item_id', 'movement_type', 'from', 'to']))
                    <div class="col-12">
                        <a href="{{ route('inventory.movements') }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date / Time</th>
                            <th>SKU</th>
                            <th>Item</th>
                            <th>Movement</th>
                            <th class="text-end">Change</th>
                            <th>Reference</th>
                            <th>Recorded By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $movement)
                            @php
                                $quantity = (float) $movement->quantity_delta;
                            @endphp
                            <tr>
                                <td>{{ $movement->occurred_at?->format('d M Y H:i') }}</td>
                                <td><strong>{{ $movement->inventoryItem?->sku ?? '—' }}</strong></td>
                                <td>{{ $movement->inventoryItem?->name ?? 'Unknown Item' }}</td>
                                <td>{{ \Illuminate\Support\Str::headline($movement->movement_type) }}</td>
                                <td class="text-end fw-semibold {{ $quantity < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $quantity > 0 ? '+' : '' }}{{ number_format($quantity, 3) }}
                                    {{ $movement->inventoryItem?->unit === 'litre' ? 'L' : '' }}
                                </td>
                                <td>{{ $movement->reference ?: '—' }}</td>
                                <td>{{ $movement->creator?->name ?? 'System' }}</td>
                                <td>{{ $movement->notes ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No stock movements found for the selected filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($movements->hasPages())
        <div class="mt-3">
            {{ $movements->links() }}
        </div>
    @endif
</div>
@endsection
