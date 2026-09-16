<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesHistoryFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_sales_can_be_filtered_by_reference(): void
    {
        [$admin] = $this->seedCompletedSales();

        $this->actingAs($admin)
            ->get(route('sales-orders.history', ['reference' => 'ALPHA']))
            ->assertOk()
            ->assertSee('SALE-BIZ-ALPHA')
            ->assertDontSee('SALE-BIZ-BETA')
            ->assertDontSee('SALE-WALK-OLD')
            ->assertDontSee('SALE-WALK-NEW');
    }

    public function test_completed_sales_can_be_filtered_by_sale_type(): void
    {
        [$admin] = $this->seedCompletedSales();

        $this->actingAs($admin)
            ->get(route('sales-orders.history', ['sale_type' => SalesOrder::TYPE_BUSINESS]))
            ->assertOk()
            ->assertSee('SALE-BIZ-ALPHA')
            ->assertSee('SALE-BIZ-BETA')
            ->assertDontSee('SALE-WALK-OLD')
            ->assertDontSee('SALE-WALK-NEW');
    }

    public function test_completed_sales_can_be_filtered_by_business_customer(): void
    {
        [$admin, $customerAlpha] = $this->seedCompletedSales();

        $this->actingAs($admin)
            ->get(route('sales-orders.history', ['customer_id' => $customerAlpha->id]))
            ->assertOk()
            ->assertSee('SALE-BIZ-ALPHA')
            ->assertDontSee('SALE-BIZ-BETA')
            ->assertDontSee('SALE-WALK-OLD')
            ->assertDontSee('SALE-WALK-NEW');
    }

    public function test_completed_sales_can_be_filtered_by_date_range(): void
    {
        [$admin] = $this->seedCompletedSales();

        $this->actingAs($admin)
            ->get(route('sales-orders.history', [
                'date_from' => '2026-09-14',
                'date_to' => '2026-09-16',
            ]))
            ->assertOk()
            ->assertSee('SALE-BIZ-ALPHA')
            ->assertSee('SALE-WALK-NEW')
            ->assertDontSee('SALE-BIZ-BETA')
            ->assertDontSee('SALE-WALK-OLD');
    }

    private function seedCompletedSales(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $customerAlpha = Customer::create([
            'name' => 'Alpha Supermarket',
            'customer_type' => 'supermarket',
            'is_active' => true,
        ]);

        $customerBeta = Customer::create([
            'name' => 'Beta Distributor',
            'customer_type' => 'distributor_wholesaler',
            'is_active' => true,
        ]);

        SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-WALK-OLD',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-10 09:00:00',
            'total_amount' => 30,
            'created_by' => $admin->id,
        ]);

        SalesOrder::create([
            'customer_id' => null,
            'sale_type' => SalesOrder::TYPE_WALK_IN,
            'reference' => 'SALE-WALK-NEW',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-16 10:00:00',
            'total_amount' => 60,
            'created_by' => $admin->id,
        ]);

        SalesOrder::create([
            'customer_id' => $customerAlpha->id,
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'reference' => 'SALE-BIZ-ALPHA',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-15 11:00:00',
            'total_amount' => 100,
            'created_by' => $admin->id,
        ]);

        SalesOrder::create([
            'customer_id' => $customerBeta->id,
            'sale_type' => SalesOrder::TYPE_BUSINESS,
            'reference' => 'SALE-BIZ-BETA',
            'status' => SalesOrder::STATUS_COMPLETED,
            'sale_at' => '2026-09-12 12:00:00',
            'total_amount' => 200,
            'created_by' => $admin->id,
        ]);

        return [$admin, $customerAlpha, $customerBeta];
    }
}
