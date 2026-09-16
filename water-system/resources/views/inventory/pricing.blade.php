@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h2 class="mb-1">Finished Product Sales Pricing</h2>
            <p class="text-muted mb-0">Set the standard retail price for walk-in sales and wholesale price for business customer sales.</p>
        </div>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Back to Inventory</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
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
                            <th>Finished Product</th>
                            <th style="min-width: 180px;">Retail Price (KES)</th>
                            <th style="min-width: 180px;">Wholesale Price (KES)</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td><strong>{{ $product->sku }}</strong></td>
                                <td>{{ $product->name }}</td>
                                <td colspan="3">
                                    <form method="POST"
                                          action="{{ route('inventory.pricing.update', $product) }}"
                                          class="row g-2 align-items-center">
                                        @csrf
                                        @method('PATCH')

                                        <div class="col-md-4">
                                            <input
                                                type="number"
                                                name="retail_price"
                                                min="0"
                                                step="0.01"
                                                value="{{ old('retail_price', $product->retail_price !== null ? number_format((float) $product->retail_price, 2, '.', '') : '') }}"
                                                class="form-control"
                                                placeholder="Retail price"
                                                required
                                            >
                                        </div>

                                        <div class="col-md-4">
                                            <input
                                                type="number"
                                                name="wholesale_price"
                                                min="0"
                                                step="0.01"
                                                value="{{ old('wholesale_price', $product->wholesale_price !== null ? number_format((float) $product->wholesale_price, 2, '.', '') : '') }}"
                                                class="form-control"
                                                placeholder="Wholesale price"
                                                required
                                            >
                                        </div>

                                        <div class="col-md-4 text-md-end">
                                            <button type="submit" class="btn btn-primary">Save Prices</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No active sellable finished products found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0">
        Wholesale price must be equal to or lower than retail price. In the next sales step, walk-in drafts will use retail price automatically and business customer drafts will use wholesale price automatically.
    </div>
</div>
@endsection
