@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Adjust Stock</h2>
            <p class="text-muted mb-0">Record damage, wastage, or a verified stock-count correction.</p>
        </div>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">
            Back to Inventory
        </a>
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

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.adjustments.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="inventory_item_id" class="form-label">Inventory Item</label>
                    <select name="inventory_item_id"
                            id="inventory_item_id"
                            class="form-select"
                            required>
                        <option value="">Select an item</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}"
                                    {{ (string) old('inventory_item_id') === (string) $item->id ? 'selected' : '' }}>
                                {{ $item->sku }} — {{ $item->name }}
                                (Balance: {{ number_format((float) ($item->stock_balance ?? 0), 3) }} {{ $item->unit }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label for="adjustment_type" class="form-label">Adjustment Type</label>
                    <select name="adjustment_type"
                            id="adjustment_type"
                            class="form-select"
                            required>
                        <option value="">Select a reason</option>
                        <option value="damage" {{ old('adjustment_type') === 'damage' ? 'selected' : '' }}>
                            Damage / Breakage
                        </option>
                        <option value="wastage" {{ old('adjustment_type') === 'wastage' ? 'selected' : '' }}>
                            Wastage / Spoilage
                        </option>
                        <option value="stock_correction_decrease" {{ old('adjustment_type') === 'stock_correction_decrease' ? 'selected' : '' }}>
                            Physical Count Correction — Decrease
                        </option>
                        <option value="stock_correction_increase" {{ old('adjustment_type') === 'stock_correction_increase' ? 'selected' : '' }}>
                            Physical Count Correction — Increase
                        </option>
                    </select>
                    <div class="form-text">
                        Damage, wastage, and decrease corrections reduce stock. Increase corrections add stock.
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number"
                               name="quantity"
                               id="quantity"
                               class="form-control"
                               min="0.001"
                               step="0.001"
                               value="{{ old('quantity') }}"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="occurred_at" class="form-label">Date & Time</label>
                        <input type="datetime-local"
                               name="occurred_at"
                               id="occurred_at"
                               class="form-control"
                               value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}"
                               required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="reference" class="form-label">Reference <span class="text-muted">(optional)</span></label>
                    <input type="text"
                           name="reference"
                           id="reference"
                           class="form-control"
                           maxlength="150"
                           value="{{ old('reference') }}"
                           placeholder="e.g. COUNT-2026-09-14 or DAMAGE-001">
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Reason / Notes</label>
                    <textarea name="notes"
                              id="notes"
                              class="form-control"
                              rows="4"
                              maxlength="1000"
                              required
                              placeholder="Explain what happened and why this adjustment is necessary.">{{ old('notes') }}</textarea>
                    <div class="form-text">
                        Notes are required so every manual stock change has an audit reason.
                    </div>
                </div>

                <button type="submit" class="btn btn-warning">
                    Record Adjustment
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
