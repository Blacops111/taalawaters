@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Record Production Run</h2>
            <p class="text-muted mb-0">Consume configured recipe materials and add finished water to inventory.</p>
        </div>
        <a href="{{ route('production.recipes.index') }}" class="btn btn-outline-secondary">
            Manage Recipes
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Production action was not completed.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('production.runs.store') }}">
                @csrf
                <input type="hidden" name="completion_key" value="{{ old('completion_key', (string) \Illuminate\Support\Str::uuid()) }}">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="production_recipe_id" class="form-label">Finished Product</label>
                        <select
                            id="production_recipe_id"
                            name="production_recipe_id"
                            class="form-select @error('production_recipe_id') is-invalid @enderror"
                            required
                        >
                            <option value="">Select configured product</option>
                            @foreach($recipes as $recipe)
                                <option
                                    value="{{ $recipe->id }}"
                                    @selected((string) old('production_recipe_id') === (string) $recipe->id)
                                >
                                    {{ $recipe->finishedProduct->sku }} — {{ $recipe->finishedProduct->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="quantity_produced" class="form-label">Quantity Produced</label>
                        <input
                            type="number"
                            min="1"
                            step="1"
                            id="quantity_produced"
                            name="quantity_produced"
                            value="{{ old('quantity_produced') }}"
                            class="form-control @error('quantity_produced') is-invalid @enderror"
                            required
                        >
                        <div class="form-text">Enter whole finished units only, for example 1, 10, or 100 bottles.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="occurred_at" class="form-label">Production Date & Time</label>
                        <input
                            type="datetime-local"
                            id="occurred_at"
                            name="occurred_at"
                            value="{{ old('occurred_at', now()->format('Y-m-d\\TH:i')) }}"
                            class="form-control @error('occurred_at') is-invalid @enderror"
                            required
                        >
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes <span class="text-muted">(optional)</span></label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            maxlength="2000"
                            class="form-control @error('notes') is-invalid @enderror"
                            placeholder="Example: Morning production batch"
                        >{{ old('notes') }}</textarea>
                    </div>
                </div>

                @if($costingActive)
                    <div class="border rounded p-3 mt-4">
                        <h5>Production labour, electricity and overhead <span class="text-muted">(optional)</span></h5>
                        <p>Allocate only the part of a recorded expense used by this batch. This increases finished-stock cost and reduces the original expense balance without another payment. Leave blank for material-only costing.</p>
                        <p class="small text-muted">Record labour under General Operating Expense, power under Utilities, and eligible production overhead under Repairs or General Operating Expense. Only expenses recorded after costing activation are available. Give the calculation basis; exclude delivery, office administration, abnormal waste and idle-capacity costs. Costs already included in opening stock must not be allocated again.</p>
                        @if($allocationExpenses->isEmpty())
                            <p class="text-muted">No eligible unallocated expenses. Record an eligible expense under <a href="{{ route('accounting.expenses') }}">Accounting → Expenses</a> first.</p>
                        @endif
                        @php($previousAllocations = old('allocations', [[]]))
                        @php($allocationRows = is_array($previousAllocations) ? array_slice(array_values($previousAllocations ?: [[]]), 0, 20) : [[]])
                        <div id="allocation-rows">
                            @foreach($allocationRows as $index => $row)
                                @include('production.runs.allocation-row', ['index' => $index, 'row' => $row])
                            @endforeach
                        </div>
                        <button type="button" id="add-allocation" class="btn btn-sm btn-outline-primary">Add expense allocation</button>
                        <a class="btn btn-sm btn-link" href="{{ route('accounting.production-costs') }}">View allocation history</a>
                    </div>
                    <template id="allocation-template">@include('production.runs.allocation-row', ['index' => '__INDEX__', 'row' => []])</template>
                @else
                    <p class="text-muted mt-4">Activate <a href="{{ route('accounting.inventory-costing') }}">Inventory Costing</a> before allocating production expenses.</p>
                @endif

                <div class="alert alert-info mt-4 mb-3">
                    The system will check every recipe component first. If any material is insufficient, no inventory quantities will be changed.
                </div>

                <button type="submit" class="btn btn-primary">
                    Complete Production Run
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <strong>Recent Production Runs</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Finished Product</th>
                            <th class="text-end">Quantity</th>
                            <th>Status</th>
                            <th>Recorded By</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentRuns as $run)
                            @php
                                $quantity = (float) $run->quantity_produced;
                                $formattedQuantity = abs($quantity - round($quantity)) < 0.0005
                                    ? number_format($quantity, 0)
                                    : number_format($quantity, 3);
                            @endphp
                            <tr>
                                <td><strong>{{ $run->reference }}</strong>
                                    @if($run->costAllocations->isNotEmpty())
                                        <div class="small"><a href="{{ route('accounting.production-costs', ['search' => $run->reference]) }}">Conversion costs: KES {{ number_format($run->costAllocations->sum('amount_cents') / 100, 2) }}</a></div>
                                    @endif
                                </td>
                                <td>{{ $run->occurred_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $run->finishedProduct?->name }}</td>
                                <td class="text-end">
                                    {{ $formattedQuantity }} {{ $run->finishedProduct?->unit }}
                                </td>
                                <td>
                                    @if($run->status === 'reversed')
                                        <span class="badge text-bg-secondary">Reversed</span>
                                    @else
                                        <span class="badge text-bg-success">Completed</span>
                                    @endif
                                </td>
                                <td>{{ $run->creator?->name ?? $run->creator?->email ?? 'System' }}</td>
                                <td class="text-end">
                                    @if($run->status === 'completed' && !$run->reversal)
                                        <a
                                            href="{{ route('production.runs.reversal', $run) }}"
                                            class="btn btn-sm btn-outline-danger"
                                        >
                                            Reverse
                                        </a>
                                    @elseif($run->reversal)
                                        <span class="text-muted small">{{ $run->reversal->reference }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No production runs recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@if($costingActive)
<script>
(() => {
    const rows = document.getElementById('allocation-rows');
    const add = document.getElementById('add-allocation');
    const template = document.getElementById('allocation-template');
    let next = rows.children.length;
    add.addEventListener('click', () => {
        if (rows.children.length >= 20) return;
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(next++)));
        add.disabled = rows.children.length >= 20;
    });
    rows.addEventListener('click', event => {
        const button = event.target.closest('.remove-allocation');
        if (!button) return;
        button.closest('.allocation-row').remove();
        add.disabled = false;
    });
})();
</script>
@endif
@endsection
