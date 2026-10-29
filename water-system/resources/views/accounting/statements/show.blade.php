@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="small text-muted">Accounting · {{ $accountCode }} {{ $isCustomer ? 'Accounts Receivable' : 'Accounts Payable' }}</div>
            <h2>{{ $isCustomer ? 'Customer Statement' : 'Supplier Statement' }}</h2>
            <h4>{{ $party->name }} @if(!$party->is_active)<span class="badge bg-secondary">Inactive</span>@endif</h4>
            <div class="text-muted">{{ $party->phone }} {{ $party->email }}</div>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route($isCustomer ? 'accounting.customer-statements' : 'accounting.supplier-statements') }}">Choose another account</a>
    </div>
    <form method="GET" action="{{ route($isCustomer ? 'accounting.customer-statements.show' : 'accounting.supplier-statements.show', $party) }}" class="card mb-4">
        <div class="card-body d-flex gap-3 align-items-end flex-wrap">
            <div><label class="form-label" for="date_from">From</label><input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}" class="form-control" required></div>
            <div><label class="form-label" for="date_to">Through</label><input type="date" id="date_to" name="date_to" value="{{ $dateTo }}" class="form-control" required></div>
            <button class="btn btn-primary" type="submit">View statement</button>
            <a class="btn btn-outline-secondary" href="{{ route($isCustomer ? 'accounting.customer-statements.show' : 'accounting.supplier-statements.show', $party) }}">This month</a>
        </div>
    </form>
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if($issues !== [])
        <div class="alert alert-warning" role="alert">
            <strong>Control-account review required</strong>
            @foreach($issues as $issue)<div>{{ $issue }}</div>@endforeach
            @if($unassignedCount > 0)
                <div>Across all {{ $isCustomer ? 'customers' : 'suppliers' }}, {{ $unassignedCount }} unassigned journal entries through {{ $dateTo }} have a net balance of KES {{ number_format($unassignedBalanceCents / 100, 2) }}. This is not an amount assigned to {{ $party->name }}.</div>
            @endif
        </div>
    @endif
    <p>Statement period: <strong>{{ $dateFrom }} to {{ $dateTo }}</strong>, inclusive journal dates. All amounts are in KES.</p>
    <p class="text-muted">{{ $isCustomer ? 'Positive balances are owed by the customer. Negative balances are customer credits.' : 'Positive balances are owed to the supplier. Negative balances are supplier debit balances.' }}</p>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card h-100"><div class="card-body">Opening balance before {{ $dateFrom }}<strong class="d-block">KES {{ number_format($openingCents / 100, 2) }}</strong></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body">Period change<strong class="d-block">KES {{ number_format(($closingCents - $openingCents) / 100, 2) }}</strong></div></div></div>
        <div class="col-md-4"><div class="card h-100"><div class="card-body">Closing balance through {{ $dateTo }}<strong class="d-block">KES {{ number_format($closingCents / 100, 2) }}</strong></div></div></div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th scope="col">Date</th><th scope="col">Document / journal</th><th scope="col">Activity</th><th scope="col" class="text-end">Debit (KES)</th><th scope="col" class="text-end">Credit (KES)</th><th scope="col" class="text-end">Balance (KES)</th></tr></thead>
                <tbody>
                    <tr class="table-light"><th colspan="5" scope="row">{{ $rows->currentPage() === 1 ? 'Opening balance' : 'Balance carried forward to this page' }}</th><td class="text-end">{{ number_format($pageOpeningCents / 100, 2) }}</td></tr>
                    @forelse($rows as $row)
                        <tr>
                            <td class="text-nowrap">{{ $row->date }}</td>
                            <td>
                                <strong>{{ $row->document_reference ?: 'Linked document' }}</strong>
                                <div><a href="{{ route('accounting.journals', ['search' => $row->journal_reference ?: ('JRN-' . str_pad((string) $row->id, 8, '0', STR_PAD_LEFT)), 'status' => 'posted']) }}">{{ $row->journal_reference ?: ('Journal #' . $row->id) }}</a></div>
                                @if($row->external_reference)<div class="small text-muted">{{ $row->external_reference }}</div>@endif
                            </td>
                            <td>{{ $row->kind }}<div class="small text-muted">{{ $row->description }}</div></td>
                            <td class="text-end">{{ number_format($row->debit_cents / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($row->credit_cents / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($row->balance_cents / 100, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">{{ $rows->total() === 0 ? 'No posted activity in this period. Any earlier balance is carried forward.' : 'No transactions on this page. Use the pagination links to return to the statement.' }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light"><tr><th colspan="3" scope="row">Full-period totals and closing balance (all pages)</th><td class="text-end">{{ number_format($periodDebitCents / 100, 2) }}</td><td class="text-end">{{ number_format($periodCreditCents / 100, 2) }}</td><td class="text-end fw-bold">{{ number_format($closingCents / 100, 2) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="card-footer small text-muted">
            {{ $isCustomer ? 'Balance = opening + debits − credits.' : 'Balance = opening + credits − debits.' }}
            Only posted entries linked to this account's documents are included. Reversed documents remain in history; reversals apply on their own journal dates.
            Opening balance is earlier linked journal activity, not an estimated or imported opening debt. Walk-in cash sales are not customer receivables.
            Current contact details label the statement. Later backdated postings can change historical reports. This report does not record a payment.
        </div>
    </div>
    <div class="mt-3">{{ $rows->links() }}</div>
    <details class="mt-4">
        <summary>Control-account coverage through {{ $dateTo }} — all {{ $isCustomer ? 'customers' : 'suppliers' }}</summary>
        <p class="mt-2">Account {{ $accountCode }}: KES {{ number_format($controlBalanceCents / 100, 2) }}. Attributed entries: KES {{ number_format($attributedBalanceCents / 100, 2) }}. Unassigned entries: KES {{ number_format($unassignedBalanceCents / 100, 2) }}.</p>
        <p class="small text-muted">This check covers posted control-account entries; it does not prove every business document was posted. Unlinked manual or legacy entries need review and are never guessed onto an individual account.</p>
        <a href="{{ route('accounting.trial-balance', ['as_of' => $dateTo]) }}">Trial Balance for this date</a>
    </details>
</div>
@endsection
