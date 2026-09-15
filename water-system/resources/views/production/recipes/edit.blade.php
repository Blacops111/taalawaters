@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Configure Recipe</h2>
            <p class="text-muted mb-0">
                {{ $finishedProduct->sku }} — {{ $finishedProduct->name }}
            </p>
        </div>
        <a href="{{ route('production.recipes.index') }}" class="btn btn-outline-secondary">
            Back to Recipes
        </a>
    </div>

    <div class="alert alert-info">
        Enter the exact material quantities Taala uses for the output quantity below.
        Leave materials that are not part of this recipe blank. No stock is deducted on this page.
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

    <form method="POST" action="{{ route('production.recipes.update', $finishedProduct) }}">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-body">
                <label for="output_quantity" class="form-label fw-semibold">Finished Output Quantity</label>
                <div class="input-group" style="max-width: 360px;">
                    <input type="number"
                           id="output_quantity"
                           name="output_quantity"
                           class="form-control"
                           min="0.001"
                           step="0.001"
                           value="{{ old('output_quantity', $finishedProduct->productionRecipe?->output_quantity ?? 1) }}"
                           required>
                    <span class="input-group-text">{{ $finishedProduct->unit }}</span>
                </div>
                <div class="form-text">
                    Component quantities below must be the amounts needed to produce this finished quantity.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">Recipe Components</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Material</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th style="width: 220px;">Quantity Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($materials as $material)
                                <tr>
                                    <td><strong>{{ $material->sku }}</strong></td>
                                    <td>{{ $material->name }}</td>
                                    <td>{{ \Illuminate\Support\Str::headline($material->category) }}</td>
                                    <td>{{ $material->unit === 'litre' ? 'Litre' : \Illuminate\Support\Str::headline($material->unit) }}</td>
                                    <td>
                                        <input type="number"
                                               name="components[{{ $material->id }}]"
                                               class="form-control"
                                               min="0.001"
                                               step="0.001"
                                               value="{{ old('components.'.$material->id, $componentQuantities->get($material->id)) }}"
                                               placeholder="Leave blank if unused"
                                               aria-label="Quantity of {{ $material->name }} required">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No active raw materials or packaging items are available.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button type="submit" class="btn btn-primary">
                Save Recipe
            </button>
        </div>
    </form>
</div>
@endsection
