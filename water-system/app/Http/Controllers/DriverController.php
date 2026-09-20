<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function index()
    {
        $drivers = Driver::query()
            ->orderBy('name')
            ->paginate(25);

        return view('drivers.index', compact('drivers'));
    }

    public function create()
    {
        return view('drivers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $driver = Driver::create([
            'name' => trim($validated['name']),
            'phone' => $this->nullableTrim($validated['phone'] ?? null),
            'license_number' => $this->nullableTrim($validated['license_number'] ?? null),
            'license_expiry' => $validated['license_expiry'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'notes' => $this->nullableTrim($validated['notes'] ?? null),
        ]);

        return redirect()
            ->route('drivers.index')
            ->with('success', $driver->name.' was added successfully.');
    }

    public function edit(Driver $driver)
    {
        return view('drivers.edit', compact('driver'));
    }

    public function update(Request $request, Driver $driver)
    {
        $validated = $request->validate($this->rules($driver));

        $driver->update([
            'name' => trim($validated['name']),
            'phone' => $this->nullableTrim($validated['phone'] ?? null),
            'license_number' => $this->nullableTrim($validated['license_number'] ?? null),
            'license_expiry' => $validated['license_expiry'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'notes' => $this->nullableTrim($validated['notes'] ?? null),
        ]);

        return redirect()
            ->route('drivers.index')
            ->with('success', $driver->name.' was updated successfully.');
    }

    private function rules(?Driver $driver = null): array
    {
        $licenseRule = Rule::unique('drivers', 'license_number');

        if ($driver) {
            $licenseRule = $licenseRule->ignore($driver->id);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:100', $licenseRule],
            'license_expiry' => ['nullable', 'date'],
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
