@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Add Stock</h2>

    <form action="{{ route('stocks.store') }}"
          method="POST">

        @csrf

        <!-- Product Dropdown -->

        <div class="mb-3">
            <label>Select Product</label>

            <select name="product_id"
                    class="form-control"
                    required>

                <option value="">
                    -- Select Product --
                </option>

                @foreach($products as $product)

                <option value="{{ $product->id }}">
                    {{ $product->name }} ({{ $product->size }})
                </option>

                @endforeach

            </select>
        </div>

        <!-- Quantity -->

        <div class="mb-3">
            <label>Quantity Added</label>

            <input type="number"
                   name="quantity_added"
                   class="form-control"
                   placeholder="Example: 100"
                   required>
        </div>

        <!-- Date -->

        <div class="mb-3">
            <label>Date Added</label>

            <input type="date"
                   name="date_added"
                   class="form-control"
                   required>
        </div>

        <!-- Buttons -->

        <button type="submit"
                class="btn btn-primary">
            Save Stock
        </button>

        <a href="{{ route('stocks.index') }}"
           class="btn btn-secondary">
            Cancel
        </a>

    </form>

</div>

@endsection
