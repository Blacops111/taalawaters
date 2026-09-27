@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 1100px;">
    <div class="mb-4">
        <div class="small text-muted">Accounting</div>
        <h2 class="mb-1">Profit &amp; Loss</h2>
        <p class="text-muted mb-0">Posted revenue and expenses for the selected period. All amounts are in KES.</p>
    </div>
    <form method="GET" action="{{ route('accounting.profit-loss') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex align-items-end gap-3 flex-wrap">
            <div>
                <label for="date_from" class="form-label">From</label>
                <input type="date" id="date_from" name="date_from" value="{{ old('date_from', $dateFrom) }}" class="form-control @error('date_from') is-invalid @enderror" required>
                @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="date_to" class="form-label">To (inclusive)</label>
                <input type="date" id="date_to" name="date_to" value="{{ old('date_to', $dateTo) }}" class="form-control @error('date_to') is-invalid @enderror" required>
                @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">View Report</button>
            <a class="btn btn-outline-secondary" href="{{ route('accounting.profit-loss') }}">Month to Date</a>
            <a class="btn btn-outline-secondary" href="{{ route('accounting.journals', ['status' => 'posted', 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}">View Period Journals</a>
        </div>
    </form>
    @if(!$hasActivity)
        <div class="alert alert-info">No posted revenue or expense activity for this period.</div>
    @endif
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>{{ $dateFrom }} to {{ $dateTo }}</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th scope="col">Code</th><th scope="col">Account</th><th scope="col" class="text-end">Amount (KES)</th></tr></thead>
                <tbody>
                    @foreach(['Revenue' => $revenueRows, 'Expenses' => $expenseRows] as $label => $rows)
                        <tr class="table-light"><th scope="rowgroup" colspan="3">{{ $label }}</th></tr>
                        @forelse($rows as $row)
                            <tr><td>{{ $row->code }}</td><td>{{ $row->name }} @if(!$row->is_active)<span class="badge bg-secondary">Inactive</span>@endif</td><td class="text-end">{{ number_format($row->amount_cents / 100, 2) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No posted {{ strtolower($label) }} activity.</td></tr>
                        @endforelse
                        <tr class="fw-semibold"><th colspan="2" scope="row">Total {{ strtolower($label) }}</th><td class="text-end">{{ number_format(($label === 'Revenue' ? $revenueCents : $expenseCents) / 100, 2) }}</td></tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light"><tr class="fw-bold"><th colspan="2" scope="row">{{ $netProfitCents > 0 ? 'Net profit' : ($netProfitCents < 0 ? 'Net loss' : 'Net result') }}</th><td class="text-end {{ $netProfitCents < 0 ? 'text-danger' : '' }}">{{ number_format($netProfitCents / 100, 2) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="card-footer bg-white text-muted small">
            Includes posted adjustments and reversals on their journal dates. Accounts with activity remain visible even when their net amount is zero.
            This report reflects posted revenue and expense movements, including any posted closing entries.
            Profit remains provisional while costs such as cost of goods sold (COGS) are not fully posted.
        </div>
    </div>
</div>
@endsection
