@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 1100px;">
    <div class="mb-4">
        <div class="small text-muted">Accounting</div>
        <h2 class="mb-1">Balance Sheet</h2>
        <p class="text-muted mb-0">Assets, liabilities and equity from posted journals up to and including the selected date. All amounts are in KES.</p>
    </div>

    <form method="GET" action="{{ route('accounting.balance-sheet') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex align-items-end gap-3 flex-wrap">
            <div>
                <label for="as_of" class="form-label">As of</label>
                <input type="date" id="as_of" name="as_of" value="{{ old('as_of', $asOf) }}"
                    class="form-control @error('as_of') is-invalid @enderror" required>
                @error('as_of')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">View Balance Sheet</button>
            <a href="{{ route('accounting.balance-sheet') }}" class="btn btn-outline-secondary">Today</a>
            <a href="{{ route('accounting.trial-balance', ['as_of' => $asOf]) }}" class="btn btn-outline-secondary">Compare Trial Balance</a>
        </div>
    </form>

    @if(!$hasActivity)
        <div class="alert alert-info">No posted journal activity on or before {{ $asOf }}.</div>
    @elseif($unclassifiedRows->isNotEmpty())
        <div class="alert alert-danger">Some accounts have an unsupported account type. Correct their classification before relying on this report:
            @foreach($unclassifiedRows as $account) {{ $account->code }} — {{ $account->name }}@if(!$loop->last),@endif @endforeach
        </div>
    @elseif($isBalanced)
        <div class="alert alert-success">Balanced — assets equal liabilities plus equity as of {{ $asOf }}.</div>
    @else
        <div class="alert alert-danger" role="alert">Out of balance — difference KES {{ number_format($differenceCents / 100, 2) }}. Review the posted journals.</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Balance Sheet as of {{ $asOf }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th scope="col">Code</th><th scope="col">Account</th><th scope="col" class="text-end">Balance (KES)</th></tr></thead>
                <tbody>
                    @foreach(['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Equity'] as $type => $label)
                        <tr class="table-light"><th colspan="3" scope="rowgroup">{{ $label }}</th></tr>
                        @forelse($sections[$type] as $account)
                            <tr>
                                <td>{{ $account->code }}</td>
                                <td>{{ $account->name }} @if(!$account->is_active)<span class="badge bg-secondary">Inactive</span>@endif</td>
                                <td class="text-end">{{ number_format($account->balance_cents / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No posted {{ strtolower($label) }} account activity.</td></tr>
                        @endforelse
                        @if($type === 'equity')
                            <tr><td>—</td><td>Unclosed earnings / (loss)<div class="small text-muted">Posted revenue less expenses not yet transferred to equity.</div></td><td class="text-end">{{ number_format($earningsCents / 100, 2) }}</td></tr>
                        @endif
                        <tr class="fw-semibold">
                            <th colspan="2" scope="row">Total {{ strtolower($label) }}</th>
                            <td class="text-end">{{ number_format(($type === 'asset' ? $assetCents : ($type === 'liability' ? $liabilityCents : $equityCents)) / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light"><tr class="fw-bold"><th colspan="2" scope="row">Total liabilities and equity</th><td class="text-end">{{ number_format($liabilitiesAndEquityCents / 100, 2) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="card-footer bg-white text-muted small">
            Earnings include all unclosed revenue and expense balances through {{ $asOf }}; no fiscal-year start is assumed.
            Posted closing entries transfer earnings into recorded equity without counting them twice.
            This report reflects recorded journals; missing opening balances, costs or expenses will affect the financial position shown.
        </div>
    </div>
</div>
@endsection
