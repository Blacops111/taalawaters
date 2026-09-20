@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <h2 class="mb-1">Driver & Vehicle Assignments</h2>
            <p class="text-muted mb-0">
                Assign one active driver to one available vehicle and keep the full assignment history.
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('drivers.index') }}" class="btn btn-outline-secondary">Drivers</a>
            <a href="{{ route('vehicles.index') }}" class="btn btn-outline-secondary">Vehicles</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <strong>Create Assignment</strong>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('vehicle-assignments.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="driver_id" class="form-label">Driver</label>
                        <select
                            id="driver_id"
                            name="driver_id"
                            class="form-select @error('driver_id') is-invalid @enderror"
                            required
                        >
                            <option value="">Select active driver</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->id }}" @selected((string) old('driver_id') === (string) $driver->id)>
                                    {{ $driver->name }}
                                    @if($driver->license_number)
                                        — {{ $driver->license_number }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('driver_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-5">
                        <label for="vehicle_id" class="form-label">Vehicle</label>
                        <select
                            id="vehicle_id"
                            name="vehicle_id"
                            class="form-select @error('vehicle_id') is-invalid @enderror"
                            required
                        >
                            <option value="">Select available vehicle</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id') === (string) $vehicle->id)>
                                    {{ $vehicle->registration_number }}
                                    @if($vehicle->name)
                                        — {{ $vehicle->name }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            Assign
                        </button>
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">
                            Notes <span class="text-muted">(optional)</span>
                        </label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="2"
                            maxlength="2000"
                            class="form-control @error('notes') is-invalid @enderror"
                        >{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <strong>Active Assignments</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Driver</th>
                            <th>Vehicle</th>
                            <th>Assigned At</th>
                            <th>Assigned By</th>
                            <th>Notes</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activeAssignments as $assignment)
                            <tr>
                                <td><strong>{{ $assignment->driver->name }}</strong></td>
                                <td>
                                    <strong>{{ $assignment->vehicle->registration_number }}</strong>
                                    <div class="small text-muted">
                                        {{ $assignment->vehicle->vehicle_type === 'motorbike' ? 'Motorbike' : 'Tanker Truck' }}
                                    </div>
                                </td>
                                <td>{{ $assignment->assigned_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $assignment->assigner?->name ?: '—' }}</td>
                                <td>{{ $assignment->notes ?: '—' }}</td>
                                <td class="text-end">
                                    <form
                                        method="POST"
                                        action="{{ route('vehicle-assignments.destroy', $assignment) }}"
                                        class="d-inline"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            End Assignment
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    No active driver/vehicle assignments.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <strong>Assignment History</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Driver</th>
                            <th>Vehicle</th>
                            <th>Assigned At</th>
                            <th>Ended At</th>
                            <th>Assigned By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assignmentHistory as $assignment)
                            <tr>
                                <td>{{ $assignment->driver->name }}</td>
                                <td>{{ $assignment->vehicle->registration_number }}</td>
                                <td>{{ $assignment->assigned_at->format('Y-m-d H:i') }}</td>
                                <td>{{ $assignment->unassigned_at?->format('Y-m-d H:i') ?: '—' }}</td>
                                <td>{{ $assignment->assigner?->name ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No completed assignment history yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($assignmentHistory->hasPages())
            <div class="card-footer bg-white">
                {{ $assignmentHistory->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
