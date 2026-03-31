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

                <th>#</th>

                <th>Product</th>

                <th>Quantity Sold</th>

                <th>Price</th>

                <th>Total Amount</th>

                <th>Sale Date</th>

            </tr>

        </thead>

        <tbody>

            @foreach($sales as $sale)

            <tr>

                <td>{{ $sale->id }}</td>

                <td>
                    {{ $sale->product->name }}
                </td>

                <td>
                    {{ $sale->quantity_sold }}
                </td>

                <td>
                    {{ $sale->price }}
                </td>

                <td>
                    {{ $sale->total_amount }}
                </td>

                <td>
                    {{ $sale->sale_date }}
                </td>

            </tr>

            @endforeach

        </tbody>

    </table>

</div>

@endsection
