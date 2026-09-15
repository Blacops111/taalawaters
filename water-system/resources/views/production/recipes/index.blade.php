@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Production Recipes</h2>
            <p class="text-muted mb-0">Define the materials required to produce each finished-water SKU.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('production.runs.create') }}" class="btn btn-primary">
                Record Production
            </a>
            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">
                Back to Inventory
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
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
                            <th class="text-end">Recipe Output</th>
                            <th class="text-end">Components</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($finishedProducts as $product)
                            <tr>
                                <td><strong>{{ $product->sku }}</strong></td>
                                <td>{{ $product->name }}</td>
                                <td class="text-end">
                                    @if($product->productionRecipe)
                                        {{ number_format((float) $product->productionRecipe->output_quantity, 3) }} {{ $product->unit }}
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    {{ $product->productionRecipe?->components_count ?? 0 }}
                                </td>
                                <td>
                                    @if($product->productionRecipe)
                                        <span class="badge bg-success">Configured</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Not Configured</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('production.recipes.edit', $product) }}"
                                       class="btn btn-sm btn-primary">
                                        {{ $product->productionRecipe ? 'Edit Recipe' : 'Configure' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No active finished products found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
