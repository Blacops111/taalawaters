@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <div class="text-muted small">Accounting</div>
            <h2 class="mb-1">Supplier Payments</h2>
            <p class="text-muted mb-0">
                Settle goods receipts against Accounts Payable using Cash on Hand or Bank Account.
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
                    <h5 class="mb-3">Record Supplier Payment</h5>

                    @if($outstandingReceipts->isEmpty())
                        <div class="alert alert-info mb-0">
                            There are no outstanding goods receipts available for payment.
                        </div>
                    @else
                        <form method="POST" action="{{ route('accounting.supplier-payments.store') }}">
                            @csrf

                            <div class="mb-3">
                                <label for="purchase_receipt_id" class="form-label">Goods Receipt</label>
                                <select
                                    id="purchase_receipt_id"
                                    name="purchase_receipt_id"
                                    class="form-select @error('purchase_receipt_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">Select an outstanding GRN</option>
                                    @foreach($outstandingReceipts as $receipt)
                                        <option
                                            value="{{ $receipt->id }}"
                                            @selected((string) old('purchase_receipt_id') === (string) $receipt->id)
                                        >
                                            {{ $receipt->reference }}
                                            — {{ $receipt->purchaseRequest?->supplier?->name ?: 'Supplier' }}
                                            — Outstanding KES {{ number_format((float) $receipt->outstanding_amount, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('purchase_receipt_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="payment_account_id" class="form-label">Pay From</label>
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
                                            max="9999999999999"
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
                    <h5 class="mb-0">Outstanding Goods Receipts</h5>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>GRN</th>
                                    <th>Supplier</th>
                                    <th class="text-end">Receipt Value</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Outstanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($outstandingReceipts as $receipt)
                                    <tr>
                                        <td><strong>{{ $receipt->reference }}</strong></td>
                                        <td>{{ $receipt->purchaseRequest?->supplier?->name ?: '—' }}</td>
                                        <td class="text-end">
                                            KES {{ number_format((float) $receipt->receipt_value, 2) }}
                                        </td>
                                        <td class="text-end">
                                            KES {{ number_format((float) ($receipt->paid_amount ?? 0), 2) }}
                                        </td>
                                        <td class="text-end fw-semibold">
                                            KES {{ number_format((float) $receipt->outstanding_amount, 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No outstanding goods receipts.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white border-0 pt-3">
            <h5 class="mb-0">Supplier Payment History</h5>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>GRN</th>
                            <th>Paid From</th>
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
                                <td>{{ $payment->supplier?->name ?: '—' }}</td>
                                <td>{{ $payment->purchaseReceipt?->reference ?: '—' }}</td>
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
                                    No supplier payments recorded yet.
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
