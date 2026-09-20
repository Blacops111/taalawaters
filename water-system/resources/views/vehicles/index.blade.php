@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">Vehicles</h2>
            <p class="text-muted mb-0">
                Manage Taala Crystal motorbikes and tanker trucks for delivery operations.
            </p>
        </div>

        <a href="{{ route('vehicles.create') }}" class="btn btn-primary">
            Add Vehicle
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
                            <th>Registration</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Active</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vehicles as $vehicle)
                            <tr>
                                <td><strong>{{ $vehicle->registration_number }}</strong></td>
                                <td>{{ $vehicle->name ?: '—' }}</td>
                                <td>
                                    {{ $vehicle->vehicle_type === 'motorbike' ? 'Motorbike' : 'Tanker Truck' }}
                                </td>
                                <td>
                                    @if($vehicle->capacity_quantity)
                                        {{ number_format((float) $vehicle->capacity_quantity, 3) }}
                                        {{ $vehicle->capacity_unit }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @switch($vehicle->status)
                                        @case('available')
                                            <span class="badge bg-success">Available</span>
                                            @break
                                        @case('maintenance')
                                            <span class="badge bg-warning text-dark">Maintenance</span>
                                            @break
                                        @case('assigned')
                                            <span class="badge bg-info text-dark">Assigned</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">Inactive</span>
                                    @endswitch
                                </td>
                                <td>
                                    @if($vehicle->is_active)
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a
                                        href="{{ route('vehicles.edit', $vehicle) }}"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    No vehicles registered yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $vehicles->links() }}
    </div>
</div>
@endsection
