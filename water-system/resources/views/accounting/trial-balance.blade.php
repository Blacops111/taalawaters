@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 1100px;">
    <div class="mb-4">
        <div class="small text-muted">Accounting</div>
        <h2 class="mb-1">Trial Balance</h2>
        <p class="text-muted mb-0">Closing account balances from posted journals up to and including the selected date. All amounts are in KES.</p>
    </div>

    <form method="GET" action="{{ route('accounting.trial-balance') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex align-items-end gap-3 flex-wrap">
            <div>
                <label for="as_of" class="form-label">As of</label>
                <input type="date" id="as_of" name="as_of" value="{{ old('as_of', $asOf) }}"
                    class="form-control @error('as_of') is-invalid @enderror" required>
                @error('as_of')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button class="btn btn-primary" type="submit">View Trial Balance</button>
            <a href="{{ route('accounting.trial-balance') }}" class="btn btn-outline-secondary">Today</a>
        </div>
    </form>

    @if($rows->isEmpty())
        <div class="alert alert-info">No posted journal activity on or before {{ $asOf }}.</div>
    @elseif($isBalanced)
        <div class="alert alert-success">Balanced — total debits equal total credits as of {{ $asOf }}.</div>
    @else
        <div class="alert alert-danger" role="alert">
            Out of balance — difference KES {{ number_format($differenceCents / 100, 2) }}.
            Review the posted journals before relying on this report.
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><strong>Trial Balance as of {{ $asOf }}</strong></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th scope="col">Code</th><th scope="col">Account</th><th scope="col">Type</th><th scope="col" class="text-end">Debit (KES)</th><th scope="col" class="text-end">Credit (KES)</th></tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->code }}</td>
                            <td>{{ $row->name }} @if(!$row->is_active)<span class="badge bg-secondary">Inactive</span>@endif</td>
                            <td>{{ ucfirst($row->type) }}</td>
                            <td class="text-end">{{ number_format($row->debit_cents / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($row->credit_cents / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No account balances to display.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold"><th scope="row" colspan="3">Total</th><td class="text-end">{{ number_format($totalDebitCents / 100, 2) }}</td><td class="text-end">{{ number_format($totalCreditCents / 100, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer bg-white text-muted small">
            Accounts with posted activity are shown, including accounts whose balance is now zero.
            Reversals take effect on their journal date. Equal totals do not guarantee that every transaction is complete or correctly classified.
        </div>
    </div>
</div>
@endsection
