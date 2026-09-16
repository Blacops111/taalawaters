<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderDraftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_v2_sales_draft_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Westlands Supermarket',
            'customer_type' => 'supermarket',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('sales-orders.create'))
            ->assertOk()
            ->assertSee('New V2 Sale')
            ->assertSee('Walk-in Sale')
            ->assertSee('Business Customer Sale')
            ->assertSee($customer->name);
    }

    public function test_admin_can_create_walk_in_sale_draft_from_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('sales-orders.store'), [
                'sale_type' => SalesOrder::TYPE_WALK_IN,
                'sale_at' => '2026-09-16 10:30:00',
                'notes' => 'Counter sale.',
            ])
            ->assertRedirect(route('sales-orders.create'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('sales_orders', [
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'customer_id' => null,
            'status' => SalesOrder::STATUS_DRAFT,
            'created_by' => $admin->id,
            'notes' => 'Counter sale.',
        ]);
    }

    public function test_admin_can_create_business_customer_sale_draft_from_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Westlands Supermarket',
            'customer_type' => 'supermarket',
            'contact_person' => 'James Otieno',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('sales-orders.store'), [
                'sale_type' => SalesOrder::TYPE_BUSINESS,
                'customer_id' => $customer->id,
                'sale_at' => '2026-09-16 11:00:00',
            ])
            ->assertRedirect(route('sales-orders.create'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('sales_orders', [
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'customer_id' => $customer->id,
            'status' => SalesOrder::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);
    }

    public function test_business_sale_form_requires_business_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('sales-orders.create'))
            ->post(route('sales-orders.store'), [
                'sale_type' => SalesOrder::TYPE_BUSINESS,
                'sale_at' => '2026-09-16 11:30:00',
            ])
            ->assertRedirect(route('sales-orders.create'))
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseCount('sales_orders', 0);
    }
}
