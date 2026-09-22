@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <div class="text-muted small">Accounting</div>
            <h2 class="mb-1">Customer Payments</h2>
            <p class="text-muted mb-0">
                Settle business sales against Accounts Receivable using Cash on Hand or Bank Account.
            </p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Record Customer Payment</h5>

                    @if($outstandingSales->isEmpty())
                        <div class="alert alert-info mb-0">
                            There are no outstanding business sales available for payment.
                        </div>
                    @else
                        <form method="POST" action="{{ route('accounting.customer-payments.store') }}">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

                            <div class="mb-3">
                                <label for="sales_order_id" class="form-label">Business Sale</label>
                                <select
                                    id="sales_order_id"
                                    name="sales_order_id"
                                    class="form-select @error('sales_order_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">Select an outstanding sale</option>
                                    @foreach($outstandingSales as $sale)
                                        <option
                                            value="{{ $sale->id }}"
                                            @selected((string) old('sales_order_id') === (string) $sale->id)
                                        >
                                            {{ $sale->reference }}
                                            — {{ $sale->customer?->name ?: 'Customer' }}
                                            — Outstanding KES {{ number_format((float) $sale->outstanding_amount, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sales_order_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="payment_account_id" class="form-label">Receive Into</label>
                                <select
                                    id="payment_account_id"
                                    name="payment_account_id"
                                    class="form-select @error('payment_account_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">Select cash or bank</option>
                                    @foreach($paymentAccounts as $account)
                                        <option
                                            value="{{ $account->id }}"
                                            @selected((string) old('payment_account_id') === (string) $account->id)
                                        >
                                            {{ $account->code }} — {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('payment_account_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="payment_date" class="form-label">Payment Date</label>
                                    <input
                                        type="date"
                                        id="payment_date"
                                        name="payment_date"
                                        value="{{ old('payment_date', now()->toDateString()) }}"
                                        class="form-control @error('payment_date') is-invalid @enderror"
                                        required
                                    >
                                    @error('payment_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="amount" class="form-label">Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">KES</span>
                                        <input
                                            type="number"
                                            id="amount"
                                            name="amount"
                                            min="0.01"
                                            step="0.01"
                                            max="9999999999999.99"
                                            value="{{ old('amount') }}"
                                            class="form-control @error('amount') is-invalid @enderror"
                                            required
                                        >
                                        @error('amount')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="external_reference" class="form-label">
                                    Payment Reference
                                </label>
                                <input
                                    type="text"
                                    id="external_reference"
                                    name="external_reference"
                                    maxlength="150"
                                    value="{{ old('external_reference') }}"
                                    class="form-control @error('external_reference') is-invalid @enderror"
                                    placeholder="e.g. bank transfer / cheque / receipt number"
                                >
                                @error('external_reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mt-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="3"
                                    maxlength="500"
                                    class="form-control @error('notes') is-invalid @enderror"
                                >{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary mt-4">
                                Record & Post Payment
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-3">
                    <h5 class="mb-0">Outstanding Business Sales</h5>
                    <p class="small text-muted mt-2 mb-0">Select a sale from this page to record its payment. Use the page links below for more sales.</p>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Sale</th>
                                    <th>Customer</th>
                                    <th class="text-end">Receivable</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($outstandingSales as $sale)
                                    <tr>
                                        <td><strong>{{ $sale->reference }}</strong></td>
                                        <td>{{ $sale->customer?->name ?: '—' }}</td>
                                        <td class="text-end">
                                            KES {{ number_format((float) $sale->receivable_amount, 2) }}
                                        </td>
                                        <td class="text-end">
                                            KES {{ number_format((float) ($sale->paid_amount ?? 0), 2) }}
                                        </td>
                                        <td class="text-end fw-semibold">
                                            KES {{ number_format((float) $sale->outstanding_amount, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No outstanding business sales.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($outstandingSales->hasPages())
                    <div class="card-footer bg-white">{{ $outstandingSales->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 pt-3">
            <h5 class="mb-0">Customer Payment History</h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Sale</th>
                            <th>Received Into</th>
                            <th>External Ref</th>
                            <th>Recorded By</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td><strong>{{ $payment->reference }}</strong></td>
                                <td>{{ $payment->payment_date?->format('Y-m-d') }}</td>
                                <td>{{ $payment->customer?->name ?: '—' }}</td>
                                <td>{{ $payment->salesOrder?->reference ?: '—' }}</td>
                                <td>
                                    {{ $payment->paymentAccount?->code }}
                                    — {{ $payment->paymentAccount?->name }}
                                </td>
                                <td>{{ $payment->external_reference ?: '—' }}</td>
                                <td>{{ $payment->creator?->name ?: '—' }}</td>
                                <td class="text-end fw-semibold">
                                    KES {{ number_format((float) $payment->amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No customer payments recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($payments->hasPages())
            <div class="card-footer bg-white">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
