@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Sales List</h2>

    {{-- Success Message --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('sales.create') }}"
       class="btn btn-primary mb-3">

        Record New Sale

    </a>

    <table class="table table-bordered">

        <thead>
            <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Total</th>
                <th>Date</th>
            </tr>
        </thead>

        <tbody>

        @forelse($sales as $sale)

            <tr>

                <td>{{ $sale->id }}</td>

                <td>
                    {{ $sale->product->name ?? 'No Product' }}
                </td>

                <td>{{ $sale->quantity_sold }}</td>

                <td>{{ $sale->price }}</td>

                <td>{{ $sale->total_amount }}</td>

                <td>{{ $sale->sale_date }}</td>

            </tr>

        @empty

            <tr>
                <td colspan="6"
                    class="text-center">

                    No sales recorded yet

                </td>
            </tr>

        @endforelse

        </tbody>

    </table>

</div>

@endsection
