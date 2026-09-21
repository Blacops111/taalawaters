<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_seeded_chart_of_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        app(AccountingAccountSeeder::class)->run();

        $this->actingAs($admin)
            ->get(route('accounting.accounts'))
            ->assertOk()
            ->assertSee('Chart of Accounts')
            ->assertSee('Assets')
            ->assertSee('Cash on Hand')
            ->assertSee('Accounts Receivable')
            ->assertSee('Accounts Payable')
            ->assertSee('Water Sales Revenue')
            ->assertSee('Cost of Goods Sold');
    }

    public function test_admin_navigation_links_to_chart_of_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        app(AccountingAccountSeeder::class)->run();

        $this->actingAs($admin)
            ->get(route('accounting.accounts'))
            ->assertOk()
            ->assertSee('Accounting')
            ->assertSee('Chart of Accounts');
    }

    public function test_non_admin_cannot_view_chart_of_accounts(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get(route('accounting.accounts'))
            ->assertRedirect('/dashboard');
    }
}
