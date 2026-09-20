@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">Edit Vehicle</h2>
        <p class="text-muted mb-0">
            Update vehicle details or deactivate the vehicle without deleting logistics history.
        </p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('vehicles.update', $vehicle) }}">
                @include('vehicles._form', ['vehicle' => $vehicle])
            </form>
        </div>
    </div>
</div>
@endsection
