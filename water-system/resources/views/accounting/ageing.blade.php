@extends('layouts.app')

@section('content')
<div class="container">
    <h2>{{ $isCustomer ? 'Customer' : 'Supplier' }} Outstanding Balances &amp; Ageing</h2>
    <p class="text-muted">Posted account {{ $accountCode }} balances through {{ $asOf }} (inclusive journal dates, UTC). All amounts in KES.</p>
    <form method="GET" class="card mb-4">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <div><label for="as_of" class="form-label">As of</label><input type="date" name="as_of" id="as_of" class="form-control" value="{{ $asOf }}" max="{{ now()->utc()->toDateString() }}" required></div>
            <div><label for="party_id" class="form-label">{{ $isCustomer ? 'Customer' : 'Supplier' }}</label>
                <select name="party_id" id="party_id" class="form-select"><option value="">All accounts</option>
                    @foreach($parties as $party)<option value="{{ $party->id }}" @selected($partyId === $party->id)>{{ $party->name }}{{ $party->is_active ? '' : ' (Inactive)' }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">View report</button>
            <a class="btn btn-outline-secondary" href="{{ route($isCustomer ? 'accounting.customer-ageing' : 'accounting.supplier-ageing') }}">Reset</a>
        </div>
    </form>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($issues !== [])
        <div class="alert alert-warning"><strong>Control-account review required</strong>
            @foreach($issues as $issue)<div>{{ $issue }}</div>@endforeach
            <div>{{ $unassignedCount }} unassigned journal entries across all accounts; net KES {{ number_format($unassignedBalanceCents / 100, 2) }}.</div>
        </div>
    @endif
    <p>Ageing starts the day after the saved due date. Unknown due dates are shown separately. Payments and reversals affect only their linked document on their posted journal date.</p>
    <div class="row g-3 mb-3">
        @foreach($bucketLabels as $key => $label)
            <div class="col-sm-6 col-lg-4"><div class="card h-100"><div class="card-body">{{ $label }}<strong class="d-block">KES {{ number_format($totals[$key] / 100, 2) }}</strong></div></div></div>
        @endforeach
    </div>
    <div class="alert alert-light border">
        Full selection, all pages: outstanding KES {{ number_format($outstandingCents / 100, 2) }}
        + {{ $isCustomer ? 'customer credits' : 'supplier debit balances' }} KES {{ number_format($creditCents / 100, 2) }}
        = <strong>net KES {{ number_format($netCents / 100, 2) }}</strong>.
        Credits are not automatically offset against other documents.
    </div>
    <div class="card"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th scope="col">Account</th><th scope="col">Document</th><th scope="col">Due date</th><th scope="col">Days overdue</th><th scope="col">Category</th><th scope="col" class="text-end">Outstanding (KES)</th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td><a href="{{ route($isCustomer ? 'accounting.customer-statements.show' : 'accounting.supplier-statements.show', [$isCustomer ? 'customer' : 'supplier' => $row->party_id, 'date_to' => $asOf]) }}">{{ $row->party_name }}</a>@if(!$row->party_active)<span class="badge bg-secondary">Inactive</span>@endif</td>
                        <td>{{ $row->ageing_reference ?: ('Document #'.$row->document_id) }}</td>
                        <td>{{ $row->due_date ?? 'Due date unknown' }}</td>
                        <td>{{ $row->bucket === 'credit' ? '—' : ($row->days_overdue ?? '—') }}</td>
                        <td>{{ $row->bucket === 'credit' ? ($isCustomer ? 'Customer credit' : 'Supplier debit balance') : $bucketLabels[$row->bucket] }}</td>
                        <td class="text-end">{{ number_format($row->balance_cents / 100, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ $rows->total() === 0 ? 'No outstanding posted balances for this selection.' : 'No records on this page. Use the pagination links.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $rows->links() }}</div>
    <details class="mt-4"><summary>Control-account coverage — all {{ $isCustomer ? 'customers' : 'suppliers' }}</summary>
        <p class="mt-2">Account {{ $accountCode }}: KES {{ number_format($controlBalanceCents / 100, 2) }} = attributed KES {{ number_format($attributedBalanceCents / 100, 2) }} + unassigned KES {{ number_format($unassignedBalanceCents / 100, 2) }}. This coverage always includes all accounts, even when a filter is selected.</p>
        <a href="{{ route('accounting.trial-balance', ['as_of' => $asOf]) }}">Trial Balance for this date</a>
    </details>
    <p class="small text-muted mt-3">This read-only report does not prove every business document was posted. Unlinked legacy or manual balances require review. Account terms apply only to newly created sales drafts and goods receipts; they never backfill historic due dates. Later backdated journals can change historical balances. Current account names label the report.</p>
</div>
@endsection
