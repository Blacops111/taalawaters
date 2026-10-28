<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\InventoryCostingSetup;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\OperatingExpense;
use App\Models\ProductionCostAllocation;
use App\Models\ProductionRecipe;
use App\Models\ProductionRun;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\BalanceSheetService;
use App\Services\InventoryCostingService as Cost;
use App\Services\JournalPostingService;
use App\Services\OperatingExpenseReversalService;
use App\Services\OperatingExpenseService;
use App\Services\ProductionCostAllocationService;
use App\Services\ProductionRunReversalService;
use App\Services\ProductionRunService;
use App\Services\ProfitLossService;
use App\Services\SalesOrderCompletionService;
use App\Services\SalesOrderReversalService;
use App\Services\TrialBalanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionCostAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private InventoryItem $material;
    private InventoryItem $water;
    private InventoryItem $finished;
    private ProductionRecipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-28 08:00:00', 'UTC'));
        $this->seed(AccountingAccountSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->material = $this->item('CAP-CONVERSION', 'cap');
        $this->water = $this->item('RAW-CONVERSION', 'raw_water');
        $this->finished = $this->item('FINISHED-CONVERSION', 'finished_product');
        foreach ([$this->material, $this->water] as $item) {
            StockMovement::create(['inventory_item_id' => $item->id, 'movement_type' => 'opening_balance', 'quantity_delta' => 100, 'occurred_at' => now(), 'created_by' => $this->admin->id]);
        }
        $this->recipe = ProductionRecipe::create(['name' => 'Conversion test recipe', 'finished_product_id' => $this->finished->id, 'output_quantity' => 1, 'is_active' => true]);
        $this->recipe->components()->createMany([
            ['inventory_item_id' => $this->material->id, 'quantity_required' => '1.000'],
            ['inventory_item_id' => $this->water->id, 'quantity_required' => '0.500'],
        ]);
        app(Cost::class)->activate($this->admin, [
            'values' => [$this->material->id => '200', $this->water->id => '0', $this->finished->id => '0'],
            'quantities' => [$this->material->id => '100', $this->water->id => '100', $this->finished->id => '0'],
            'ledger_cents' => 0, 'offset_code' => '3100', 'notes' => 'Verified material opening costs', 'acknowledgement' => '1',
        ]);
        $this->travelTo(CarbonImmutable::parse('2026-10-28 09:00:00', 'UTC'));
    }

    public function test_admin_allocates_recorded_expense_and_preserves_original_payment_and_history(): void
    {
        $expense = $this->expense();
        $before = $expense->fresh()->getAttributes();
        $original = $expense->journalEntries()->with('lines')->sole()->toArray();
        $payload = $this->payload([$this->row($expense, '50.00')]);
        $this->actingAs($this->admin)->get(route('production.runs.create'))->assertOk()->assertSee($expense->reference)->assertSee('100.00');
        $this->post(route('production.runs.store'), $payload)->assertRedirect(route('production.runs.create'))->assertSessionHasNoErrors();
        $run = ProductionRun::sole();
        $allocation = ProductionCostAllocation::sole();
        $this->assertSame(5000, $allocation->amount_cents);
        $this->assertSame($expense->id, $allocation->operating_expense_id);
        $this->assertSame($run->id, $allocation->production_run_id);
        $this->assertSame('Measured production share', $allocation->basis);
        $this->assertSame($this->admin->id, $allocation->allocated_by);
        $this->assertSame($before, $expense->fresh()->getAttributes());
        $this->assertSame($original, $expense->journalEntries()->with('lines')->sole()->toArray());
        $this->assertSame(1, OperatingExpense::count());
        $journal = $run->journalEntries()->sole();
        $this->assertCount(2, $journal->lines);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'accounting_account_id' => $this->account('1300')->id, 'debit' => 50, 'credit' => 0]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'accounting_account_id' => $this->account('5300')->id, 'debit' => 0, 'credit' => 50]);
        $this->assertSame(0, $journal->lines()->whereIn('accounting_account_id', [$this->account('1100')->id, $this->account('1110')->id])->count());
        $this->assertSame(7000, $run->stockMovements()->where('movement_type', 'production_output')->sole()->value_delta_cents);
        $this->assertBalance($this->finished, 10000, 7000);
        $this->assertReconciled(25000);
        $this->get(route('accounting.production-costs'))->assertOk()->assertSee($run->reference)->assertSee($expense->reference)->assertSee('Measured production share');
        $this->get(route('accounting.expenses'))->assertOk()->assertSee('Allocated to production:');
    }

    public function test_multiple_expenses_share_accounts_and_round_to_exact_cents(): void
    {
        $power = $this->expense('5300', '100', '1110');
        $labour = $this->expense('5500', '100');
        $other = $this->expense('5500', '100');
        $repairs = $this->expense('5400', '100');
        $run = $this->produce([$this->row($power, '10.01'), $this->row($labour, '20.02'), $this->row($other, '0.03'), $this->row($repairs, '5.04')]);
        $this->assertSame(3510, (int) $run->costAllocations()->sum('amount_cents'));
        $this->assertCount(4, $run->journalEntries()->sole()->lines);
        $this->assertBalance($this->finished, 10000, 5510);
        $this->assertReconciled(23510);
    }

    public function test_partial_allocations_across_batches_cannot_exceed_original_expense(): void
    {
        $expense = $this->expense();
        $this->produce([$this->row($expense, '60')]);
        $this->reject(fn () => $this->produce([$this->row($expense, '40.01')]));
        $this->assertSame(1, ProductionRun::count());
        $this->produce([$this->row($expense, '40')]);
        $this->assertSame(10000, app(ProductionCostAllocationService::class)->activeCents($expense));
        $this->assertSame(0, app(ProductionCostAllocationService::class)->availableExpenses()->where('operating_expenses.id', $expense->id)->count());
        $this->reject(fn () => $this->produce([$this->row($expense, '0.01')]));
        $this->assertSame(2, ProductionCostAllocation::count());
        $this->assertReconciled(30000);
    }

    public function test_duplicate_submission_returns_same_batch_and_changed_submission_is_rejected(): void
    {
        $expense = $this->expense();
        $rows = [$this->row($expense, '50')];
        $key = (string) Str::uuid();
        $first = $this->produce($rows, $key);
        $second = $this->produce($rows, $key);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProductionCostAllocation::count());
        $this->assertSame(1, $first->journalEntries()->count());
        $this->reject(fn () => $this->produce([$this->row($expense, '49')], $key));
        $this->assertBalance($this->finished, 10000, 7000);
    }

    public function test_new_submission_may_record_another_legitimate_batch(): void
    {
        $expense = $this->expense();
        $this->produce([$this->row($expense, '50')]);
        $this->produce([$this->row($expense, '50')]);
        $this->assertSame(2, ProductionRun::count());
        $this->assertBalance($this->finished, 20000, 14000);
    }

    public function test_costed_sale_and_sale_reversal_include_allocated_conversion_costs(): void
    {
        $expense = $this->expense();
        $this->produce([$this->row($expense, '50')]);
        $sale = $this->sale(2);
        $journal = $sale->journalEntries()->sole();
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'accounting_account_id' => $this->account('5100')->id, 'debit' => 14, 'credit' => 0]);
        $this->assertBalance($this->finished, 8000, 5600);
        $report = app(ProfitLossService::class)->report('2026-10-28', '2026-10-28');
        $this->assertSame(6400, $report['expenseCents']);
        $this->assertSame(13600, $report['netProfitCents']);
        $this->assertTrue(app(TrialBalanceService::class)->report('2026-10-28')['isBalanced']);
        $this->assertTrue(app(BalanceSheetService::class)->report('2026-10-28')['isBalanced']);
        $this->assertReconciled(23600);
        app(SalesOrderReversalService::class)->reverse($sale, $this->admin, 'Sale entered twice', now());
        $this->assertBalance($this->finished, 10000, 7000);
        $this->assertReconciled(25000);
        $this->assertSame(-5000, app(ProfitLossService::class)->report('2026-10-28', '2026-10-28')['netProfitCents']);
    }

    public function test_batch_reversal_restores_expense_and_releases_allocation_without_changing_cash(): void
    {
        $expense = $this->expense();
        $run = $this->produce([$this->row($expense, '50')]);
        $originalJournal = $run->journalEntries()->with('lines')->sole()->toArray();
        $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00:00', 'UTC'));
        $reversal = app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now());
        $journal = $reversal->journalEntries()->sole();
        $this->assertSame($originalJournal['id'], $journal->reversal_of_id);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $journal->id, 'accounting_account_id' => $this->account('5300')->id, 'debit' => 50, 'credit' => 0]);
        $this->assertSame($originalJournal, $run->journalEntries()->with('lines')->sole()->toArray());
        $this->assertBalance($this->finished, 0, 0);
        $this->assertBalance($this->material, 100000, 20000);
        $this->assertReconciled(20000);
        $this->assertSame(0, app(ProductionCostAllocationService::class)->activeCents($expense));
        $this->assertSame(1, ProductionCostAllocation::count());
        $this->assertSame(1, $expense->journalEntries()->count());
        $before = app(ProfitLossService::class)->report('2026-10-28', '2026-10-28');
        $after = app(ProfitLossService::class)->report('2026-10-28', '2026-10-29');
        $this->assertSame(5000, $before['expenseCents']);
        $this->assertSame(10000, $after['expenseCents']);
        $this->actingAs($this->admin)->get(route('accounting.production-costs'))->assertOk()->assertSee('Reversed');
        $this->produce([$this->row($expense, '100')]);
        $this->assertSame(10000, app(ProductionCostAllocationService::class)->activeCents($expense));
    }

    public function test_expense_reversal_is_blocked_until_batch_reversal_and_cannot_be_backdated(): void
    {
        $expense = $this->expense();
        $run = $this->produce([$this->row($expense, '50')]);
        $this->reject(fn () => app(OperatingExpenseReversalService::class)->reverse($expense, $this->admin, ['reason' => 'Wrong expense', 'reversal_date' => '2026-10-28']));
        $this->actingAs($this->admin)->get(route('accounting.expenses.reversal', $expense))->assertOk()->assertSee('allocated to production');
        $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00:00', 'UTC'));
        app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now());
        $this->reject(fn () => app(OperatingExpenseReversalService::class)->reverse($expense, $this->admin, ['reason' => 'Wrong expense', 'reversal_date' => '2026-10-28']));
        app(OperatingExpenseReversalService::class)->reverse($expense, $this->admin, ['reason' => 'Wrong expense', 'reversal_date' => '2026-10-29']);
        $this->assertNotNull($expense->reversal()->first());
        $this->assertSame(0, app(ProfitLossService::class)->report('2026-10-28', '2026-10-29')['expenseCents']);
    }

    public function test_batch_reversal_after_a_sale_is_blocked_without_releasing_costs(): void
    {
        $expense = $this->expense();
        $run = $this->produce([$this->row($expense, '50')]);
        $this->sale(1);
        $count = JournalEntry::count();
        $this->reject(fn () => app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now()));
        $this->assertSame(5000, app(ProductionCostAllocationService::class)->activeCents($expense));
        $this->assertSame($count, JournalEntry::count());
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_inactive_accounts_can_be_reversed_using_original_journal(): void
    {
        $expense = $this->expense();
        $run = $this->produce([$this->row($expense, '50')]);
        AccountingAccount::whereIn('code', ['1300', '5300'])->update(['is_active' => false]);
        $reversal = app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now());
        $this->assertSame(1, $reversal->journalEntries()->count());
        $this->assertReconciled(20000);
    }

    public function test_duplicate_and_replayed_reversed_batches_cannot_allocate_again(): void
    {
        $expense = $this->expense();
        $key = (string) Str::uuid();
        $rows = [$this->row($expense, '50')];
        $run = $this->produce($rows, $key);
        app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now());
        $this->reject(fn () => app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Again', now()));
        $this->reject(fn () => $this->produce($rows, $key));
        $this->assertSame(1, ProductionRun::count());
        $this->assertSame(0, app(ProductionCostAllocationService::class)->activeCents($expense));
    }

    public function test_reversed_and_pre_activation_expenses_are_not_allocatable(): void
    {
        $reversed = $this->expense();
        app(OperatingExpenseReversalService::class)->reverse($reversed, $this->admin, ['reason' => 'Wrong expense', 'reversal_date' => '2026-10-28']);
        $this->reject(fn () => $this->produce([$this->row($reversed, '50')]));
        $old = $this->expense();
        DB::table('operating_expenses')->where('id', $old->id)->update(['created_at' => '2026-10-28 07:00:00']);
        $this->reject(fn () => $this->produce([$this->row($old, '50')]));
        $this->assertSame(0, app(ProductionCostAllocationService::class)->availableExpenses()->count());
        $this->assertSame(0, ProductionRun::count());
    }

    public function test_inactive_wrong_type_and_distribution_expenses_are_rejected(): void
    {
        $distribution = $this->expense('5200');
        $this->reject(fn () => $this->produce([$this->row($distribution, '10')]));
        $power = $this->expense();
        $power->expenseAccount->update(['is_active' => false]);
        $this->reject(fn () => $this->produce([$this->row($power, '10')]));
        $power->expenseAccount->update(['is_active' => true, 'type' => 'asset']);
        $this->reject(fn () => $this->produce([$this->row($power, '10')]));
        $this->assertSame(0, ProductionCostAllocation::count());
    }

    public function test_inactive_inventory_account_rolls_back_everything(): void
    {
        $expense = $this->expense();
        $this->account('1300')->update(['is_active' => false]);
        $this->reject(fn () => $this->produce([$this->row($expense, '50')]));
        $this->assertSame(0, ProductionRun::count());
        $this->assertSame(0, ProductionCostAllocation::count());
        $this->assertBalance($this->material, 100000, 20000);
    }

    public function test_missing_and_ambiguous_expense_journals_are_rejected(): void
    {
        $expense = $this->expense();
        $journal = $expense->journalEntries()->sole();
        DB::table('journal_entries')->where('id', $journal->id)->update(['status' => 'draft']);
        $this->reject(fn () => $this->produce([$this->row($expense, '50')]));
        DB::table('journal_entries')->where('id', $journal->id)->update(['status' => 'posted']);
        app(JournalPostingService::class)->post($this->admin, '2026-10-28', 'Duplicate original', [
            ['account_id' => $this->account('5300')->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->account('1100')->id, 'debit' => 0, 'credit' => 100],
        ], $expense);
        $this->reject(fn () => $this->produce([$this->row($expense, '50')]));
        $this->assertSame(0, ProductionCostAllocation::count());
    }

    public function test_mismatched_or_separately_reversed_expense_journal_is_rejected(): void
    {
        $expense = $this->expense();
        $journal = $expense->journalEntries()->sole();
        DB::table('journal_lines')->where('journal_entry_id', $journal->id)->where('accounting_account_id', $expense->expense_account_id)->update(['debit' => '99.99']);
        $this->reject(fn () => $this->produce([$this->row($expense, '50')]));
        DB::table('journal_lines')->where('journal_entry_id', $journal->id)->where('accounting_account_id', $expense->expense_account_id)->update(['debit' => '100.00']);
        app(JournalPostingService::class)->post($this->admin, '2026-10-28', 'Separate reversal', $journal->fresh('lines')->lines->map(fn ($line) => [
            'account_id' => $line->accounting_account_id, 'debit' => $line->credit, 'credit' => $line->debit,
        ])->all(), null, $journal);
        $this->reject(fn () => $this->produce([$this->row($expense, '50')]));
    }

    public function test_invalid_amounts_missing_basis_duplicate_expenses_and_missing_key_are_rejected(): void
    {
        $expense = $this->expense();
        foreach (['0', '-1', '0.001', '1.999', '1e2', 'abc'] as $amount) {
            $this->reject(fn () => $this->produce([$this->row($expense, $amount)]));
        }
        $this->reject(fn () => $this->produce([$this->row($expense, '10', '   ')]));
        $this->reject(fn () => $this->produce([$this->row($expense, '10'), $this->row($expense, '10')]));
        $this->reject(fn () => app(ProductionRunService::class)->complete($this->recipe, 10, $this->admin, now(), null, [$this->row($expense, '10')]));
        $this->assertSame(0, ProductionRun::count());
        $this->assertBalance($this->material, 100000, 20000);
    }

    public function test_expense_date_future_batch_and_released_cost_backdating_are_rejected(): void
    {
        $future = $this->expense('5300', '100', '1100', '2026-10-29');
        $this->reject(fn () => $this->produce([$this->row($future, '10')]));
        $expense = $this->expense();
        $this->reject(fn () => app(ProductionRunService::class)->complete($this->recipe, 10, $this->admin, now()->addDay(), null,
            [$this->row($expense, '10')], (string) Str::uuid()));
        $run = $this->produce([$this->row($expense, '50')]);
        $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00:00', 'UTC'));
        app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now());
        $this->reject(fn () => app(ProductionRunService::class)->complete($this->recipe, 10, $this->admin,
            CarbonImmutable::parse('2026-10-28 10:00:00', 'UTC'), null, [$this->row($expense, '10')], (string) Str::uuid()));
        $this->assertSame(1, ProductionRun::count());
    }

    public function test_allocations_require_active_costing_but_material_only_production_still_works(): void
    {
        InventoryCostingSetup::findOrFail(1)->update(['is_active' => false]);
        $expense = $this->expense();
        $this->reject(fn () => $this->produce([$this->row($expense, '10')]));
        $this->produce([]);
        $this->assertSame(1, ProductionRun::count());
        $this->assertSame(0, ProductionCostAllocation::count());
    }

    public function test_posting_failure_rolls_back_batch_audit_and_inventory(): void
    {
        $expense = $this->expense();
        $this->mock(JournalPostingService::class)->shouldReceive('post')->once()
            ->andThrow(ValidationException::withMessages(['accounting' => 'Simulated posting failure']));
        $this->reject(fn () => $this->produce([$this->row($expense, '50')]));
        $this->assertSame(0, ProductionRun::count());
        $this->assertSame(0, ProductionCostAllocation::count());
        $this->assertSame(2, StockMovement::count());
        $this->assertBalance($this->material, 100000, 20000);
        $this->assertReconciled(20000);
    }

    public function test_stock_output_failure_rolls_back_previously_created_allocation_journal(): void
    {
        $expense = $this->expense();
        // Simulate an unreconciled finished-item balance; consumption happens before output validation.
        DB::table('inventory_cost_balances')->where('inventory_item_id', $this->finished->id)->update(['quantity_milli' => 1000]);
        $journalCount = JournalEntry::count();
        $this->reject(fn () => $this->produce([$this->row($expense, '50')]));
        $this->assertSame($journalCount, JournalEntry::count());
        $this->assertSame(0, ProductionCostAllocation::count());
        $this->assertSame(0, ProductionRun::count());
        $this->assertBalance($this->material, 100000, 20000);
    }

    public function test_reversal_posting_failure_rolls_back_stock_and_keeps_allocation_reserved(): void
    {
        $expense = $this->expense();
        $run = $this->produce([$this->row($expense, '50')]);
        $this->mock(JournalPostingService::class)->shouldReceive('post')->once()
            ->andThrow(ValidationException::withMessages(['accounting' => 'Simulated reversal failure']));
        $this->reject(fn () => app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now()));
        $this->assertBalance($this->finished, 10000, 7000);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertNull($run->reversal()->first());
        $this->assertSame(5000, app(ProductionCostAllocationService::class)->activeCents($expense));
        $this->assertReconciled(25000);
    }

    public function test_mismatched_batch_journal_prevents_reversal_and_rolls_back_stock(): void
    {
        $expense = $this->expense();
        $run = $this->produce([$this->row($expense, '50')]);
        $entry = $run->journalEntries()->sole();
        DB::table('journal_lines')->where('journal_entry_id', $entry->id)->where('accounting_account_id', $expense->expense_account_id)->update(['credit' => '49.99']);
        $this->reject(fn () => app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now()));
        $this->assertBalance($this->finished, 10000, 7000);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(5000, app(ProductionCostAllocationService::class)->activeCents($expense));
    }

    public function test_allocation_history_is_immutable_paginated_searchable_and_escaped(): void
    {
        $expense = $this->expense('5300', '100');
        for ($i = 0; $i < 26; $i++) {
            app(ProductionRunService::class)->complete($this->recipe, 1, $this->admin, now(), null,
                [$this->row($expense, '1', '<script>alert(1)</script>')], (string) Str::uuid());
        }
        $allocation = ProductionCostAllocation::firstOrFail();
        try {
            $allocation->update(['amount_cents' => 2]);
            $this->fail('Expected immutable allocation.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('cannot be edited', $exception->getMessage());
        }
        $this->actingAs($this->admin)->get(route('accounting.production-costs'))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertViewHas('allocations', fn ($rows) => $rows->total() === 26 && $rows->count() === 25);
        $this->get(route('accounting.production-costs', ['page' => 2]))->assertOk()->assertSee($allocation->productionRun->reference);
        $this->get(route('accounting.production-costs', ['search' => $allocation->productionRun->reference]))->assertOk()
            ->assertViewHas('allocations', fn ($rows) => $rows->total() === 1);
    }

    public function test_guests_and_non_admins_cannot_view_or_record_allocations(): void
    {
        $expense = $this->expense();
        $payload = $this->payload([$this->row($expense, '50')]);
        $this->get(route('accounting.production-costs'))->assertRedirect(route('login'));
        $this->post(route('production.runs.store'), $payload)->assertRedirect(route('login'));
        foreach (['user', 'staff', 'driver'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('accounting.production-costs'))->assertRedirect('/dashboard');
            $this->post(route('production.runs.store'), $payload)->assertRedirect('/dashboard');
        }
        $this->assertSame(0, ProductionCostAllocation::count());
        $this->assertSame(0, ProductionRun::count());
    }

    public function test_blank_optional_rows_preserve_material_only_production_and_retry_is_idempotent(): void
    {
        $payload = $this->payload([['expense_id' => '', 'amount' => '', 'basis' => '']]);
        $this->actingAs($this->admin)->post(route('production.runs.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('production.runs.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(1, ProductionRun::count());
        $this->assertSame(0, ProductionCostAllocation::count());
        $this->assertBalance($this->finished, 10000, 2000);
        $this->assertReconciled(20000);
    }

    public function test_missing_allocation_audit_or_journal_blocks_batch_reversal(): void
    {
        $expense = $this->expense();
        $run = $this->produce([$this->row($expense, '50')]);
        $journal = $run->journalEntries()->sole();
        DB::table('journal_entries')->where('id', $journal->id)->update(['status' => 'draft']);
        $this->reject(fn () => app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now()));
        DB::table('journal_entries')->where('id', $journal->id)->update(['status' => 'posted']);
        DB::table('production_cost_allocations')->where('production_run_id', $run->id)->delete();
        $this->reject(fn () => app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now()));
        $this->assertBalance($this->finished, 10000, 7000);
        $this->assertSame('completed', $run->fresh()->status);
    }

    private function item(string $sku, string $category): InventoryItem
    {
        return InventoryItem::create(['sku' => $sku, 'name' => $sku, 'category' => $category,
            'unit' => $category === 'raw_water' ? 'litre' : 'unit', 'is_active' => true, 'is_sellable' => $category === 'finished_product']);
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::where('code', $code)->firstOrFail();
    }

    private function expense(string $code = '5300', string $amount = '100', string $payment = '1100', string $date = '2026-10-28'): OperatingExpense
    {
        return app(OperatingExpenseService::class)->record($this->admin, [
            'expense_account_id' => $this->account($code)->id, 'payment_account_id' => $this->account($payment)->id,
            'expense_date' => $date, 'amount' => $amount, 'description' => 'Production '.$code,
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function row(OperatingExpense $expense, string $amount, string $basis = 'Measured production share'): array
    {
        return ['expense_id' => $expense->id, 'amount' => $amount, 'basis' => $basis];
    }

    private function produce(array $rows, ?string $key = null): ProductionRun
    {
        return app(ProductionRunService::class)->complete($this->recipe, 10, $this->admin, now(), null, $rows, $key ?? (string) Str::uuid());
    }

    private function payload(array $rows): array
    {
        return ['production_recipe_id' => $this->recipe->id, 'quantity_produced' => 10, 'occurred_at' => now()->format('Y-m-d H:i:s'),
            'allocations' => $rows, 'completion_key' => (string) Str::uuid()];
    }

    private function sale(int $quantity): SalesOrder
    {
        $sale = SalesOrder::create(['sale_type' => SalesOrder::TYPE_WALK_IN, 'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => now(), 'total_amount' => $quantity * 100, 'created_by' => $this->admin->id]);
        $sale->items()->create(['inventory_item_id' => $this->finished->id, 'quantity' => $quantity, 'unit_price' => 100, 'line_total' => $quantity * 100]);
        return app(SalesOrderCompletionService::class)->complete($sale, $this->admin);
    }

    private function assertBalance(InventoryItem $item, int $quantity, int $cents): void
    {
        $row = DB::table('inventory_cost_balances')->where('inventory_item_id', $item->id)->first();
        $this->assertSame($quantity, (int) $row->quantity_milli);
        $this->assertSame($cents, (int) $row->value_cents);
        $this->assertSame($quantity, Cost::quantityMilli($item->id));
    }

    private function assertReconciled(int $cents): void
    {
        $this->assertSame($cents, app(Cost::class)->ledgerCents());
        $this->assertSame($cents, (int) DB::table('inventory_cost_balances')->sum('value_cents'));
    }

    private function reject(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
