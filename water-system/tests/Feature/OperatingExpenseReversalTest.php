<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\OperatingExpense;
use App\Models\OperatingExpenseReversal;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\BalanceSheetService;
use App\Services\JournalPostingService;
use App\Services\OperatingExpenseService;
use App\Services\TrialBalanceService;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperatingExpenseReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingAccountSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_reverse_exact_cash_journal_and_preserve_original_history(): void
    {
        $expense = $this->expense();
        // Compare persisted snapshots: SQLite normalizes numeric types and column order on reload.
        $before = $expense->fresh()->getAttributes();
        $original = $expense->journalEntries()->with('lines')->sole();
        $journalBefore = $original->toArray();
        $this->actingAs($this->admin)->get(route('accounting.expenses.reversal', $expense))
            ->assertOk()->assertSee('Confirm Reversal')->assertSee($expense->reference)->assertSee('125.75');
        $this->post(route('accounting.expenses.reverse', $expense), $this->payload())
            ->assertRedirect(route('accounting.expenses'))->assertSessionHasNoErrors();
        $reversal = OperatingExpenseReversal::sole();
        $entry = $reversal->journalEntries()->sole();
        $this->assertSame('REV-'.$expense->reference, $reversal->reference);
        $this->assertSame($original->id, $reversal->original_journal_id);
        $this->assertSame($this->admin->id, $reversal->reversed_by);
        $this->assertNotNull($reversal->reversed_at);
        $this->assertSame('Recorded in error', $reversal->reason);
        $this->assertSame('2026-09-26', $entry->entry_date->toDateString());
        $this->assertSame($original->id, $entry->reversal_of_id);
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertCount(2, $entry->lines);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $entry->id,
            'accounting_account_id' => $expense->payment_account_id, 'debit' => 125.75, 'credit' => 0]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $entry->id,
            'accounting_account_id' => $expense->expense_account_id, 'debit' => 0, 'credit' => 125.75]);
        $this->assertSame($before, $expense->fresh()->getAttributes());
        $this->assertSame($journalBefore, $original->fresh('lines')->toArray());
        $this->assertSame(0, StockMovement::count());
        $this->get(route('accounting.expenses'))->assertOk()->assertSee('Reversed')
            ->assertSee($reversal->reference)->assertSee('Recorded in error');
        $this->get(route('accounting.journals', ['search' => $reversal->reference]))
            ->assertOk()->assertSee($reversal->reference)->assertSee('Recorded in error');
    }

    public function test_bank_expense_can_be_reversed_even_after_accounts_are_deactivated(): void
    {
        $expense = $this->expense('1110');
        $expense->paymentAccount->update(['is_active' => false]);
        $expense->expenseAccount->update(['is_active' => false]);
        $this->actingAs($this->admin)->post(route('accounting.expenses.reverse', $expense), $this->payload())
            ->assertSessionHasNoErrors();
        $entry = OperatingExpenseReversal::sole()->journalEntries()->sole();
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $entry->id,
            'accounting_account_id' => $this->account('1110')->id, 'debit' => 125.75, 'credit' => 0]);
    }

    public function test_duplicate_reversal_cannot_create_another_record_or_journal(): void
    {
        $expense = $this->expense();
        $this->actingAs($this->admin)->post(route('accounting.expenses.reverse', $expense), $this->payload())
            ->assertSessionHasNoErrors();
        $this->post(route('accounting.expenses.reverse', $expense), $this->payload())->assertSessionHasErrors('expense');
        $this->get(route('accounting.expenses.reversal', $expense))->assertRedirect(route('accounting.expenses'));
        $this->assertDatabaseCount('operating_expense_reversals', 1);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_reason_and_valid_date_not_before_expense_are_required(): void
    {
        $expense = $this->expense();
        foreach (['', '   ', str_repeat('x', 301)] as $reason) {
            $this->actingAs($this->admin)->post(route('accounting.expenses.reverse', $expense),
                array_replace($this->payload(), ['reason' => $reason]))->assertSessionHasErrors('reason');
        }
        foreach (['2026-09-24', '2026-02-30', 'bad-date'] as $date) {
            $this->post(route('accounting.expenses.reverse', $expense),
                array_replace($this->payload(), ['reversal_date' => $date]))->assertSessionHasErrors('reversal_date');
        }
        $this->assertDatabaseCount('operating_expense_reversals', 0);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_reports_keep_expense_before_reversal_and_offset_it_on_reversal_date(): void
    {
        $expense = $this->expense();
        $this->actingAs($this->admin)->post(route('accounting.expenses.reverse', $expense), $this->payload())
            ->assertSessionHasNoErrors();
        $before = app(BalanceSheetService::class)->report('2026-09-25');
        $after = app(BalanceSheetService::class)->report('2026-09-26');
        $this->assertSame(-12575, $before['earningsCents']);
        $this->assertSame(-12575, $before['assetCents']);
        $this->assertSame(0, $after['earningsCents']);
        $this->assertSame(0, $after['assetCents']);
        $this->assertTrue($after['isBalanced']);
        $trial = app(TrialBalanceService::class)->report('2026-09-26');
        $this->assertTrue($trial['isBalanced']);
        $this->assertSame(0, $trial['totalDebitCents']);
        $this->assertSame(0, $trial['totalCreditCents']);
    }

    public function test_missing_or_ambiguous_original_journal_blocks_reversal(): void
    {
        $expense = $this->expense();
        $original = $expense->journalEntries()->sole();
        DB::table('journal_entries')->where('id', $original->id)->update(['source_id' => null]);
        $this->actingAs($this->admin)->post(route('accounting.expenses.reverse', $expense), $this->payload())
            ->assertSessionHasErrors('accounting');
        DB::table('journal_entries')->where('id', $original->id)->update(['source_id' => $expense->id]);
        app(JournalPostingService::class)->post($this->admin, '2026-09-25', 'Ambiguous duplicate', [
            ['account_id' => $expense->expense_account_id, 'debit' => 125.75],
            ['account_id' => $expense->payment_account_id, 'credit' => 125.75],
        ], $expense);
        $this->post(route('accounting.expenses.reverse', $expense), $this->payload())->assertSessionHasErrors('accounting');
        $this->assertDatabaseCount('operating_expense_reversals', 0);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_original_journal_reversed_elsewhere_cannot_be_reversed_again(): void
    {
        $expense = $this->expense();
        $original = $expense->journalEntries()->sole();
        app(JournalPostingService::class)->post($this->admin, '2026-09-26', 'Existing reversal', [
            ['account_id' => $expense->expense_account_id, 'credit' => 125.75],
            ['account_id' => $expense->payment_account_id, 'debit' => 125.75],
        ], null, $original);
        $this->actingAs($this->admin)->post(route('accounting.expenses.reverse', $expense), $this->payload())
            ->assertSessionHasErrors('expense');
        $this->assertDatabaseCount('operating_expense_reversals', 0);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_mismatched_journal_amount_cannot_be_reversed(): void
    {
        $expense = $this->expense();
        $original = $expense->journalEntries()->sole();
        DB::table('journal_lines')->where('journal_entry_id', $original->id)
            ->where('accounting_account_id', $expense->expense_account_id)->update(['debit' => 1]);
        $this->actingAs($this->admin)->post(route('accounting.expenses.reverse', $expense), $this->payload())
            ->assertSessionHasErrors('accounting');
        $this->assertDatabaseCount('operating_expense_reversals', 0);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_journal_failure_rolls_back_reversal_and_allows_retry(): void
    {
        $expense = $this->expense();
        $this->mock(JournalPostingService::class)->shouldReceive('post')->once()
            ->andThrow(ValidationException::withMessages(['accounting' => 'Posting unavailable.']));
        $this->actingAs($this->admin)->post(route('accounting.expenses.reverse', $expense), $this->payload())
            ->assertSessionHasErrors('accounting');
        $this->assertDatabaseCount('operating_expense_reversals', 0);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertNull($expense->fresh()->reversal);
        $this->app->forgetInstance(JournalPostingService::class);
        $this->post(route('accounting.expenses.reverse', $expense), $this->payload())->assertSessionHas('success');
        $this->assertDatabaseCount('operating_expense_reversals', 1);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_guests_and_non_admins_cannot_view_or_submit_reversals(): void
    {
        $expense = $this->expense();
        $this->get(route('accounting.expenses.reversal', $expense))->assertRedirect(route('login'));
        $this->post(route('accounting.expenses.reverse', $expense), $this->payload())->assertRedirect(route('login'));
        foreach (['user', 'staff', 'driver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('accounting.expenses.reversal', $expense))->assertRedirect('/dashboard');
            $this->post(route('accounting.expenses.reverse', $expense), $this->payload())->assertRedirect('/dashboard');
        }
        $this->assertDatabaseCount('operating_expense_reversals', 0);
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::where('code', $code)->firstOrFail();
    }

    private function expense(string $paymentCode = '1100'): OperatingExpense
    {
        return app(OperatingExpenseService::class)->record($this->admin, [
            'expense_account_id' => $this->account('5200')->id,
            'payment_account_id' => $this->account($paymentCode)->id,
            'expense_date' => '2026-09-25', 'amount' => '125.75', 'description' => 'Fuel for deliveries',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function payload(): array
    {
        return ['reversal_date' => '2026-09-26', 'reason' => 'Recorded in error'];
    }
}
