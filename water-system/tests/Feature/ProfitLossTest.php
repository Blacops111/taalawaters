<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalPostingService;
use App\Services\OperatingExpenseService;
use App\Services\OperatingExpenseReversalService;
use App\Services\ProfitLossService;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfitLossTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingAccountSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_sees_revenue_expenses_and_net_profit_in_cents(): void
    {
        $this->postEntry('1100', '4100', '300.25');
        $this->postEntry('1200', '4200', '50.00');
        $this->postEntry('5200', '1100', '25.10');
        $this->postEntry('5100', '1300', '100.00');
        $report = $this->report();
        $this->assertSame(35025, $report['revenueCents']);
        $this->assertSame(12510, $report['expenseCents']);
        $this->assertSame(22515, $report['netProfitCents']);
        $this->assertCount(2, $report['revenueRows']);
        $this->assertCount(2, $report['expenseRows']);
        $this->actingAs($this->admin)->get($this->url())->assertOk()
            ->assertSee('350.25')->assertSee('125.10')->assertSee('225.15')->assertSee('Net profit')
            ->assertSee('Cost of Goods Sold')->assertSee('View Period Journals');
    }

    public function test_both_boundary_dates_are_included_and_other_periods_are_excluded(): void
    {
        $this->postEntry('1100', '4100', '999.00', '2026-08-31');
        $this->postEntry('1100', '4100', '10.00', '2026-09-01');
        $this->postEntry('1100', '4100', '20.00', '2026-09-25');
        $this->postEntry('1100', '4100', '999.00', '2026-09-26');
        $this->assertSame(3000, $this->report()['revenueCents']);
        $this->assertSame(2000, $this->report('2026-09-25', '2026-09-25')['revenueCents']);
    }

    public function test_drafts_with_lines_are_excluded(): void
    {
        $draft = JournalEntry::create(['entry_date' => '2026-09-25', 'status' => JournalEntry::STATUS_DRAFT,
            'description' => 'Draft sale', 'posted_by' => $this->admin->id]);
        $draft->lines()->create(['accounting_account_id' => $this->account('1100')->id, 'debit' => 900, 'credit' => 0]);
        $draft->lines()->create(['accounting_account_id' => $this->account('4100')->id, 'debit' => 0, 'credit' => 900]);
        $this->postEntry('1100', '4100', '10.00');
        $this->assertSame(1000, $this->report()['netProfitCents']);
    }

    public function test_payments_capital_and_inventory_purchases_do_not_count_as_income_or_expenses(): void
    {
        $this->postEntry('1100', '3100', '1000.00');
        $this->postEntry('1300', '2100', '400.00');
        $this->postEntry('1100', '1200', '100.00');
        $this->postEntry('2100', '1110', '150.00');
        $report = $this->report();
        $this->assertFalse($report['hasActivity']);
        $this->assertSame(0, $report['revenueCents']);
        $this->assertSame(0, $report['expenseCents']);
        $this->assertSame(0, $report['netProfitCents']);
    }

    public function test_expense_recording_and_reversal_affect_their_own_periods(): void
    {
        $expense = app(OperatingExpenseService::class)->record($this->admin, [
            'expense_account_id' => $this->account('5200')->id, 'payment_account_id' => $this->account('1100')->id,
            'expense_date' => '2026-09-25', 'amount' => '125.75', 'description' => 'Test fuel',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        app(OperatingExpenseReversalService::class)->reverse($expense, $this->admin, [
            'reversal_date' => '2026-09-26', 'reason' => 'Incorrect entry',
        ]);
        $this->assertSame(12575, $this->report()['expenseCents']);
        $this->assertSame(-12575, $this->report()['netProfitCents']);
        $reversalOnly = $this->report('2026-09-26', '2026-09-26');
        $this->assertSame(-12575, $reversalOnly['expenseCents']);
        $this->assertSame(12575, $reversalOnly['netProfitCents']);
        $both = $this->report('2026-09-25', '2026-09-26');
        $this->assertSame(0, $both['netProfitCents']);
        $this->assertCount(1, $both['expenseRows']);
    }

    public function test_revenue_reversal_can_show_negative_revenue_in_a_later_period(): void
    {
        $original = $this->postEntry('1100', '4100', '50.25', '2026-08-31');
        app(JournalPostingService::class)->post($this->admin, '2026-09-01', 'Reverse sale', [
            ['account_id' => $this->account('4100')->id, 'debit' => '50.25'],
            ['account_id' => $this->account('1100')->id, 'credit' => '50.25'],
        ], null, $original);
        $this->assertSame(-5025, $this->report()['revenueCents']);
        $this->assertSame(-5025, $this->report()['netProfitCents']);
        $this->actingAs($this->admin)->get($this->url())->assertOk()->assertSee('Net loss')->assertSee('-50.25');
    }

    public function test_inactive_and_parent_accounts_are_included_without_double_counting(): void
    {
        $this->postEntry('1100', '4000', '25.00');
        $this->postEntry('1100', '4100', '50.00');
        $this->postEntry('5000', '1100', '5.00');
        $this->account('4100')->update(['is_active' => false]);
        $report = $this->report();
        $this->assertSame(7500, $report['revenueCents']);
        $this->assertSame(500, $report['expenseCents']);
        $this->assertSame(7000, $report['netProfitCents']);
        $this->assertCount(2, $report['revenueRows']);
        $this->actingAs($this->admin)->get($this->url())->assertOk()->assertSee('Inactive');
    }

    public function test_empty_report_defaults_to_month_to_date_and_end_only_uses_its_month(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 25)->startOfDay());
        $this->actingAs($this->admin)->get(route('accounting.profit-loss'))->assertOk()
            ->assertViewHas('dateFrom', '2026-09-01')->assertViewHas('dateTo', '2026-09-25')
            ->assertSee('No posted revenue or expense activity')->assertSee('Net result');
        $this->get(route('accounting.profit-loss', ['date_to' => '2026-08-20']))->assertOk()
            ->assertViewHas('dateFrom', '2026-08-01')->assertViewHas('dateTo', '2026-08-20');
        $this->assertSame(0, $this->report()['netProfitCents']);
        $this->travelBack();
    }

    public function test_invalid_dates_and_reversed_ranges_are_rejected(): void
    {
        foreach ([
            ['date_from' => '2026-02-30', 'date_to' => '2026-09-25', 'error' => 'date_from'],
            ['date_from' => '2026-09-01', 'date_to' => 'bad-date', 'error' => 'date_to'],
            ['date_from' => '2026-09-26', 'date_to' => '2026-09-25', 'error' => 'date_to'],
        ] as $case) {
            $this->actingAs($this->admin)->from(route('accounting.profit-loss'))
                ->get(route('accounting.profit-loss', ['date_from' => $case['date_from'], 'date_to' => $case['date_to']]))
                ->assertRedirect(route('accounting.profit-loss'))->assertSessionHasErrors($case['error']);
        }
    }

    public function test_guest_and_non_admin_cannot_access_profit_loss(): void
    {
        $this->get(route('accounting.profit-loss'))->assertRedirect(route('login'));
        foreach (['user', 'staff', 'driver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('accounting.profit-loss'))->assertRedirect('/dashboard');
        }
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::where('code', $code)->firstOrFail();
    }

    private function postEntry(string $debit, string $credit, string $amount, string $date = '2026-09-25'): JournalEntry
    {
        return app(JournalPostingService::class)->post($this->admin, $date, 'Profit and loss test', [
            ['account_id' => $this->account($debit)->id, 'debit' => $amount],
            ['account_id' => $this->account($credit)->id, 'credit' => $amount],
        ]);
    }

    private function report(string $from = '2026-09-01', string $to = '2026-09-25'): array
    {
        return app(ProfitLossService::class)->report($from, $to);
    }

    private function url(): string
    {
        return route('accounting.profit-loss', ['date_from' => '2026-09-01', 'date_to' => '2026-09-25']);
    }
}
