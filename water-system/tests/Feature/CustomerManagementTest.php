<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_customer_list_and_create_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Nairobi Retail Customer',
            'phone' => '0700000000',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Customers')
            ->assertSee($customer->name);

        $this->actingAs($admin)
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Add Customer');
    }

    public function test_admin_can_create_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('customers.store'), [
                'name' => 'Westlands Office',
                'phone' => '0712345678',
                'email' => 'office@example.com',
                'address' => 'Westlands, Nairobi',
                'is_active' => 1,
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'name' => 'Westlands Office',
            'phone' => '0712345678',
            'email' => 'office@example.com',
            'address' => 'Westlands, Nairobi',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_and_deactivate_customer_without_deleting_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Original Customer',
            'phone' => '0700000000',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('customers.update', $customer), [
                'name' => 'Updated Customer',
                'phone' => '0799999999',
                'email' => 'updated@example.com',
                'address' => 'Nairobi',
                'is_active' => 0,
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Customer',
            'phone' => '0799999999',
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_customer_name_and_email_are_validated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('customers.create'))
            ->post(route('customers.store'), [
                'name' => '',
                'email' => 'not-an-email',
                'is_active' => 1,
            ])
            ->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['name', 'email']);

        $this->assertDatabaseCount('customers', 0);
    }
}
