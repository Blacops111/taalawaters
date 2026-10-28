@php
    $row = is_array($row) ? $row : [];
    foreach (['expense_id', 'amount', 'basis'] as $field) {
        if (!is_scalar($row[$field] ?? '')) $row[$field] = '';
    }
@endphp
<div class="row g-2 align-items-end mb-3 allocation-row">
    <div class="col-md-5">
        <label class="form-label" for="allocation-expense-{{ $index }}">Recorded production expense</label>
        <select class="form-select" id="allocation-expense-{{ $index }}" name="allocations[{{ $index }}][expense_id]">
            <option value="">No allocation</option>
            @foreach($allocationExpenses as $expense)
                <option value="{{ $expense->id }}" @selected((string) ($row['expense_id'] ?? '') === (string) $expense->id)>{{ $expense->reference }} — {{ $expense->description }} — KES {{ number_format((\App\Services\InventoryCostingService::scaled($expense->amount, 2) - (int) $expense->allocated_cents) / 100, 2) }} available</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label" for="allocation-amount-{{ $index }}">Allocate (KES)</label>
        <input class="form-control" type="number" min="0.01" max="999999999999.99" step="0.01" id="allocation-amount-{{ $index }}" name="allocations[{{ $index }}][amount]" value="{{ $row['amount'] ?? '' }}">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="allocation-basis-{{ $index }}">Allocation basis</label>
        <input class="form-control" maxlength="300" id="allocation-basis-{{ $index }}" name="allocations[{{ $index }}][basis]" value="{{ $row['basis'] ?? '' }}" placeholder="e.g. 2 production hours × KES 50">
    </div>
    <div class="col-md-1"><button type="button" class="btn btn-outline-secondary remove-allocation" aria-label="Remove expense allocation">Remove</button></div>
</div>
