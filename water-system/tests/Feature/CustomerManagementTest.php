<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_business_customer_list_and_create_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Nairobi Supermarket',
            'customer_type' => 'supermarket',
            'contact_person' => 'Jane Wanjiku',
            'phone' => '0700000000',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Business Customers')
            ->assertSee($customer->name)
            ->assertSee('Jane Wanjiku');

        $this->actingAs($admin)
            ->get(route('customers.create'))
            ->assertOk()
            ->assertSee('Add Business Customer')
            ->assertSee('Supermarket');
    }

    public function test_admin_can_create_business_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('customers.store'), [
                'name' => 'Westlands Supermarket',
                'customer_type' => 'supermarket',
                'contact_person' => 'John Kamau',
                'phone' => '0712345678',
                'email' => 'orders@example.com',
                'address' => 'Westlands, Nairobi',
                'is_active' => 1,
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'name' => 'Westlands Supermarket',
            'customer_type' => 'supermarket',
            'contact_person' => 'John Kamau',
            'phone' => '0712345678',
            'email' => 'orders@example.com',
            'address' => 'Westlands, Nairobi',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_and_deactivate_business_customer_without_deleting_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Original Business',
            'customer_type' => 'shop',
            'phone' => '0700000000',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('customers.update', $customer), [
                'name' => 'Updated Business',
                'customer_type' => 'distributor',
                'contact_person' => 'Mary Njeri',
                'phone' => '0799999999',
                'email' => 'updated@example.com',
                'address' => 'Nairobi',
                'is_active' => 0,
            ])
            ->assertRedirect(route('customers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Business',
            'customer_type' => 'distributor',
            'contact_person' => 'Mary Njeri',
            'phone' => '0799999999',
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_business_customer_name_type_and_email_are_validated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('customers.create'))
            ->post(route('customers.store'), [
                'name' => '',
                'customer_type' => 'walk_in',
                'email' => 'not-an-email',
                'is_active' => 1,
            ])
            ->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors(['name', 'customer_type', 'email']);

        $this->assertDatabaseCount('customers', 0);
    }
}
