@extends('layouts.app')
@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3"><h2>Production Costs</h2><a class="btn btn-primary" href="{{ route('production.runs.create') }}">Record production</a></div>
    <p>Recorded operating expenses allocated to finished stock when a batch is completed. The original payment remains unchanged. Batch reversal restores these costs to their original expense accounts.</p>
    <form method="GET" class="d-flex gap-2 mb-3"><input class="form-control" name="search" maxlength="150" value="{{ $search }}" aria-label="Search batch or expense reference" placeholder="Batch or expense reference"><button class="btn btn-outline-primary">Search</button></form>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Production date</th><th>Batch</th><th>Expense</th><th>Category</th><th class="text-end">Allocated (KES)</th><th>Basis</th><th>Status</th></tr></thead>
        <tbody>@forelse($allocations as $allocation)
            <tr>
                <td>{{ $allocation->productionRun->occurred_at->format('Y-m-d H:i') }}</td>
                <td><a href="{{ route('accounting.journals', ['search' => $allocation->productionRun->reference]) }}">{{ $allocation->productionRun->reference }}</a></td>
                <td>{{ $allocation->expense->reference }}<div class="small text-muted">{{ $allocation->expense->description }}</div></td>
                <td>{{ $allocation->expenseAccount->code }} — {{ $allocation->expenseAccount->name }}</td>
                <td class="text-end">{{ number_format($allocation->amount_cents / 100, 2) }}</td>
                <td>{{ $allocation->basis }}</td>
                <td>@if($allocation->productionRun->reversal)<span class="badge bg-secondary">Reversed</span><div class="small">{{ $allocation->productionRun->reversal->reversed_at->format('Y-m-d H:i') }}</div>@else<span class="badge bg-success">Allocated</span>@endif</td>
            </tr>
        @empty<tr><td colspan="7" class="text-muted">No production expense allocations recorded.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $allocations->links() }}
</div>
@endsection
