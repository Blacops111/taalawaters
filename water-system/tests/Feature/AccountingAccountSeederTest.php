<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_chart_of_accounts_is_seeded_idempotently(): void
    {
        $seeder = app(AccountingAccountSeeder::class);

        $seeder->run();
        $seeder->run();

        $this->assertDatabaseCount('accounting_accounts', 19);

        $assets = AccountingAccount::query()
            ->where('code', '1000')
            ->firstOrFail();

        $cash = AccountingAccount::query()
            ->where('code', '1100')
            ->firstOrFail();

        $salesRevenue = AccountingAccount::query()
            ->where('code', '4100')
            ->firstOrFail();

        $costOfGoodsSold = AccountingAccount::query()
            ->where('code', '5100')
            ->firstOrFail();

        $this->assertSame(AccountingAccount::TYPE_ASSET, $assets->type);
        $this->assertNull($assets->parent_id);
        $this->assertTrue($assets->is_system);
        $this->assertTrue($assets->is_active);

        $this->assertSame($assets->id, $cash->parent_id);
        $this->assertSame(AccountingAccount::TYPE_ASSET, $cash->type);

        $this->assertSame(
            AccountingAccount::TYPE_REVENUE,
            $salesRevenue->type
        );
        $this->assertSame(
            AccountingAccount::TYPE_EXPENSE,
            $costOfGoodsSold->type
        );

        $this->assertSame(
            19,
            AccountingAccount::query()->distinct()->count('code')
        );
    }
}
