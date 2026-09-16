@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h2 class="mb-1">Sales Draft #{{ $salesOrder->id }}</h2>
            <p class="text-muted mb-0">Add finished-water products to this draft. Stock is deducted only when the sale is completed.</p>
        </div>
        <a href="{{ route('sales-orders.create') }}" class="btn btn-outline-secondary">Back to V2 Sales</a>
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
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Sale Type</div>
                    <strong>{{ $salesOrder->sale_type === 'business' ? 'Business Customer' : 'Walk-in' }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Customer</div>
                    <strong>{{ $salesOrder->customer?->name ?? 'Walk-in' }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Price Level</div>
                    <strong>{{ $salesOrder->sale_type === 'business' ? 'Wholesale Price' : 'Retail Price' }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Draft Total</div>
                    <strong>KES {{ number_format((float) $salesOrder->total_amount, 2) }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Sale Date</div>
                    <strong>{{ $salesOrder->sale_at?->format('Y-m-d H:i') }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Add Product</strong></div>
        <div class="card-body">
            <form method="POST" action="{{ route('sales-orders.items.store', $salesOrder) }}">
                @csrf

                <div class="row g-3 align-items-end">
                    <div class="col-md-7">
                        <label for="inventory_item_id" class="form-label">Finished Product</label>
                        <select id="inventory_item_id" name="inventory_item_id" class="form-select" required>
                            <option value="">-- Select Finished Product --</option>
                            @foreach($finishedProducts as $product)
                                @php
                                    $displayPrice = $product->{$priceColumn};
                                @endphp
                                <option value="{{ $product->id }}" @selected((string) old('inventory_item_id') === (string) $product->id)>
                                    {{ $product->sku }} — {{ $product->name }} — KES {{ number_format((float) $displayPrice, 2) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">
                            Price is selected automatically from the admin-configured {{ $salesOrder->sale_type === 'business' ? 'wholesale' : 'retail' }} price.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            min="1"
                            step="1"
                            value="{{ old('quantity', 1) }}"
                            class="form-control"
                            required
                        >
                        <div class="form-text">Whole finished units only.</div>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Draft Items</strong>
            <span class="badge bg-secondary">{{ $salesOrder->items->count() }} item(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Product</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($salesOrder->items as $item)
                            <tr>
                                <td>{{ $item->inventoryItem->sku }}</td>
                                <td>{{ $item->inventoryItem->name }}</td>
                                <td class="text-end">{{ number_format((float) $item->quantity, 0) }}</td>
                                <td class="text-end">KES {{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="text-end"><strong>KES {{ number_format((float) $item->line_total, 2) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No products have been added to this draft yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($salesOrder->items->isNotEmpty())
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Draft Total</th>
                                <th class="text-end">KES {{ number_format((float) $salesOrder->total_amount, 2) }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    @if($salesOrder->items->isNotEmpty())
        <div class="card border-danger mt-4">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <strong>Ready to complete this sale?</strong>
                    <div class="text-muted small">
                        Completing the sale will permanently deduct the sold quantities from live inventory.
                    </div>
                </div>

                <form method="POST"
                      action="{{ route('sales-orders.complete', $salesOrder) }}"
                      onsubmit="return confirm('Complete this sale and deduct the sold stock from inventory?');">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        Complete Sale & Deduct Stock
                    </button>
                </form>
            </div>
        </div>
    @else
        <div class="alert alert-info mt-4 mb-0">
            Add at least one finished product before the sale can be completed.
        </div>
    @endif
</div>
@endsection
