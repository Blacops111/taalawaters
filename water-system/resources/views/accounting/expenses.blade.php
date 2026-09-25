@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <div class="small text-muted">Accounting</div>
        <h2 class="mb-1">Expenses</h2>
        <p class="text-muted mb-0">Record operating expenses paid from Cash on Hand or Bank Account.</p>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3">Record Expense</h5>
            @if($expenseAccounts->isEmpty() || $paymentAccounts->isEmpty())
                <div class="alert alert-warning mb-0">An active operating expense account and Cash or Bank account are required to record an expense.</div>
            @else
                <form method="POST" action="{{ route('accounting.expenses.store') }}">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="expense_account_id" class="form-label">Expense Category</label>
                            <select id="expense_account_id" name="expense_account_id" class="form-select @error('expense_account_id') is-invalid @enderror" required>
                                <option value="">Select expense category</option>
                                @foreach($expenseAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('expense_account_id') === (string) $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                            @error('expense_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="payment_account_id" class="form-label">Pay From</label>
                            <select id="payment_account_id" name="payment_account_id" class="form-select @error('payment_account_id') is-invalid @enderror" required>
                                <option value="">Select cash or bank</option>
                                @foreach($paymentAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('payment_account_id') === (string) $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                            @error('payment_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="expense_date" class="form-label">Expense / Payment Date</label>
                            <input type="date" id="expense_date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" class="form-control @error('expense_date') is-invalid @enderror" required>
                            @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount (KES)</label>
                            <input type="number" id="amount" name="amount" min="0.01" max="9999999999999.99" step="0.01" value="{{ old('amount') }}" class="form-control @error('amount') is-invalid @enderror" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" id="description" name="description" maxlength="300" value="{{ old('description') }}" class="form-control @error('description') is-invalid @enderror" placeholder="What was this expense for?" required>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="payee" class="form-label">Paid To (optional)</label>
                            <input type="text" id="payee" name="payee" maxlength="150" value="{{ old('payee') }}" class="form-control @error('payee') is-invalid @enderror">
                            @error('payee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="external_reference" class="form-label">Receipt / Payment Reference (optional)</label>
                            <input type="text" id="external_reference" name="external_reference" maxlength="150" value="{{ old('external_reference') }}" class="form-control @error('external_reference') is-invalid @enderror">
                            @error('external_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes (optional)</label>
                            <textarea id="notes" name="notes" maxlength="500" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <p class="small text-muted mt-3">Review before posting. Recorded expenses cannot be edited or deleted; expense reversals are not available yet.</p>
                    <button type="submit" class="btn btn-primary">Record & Post Expense</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h5 class="mb-0">Expense History</h5></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Reference / Date</th><th>Expense</th><th>Paid From</th><th>Payee / Receipt</th><th>Recorded By</th><th class="text-end">Amount (KES)</th></tr></thead>
                <tbody>
                    @forelse($expenses as $expense)
                        <tr>
                            <td><a href="{{ route('accounting.journals', ['search' => $expense->reference]) }}">{{ $expense->reference }}</a><div class="small text-muted">{{ $expense->expense_date->format('Y-m-d') }}</div></td>
                            <td>{{ $expense->expenseAccount?->code }} — {{ $expense->expenseAccount?->name }}<div>{{ $expense->description }}</div>@if($expense->notes)<div class="small text-muted">{{ $expense->notes }}</div>@endif</td>
                            <td>{{ $expense->paymentAccount?->code }} — {{ $expense->paymentAccount?->name }}</td>
                            <td>{{ $expense->payee ?: '—' }}<div class="small text-muted">{{ $expense->external_reference ?: '—' }}</div></td>
                            <td>{{ $expense->creator?->name }}</td>
                            <td class="text-end">{{ number_format((float) $expense->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No expenses recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())<div class="card-footer bg-white">{{ $expenses->links() }}</div>@endif
    </div>
</div>
@endsection
