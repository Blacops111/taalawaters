@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">Add Vehicle</h2>
        <p class="text-muted mb-0">
            Register a motorbike or tanker truck for future delivery assignments.
        </p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('vehicles.store') }}">
                @include('vehicles._form')
            </form>
        </div>
    </div>
</div>
@endsection
