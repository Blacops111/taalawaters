<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\OperatingExpense;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\BalanceSheetService;
use App\Services\JournalPostingService;
use App\Services\OperatingExpenseService;
use App\Services\TrialBalanceService;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class OperatingExpenseAccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingAccountSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_post_cash_expense_with_linked_journal_and_audit_history(): void
    {
        $data = $this->payload();
        $this->actingAs($this->admin)->post(route('accounting.expenses.store'), $data)
            ->assertRedirect(route('accounting.expenses'))->assertSessionHasNoErrors();
        $expense = OperatingExpense::sole();
        $journal = $expense->journalEntries()->sole();
        $this->assertSame('EXP-00000001', $expense->reference);
        $this->assertSame('125.75', $expense->amount);
        $this->assertSame('Station A', $expense->payee);
        $this->assertSame('RCPT-001', $expense->external_reference);
        $this->assertSame('Fuel for deliveries', $expense->description);
        $this->assertSame('Company vehicle', $expense->notes);
        $this->assertSame($this->admin->id, $expense->created_by);
        $this->assertSame($this->admin->id, $journal->posted_by);
        $this->assertSame('2026-09-25', $journal->entry_date->toDateString());
        $this->assertSame(JournalEntry::STATUS_POSTED, $journal->status);
        $this->assertCount(2, $journal->lines);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id,
            'accounting_account_id' => $this->account('5200')->id, 'debit' => 125.75, 'credit' => 0]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id,
            'accounting_account_id' => $this->account('1100')->id, 'debit' => 0, 'credit' => 125.75]);
        $this->assertSame(0, StockMovement::count());
        $this->get(route('accounting.expenses'))->assertOk()->assertSee($expense->reference)
            ->assertSee('Station A')->assertSee('125.75')->assertSee('Company vehicle');
        $this->get(route('accounting.journals', ['search' => $expense->reference]))->assertOk()
            ->assertSee($expense->reference)->assertSee('Fuel for deliveries');
    }

    public function test_all_four_operating_categories_can_be_paid_from_bank(): void
    {
        $this->actingAs($this->admin);
        foreach (['5200', '5300', '5400', '5500'] as $code) {
            $this->post(route('accounting.expenses.store'), $this->payload($code, '1110'))
                ->assertSessionHasNoErrors();
            $expense = OperatingExpense::latest('id')->firstOrFail();
            $this->assertSame($this->account($code)->id, $expense->expense_account_id);
            $this->assertDatabaseHas('journal_lines', [
                'journal_entry_id' => $expense->journalEntries()->sole()->id,
                'accounting_account_id' => $this->account('1110')->id, 'debit' => 0, 'credit' => 125.75,
            ]);
        }
        $this->assertDatabaseCount('operating_expenses', 4);
        $this->assertDatabaseCount('journal_entries', 4);
    }

    public function test_expense_flows_into_reports_on_its_date_without_changing_revenue(): void
    {
        app(JournalPostingService::class)->post($this->admin, '2026-09-24', 'Existing revenue', [
            ['account_id' => $this->account('1100')->id, 'debit' => 300],
            ['account_id' => $this->account('4100')->id, 'credit' => 300],
        ]);
        $this->actingAs($this->admin)->post(route('accounting.expenses.store'), $this->payload())->assertSessionHasNoErrors();
        $before = app(BalanceSheetService::class)->report('2026-09-24');
        $after = app(BalanceSheetService::class)->report('2026-09-25');
        $this->assertSame(30000, $before['earningsCents']);
        $this->assertSame(30000, $after['revenueCents']);
        $this->assertSame(12575, $after['expenseCents']);
        $this->assertSame(17425, $after['earningsCents']);
        $this->assertSame(17425, $after['assetCents']);
        $this->assertTrue($after['isBalanced']);
        $trial = app(TrialBalanceService::class)->report('2026-09-25');
        $this->assertTrue($trial['isBalanced']);
        $this->assertSame(12575, $trial['rows']->keyBy('code')['5200']->debit_cents);
    }

    public function test_repeated_submission_posts_once_and_changed_payload_is_rejected(): void
    {
        $data = $this->payload();
        $this->actingAs($this->admin)->post(route('accounting.expenses.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('accounting.expenses.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('operating_expenses', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $data['amount'] = '120.00';
        $this->post(route('accounting.expenses.store'), $data)->assertSessionHasErrors('idempotency_key');
        $this->assertDatabaseCount('operating_expenses', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_new_submission_can_record_another_identical_legitimate_expense(): void
    {
        $this->actingAs($this->admin)->post(route('accounting.expenses.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('accounting.expenses.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('operating_expenses', 2);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_inventory_cogs_parent_and_revenue_accounts_are_not_operating_expense_categories(): void
    {
        foreach (['1300', '5100', '5000', '4100'] as $code) {
            $this->actingAs($this->admin)->post(route('accounting.expenses.store'), $this->payload($code))
                ->assertSessionHasErrors('expense_account_id');
        }
        $this->assertDatabaseCount('operating_expenses', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_inactive_wrong_type_and_invalid_payment_accounts_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('accounting.expenses.store'), $this->payload('5200', '2100'))
            ->assertSessionHasErrors('payment_account_id');
        $this->account('1100')->update(['is_active' => false]);
        $this->post(route('accounting.expenses.store'), $this->payload())->assertSessionHasErrors('payment_account_id');
        $this->account('5200')->update(['is_active' => false]);
        $this->post(route('accounting.expenses.store'), $this->payload('5200', '1110'))->assertSessionHasErrors('expense_account_id');
        $this->account('5300')->update(['type' => AccountingAccount::TYPE_ASSET]);
        $this->post(route('accounting.expenses.store'), $this->payload('5300', '1110'))->assertSessionHasErrors('expense_account_id');
        $this->assertDatabaseCount('operating_expenses', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_invalid_amounts_dates_and_missing_details_are_rejected(): void
    {
        foreach (['0', '-1', '0.001', '100.999', '1e2', '10000000000000', 'abc'] as $amount) {
            $data = $this->payload();
            $data['amount'] = $amount;
            $this->actingAs($this->admin)->post(route('accounting.expenses.store'), $data)->assertSessionHasErrors('amount');
        }
        foreach (['expense_date' => '2026-02-30', 'description' => '   ', 'idempotency_key' => 'not-a-uuid'] as $field => $value) {
            $data = $this->payload();
            $data[$field] = $value;
            $this->post(route('accounting.expenses.store'), $data)->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('operating_expenses', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_posting_failure_rolls_back_expense_and_allows_retry(): void
    {
        $data = $this->payload();
        $this->mock(JournalPostingService::class)->shouldReceive('post')->once()
            ->andThrow(ValidationException::withMessages(['accounting' => 'Posting unavailable.']));
        $this->actingAs($this->admin)->post(route('accounting.expenses.store'), $data)->assertSessionHasErrors('accounting');
        $this->assertDatabaseCount('operating_expenses', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->app->forgetInstance(JournalPostingService::class);
        $this->post(route('accounting.expenses.store'), $data)->assertSessionHas('success');
        $this->assertDatabaseCount('operating_expenses', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_guest_and_non_admin_cannot_view_or_record_expenses(): void
    {
        $this->get(route('accounting.expenses'))->assertRedirect(route('login'));
        $this->post(route('accounting.expenses.store'), $this->payload())->assertRedirect(route('login'));
        foreach (['user', 'staff', 'driver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('accounting.expenses'))->assertRedirect('/dashboard');
            $this->post(route('accounting.expenses.store'), $this->payload())->assertRedirect('/dashboard');
        }
        $this->assertDatabaseCount('operating_expenses', 0);
    }

    public function test_recorded_expenses_cannot_be_edited_or_deleted(): void
    {
        $expense = app(OperatingExpenseService::class)->record($this->admin, $this->payload());
        foreach (['edit', 'delete'] as $operation) {
            try {
                $record = $expense->fresh();
                $operation === 'edit' ? $record->update(['amount' => '1.00']) : $record->delete();
                $this->fail('Recorded expenses must remain immutable.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('audited reversal', $exception->getMessage());
            }
        }
        $this->assertSame('125.75', $expense->fresh()->amount);
        $this->assertDatabaseCount('operating_expenses', 1);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_history_is_paginated_and_user_text_is_escaped(): void
    {
        $data = $this->payload();
        $data['description'] = '<script>alert(1)</script>';
        app(OperatingExpenseService::class)->record($this->admin, $data);
        for ($i = 0; $i < 25; $i++) {
            app(OperatingExpenseService::class)->record($this->admin, $this->payload());
        }
        $this->actingAs($this->admin)->get(route('accounting.expenses'))->assertOk()
            ->assertViewHas('expenses', fn ($expenses) => $expenses->total() === 26 && $expenses->count() === 25);
        $this->get(route('accounting.expenses', ['page' => 2]))->assertOk()
            ->assertSee('EXP-00000001')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::where('code', $code)->firstOrFail();
    }

    private function payload(string $expenseCode = '5200', string $paymentCode = '1100'): array
    {
        return [
            'expense_account_id' => $this->account($expenseCode)->id,
            'payment_account_id' => $this->account($paymentCode)->id,
            'expense_date' => '2026-09-25', 'amount' => '125.75', 'description' => 'Fuel for deliveries',
            'payee' => 'Station A', 'external_reference' => 'RCPT-001', 'notes' => 'Company vehicle',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
