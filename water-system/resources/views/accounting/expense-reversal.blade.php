@extends('layouts.app')

@section('content')
<div class="container" style="max-width: 800px;">
    <h2 class="mb-3">Reverse Expense {{ $expense->reference }}</h2>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <p><strong>{{ $expense->description }}</strong><br>Expense date: {{ $expense->expense_date->format('Y-m-d') }}<br>Amount: KES {{ number_format((float) $expense->amount, 2) }}</p>
            <p>This correction will debit {{ $expense->paymentAccount?->code }} — {{ $expense->paymentAccount?->name }} and credit {{ $expense->expenseAccount?->code }} — {{ $expense->expenseAccount?->name }} for the original amount.</p>
            <p class="text-muted">The original expense stays in history. This records an accounting correction; it does not move money or issue a refund.</p>
            <form method="POST" action="{{ route('accounting.expenses.reverse', $expense) }}">
                @csrf
                <div class="mb-3">
                    <label for="reversal_date" class="form-label">Reversal Date</label>
                    <input type="date" id="reversal_date" name="reversal_date" min="{{ $expense->expense_date->format('Y-m-d') }}" value="{{ old('reversal_date', max(now()->toDateString(), $expense->expense_date->format('Y-m-d'))) }}" class="form-control @error('reversal_date') is-invalid @enderror" required>
                    @error('reversal_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="reason" class="form-label">Reason for Reversal</label>
                    <textarea id="reason" name="reason" rows="3" maxlength="300" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn btn-danger">Confirm Reversal</button>
                <a href="{{ route('accounting.expenses') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
