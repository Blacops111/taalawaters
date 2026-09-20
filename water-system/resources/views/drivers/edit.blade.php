@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-4">
        <h2 class="mb-1">Edit Driver</h2>
        <p class="text-muted mb-0">
            Update driver details or deactivate the driver without deleting logistics history.
        </p>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('drivers.update', $driver) }}">
                @include('drivers._form', ['driver' => $driver])
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <strong>Driver Login Account</strong>
        </div>
        <div class="card-body">
            @if($driver->user)
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <div class="text-muted small">Linked Account</div>
                        <div class="fw-semibold">{{ $driver->user->name }}</div>
                        <div>{{ $driver->user->email }}</div>
                        <div class="form-text">
                            This account has driver-only access to assigned deliveries.
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <form
                            method="POST"
                            action="{{ route('drivers.login-account.unlink', $driver) }}"
                            onsubmit="return confirm('Unlink this driver login account?');"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger">
                                Unlink Login Account
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <p class="text-muted">
                    The driver should first create a normal Taala Crystal account using their own email and password.
                    Then link that existing account here. Administrators never need to know the driver's password.
                </p>

                <form method="POST" action="{{ route('drivers.login-account.link', $driver) }}">
                    @csrf

                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label for="login_email" class="form-label">Driver Account Email</label>
                            <input
                                type="email"
                                id="login_email"
                                name="login_email"
                                maxlength="255"
                                value="{{ old('login_email') }}"
                                class="form-control @error('login_email') is-invalid @enderror"
                                placeholder="driver@example.com"
                                required
                            >
                            @error('login_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">
                                Link Driver Login
                            </button>
                        </div>
                    </div>
                </form>
            @endif

            @error('login_account')
                <div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
@endsection
