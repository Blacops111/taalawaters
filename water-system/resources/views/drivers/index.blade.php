@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">Drivers</h2>
            <p class="text-muted mb-0">
                Manage drivers available for Taala Crystal delivery and logistics assignments.
            </p>
        </div>

        <a href="{{ route('drivers.create') }}" class="btn btn-primary">
            Add Driver
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Driver</th>
                            <th>Phone</th>
                            <th>Licence No.</th>
                            <th>Licence Expiry</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($drivers as $driver)
                            <tr>
                                <td><strong>{{ $driver->name }}</strong></td>
                                <td>{{ $driver->phone ?: '—' }}</td>
                                <td>{{ $driver->license_number ?: '—' }}</td>
                                <td>
                                    @if($driver->license_expiry)
                                        {{ $driver->license_expiry->format('Y-m-d') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($driver->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $driver->notes ?: '—' }}</td>
                                <td class="text-end">
                                    <a
                                        href="{{ route('drivers.edit', $driver) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    No drivers registered yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $drivers->links() }}
    </div>
</div>
@endsection
