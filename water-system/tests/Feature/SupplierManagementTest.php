<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_supplier_list_and_create_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Bottle Supplier Ltd',
            'contact_person' => 'Jane Supplier',
            'phone' => '0712345678',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('Suppliers')
            ->assertSee($supplier->name)
            ->assertSee('Jane Supplier');

        $this->actingAs($admin)
            ->get(route('suppliers.create'))
            ->assertOk()
            ->assertSee('Add Supplier')
            ->assertSee('Save Supplier');
    }

    public function test_admin_can_create_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('suppliers.store'), [
                'name' => 'Labels Kenya Ltd',
                'contact_person' => 'John Supplier',
                'phone' => '0700111222',
                'email' => 'orders@labels.test',
                'address' => 'Nairobi, Kenya',
                'is_active' => 1,
            ])
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Labels Kenya Ltd',
            'contact_person' => 'John Supplier',
            'phone' => '0700111222',
            'email' => 'orders@labels.test',
            'address' => 'Nairobi, Kenya',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_and_deactivate_supplier_without_deleting_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Old Supplier',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('suppliers.update', $supplier), [
                'name' => 'Updated Supplier',
                'contact_person' => 'New Contact',
                'is_active' => 0,
            ])
            ->assertRedirect(route('suppliers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier',
            'contact_person' => 'New Contact',
            'is_active' => false,
        ]);

        $this->assertNotNull(Supplier::find($supplier->id));
    }

    public function test_non_admin_is_redirected_away_from_supplier_management(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('suppliers.index'))
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error', 'Access denied.');
    }
}
