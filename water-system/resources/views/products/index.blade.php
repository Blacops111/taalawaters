@extends('layouts.app')

@section('content')

<div class="container">

    <h2>Products List</h2>

    <a href="{{ route('products.create') }}"
       class="btn btn-success mb-3">
        Add New Product
    </a>

    <table class="table table-bordered">

        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Size</th>
                <th>Price (KSh)</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>

            @forelse($products as $product)

            <tr>
                <td>{{ $product->id }}</td>
                <td>{{ $product->name }}</td>
                <td>{{ $product->size }}</td>
                <td>{{ $product->price }}</td>

                <td>

                    <a href="{{ route('products.edit', $product->id) }}"
                       class="btn btn-warning btn-sm">
                        Edit
                    </a>

                    <form action="{{ route('products.destroy', $product->id) }}"
                          method="POST"
                          style="display:inline;">

                        @csrf
                        @method('DELETE')

                        <button type="submit"
                                class="btn btn-danger btn-sm"
                                onclick="return confirm('Are you sure you want to delete this product?')">
                            Delete
                        </button>

                    </form>

                </td>
            </tr>

            @empty

            <tr>
                <td colspan="5" class="text-center">
                    No products found
                </td>
            </tr>

            @endforelse

        </tbody>

    </table>

</div>

@endsection
