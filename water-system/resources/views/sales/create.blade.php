@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Record Sale</h2>

    {{-- Error Message --}}
    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST"
          action="{{ route('sales.store') }}">

        @csrf

        {{-- Product --}}
        <div class="mb-3">

            <label class="form-label">
                Select Product
            </label>

            <select name="product_id"
                    class="form-control"
                    required>

                <option value="">
                    -- Choose Product --
                </option>

                @foreach($products as $product)

                    <option value="{{ $product->id }}">

                        {{ $product->name }}

                    </option>

                @endforeach

            </select>

        </div>


        {{-- Quantity --}}
        <div class="mb-3">

            <label class="form-label">
                Quantity Sold
            </label>

            <input type="number"
                   name="quantity_sold"
                   class="form-control"
                   required>

        </div>


        {{-- Price --}}
        <div class="mb-3">

            <label class="form-label">
                Price per Unit
            </label>

            <input type="number"
                   step="0.01"
                   name="price"
                   class="form-control"
                   required>

        </div>


        {{-- Date --}}
        <div class="mb-3">

            <label class="form-label">
                Sale Date
            </label>

            <input type="date"
                   name="sale_date"
                   class="form-control"
                   required>

        </div>


        <button type="submit"
                class="btn btn-success">

            Save Sale

        </button>

    </form>

</div>

@endsection
