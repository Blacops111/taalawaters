<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\JournalPostingService;
use App\Services\TrialBalanceService;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrialBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingAccountSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_sees_net_account_balances_and_equal_totals(): void
    {
        $this->postEntry('1100', '4100', '100.25');
        $this->postEntry('5500', '1100', '30.10');
        $report = $this->report();
        $rows = $report['rows']->keyBy('code');
        $this->assertSame(7015, $rows['1100']->debit_cents);
        $this->assertSame(0, $rows['1100']->credit_cents);
        $this->assertSame(3010, $rows['5500']->debit_cents);
        $this->assertSame(10025, $rows['4100']->credit_cents);
        $this->assertSame(10025, $report['totalDebitCents']);
        $this->assertSame(10025, $report['totalCreditCents']);
        $this->assertTrue($report['isBalanced']);
        $this->assertSame(0, $report['differenceCents']);
        $this->assertCount(3, $rows);
        $this->actingAs($this->admin)->get(route('accounting.trial-balance', ['as_of' => '2026-09-22']))
            ->assertOk()->assertSee('Trial Balance as of 2026-09-22')
            ->assertSee('70.15')->assertSee('100.25')->assertSee('Balanced');
    }

    public function test_cutoff_is_inclusive_and_uses_entry_date_not_posting_timestamp(): void
    {
        $this->postEntry('1100', '4100', '10.00', '2026-09-20');
        $this->postEntry('1100', '4100', '20.00', '2026-09-22');
        $this->postEntry('1100', '4100', '40.00', '2026-09-23');
        $this->assertSame(3000, $this->report()['totalDebitCents']);
        $this->assertSame(1000, $this->report('2026-09-21')['totalDebitCents']);
        $this->assertSame(7000, $this->report('2026-09-23')['totalDebitCents']);
    }

    public function test_drafts_with_lines_are_excluded(): void
    {
        $entry = JournalEntry::create([
            'entry_date' => '2026-09-22', 'status' => JournalEntry::STATUS_DRAFT,
            'description' => 'Unposted', 'posted_by' => $this->admin->id,
        ]);
        $entry->lines()->create(['accounting_account_id' => $this->account('1100')->id, 'debit' => 90, 'credit' => 0]);
        $entry->lines()->create(['accounting_account_id' => $this->account('4100')->id, 'debit' => 0, 'credit' => 90]);
        $this->postEntry('1100', '4100', '10.00');
        $this->assertSame(1000, $this->report()['totalDebitCents']);
        $this->assertSame(1000, $this->report()['totalCreditCents']);
    }

    public function test_reversal_offsets_original_only_from_reversal_date(): void
    {
        $original = $this->postEntry('1100', '4100', '120.50', '2026-09-21');
        app(JournalPostingService::class)->post($this->admin, '2026-09-23', 'Reverse receipt', [
            ['account_id' => $this->account('4100')->id, 'debit' => '120.50'],
            ['account_id' => $this->account('1100')->id, 'credit' => '120.50'],
        ], null, $original);
        $this->assertSame(12050, $this->report()['totalDebitCents']);
        $after = $this->report('2026-09-23');
        $this->assertCount(2, $after['rows']);
        $this->assertSame(0, $after['totalDebitCents']);
        $this->assertSame(0, $after['totalCreditCents']);
        $this->assertTrue($after['isBalanced']);
    }

    public function test_inactive_and_parent_account_postings_are_not_lost_or_double_counted(): void
    {
        $this->postEntry('1100', '4100', '50.00');
        $this->postEntry('1000', '4100', '25.00');
        $this->account('1100')->update(['is_active' => false]);
        $report = $this->report();
        $rows = $report['rows']->keyBy('code');
        $this->assertSame(5000, $rows['1100']->debit_cents);
        $this->assertSame(2500, $rows['1000']->debit_cents);
        $this->assertSame(7500, $report['totalDebitCents']);
        $this->assertSame(7500, $report['totalCreditCents']);
        $this->actingAs($this->admin)->get(route('accounting.trial-balance', ['as_of' => '2026-09-22']))
            ->assertOk()->assertSee('Inactive');
    }

    public function test_credit_asset_balance_and_payment_settlements_are_reported_on_correct_side(): void
    {
        // Business sale, customer settlement, goods receipt, and supplier settlement.
        $this->postEntry('1200', '4100', '300.00');
        $this->postEntry('1100', '1200', '100.00');
        $this->postEntry('1300', '2100', '400.00');
        $this->postEntry('2100', '1100', '150.00');
        $report = $this->report();
        $rows = $report['rows']->keyBy('code');
        $this->assertSame(0, $rows['1100']->debit_cents);
        $this->assertSame(5000, $rows['1100']->credit_cents);
        $this->assertSame(20000, $rows['1200']->debit_cents);
        $this->assertSame(40000, $rows['1300']->debit_cents);
        $this->assertSame(25000, $rows['2100']->credit_cents);
        $this->assertSame(30000, $rows['4100']->credit_cents);
        $this->assertSame(60000, $report['totalDebitCents']);
        $this->assertSame(60000, $report['totalCreditCents']);
    }

    public function test_empty_report_and_default_date_are_clear(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 22)->startOfDay());
        $this->actingAs($this->admin)->get(route('accounting.trial-balance'))
            ->assertOk()->assertViewHas('asOf', '2026-09-22')
            ->assertSee('No posted journal activity')->assertDontSee('Balanced —');
        $this->assertSame(0, $this->report()['totalDebitCents']);
        $this->assertSame(0, $this->report()['totalCreditCents']);
        $this->travelBack();
    }

    public function test_invalid_dates_are_rejected(): void
    {
        foreach (['not-a-date', '2026-02-30', '22/09/2026'] as $date) {
            $this->actingAs($this->admin)->from(route('accounting.trial-balance'))
                ->get(route('accounting.trial-balance', ['as_of' => $date]))
                ->assertRedirect(route('accounting.trial-balance'))->assertSessionHasErrors('as_of');
        }
    }

    public function test_imbalance_is_visible_instead_of_being_hidden(): void
    {
        $entry = $this->postEntry('1100', '4100', '100.00');
        // Simulate legacy/import corruption that bypassed the posting service.
        DB::table('journal_lines')->where('journal_entry_id', $entry->id)
            ->where('accounting_account_id', $this->account('4100')->id)->update(['credit' => '99.99']);
        $report = $this->report();
        $this->assertFalse($report['isBalanced']);
        $this->assertSame(1, $report['differenceCents']);
        $this->actingAs($this->admin)->get(route('accounting.trial-balance', ['as_of' => '2026-09-22']))
            ->assertOk()->assertSee('Out of balance')->assertSee('KES 0.01');
    }

    public function test_guests_and_non_admins_cannot_access_trial_balance(): void
    {
        $this->get(route('accounting.trial-balance'))->assertRedirect(route('login'));
        foreach (['user', 'staff', 'driver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('accounting.trial-balance'))->assertRedirect('/dashboard');
        }
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::where('code', $code)->firstOrFail();
    }

    private function postEntry(string $debit, string $credit, string $amount, string $date = '2026-09-22'): JournalEntry
    {
        return app(JournalPostingService::class)->post($this->admin, $date, 'Trial balance test', [
            ['account_id' => $this->account($debit)->id, 'debit' => $amount],
            ['account_id' => $this->account($credit)->id, 'credit' => $amount],
        ]);
    }

    private function report(string $asOf = '2026-09-22'): array
    {
        return app(TrialBalanceService::class)->report($asOf);
    }
}
