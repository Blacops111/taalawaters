@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">Add Supplier</h2>
        <p class="text-muted mb-0">Create a supplier record for purchased raw materials and packaging inputs.</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('suppliers.store') }}">
                @include('suppliers._form')
            </form>
        </div>
    </div>
</div>
@endsection
