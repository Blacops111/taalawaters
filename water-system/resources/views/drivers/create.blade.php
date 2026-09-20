@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">Add Driver</h2>
        <p class="text-muted mb-0">
            Create a driver record for future delivery and vehicle assignments.
        </p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('drivers.store') }}">
                @include('drivers._form')
            </form>
        </div>
    </div>
</div>
@endsection
