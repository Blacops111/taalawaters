@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <div class="text-muted small">Purchase Request</div>
            <h2 class="mb-1">{{ $purchaseRequest->reference }}</h2>
            <p class="text-muted mb-0">
                Add the materials and quantities required for this request.
            </p>
        </div>

        <a href="{{ route('purchase-requests.index') }}" class="btn btn-outline-secondary">
            Back to Purchase Requests
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->has('purchase_request'))
        <div class="alert alert-danger">{{ $errors->first('purchase_request') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-3">Request Details</h5>

                    <dl class="row mb-0">
                        <dt class="col-5">Status</dt>
                        <dd class="col-7">
                            <span class="badge bg-secondary">{{ ucfirst($purchaseRequest->status) }}</span>
                        </dd>

                        <dt class="col-5">Requested By</dt>
                        <dd class="col-7">{{ $purchaseRequest->requester?->name ?: '—' }}</dd>

                        <dt class="col-5">Requested At</dt>
                        <dd class="col-7">{{ optional($purchaseRequest->requested_at)->format('Y-m-d H:i') }}</dd>

                        <dt class="col-5">Supplier</dt>
                        <dd class="col-7">{{ $purchaseRequest->supplier?->name ?: 'Not selected yet' }}</dd>

                        @if($purchaseRequest->submitted_at)
                            <dt class="col-5">Submitted By</dt>
                            <dd class="col-7">{{ $purchaseRequest->submitter?->name ?: '—' }}</dd>

                            <dt class="col-5">Submitted At</dt>
                            <dd class="col-7">{{ $purchaseRequest->submitted_at->format('Y-m-d H:i') }}</dd>
                        @endif

                        @if($purchaseRequest->approved_at)
                            <dt class="col-5">Approved By</dt>
                            <dd class="col-7">{{ $purchaseRequest->approver?->name ?: '—' }}</dd>

                            <dt class="col-5">Approved At</dt>
                            <dd class="col-7">{{ $purchaseRequest->approved_at->format('Y-m-d H:i') }}</dd>
                        @endif

                        @if($purchaseRequest->rejected_at)
                            <dt class="col-5">Rejected By</dt>
                            <dd class="col-7">{{ $purchaseRequest->rejector?->name ?: '—' }}</dd>

                            <dt class="col-5">Rejected At</dt>
                            <dd class="col-7">{{ $purchaseRequest->rejected_at->format('Y-m-d H:i') }}</dd>

                            <dt class="col-5">Reason</dt>
                            <dd class="col-7">{{ $purchaseRequest->rejection_reason ?: '—' }}</dd>
                        @endif

                        <dt class="col-5">Notes</dt>
                        <dd class="col-7">{{ $purchaseRequest->notes ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            @if($purchaseRequest->status === 'draft')
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="mb-3">Add Requested Material</h5>

                        <form method="POST" action="{{ route('purchase-requests.items.store', $purchaseRequest) }}">
                            @csrf

                            <div class="row g-3 align-items-end">
                                <div class="col-md-7">
                                    <label for="inventory_item_id" class="form-label">Material</label>
                                    <select
                                        id="inventory_item_id"
                                        name="inventory_item_id"
                                        class="form-select @error('inventory_item_id') is-invalid @enderror"
                                        required
                                    >
                                        <option value="">Select a material</option>
                                        @foreach($purchasableItems as $item)
                                            <option
                                                value="{{ $item->id }}"
                                                @selected((string) old('inventory_item_id') === (string) $item->id)
                                            >
                                                {{ $item->sku }} — {{ $item->name }} ({{ $item->unit }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('inventory_item_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3">
                                    <label for="quantity" class="form-label">Quantity</label>
                                    <input
                                        type="number"
                                        id="quantity"
                                        name="quantity"
                                        min="0.001"
                                        step="0.001"
                                        max="9999999999999"
                                        value="{{ old('quantity') }}"
                                        class="form-control @error('quantity') is-invalid @enderror"
                                        required
                                    >
                                    @error('quantity')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-2 d-grid">
                                    <button type="submit" class="btn btn-primary">Add</button>
                                </div>
                            </div>
                        </form>

                        <div class="form-text mt-3">
                            Raw borehole water and finished products are excluded because they are not purchased production inputs.
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-info">
                    Items are read-only because this purchase request is no longer a draft.
                </div>
            @endif

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-3">
                    <h5 class="mb-0">Requested Items</h5>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>SKU</th>
                                    <th>Material</th>
                                    <th>Unit</th>
                                    <th class="text-end">Quantity</th>
                                    @if($purchaseRequest->status === 'draft')
                                        <th class="text-end">Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($purchaseRequest->items as $requestItem)
                                    <tr>
                                        <td>{{ $requestItem->inventoryItem->sku }}</td>
                                        <td>{{ $requestItem->inventoryItem->name }}</td>
                                        <td>{{ $requestItem->inventoryItem->unit }}</td>
                                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $requestItem->quantity, 3, '.', ''), '0'), '.') }}</td>

                                        @if($purchaseRequest->status === 'draft')
                                            <td class="text-end">
                                                <form
                                                    method="POST"
                                                    action="{{ route('purchase-requests.items.destroy', [$purchaseRequest, $requestItem]) }}"
                                                    class="d-inline"
                                                    onsubmit="return confirm('Remove this material from the purchase request?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        Remove
                                                    </button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td
                                            colspan="{{ $purchaseRequest->status === 'draft' ? 5 : 4 }}"
                                            class="text-center text-muted py-5"
                                        >
                                            No materials have been added yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($purchaseRequest->status === 'draft')
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                            <h5 class="mb-1">Ready for Approval?</h5>
                            <p class="text-muted mb-0">
                                Supplier can still be left blank. Submission locks the requested items for review.
                            </p>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('purchase-requests.submit', $purchaseRequest) }}"
                            onsubmit="return confirm('Submit this purchase request for approval? You will no longer be able to change its items.');"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-success"
                                @disabled($purchaseRequest->items->isEmpty())
                            >
                                Submit for Approval
                            </button>
                        </form>
                    </div>
                </div>

                @if($purchaseRequest->items->isEmpty())
                    <div class="form-text mt-2">
                        Add at least one material before submitting.
                    </div>
                @endif
            @endif

            @if($purchaseRequest->status === 'submitted')
                <div class="row g-4 mt-1">
                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="mb-2">Approve Purchase Request</h5>
                                <p class="text-muted">
                                    Select the supplier that will fulfill this approved request.
                                </p>

                                <form
                                    method="POST"
                                    action="{{ route('purchase-requests.approve', $purchaseRequest) }}"
                                    onsubmit="return confirm('Approve this purchase request? Inventory will not change until goods are received.');"
                                >
                                    @csrf

                                    <div class="mb-3">
                                        <label for="supplier_id" class="form-label">Supplier</label>
                                        <select
                                            id="supplier_id"
                                            name="supplier_id"
                                            class="form-select @error('supplier_id') is-invalid @enderror"
                                            required
                                        >
                                            <option value="">Select supplier</option>
                                            @foreach($reviewSuppliers as $supplier)
                                                <option
                                                    value="{{ $supplier->id }}"
                                                    @selected((string) old('supplier_id', $purchaseRequest->supplier_id) === (string) $supplier->id)
                                                >
                                                    {{ $supplier->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('supplier_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <button type="submit" class="btn btn-success">
                                        Approve Purchase Request
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="mb-2">Reject Purchase Request</h5>
                                <p class="text-muted">
                                    A rejection reason is required for the audit trail.
                                </p>

                                <form
                                    method="POST"
                                    action="{{ route('purchase-requests.reject', $purchaseRequest) }}"
                                    onsubmit="return confirm('Reject this purchase request?');"
                                >
                                    @csrf

                                    <div class="mb-3">
                                        <label for="rejection_reason" class="form-label">Reason</label>
                                        <textarea
                                            id="rejection_reason"
                                            name="rejection_reason"
                                            rows="4"
                                            maxlength="2000"
                                            class="form-control @error('rejection_reason') is-invalid @enderror"
                                            required
                                        >{{ old('rejection_reason') }}</textarea>
                                        @error('rejection_reason')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <button type="submit" class="btn btn-outline-danger">
                                        Reject Purchase Request
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="alert alert-info mt-4 mb-0">
                Creating, editing, submitting, approving, or rejecting a purchase request does <strong>not</strong> change inventory stock.
                Stock will change only when received goods are recorded.
            </div>
        </div>
    </div>
</div>
@endsection
