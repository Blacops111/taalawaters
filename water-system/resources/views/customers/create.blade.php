@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">Add Customer</h2>
        <p class="text-muted mb-0">Create a customer record for future V2 sales.</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('customers.store') }}">
                @include('customers._form')
            </form>
        </div>
    </div>
</div>
@endsection
