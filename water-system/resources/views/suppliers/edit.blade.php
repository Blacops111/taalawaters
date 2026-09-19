@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">Edit Supplier</h2>
        <p class="text-muted mb-0">Update supplier details or deactivate the supplier without deleting future purchasing history.</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
                @include('suppliers._form', ['supplier' => $supplier])
            </form>
        </div>
    </div>
</div>
@endsection
