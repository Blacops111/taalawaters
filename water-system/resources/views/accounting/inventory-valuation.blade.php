@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <div class="small text-muted">Accounting</div>
        <h2>Historical Inventory Valuation</h2>
        <p class="text-muted">Approved opening values plus recorded stock movements through the selected day (UTC). All values are in KES.</p>
    </div>
    <form method="GET" action="{{ route('accounting.inventory-valuation') }}" class="card mb-4">
        <div class="card-body d-flex align-items-end gap-3 flex-wrap">
            <div>
                <label for="as_of" class="form-label">As of</label>
                <input type="date" id="as_of" name="as_of" value="{{ old('as_of', $asOf) }}" max="{{ now()->utc()->toDateString() }}" class="form-control @error('as_of') is-invalid @enderror" required>
                @error('as_of')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">View valuation</button>
            <a href="{{ route('accounting.inventory-valuation') }}" class="btn btn-outline-secondary">Today</a>
            <a href="{{ route('accounting.inventory-costing') }}" class="btn btn-outline-secondary">Current costing details</a>
        </div>
    </form>

    @if(!$available)
        <div class="alert alert-info">{{ $unavailableReason }}
            @if($activatedAt)<div>Costing activated: {{ $activatedAt->format('Y-m-d H:i:s') }} UTC.</div>@endif
        </div>
    @else
        @if($isReconciled)
            <div class="alert alert-success">Reconciled — inventory valuation equals account 1300 as of {{ $asOf }}.</div>
        @else
            <div class="alert alert-danger" role="alert">
                <strong>Review required — inventory reconciliation is not confirmed.</strong>
                @foreach($issues as $issue)<div>{{ $issue }}</div>@endforeach
                @if($differenceCents !== 0)<div>Inventory valuation minus account 1300: KES {{ number_format($differenceCents / 100, 2) }}.</div>@endif
            </div>
        @endif
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="card h-100"><div class="card-body">Inventory valuation<strong class="d-block">KES {{ number_format($totalValueCents / 100, 2) }}</strong></div></div></div>
            <div class="col-md-4"><div class="card h-100"><div class="card-body">1300 Inventory (debit minus credit)<strong class="d-block">KES {{ number_format($ledgerCents / 100, 2) }}</strong></div></div></div>
            <div class="col-md-4"><div class="card h-100"><div class="card-body">Difference (valuation minus ledger)<strong class="d-block">KES {{ number_format($differenceCents / 100, 2) }}</strong></div></div></div>
        </div>
        <div class="card">
            <div class="card-header"><strong>Inventory valuation as of {{ $asOf }}</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th scope="col">SKU</th><th scope="col">Item</th><th scope="col" class="text-end">Quantity</th><th scope="col">Unit</th><th scope="col" class="text-end">Average cost (KES)</th><th scope="col" class="text-end">Value (KES)</th></tr></thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="{{ $row->has_issue ? 'table-warning' : '' }}">
                                <td>{{ $row->sku }}</td>
                                <td>{{ $row->name }} @if(!$row->is_active)<span class="badge bg-secondary">Inactive</span>@endif @if($row->has_issue)<span class="badge bg-warning text-dark">Review required</span>@endif</td>
                                <td class="text-end">{{ number_format($row->quantity_milli / 1000, 3) }}</td>
                                <td>{{ $row->unit }}</td>
                                <td class="text-end">{{ $row->average_cost ?? '—' }}</td>
                                <td class="text-end">{{ number_format($row->value_cents / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No inventory items existed by this date.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr><th scope="row" colspan="5">Total value</th><td class="text-end fw-bold">{{ number_format($totalValueCents / 100, 2) }}</td></tr></tfoot>
                </table>
            </div>
            <div class="card-footer small text-muted">
                Costing activated {{ $activatedAt->format('Y-m-d H:i:s') }} UTC. Zero-value stock and inactive items are included.
                Item names, units and active status are current labels; quantities and values are historical.
                Average costs are rounded for display only; recorded cents determine totals. Reversals apply on their own movement and journal dates.
                Today's report includes activity recorded so far. Later backdated journals may change a past report; this is not a frozen period-close snapshot.
            </div>
        </div>
        <div class="d-flex gap-3 flex-wrap mt-3">
            <a href="{{ route('accounting.trial-balance', ['as_of' => $asOf]) }}">Trial Balance for this date</a>
            <a href="{{ route('accounting.journals', ['status' => 'posted', 'date_to' => $asOf]) }}">Posted journals through this date</a>
        </div>
    @endif
</div>
@endsection
