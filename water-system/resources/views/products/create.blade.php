@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Add New Product</h2>

    <form action="{{ route('products.store') }}" method="POST">

        @csrf

        <div class="mb-3">
            <label>Product Name</label>
            <input type="text"
                   name="name"
                   class="form-control"
                   required>
        </div>

        <div class="mb-3">
            <label>Size</label>
            <input type="text"
                   name="size"
                   class="form-control"
                   required>
        </div>

        <div class="mb-3">
            <label>Price (KSh)</label>
            <input type="number"
                   name="price"
                   class="form-control"
                   required>
        </div>

        <button type="submit" class="btn btn-primary">
            Save Product
        </button>

    </form>

</div>

@endsection