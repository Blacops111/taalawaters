@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">Add Business Customer</h2>
        <p class="text-muted mb-0">Create an account for a supermarket, hotel, distributor, office or other bulk/repeat customer. Walk-in buyers are recorded directly during a sale and do not need an account.</p>
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
