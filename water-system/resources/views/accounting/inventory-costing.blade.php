@extends('layouts.app')
@section('content')
<div class="container">
    <h2>Inventory Costing</h2>
    <p class="text-muted">Moving weighted-average material costs, in KES.</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="alert alert-info">Material costing includes approved purchases and material costs transferred through production. Borehole water has zero acquisition cost. Electricity, labour and other operating costs remain in Expenses; they are not allocated to finished stock in this release.</div>
    @if(!$setup->is_active)
        <div class="card card-body mb-4">
            <h4>Review opening inventory</h4>
            <p>Enter the total value of the stock currently on hand for every item. Use verified opening costs, not selling prices. Existing records are preserved. The next page previews any journal needed to reconcile these values with account 1300.</p>
            <p>Activation starts costing from the current time. It cannot be undone from this page. Older sales and production cannot be reversed afterwards; resolve outstanding corrections first. New receipts must use Goods Receiving, and dated stock activity must follow the latest movement for each item.</p>
            <form method="POST" action="{{ route('accounting.inventory-costing.preview') }}">
                @csrf
                <input type="hidden" name="ledger_cents" value="{{ $ledger }}">
                <p>Current Inventory journal balance: <strong>KES {{ number_format($ledger / 100, 2) }}</strong></p>
                <div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th>Item</th><th class="text-end">Quantity</th><th>Total opening value (KES)</th></tr></thead>
                    <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>{{ $item->sku }} — {{ $item->name }} @if(!$item->is_active)<span class="badge bg-secondary">Inactive</span>@endif</td>
                            <td class="text-end">{{ number_format((float) $item->quantity, 3) }} {{ $item->unit }}<input type="hidden" name="quantities[{{ $item->id }}]" value="{{ number_format((float) $item->quantity, 3, '.', '') }}"></td>
                            <td><input aria-label="Opening value for {{ $item->sku }}" class="form-control" type="number" name="values[{{ $item->id }}]" min="0" max="999999999999.99" step="0.01" required value="{{ old('values.'.$item->id, (float) $item->quantity == 0 || $item->category === 'raw_water' ? '0.00' : '') }}" @readonly((float) $item->quantity == 0 || $item->category === 'raw_water')></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <label for="offset_code" class="form-label">Account for any opening reconciliation difference</label>
                <select id="offset_code" name="offset_code" class="form-select mb-3" required>
                    <option value="">Select based on your opening records</option>
                    <option value="3100" @selected(old('offset_code') === '3100')>3100 Owner Capital — opening stock contributed by the owner</option>
                    <option value="3200" @selected(old('offset_code') === '3200')>3200 Retained Earnings — approved correction to prior accumulated results</option>
                </select>
                <label for="notes" class="form-label">Valuation basis and reconciliation reason</label>
                <textarea id="notes" name="notes" class="form-control mb-3" required maxlength="1000">{{ old('notes') }}</textarea>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="acknowledgement" value="1" id="acknowledgement" required><label class="form-check-label" for="acknowledgement">I reviewed all opening values and approve zero acquisition cost for meter-managed borehole water. Labour and power remain separately expensed.</label></div>
                <button class="btn btn-primary" @disabled($items->isEmpty())>Preview opening reconciliation</button>
            </form>
        </div>
    @else
        <p>Activated {{ $setup->activated_at->format('Y-m-d H:i:s') }} UTC. {{ $setup->notes }}</p>
        <div class="alert {{ $total === $ledger ? 'alert-success' : 'alert-warning' }}">
            Inventory valuation: KES {{ number_format($total / 100, 2) }} · Account 1300: KES {{ number_format($ledger / 100, 2) }} · Difference: KES {{ number_format(($total - $ledger) / 100, 2) }}.
            @if($total !== $ledger)Review inventory journals and stock history before relying on this valuation.@endif
        </div>
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Item</th><th class="text-end">Quantity</th><th class="text-end">Average cost (KES)</th><th class="text-end">Value (KES)</th></tr></thead>
            <tbody>@foreach($items as $item)
                @php($balance = $balances->get($item->id))
                <tr><td>{{ $item->sku }} — {{ $item->name }}</td><td class="text-end">{{ number_format((float) $item->quantity, 3) }} {{ $item->unit }}</td><td class="text-end">{{ $balance && $balance->quantity_milli > 0 ? number_format($balance->value_cents * 10 / $balance->quantity_milli, 6) : '—' }}</td><td class="text-end">{{ number_format(($balance->value_cents ?? 0) / 100, 2) }}</td></tr>
            @endforeach</tbody>
        </table></div>
        <h4>Costed stock history</h4>
        <p>Values are permanent snapshots. A sale reversal restores its original cost. Production reversal requires no subsequent stock activity on its materials or output.</p>
        <div class="table-responsive"><table class="table table-sm">
            <thead><tr><th>Date (UTC)</th><th>Item</th><th>Movement</th><th>Reference</th><th class="text-end">Quantity</th><th class="text-end">Value change (KES)</th></tr></thead>
            <tbody>@forelse($movements as $movement)
                <tr><td>{{ $movement->occurred_at->format('Y-m-d H:i:s') }}</td><td>{{ $movement->inventoryItem?->sku }}</td><td>{{ str_replace('_', ' ', $movement->movement_type) }}</td><td>{{ $movement->reference }}</td><td class="text-end">{{ number_format((float) $movement->quantity_delta, 3) }}</td><td class="text-end">{{ number_format($movement->value_delta_cents / 100, 2) }}</td></tr>
            @empty<tr><td colspan="6">No costed stock movements yet.</td></tr>@endforelse</tbody>
        </table></div>
        {{ $movements->links() }}
    @endif
</div>
@endsection
