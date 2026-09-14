@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Record Inventory Stock</h2>
            <p class="text-muted mb-0">Use this for opening balances and received materials.</p>
        </div>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Back to Inventory</a>
    </div>

    <div class="alert alert-info">
        Raw borehole water is not entered manually here; it is handled by the automated meter integration.
        Finished products may only receive an opening balance here. Normal finished-product stock will later be created by Production.
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
            <form method="POST" action="{{ route('inventory.receipts.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="inventory_item_id" class="form-label">Inventory Item</label>
                    <select name="inventory_item_id" id="inventory_item_id" class="form-select" required>
                        <option value="">Select an item</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" @selected(old('inventory_item_id') == $item->id)>
                                {{ $item->sku }} — {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label for="movement_type" class="form-label">Stock Entry Type</label>
                    <select name="movement_type" id="movement_type" class="form-select" required>
                        <option value="opening_balance" @selected(old('movement_type') === 'opening_balance')>Opening Balance</option>
                        <option value="stock_received" @selected(old('movement_type') === 'stock_received')>Stock Received</option>
                    </select>
                    <div class="form-text">Each item can have only one opening balance. Use Stock Received for later material deliveries.</div>
                </div>

                <div class="mb-3">
                    <label for="quantity" class="form-label">Quantity</label>
                    <input
                        type="number"
                        step="0.001"
                        min="0.001"
                        name="quantity"
                        id="quantity"
                        value="{{ old('quantity') }}"
                        class="form-control"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="occurred_at" class="form-label">Date & Time</label>
                    <input
                        type="datetime-local"
                        name="occurred_at"
                        id="occurred_at"
                        value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}"
                        class="form-control"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label for="reference" class="form-label">Reference</label>
                    <input
                        type="text"
                        name="reference"
                        id="reference"
                        value="{{ old('reference') }}"
                        class="form-control"
                        maxlength="150"
                        placeholder="e.g. Delivery note, invoice or opening count reference"
                    >
                    <div class="form-text">A reference is required for Stock Received and helps prevent duplicate receipts.</div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3" maxlength="1000">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary">Save Stock Entry</button>
            </form>
        </div>
    </div>
</div>
@endsection
