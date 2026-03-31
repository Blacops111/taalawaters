@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Edit Product</h2>

    <form action="{{ route('products.update', $product->id) }}"
          method="POST">

        @csrf
        @method('PUT')

        <div class="mb-3">
            <label>Product Name</label>

            <input type="text"
                   name="name"
                   class="form-control"
                   value="{{ $product->name }}"
                   required>
        </div>

        <div class="mb-3">
            <label>Size</label>

            <input type="text"
                   name="size"
                   class="form-control"
                   value="{{ $product->size }}"
                   required>
        </div>

        <div class="mb-3">
            <label>Price (KSh)</label>

            <input type="number"
                   name="price"
                   class="form-control"
                   value="{{ $product->price }}"
                   required>
        </div>

        <button type="submit"
                class="btn btn-primary">
            Update Product
        </button>

        <a href="{{ route('products.index') }}"
           class="btn btn-secondary">
            Cancel
        </a>

    </form>

</div>

@endsection
