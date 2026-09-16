@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Reverse Production Run</h2>
            <p class="text-muted mb-0">Reverse the original inventory movements without deleting the production history.</p>
        </div>
        <a href="{{ route('production.runs.create') }}" class="btn btn-outline-secondary">
            Back to Production
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Production run was not reversed.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $quantity = (float) $productionRun->quantity_produced;
        $formattedQuantity = abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0)
            : number_format($quantity, 3);
        $originalMovements = $productionRun->stockMovements
            ->whereIn('movement_type', ['production_consumption', 'production_output']);
    @endphp

    <div class="card mb-4">
        <div class="card-header bg-white">
            <strong>{{ $productionRun->reference }}</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Finished Product</div>
                    <div>{{ $productionRun->finishedProduct?->name }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Quantity</div>
                    <div>{{ $formattedQuantity }} {{ $productionRun->finishedProduct?->unit }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Production Date</div>
                    <div>{{ $productionRun->occurred_at?->format('Y-m-d H:i') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <div>{{ ucfirst($productionRun->status) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white">
            <strong>Original Inventory Movements</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th>Movement</th>
                            <th class="text-end">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($originalMovements as $movement)
                            <tr>
                                <td>{{ $movement->inventoryItem?->name }}</td>
                                <td>
                                    {{ $movement->movement_type === 'production_consumption' ? 'Consumed' : 'Produced' }}
                                </td>
                                <td class="text-end">
                                    {{ number_format((float) $movement->quantity_delta, 3) }} {{ $movement->inventoryItem?->unit }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    No original production movements were found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($productionRun->status === 'completed' && !$productionRun->reversal)
        <div class="alert alert-warning">
            Reversing this run will restore the materials consumed by production and remove the finished stock created by this run. The original record will remain in the audit history.
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('production.runs.reverse', $productionRun) }}">
                    @csrf

                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Reversal</label>
                        <textarea
                            id="reason"
                            name="reason"
                            rows="3"
                            maxlength="2000"
                            class="form-control @error('reason') is-invalid @enderror"
                            placeholder="Example: Incorrect quantity entered during testing"
                            required
                        >{{ old('reason') }}</textarea>
                        <div class="form-text">This reason becomes part of the permanent audit trail.</div>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        Confirm Reversal
                    </button>
                    <a href="{{ route('production.runs.create') }}" class="btn btn-outline-secondary ms-2">
                        Cancel
                    </a>
                </form>
            </div>
        </div>
    @else
        <div class="alert alert-secondary mb-0">
            This production run has already been reversed.
            @if($productionRun->reversal)
                Reversal reference: <strong>{{ $productionRun->reversal->reference }}</strong>.
                Reason: {{ $productionRun->reversal->reason }}
            @endif
        </div>
    @endif
</div>
@endsection
