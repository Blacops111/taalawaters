<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\SalesOrderDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesOrderTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_in_sale_draft_has_no_business_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = app(SalesOrderDraftService::class)->createDraft(
            SalesOrder::TYPE_WALK_IN,
            null,
            $admin,
        );

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'customer_id' => null,
            'status' => SalesOrder::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);
    }

    public function test_business_sale_draft_requires_and_links_active_business_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Westlands Supermarket',
            'customer_type' => 'supermarket',
            'contact_person' => 'James Otieno',
            'is_active' => true,
        ]);

        $order = app(SalesOrderDraftService::class)->createDraft(
            SalesOrder::TYPE_BUSINESS,
            $customer,
            $admin,
        );

        $this->assertDatabaseHas('sales_orders', [
            'id' => $order->id,
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'customer_id' => $customer->id,
            'status' => SalesOrder::STATUS_DRAFT,
        ]);
        $this->assertTrue($order->fresh()->customer->is($customer));
    }

    public function test_business_sale_rejects_missing_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        try {
            app(SalesOrderDraftService::class)->createDraft(
                SalesOrder::TYPE_BUSINESS,
                null,
                $admin,
            );

            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
        }

        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_business_sale_rejects_inactive_customer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Inactive Supermarket',
            'customer_type' => 'supermarket',
            'is_active' => false,
        ]);

        try {
            app(SalesOrderDraftService::class)->createDraft(
                SalesOrder::TYPE_BUSINESS,
                $customer,
                $admin,
            );

            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
        }

        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_walk_in_sale_rejects_business_customer_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Westlands Supermarket',
            'customer_type' => 'supermarket',
            'is_active' => true,
        ]);

        try {
            app(SalesOrderDraftService::class)->createDraft(
                SalesOrder::TYPE_WALK_IN,
                $customer,
                $admin,
            );

            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
        }

        $this->assertDatabaseCount('sales_orders', 0);
    }
}
