@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">Edit Customer</h2>
        <p class="text-muted mb-0">Update customer details or deactivate the customer without deleting sales history.</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('customers.update', $customer) }}">
                @include('customers._form', ['customer' => $customer])
            </form>
        </div>
    </div>
</div>
@endsection
