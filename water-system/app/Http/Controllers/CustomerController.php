<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::query()
            ->orderBy('name')
            ->paginate(25);

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create', [
            'customerTypes' => $this->customerTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $customer = Customer::create([
            'name' => $validated['name'],
            'customer_type' => $validated['customer_type'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('customers.index')
            ->with('success', $customer->name.' was added successfully.');
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', [
            'customer' => $customer,
            'customerTypes' => $this->customerTypes(),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate($this->rules());

        $customer->update([
            'name' => $validated['name'],
            'customer_type' => $validated['customer_type'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('customers.index')
            ->with('success', $customer->name.' was updated successfully.');
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'customer_type' => ['required', Rule::in(array_keys($this->customerTypes()))],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function customerTypes(): array
    {
        return [
            'supermarket' => 'Supermarket',
            'distributor' => 'Distributor / Wholesaler',
            'hotel' => 'Hotel',
            'restaurant' => 'Restaurant',
            'office' => 'Office / Company',
            'institution' => 'Institution',
            'shop' => 'Shop / Retailer',
            'other' => 'Other Business',
        ];
    }
}
