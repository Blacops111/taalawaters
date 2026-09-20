<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index()
    {
        $vehicles = Vehicle::query()
            ->orderBy('registration_number')
            ->paginate(25);

        return view('vehicles.index', compact('vehicles'));
    }

    public function create()
    {
        return view('vehicles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $isActive = $request->boolean('is_active');

        $vehicle = Vehicle::create([
            'registration_number' => strtoupper(trim($validated['registration_number'])),
            'name' => $this->nullableTrim($validated['name'] ?? null),
            'vehicle_type' => $validated['vehicle_type'],
            'capacity_quantity' => $validated['capacity_quantity'] ?? null,
            'capacity_unit' => $this->nullableTrim($validated['capacity_unit'] ?? null),
            'status' => $isActive
                ? $validated['status']
                : Vehicle::STATUS_INACTIVE,
            'is_active' => $isActive,
            'notes' => $this->nullableTrim($validated['notes'] ?? null),
        ]);

        return redirect()
            ->route('vehicles.index')
            ->with('success', $vehicle->registration_number.' was added successfully.');
    }

    public function edit(Vehicle $vehicle)
    {
        return view('vehicles.edit', compact('vehicle'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate($this->rules($vehicle));

        $isActive = $request->boolean('is_active');

        $vehicle->update([
            'registration_number' => strtoupper(trim($validated['registration_number'])),
            'name' => $this->nullableTrim($validated['name'] ?? null),
            'vehicle_type' => $validated['vehicle_type'],
            'capacity_quantity' => $validated['capacity_quantity'] ?? null,
            'capacity_unit' => $this->nullableTrim($validated['capacity_unit'] ?? null),
            'status' => $isActive
                ? $validated['status']
                : Vehicle::STATUS_INACTIVE,
            'is_active' => $isActive,
            'notes' => $this->nullableTrim($validated['notes'] ?? null),
        ]);

        return redirect()
            ->route('vehicles.index')
            ->with('success', $vehicle->registration_number.' was updated successfully.');
    }

    private function rules(?Vehicle $vehicle = null): array
    {
        $registrationRule = Rule::unique('vehicles', 'registration_number');

        if ($vehicle) {
            $registrationRule = $registrationRule->ignore($vehicle->id);
        }

        return [
            'registration_number' => [
                'required',
                'string',
                'max:50',
                $registrationRule,
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'vehicle_type' => [
                'required',
                Rule::in([
                    Vehicle::TYPE_MOTORBIKE,
                    Vehicle::TYPE_TANKER_TRUCK,
                ]),
            ],
            'capacity_quantity' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:9999999999999',
            ],
            'capacity_unit' => [
                'nullable',
                'string',
                'max:30',
                'required_with:capacity_quantity',
            ],
            'status' => [
                'required',
                Rule::in([
                    Vehicle::STATUS_AVAILABLE,
                    Vehicle::STATUS_MAINTENANCE,
                ]),
            ],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
