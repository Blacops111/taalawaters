<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::query()
            ->orderBy('name')
            ->paginate(25);

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $supplier = Supplier::create([
            'name' => trim($validated['name']),
            'contact_person' => isset($validated['contact_person']) ? trim($validated['contact_person']) : null,
            'phone' => isset($validated['phone']) ? trim($validated['phone']) : null,
            'email' => isset($validated['email']) ? strtolower(trim($validated['email'])) : null,
            'address' => isset($validated['address']) ? trim($validated['address']) : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('suppliers.index')
            ->with('success', $supplier->name.' was added successfully.');
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate($this->rules());

        $supplier->update([
            'name' => trim($validated['name']),
            'contact_person' => isset($validated['contact_person']) ? trim($validated['contact_person']) : null,
            'phone' => isset($validated['phone']) ? trim($validated['phone']) : null,
            'email' => isset($validated['email']) ? strtolower(trim($validated['email'])) : null,
            'address' => isset($validated['address']) ? trim($validated['address']) : null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('suppliers.index')
            ->with('success', $supplier->name.' was updated successfully.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
