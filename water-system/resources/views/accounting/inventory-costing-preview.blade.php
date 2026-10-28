@extends('layouts.app')
@section('content')
<div class="container">
    <h2>Confirm opening inventory valuation</h2>
    <p>New inventory value: <strong>KES {{ number_format($total / 100, 2) }}</strong>. Previous account 1300 balance: KES {{ number_format($data['ledger_cents'] / 100, 2) }}.</p>
    @if($difference !== 0)
        <div class="alert alert-warning">This will post <strong>Debit {{ $difference > 0 ? '1300 Inventory' : $data['offset_code'] }} / Credit {{ $difference > 0 ? $data['offset_code'] : '1300 Inventory' }}</strong> for <strong>KES {{ number_format(abs($difference) / 100, 2) }}</strong> today. Review the account and value before confirming.</div>
    @else<div class="alert alert-success">Opening values already match Inventory. No reconciliation journal is needed.</div>@endif
    <table class="table"><thead><tr><th>Item</th><th>Quantity</th><th>Total value (KES)</th></tr></thead><tbody>
        @foreach($items as $item)<tr><td>{{ $item->sku }} — {{ $item->name }}</td><td>{{ $data['quantities'][$item->id] ?? 'Missing' }}</td><td>{{ $data['values'][$item->id] ?? 'Missing' }}</td></tr>@endforeach
    </tbody></table>
    <p>Reason: {{ $data['notes'] }}</p>
    <p>Costing starts now. Existing journals remain unchanged. Opening values cannot be edited afterwards. Borehole acquisition cost is zero; electricity and labour remain expensed until explicitly allocated to production.</p>
    <form method="POST" action="{{ route('accounting.inventory-costing.activate') }}">
        @csrf
        @foreach($data as $key => $value)
            @if(is_array($value))
                @foreach($value as $id => $amount)<input type="hidden" name="{{ $key }}[{{ $id }}]" value="{{ $amount }}">@endforeach
            @else<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
        @endforeach
        <button class="btn btn-primary">Approve values and activate costing</button>
        <a class="btn btn-outline-secondary" href="{{ route('accounting.inventory-costing') }}">Back to setup</a>
    </form>
</div>
@endsection
