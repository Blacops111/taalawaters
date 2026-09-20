@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">Edit Driver</h2>
        <p class="text-muted mb-0">
            Update driver details or deactivate the driver without deleting logistics history.
        </p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('drivers.update', $driver) }}">
                @include('drivers._form', ['driver' => $driver])
            </form>
        </div>
    </div>
</div>
@endsection
