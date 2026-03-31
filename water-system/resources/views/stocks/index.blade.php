@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Stock List</h2>

    <a href="{{ route('stocks.create') }}"
       class="btn btn-success mb-3">
        Add New Stock
    </a>

    <table class="table table-bordered">

        <thead>
            <tr>
                <th>ID</th>
                <th>Product</th>
                <th>Quantity Added</th>
                <th>Quantity Remaining</th>
                <th>Date Added</th>
            </tr>
        </thead>

        <tbody>

            @forelse($stocks as $stock)

            <tr>
                <td>{{ $stock->id }}</td>

                <td>
                    {{ $stock->product->name }}
                    ({{ $stock->product->size }})
                </td>

                <td>
                    {{ $stock->quantity_added }}
                </td>

                <td>
                    {{ $stock->quantity_remaining }}
                </td>

                <td>
                    {{ $stock->date_added }}
                </td>

            </tr>

            @empty

            <tr>
                <td colspan="5"
                    class="text-center">
                    No stock records found
                </td>
            </tr>

            @endforelse

        </tbody>

    </table>

</div>

@endsection
