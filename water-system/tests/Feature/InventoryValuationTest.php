<?php

namespace Tests\Feature;

use App\Models\{AccountingAccount, InventoryItem, JournalEntry, ProductionRecipe, ProductionRun, SalesOrder, StockMovement, User};
use App\Services\{InventoryCostingService as Cost, InventoryValuationService, JournalPostingService, OperatingExpenseService, ProductionRunService, ProductionRunReversalService, SalesOrderCompletionService, SalesOrderReversalService};
use Carbon\CarbonImmutable;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private InventoryItem $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->at('2026-10-28 08:00:00');
        $this->seed(AccountingAccountSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->material = $this->item('CAP-HISTORY');
        $this->movement($this->material, '100', 'opening_balance');
    }

    public function test_inactive_costing_and_pre_activation_dates_are_unavailable_not_zero(): void
    {
        $this->actingAs($this->admin)->get(route('accounting.inventory-valuation'))->assertOk()
            ->assertSee('Inventory costing has not been activated.')->assertViewHas('totalValueCents', null)
            ->assertDontSee('Reconciled —');
        $this->activate();
        $report = $this->report('2026-10-27');
        $this->assertFalse($report['available']);
        $this->assertNull($report['ledgerCents']);
        $this->assertNull($report['differenceCents']);
        $this->actingAs($this->admin)->get(route('accounting.inventory-valuation', ['as_of' => '2026-10-27']))
            ->assertOk()->assertSee('Historical valuation is unavailable before costing activation')
            ->assertSee('2026-10-28 08:00:00')->assertDontSee('Reconciled —');
    }

    public function test_opening_snapshot_is_counted_once_with_same_second_costed_movements(): void
    {
        $this->activate();
        $this->movement($this->material, '-1', 'damage');
        $report = $this->report();
        $row = $report['rows']->sole();
        $this->assertSame(99000, $row->quantity_milli);
        $this->assertSame(19800, $row->value_cents);
        $this->assertSame('2.0000', $row->average_cost);
        $this->assertSame(19800, $report['ledgerCents']);
        $this->assertTrue($report['isReconciled']);
        $this->actingAs($this->admin)->get(route('accounting.inventory-valuation'))->assertOk()
            ->assertViewHas('asOf', '2026-10-28')->assertSee('Reconciled —')->assertSee('198.00');
    }

    public function test_cutoff_includes_last_second_and_excludes_next_midnight(): void
    {
        $this->activate();
        $this->at('2026-10-28 23:59:59');
        $this->movement($this->material, '-10', 'damage');
        $this->at('2026-10-29 00:00:00');
        $this->movement($this->material, '-20', 'damage');
        $this->assertSame(18000, $this->report()['totalValueCents']);
        $this->assertSame(18000, $this->report()['ledgerCents']);
        $this->assertSame(14000, $this->report('2026-10-29')['totalValueCents']);
        $this->assertTrue($this->report('2026-10-29')['isReconciled']);
    }

    public function test_receipt_changes_later_average_without_repricing_earlier_stock(): void
    {
        $this->activate();
        $this->at('2026-10-29 12:00:00');
        $this->movement($this->material, '100', 'purchase_received', 30000);
        $this->post('300.00', '2026-10-29');
        $this->assertSame('2.0000', $this->report()['rows']->sole()->average_cost);
        $later = $this->report('2026-10-29');
        $this->assertSame(200000, $later['rows']->sole()->quantity_milli);
        $this->assertSame('2.5000', $later['rows']->sole()->average_cost);
        $this->assertSame(50000, $later['totalValueCents']);
        $this->assertTrue($later['isReconciled']);
    }

    public function test_journal_cutoff_uses_entry_date_excludes_drafts_and_keeps_inactive_account(): void
    {
        $this->activate();
        $this->at('2026-10-30 12:00:00');
        $this->post('0.01', '2026-10-28');
        $this->post('0.02', '2026-10-29');
        $draft = JournalEntry::create(['entry_date' => '2026-10-28', 'status' => 'draft', 'description' => 'Not posted', 'posted_by' => $this->admin->id]);
        $draft->lines()->create(['accounting_account_id' => $this->account('1300')->id, 'debit' => 99, 'credit' => 0]);
        $this->account('1300')->update(['is_active' => false]);
        $report = $this->report();
        $this->assertSame(20001, $report['ledgerCents']);
        $this->assertSame(-1, $report['differenceCents']);
        $this->assertFalse($report['isReconciled']);
        $this->actingAs($this->admin)->get(route('accounting.inventory-valuation', ['as_of' => '2026-10-28']))
            ->assertOk()->assertSee('Review required')->assertSee('KES -0.01')->assertDontSee('Reconciled —');
    }

    public function test_inactive_and_zero_value_items_are_included_and_names_are_escaped(): void
    {
        $water = $this->item('RAW-HISTORY', 'raw_water');
        $this->movement($water, '50', 'opening_balance');
        $this->activate();
        $this->material->update(['name' => '<script>alert(1)</script>', 'is_active' => false]);
        $this->account('1300')->update(['is_active' => false]);
        $report = $this->report();
        $this->assertCount(2, $report['rows']);
        $this->assertSame(0, $report['rows']->keyBy('id')[$water->id]->value_cents);
        $this->assertTrue($report['isReconciled']);
        $this->actingAs($this->admin)->get(route('accounting.inventory-valuation'))->assertOk()
            ->assertSee('Inactive')->assertSee('RAW-HISTORY')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_new_items_appear_on_their_date_and_current_balances_do_not_rewrite_history(): void
    {
        $this->activate();
        $this->at('2026-10-29 12:00:00');
        $later = $this->item('NEW-HISTORY');
        $this->movement($later, '10', 'purchase_received', 3000);
        $this->post('30', '2026-10-29');
        $this->assertCount(1, $this->report()['rows']);
        $this->assertCount(2, $this->report('2026-10-29')['rows']);
        DB::table('inventory_cost_balances')->where('inventory_item_id', $this->material->id)
            ->update(['quantity_milli' => 1, 'value_cents' => 1]);
        $this->assertSame(20000, $this->report()['totalValueCents']);
        $this->assertSame(100000, $this->report()['rows']->sole()->quantity_milli);
        $this->assertTrue($this->report()['isReconciled']);
    }

    public function test_missing_snapshot_and_bypassed_uncosted_stock_are_flagged(): void
    {
        $this->activate();
        DB::table('stock_movements')->insert(['inventory_item_id' => $this->material->id,
            'movement_type' => 'stock_correction_increase', 'quantity_delta' => 1,
            'occurred_at' => now(), 'created_by' => $this->admin->id]);
        $report = $this->report();
        $this->assertSame(0, $report['differenceCents']);
        $this->assertTrue($report['rows']->sole()->has_issue);
        $this->assertFalse($report['isReconciled']);
        DB::table('inventory_cost_balances')->where('inventory_item_id', $this->material->id)->delete();
        $this->assertTrue($this->report()['rows']->sole()->has_issue);
        $this->assertNotEmpty($this->report()['issues']);
    }

    public function test_invalid_inventory_account_is_not_reported_as_reconciled(): void
    {
        $this->activate();
        $this->account('1300')->update(['type' => 'expense']);
        $report = $this->report();
        $this->assertSame(0, $report['differenceCents']);
        $this->assertFalse($report['isReconciled']);
        $this->assertStringContainsString('invalid account type', $report['issues'][0]);
    }

    public function test_fractional_quantity_and_final_rounding_residual_use_recorded_cents(): void
    {
        // Replace pre-activation fixture with 3 units worth KES 1.00.
        StockMovement::query()->delete();
        $this->movement($this->material, '3', 'opening_balance');
        $this->activate('1.00');
        $this->movement($this->material, '-0.500', 'damage');
        $report = $this->report();
        $this->assertSame(2500, $report['rows']->sole()->quantity_milli);
        $this->assertSame(83, $report['totalValueCents']);
        $this->assertSame('0.3320', $report['rows']->sole()->average_cost);
        $this->at('2026-10-29 09:00:00');
        $this->movement($this->material, '-2.500', 'damage');
        $after = $this->report('2026-10-29');
        $this->assertSame(0, $after['totalValueCents']);
        $this->assertSame(0, $after['rows']->sole()->quantity_milli);
        $this->assertNull($after['rows']->sole()->average_cost);
        $this->assertTrue($after['isReconciled']);
        $this->assertSame(83, $this->report()['totalValueCents']);
    }

    public function test_production_allocation_sale_and_sale_reversal_follow_their_dates(): void
    {
        [$run, $finished] = $this->produce();
        $this->assertSame(20000, $this->report()['totalValueCents']);
        $this->assertSame(7000, $this->report('2026-10-29')['rows']->keyBy('id')[$finished->id]->value_cents);
        $this->at('2026-10-30 09:00:00');
        $sale = SalesOrder::create(['sale_type' => SalesOrder::TYPE_WALK_IN, 'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => now(), 'total_amount' => 200, 'created_by' => $this->admin->id]);
        $sale->items()->create(['inventory_item_id' => $finished->id, 'quantity' => 2, 'unit_price' => 100, 'line_total' => 200]);
        app(SalesOrderCompletionService::class)->complete($sale, $this->admin);
        $this->at('2026-10-31 09:00:00');
        app(SalesOrderReversalService::class)->reverse($sale->fresh(), $this->admin, 'Wrong sale', now());
        foreach (['2026-10-29' => 25000, '2026-10-30' => 23600, '2026-10-31' => 25000] as $day => $value) {
            $report = $this->report($day);
            $this->assertSame($value, $report['totalValueCents']);
            $this->assertSame($value, $report['ledgerCents']);
            $this->assertTrue($report['isReconciled']);
        }
    }

    public function test_batch_reversal_does_not_remove_original_date_valuation(): void
    {
        [$run, $finished] = $this->produce();
        $this->at('2026-10-30 09:00:00');
        app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Wrong batch', now());
        $before = $this->report('2026-10-29');
        $after = $this->report('2026-10-30');
        $this->assertSame(25000, $before['totalValueCents']);
        $this->assertSame(7000, $before['rows']->keyBy('id')[$finished->id]->value_cents);
        $this->assertSame(20000, $after['totalValueCents']);
        $this->assertSame(0, $after['rows']->keyBy('id')[$finished->id]->value_cents);
        $this->assertTrue($before['isReconciled']);
        $this->assertTrue($after['isReconciled']);
    }

    public function test_report_is_read_only_and_rejects_invalid_and_future_dates(): void
    {
        $this->activate();
        $before = DB::table('inventory_cost_balances')->get()->toJson();
        $movementCount = StockMovement::count();
        $journalCount = JournalEntry::count();
        $this->actingAs($this->admin)->get(route('accounting.inventory-valuation'))->assertOk();
        $this->assertSame($before, DB::table('inventory_cost_balances')->get()->toJson());
        $this->assertSame($movementCount, StockMovement::count());
        $this->assertSame($journalCount, JournalEntry::count());
        foreach (['invalid', '2026-02-30', '28/10/2026', '2026-10-29', ['2026-10-28']] as $date) {
            $this->from(route('accounting.inventory-valuation'))
                ->get(route('accounting.inventory-valuation', ['as_of' => $date]))
                ->assertRedirect(route('accounting.inventory-valuation'))->assertSessionHasErrors('as_of');
        }
    }

    public function test_guests_and_non_admin_roles_cannot_access_report(): void
    {
        $this->get(route('accounting.inventory-valuation'))->assertRedirect(route('login'));
        foreach (['user', 'staff', 'driver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('accounting.inventory-valuation'))->assertRedirect('/dashboard')->assertSessionHas('error', 'Access denied.');
        }
    }

    private function report(string $asOf = '2026-10-28'): array
    {
        return app(InventoryValuationService::class)->report($asOf);
    }

    private function at(string $date): void
    {
        $this->travelTo(CarbonImmutable::parse($date, 'UTC'));
    }

    private function item(string $sku, string $category = 'cap'): InventoryItem
    {
        return InventoryItem::create(['sku' => $sku, 'name' => $sku, 'category' => $category, 'unit' => 'unit',
            'is_active' => true, 'is_sellable' => $category === 'finished_product']);
    }

    private function movement(InventoryItem $item, string $quantity, string $type, ?int $value = null): StockMovement
    {
        return DB::transaction(fn () => StockMovement::create(['inventory_item_id' => $item->id, 'quantity_delta' => $quantity,
            'movement_type' => $type, 'value_delta_cents' => $value, 'occurred_at' => now(), 'created_by' => $this->admin->id,
            'notes' => 'Valuation test']));
    }

    private function activate(string $value = '200'): void
    {
        app(Cost::class)->activate($this->admin, [
            'values' => InventoryItem::all()->mapWithKeys(fn ($item) => [$item->id => $item->id === $this->material->id ? $value : '0'])->all(),
            'quantities' => InventoryItem::all()->mapWithKeys(fn ($item) => [$item->id => number_format($item->stockBalance(), 3, '.', '')])->all(),
            'ledger_cents' => 0, 'offset_code' => '3100', 'notes' => 'Approved opening values', 'acknowledgement' => '1',
        ]);
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::where('code', $code)->firstOrFail();
    }

    private function post(string $amount, string $date): JournalEntry
    {
        return app(JournalPostingService::class)->post($this->admin, $date, 'Valuation test receipt', [
            ['account_id' => $this->account('1300')->id, 'debit' => $amount],
            ['account_id' => $this->account('2100')->id, 'credit' => $amount],
        ]);
    }

    private function produce(): array
    {
        $finished = $this->item('FINISHED-HISTORY', 'finished_product');
        $recipe = ProductionRecipe::create(['name' => 'History recipe', 'finished_product_id' => $finished->id, 'output_quantity' => 1, 'is_active' => true]);
        $recipe->components()->create(['inventory_item_id' => $this->material->id, 'quantity_required' => '1.000']);
        $this->activate();
        $this->at('2026-10-29 09:00:00');
        $expense = app(OperatingExpenseService::class)->record($this->admin, [
            'expense_account_id' => $this->account('5300')->id, 'payment_account_id' => $this->account('1100')->id,
            'expense_date' => '2026-10-29', 'amount' => '100', 'description' => 'Production electricity',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $run = app(ProductionRunService::class)->complete($recipe, 10, $this->admin, now(), null,
            [['expense_id' => $expense->id, 'amount' => '50', 'basis' => 'Measured production share']], (string) Str::uuid());

        return [$run, $finished];
    }
}
