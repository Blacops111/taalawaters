<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\BalanceSheetService;
use App\Services\JournalPostingService;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingAccountSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_assets_equal_liabilities_and_equity_including_earnings(): void
    {
        $this->postEntry('1100', '3100', '1000.00');
        $this->postEntry('1300', '2100', '400.00');
        $this->postEntry('1200', '4100', '300.25');
        $this->postEntry('5500', '1100', '50.10');
        $report = $this->report();
        $this->assertSame(165015, $report['assetCents']);
        $this->assertSame(40000, $report['liabilityCents']);
        $this->assertSame(100000, $report['recordedEquityCents']);
        $this->assertSame(30025, $report['revenueCents']);
        $this->assertSame(5010, $report['expenseCents']);
        $this->assertSame(25015, $report['earningsCents']);
        $this->assertSame(125015, $report['equityCents']);
        $this->assertSame(165015, $report['liabilitiesAndEquityCents']);
        $this->assertSame(0, $report['differenceCents']);
        $this->assertTrue($report['isBalanced']);
        $this->actingAs($this->admin)->get(route('accounting.balance-sheet', ['as_of' => '2026-09-22']))
            ->assertOk()->assertSee('Balance Sheet as of 2026-09-22')->assertSee('1,650.15')
            ->assertSee('250.15')->assertSee('Balanced')->assertSee('Unclosed earnings');
    }

    public function test_customer_and_supplier_settlements_do_not_change_earnings(): void
    {
        $this->postEntry('1200', '4100', '300.00');
        $this->postEntry('1300', '2100', '400.00');
        $before = $this->report();
        $this->postEntry('1100', '1200', '100.00');
        $this->postEntry('2100', '1100', '150.00');
        $after = $this->report();
        $this->assertSame($before['earningsCents'], $after['earningsCents']);
        $this->assertSame(55000, $after['assetCents']);
        $this->assertSame(25000, $after['liabilityCents']);
        $this->assertSame(-5000, $after['sections']['asset']->keyBy('code')['1100']->balance_cents);
        $this->assertTrue($after['isBalanced']);
    }

    public function test_loss_reduces_equity_and_is_not_clamped_to_zero(): void
    {
        $this->postEntry('1100', '3100', '100.00');
        $this->postEntry('5500', '1100', '125.75');
        $report = $this->report();
        $this->assertSame(-12575, $report['earningsCents']);
        $this->assertSame(-2575, $report['equityCents']);
        $this->assertSame(-2575, $report['assetCents']);
        $this->assertTrue($report['isBalanced']);
        $this->actingAs($this->admin)->get(route('accounting.balance-sheet', ['as_of' => '2026-09-22']))
            ->assertOk()->assertSee('-125.75');
    }

    public function test_closing_entries_do_not_double_count_retained_earnings(): void
    {
        $this->postEntry('1100', '4100', '300.00');
        $this->postEntry('5500', '1100', '50.00');
        $before = $this->report();
        $this->postEntry('4100', '3200', '300.00');
        $this->postEntry('3200', '5500', '50.00');
        $after = $this->report();
        $this->assertSame(25000, $before['earningsCents']);
        $this->assertSame(0, $after['earningsCents']);
        $this->assertSame(25000, $after['recordedEquityCents']);
        $this->assertSame($before['equityCents'], $after['equityCents']);
        $this->assertTrue($after['isBalanced']);
    }

    public function test_date_is_inclusive_and_reversal_applies_on_its_own_date(): void
    {
        $original = $this->postEntry('1100', '4100', '100.25', '2026-09-22');
        app(JournalPostingService::class)->post($this->admin, '2026-09-23', 'Reverse sale', [
            ['account_id' => $this->account('4100')->id, 'debit' => '100.25'],
            ['account_id' => $this->account('1100')->id, 'credit' => '100.25'],
        ], null, $original);
        $this->assertFalse($this->report('2026-09-21')['hasActivity']);
        $this->assertSame(10025, $this->report()['earningsCents']);
        $this->assertSame(10025, $this->report()['assetCents']);
        $after = $this->report('2026-09-23');
        $this->assertSame(0, $after['earningsCents']);
        $this->assertSame(0, $after['assetCents']);
        $this->assertTrue($after['isBalanced']);
    }

    public function test_draft_journals_do_not_affect_financial_position(): void
    {
        $entry = JournalEntry::create(['entry_date' => '2026-09-22', 'status' => JournalEntry::STATUS_DRAFT,
            'description' => 'Unposted capital', 'posted_by' => $this->admin->id]);
        $entry->lines()->create(['accounting_account_id' => $this->account('1100')->id, 'debit' => 900, 'credit' => 0]);
        $entry->lines()->create(['accounting_account_id' => $this->account('3100')->id, 'debit' => 0, 'credit' => 900]);
        $this->postEntry('1100', '3100', '10.00');
        $this->assertSame(1000, $this->report()['assetCents']);
        $this->assertSame(1000, $this->report()['equityCents']);
    }

    public function test_inactive_and_parent_accounts_remain_included_without_rollup_duplication(): void
    {
        $this->postEntry('1000', '3100', '25.00');
        $this->postEntry('1100', '3100', '50.00');
        $this->account('1100')->update(['is_active' => false]);
        $report = $this->report();
        $this->assertCount(2, $report['sections']['asset']);
        $this->assertSame(7500, $report['assetCents']);
        $this->assertSame(7500, $report['equityCents']);
        $this->actingAs($this->admin)->get(route('accounting.balance-sheet', ['as_of' => '2026-09-22']))
            ->assertOk()->assertSee('Inactive');
    }

    public function test_imbalance_is_reported(): void
    {
        $entry = $this->postEntry('1100', '3100', '100.00');
        DB::table('journal_lines')->where('journal_entry_id', $entry->id)
            ->where('accounting_account_id', $this->account('3100')->id)->update(['credit' => '99.99']);
        $this->assertFalse($this->report()['isBalanced']);
        $this->assertSame(1, $this->report()['differenceCents']);
        $this->actingAs($this->admin)->get(route('accounting.balance-sheet', ['as_of' => '2026-09-22']))
            ->assertOk()->assertSee('Out of balance')->assertSee('KES 0.01');
    }

    public function test_empty_report_defaults_to_today_and_invalid_dates_are_rejected(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 22)->startOfDay());
        $this->actingAs($this->admin)->get(route('accounting.balance-sheet'))->assertOk()
            ->assertViewHas('asOf', '2026-09-22')->assertSee('No posted journal activity');
        $this->assertSame(0, $this->report()['assetCents']);
        $this->assertSame(0, $this->report()['equityCents']);
        foreach (['2026-02-30', 'not-a-date', '22/09/2026'] as $date) {
            $this->from(route('accounting.balance-sheet'))->get(route('accounting.balance-sheet', ['as_of' => $date]))
                ->assertRedirect(route('accounting.balance-sheet'))->assertSessionHasErrors('as_of');
        }
        $this->travelBack();
    }

    public function test_guests_and_non_admins_cannot_access_report(): void
    {
        $this->get(route('accounting.balance-sheet'))->assertRedirect(route('login'));
        foreach (['user', 'staff', 'driver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('accounting.balance-sheet'))->assertRedirect('/dashboard');
        }
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::where('code', $code)->firstOrFail();
    }

    private function postEntry(string $debit, string $credit, string $amount, string $date = '2026-09-22'): JournalEntry
    {
        return app(JournalPostingService::class)->post($this->admin, $date, 'Balance sheet test', [
            ['account_id' => $this->account($debit)->id, 'debit' => $amount],
            ['account_id' => $this->account($credit)->id, 'credit' => $amount],
        ]);
    }

    private function report(string $asOf = '2026-09-22'): array
    {
        return app(BalanceSheetService::class)->report($asOf);
    }
}
