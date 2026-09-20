@extends('layouts.app')

@section('content')
@php
    $nameParts = collect(preg_split('/\s+/', trim($user->name)))
        ->filter()
        ->take(2);

    $initials = $nameParts
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $initials = $initials !== '' ? $initials : 'U';
@endphp

<div class="container" style="max-width: 920px;">
    <div class="mb-4">
        <h2 class="mb-1">My Profile</h2>
        <p class="text-muted mb-0">
            Manage your personal information and account security.
        </p>
    </div>

    @if(session('status') === 'profile-updated')
        <div class="alert alert-success">
            Profile updated successfully.
        </div>
    @endif

    @if(session('status') === 'password-updated')
        <div class="alert alert-success">
            Password updated successfully.
        </div>
    @endif

    <div class="card border-0 shadow-sm overflow-hidden mb-4">
        <div
            class="py-4 px-4 text-center"
            style="background: linear-gradient(135deg, #e9f8fa 0%, #f8fcfd 100%);"
        >
            <form
                method="POST"
                action="{{ route('profile.update') }}"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PATCH')

                <div class="position-relative d-inline-block mb-3">
                    @if($user->profile_photo_path)
                        <img
                            id="profilePhotoPreview"
                            src="{{ asset('storage/'.$user->profile_photo_path) }}"
                            alt="{{ $user->name }} profile photo"
                            class="rounded-circle border border-4 border-white shadow-sm"
                            style="width: 132px; height: 132px; object-fit: cover;"
                        >
                    @else
                        <div
                            id="profileInitials"
                            class="rounded-circle border border-4 border-white shadow-sm d-flex align-items-center justify-content-center mx-auto"
                            style="width: 132px; height: 132px; background: #dff4f7; color: #0b4f78; font-size: 2.5rem; font-weight: 700;"
                            aria-label="{{ $user->name }} initials"
                        >
                            {{ $initials }}
                        </div>

                        <img
                            id="profilePhotoPreview"
                            src=""
                            alt="{{ $user->name }} profile photo preview"
                            class="rounded-circle border border-4 border-white shadow-sm d-none"
                            style="width: 132px; height: 132px; object-fit: cover;"
                        >
                    @endif

                    <label
                        for="profile_photo"
                        class="btn btn-primary btn-sm rounded-pill position-absolute"
                        style="right: -12px; bottom: 8px;"
                    >
                        Change Photo
                    </label>

                    <input
                        type="file"
                        id="profile_photo"
                        name="profile_photo"
                        class="d-none"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >
                </div>

                @error('profile_photo')
                    <div class="text-danger small mb-3">{{ $message }}</div>
                @enderror

                <h3 class="h4 mb-1">{{ $user->name }}</h3>
                <div class="text-muted mb-2">
                    {{ $user->username ? '@'.$user->username : 'No username set' }}
                </div>
                <span class="badge rounded-pill text-bg-primary px-3 py-2">
                    {{ ucfirst($user->role) }}
                </span>
        </div>

        <div class="card-body p-4 p-lg-5">
                <div class="mb-4">
                    <h5 class="mb-1">Profile Information</h5>
                    <p class="text-muted small mb-0">
                        Similar to a messaging profile: photo, name, username, contact details, and role in one place.
                    </p>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <label for="name" class="form-label fw-semibold">Full Name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name', $user->name) }}"
                            class="form-control @error('name') is-invalid @enderror"
                            maxlength="255"
                            required
                            autocomplete="name"
                        >
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="username" class="form-label fw-semibold">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                value="{{ old('username', $user->username) }}"
                                class="form-control @error('username') is-invalid @enderror"
                                maxlength="50"
                                placeholder="your.username"
                                autocomplete="username"
                            >
                            @error('username')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text">
                            Lowercase letters, numbers, dots, and underscores only.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', $user->email) }}"
                            class="form-control @error('email') is-invalid @enderror"
                            maxlength="255"
                            required
                            autocomplete="email"
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label fw-semibold">Phone Number</label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            value="{{ old('phone', $user->phone) }}"
                            class="form-control @error('phone') is-invalid @enderror"
                            maxlength="50"
                            placeholder="+254 7..."
                            autocomplete="tel"
                        >
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Role</label>
                        <div class="form-control bg-light">
                            {{ ucfirst($user->role) }}
                        </div>
                        <div class="form-text">
                            Your role is controlled by system administration and cannot be changed from your profile.
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        Save Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4 p-lg-5">
            <div class="mb-4">
                <h5 class="mb-1">Password & Security</h5>
                <p class="text-muted small mb-0">
                    Use a strong password that you do not reuse elsewhere.
                </p>
            </div>

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-12">
                        <label for="update_password_current_password" class="form-label fw-semibold">
                            Current Password
                        </label>
                        <input
                            type="password"
                            id="update_password_current_password"
                            name="current_password"
                            class="form-control {{ $errors->updatePassword->has('current_password') ? 'is-invalid' : '' }}"
                            autocomplete="current-password"
                        >
                        @if($errors->updatePassword->has('current_password'))
                            <div class="invalid-feedback">
                                {{ $errors->updatePassword->first('current_password') }}
                            </div>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label for="update_password_password" class="form-label fw-semibold">
                            New Password
                        </label>
                        <input
                            type="password"
                            id="update_password_password"
                            name="password"
                            class="form-control {{ $errors->updatePassword->has('password') ? 'is-invalid' : '' }}"
                            autocomplete="new-password"
                        >
                        @if($errors->updatePassword->has('password'))
                            <div class="invalid-feedback">
                                {{ $errors->updatePassword->first('password') }}
                            </div>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label for="update_password_password_confirmation" class="form-label fw-semibold">
                            Confirm New Password
                        </label>
                        <input
                            type="password"
                            id="update_password_password_confirmation"
                            name="password_confirmation"
                            class="form-control"
                            autocomplete="new-password"
                        >
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-outline-primary">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-danger-subtle shadow-sm">
        <div class="card-body p-4 p-lg-5">
            <h5 class="text-danger mb-1">Account Removal</h5>
            <p class="text-muted small">
                Deleting your account is permanent. Enter your password to confirm.
            </p>

            @if($errors->userDeletion->isNotEmpty())
                <div class="alert alert-danger">
                    {{ $errors->userDeletion->first('password') }}
                </div>
            @endif

            <button
                type="button"
                class="btn btn-outline-danger"
                data-bs-toggle="modal"
                data-bs-target="#deleteAccountModal"
            >
                Delete Account
            </button>
        </div>
    </div>
</div>

<div
    class="modal fade"
    id="deleteAccountModal"
    tabindex="-1"
    aria-labelledby="deleteAccountModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('profile.destroy') }}">
                @csrf
                @method('DELETE')

                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAccountModalLabel">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p>
                        This action cannot be undone. Enter your current password to continue.
                    </p>

                    <label for="delete_account_password" class="form-label fw-semibold">
                        Current Password
                    </label>
                    <input
                        type="password"
                        id="delete_account_password"
                        name="password"
                        class="form-control"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        Permanently Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const input = document.getElementById('profile_photo');
        const preview = document.getElementById('profilePhotoPreview');
        const initials = document.getElementById('profileInitials');

        if (!input || !preview) {
            return;
        }

        input.addEventListener('change', function () {
            const file = this.files && this.files[0];

            if (!file) {
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {
                preview.src = event.target.result;
                preview.classList.remove('d-none');

                if (initials) {
                    initials.classList.add('d-none');
                }
            };

            reader.readAsDataURL(file);
        });
    });
</script>
@endsection
