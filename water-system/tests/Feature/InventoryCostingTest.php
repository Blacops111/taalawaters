<?php

namespace Tests\Feature;

use App\Models\{AccountingAccount, Customer, InventoryCostingSetup, InventoryItem, JournalEntry, ProductionRecipe, ProductionRun, PurchaseRequest, SalesOrder, StockMovement, Supplier, User};
use App\Services\{CustomerPaymentService, InventoryCostingService as Cost, ProductionRunReversalService, ProductionRunService, ProfitLossService, PurchaseReceiptService, SalesOrderCompletionService, SalesOrderReversalService, TrialBalanceService};
use Carbon\CarbonImmutable;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryCostingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-27 08:00:00', 'UTC'));
        $this->seed(AccountingAccountSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_opening_preview_activation_and_reconciliation_preserve_stock(): void
    {
        $item = $this->item('cap', 100);
        $data = $this->setupData([$item->id => '200.00']);
        $this->actingAs($this->admin)->get(route('accounting.inventory-costing'))->assertOk()->assertSee('Review opening inventory');
        $this->post(route('accounting.inventory-costing.preview'), $data)->assertOk()->assertSee('200.00')->assertSee('Confirm opening inventory valuation');
        $this->assertFalse(InventoryCostingSetup::find(1)->is_active);
        $this->post(route('accounting.inventory-costing.activate'), $data)->assertRedirect(route('accounting.inventory-costing'))->assertSessionHasNoErrors();
        $this->assertTrue(InventoryCostingSetup::find(1)->is_active);
        $this->assertSame(20000, app(Cost::class)->ledgerCents());
        $this->assertSame(1, StockMovement::count());
        $this->assertNull(StockMovement::sole()->value_delta_cents);
        $this->assertBalance($item, 100000, 20000);
        $this->get(route('accounting.inventory-costing'))->assertOk()->assertSee('200.00');
        $this->post(route('accounting.inventory-costing.activate'), $data)->assertSessionHasErrors('costing');
        $this->assertSame(1, JournalEntry::count());
    }

    public function test_existing_inventory_ledger_is_reconciled_by_difference_not_double_posted(): void
    {
        $item = $this->item('cap', 100);
        app(Cost::class)->postPair($this->admin, now(), 'Existing inventory', '1300', '3100', 15000, $item);
        $this->activate([$item->id => '200.00']);
        $this->assertSame(20000, app(Cost::class)->ledgerCents());
        $this->assertEquals(50, JournalEntry::latest('id')->first()->lines()->sum('debit'));
    }

    public function test_setup_rejects_stale_quantities_without_partial_activation(): void
    {
        $item = $this->item('cap', 100);
        $data = $this->setupData([$item->id => '200.00']);
        $this->movement($item, 1, 'stock_received');
        $this->reject(fn () => app(Cost::class)->activate($this->admin, $data));
        $this->assertFalse(InventoryCostingSetup::find(1)->is_active);
        $this->assertDatabaseCount('inventory_cost_balances', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_setup_missing_value_invalid_precision_and_nonzero_empty_value_are_rejected(): void
    {
        $item = $this->item('cap', 0);
        foreach (['1.00', '1.001', '-1', 'abc'] as $amount) {
            $this->reject(fn () => app(Cost::class)->activate($this->admin, $this->setupData([$item->id => $amount])));
        }
        $this->assertFalse(InventoryCostingSetup::find(1)->is_active);
        $this->assertDatabaseCount('inventory_cost_balances', 0);
    }

    public function test_setup_failure_to_post_rolls_back_balances(): void
    {
        $item = $this->item('cap', 10);
        AccountingAccount::where('code', '3100')->update(['is_active' => false]);
        $this->reject(fn () => $this->activate([$item->id => '10.00']));
        $this->assertDatabaseCount('inventory_cost_balances', 0);
        $this->assertFalse(InventoryCostingSetup::find(1)->is_active);
    }

    public function test_partial_receipt_uses_approved_snapshot_and_updates_weighted_average(): void
    {
        $item = $this->item('cap', 100);
        $this->activate([$item->id => '200.00']);
        [$request, $line] = $this->request($item, 200, '3.00');
        $receipt = app(PurchaseReceiptService::class)->receive($request, $this->admin, now()->toImmutable(), [$line->id => 100]);
        $this->assertBalance($item, 200000, 50000);
        $this->assertSame('3.00', $receipt->items->sole()->unit_cost);
        $this->assertSame(30000, $receipt->items->sole()->receipt_value_cents);
        $this->assertSame(50000, app(Cost::class)->ledgerCents());
        $line->update(['approved_unit_cost' => '9.00']);
        $this->assertSame('3.00', $receipt->items()->sole()->unit_cost);
        $this->actingAs($this->admin)->get(route('purchase-requests.receipts.create', $request))->assertOk()->assertSee('KES 300.00');
    }

    public function test_production_transfers_material_value_without_another_inventory_debit(): void
    {
        [$recipe, $water, $cap, $finished] = $this->recipe();
        $this->activate([$water->id => '0', $cap->id => '250', $finished->id => '0']);
        $run = app(ProductionRunService::class)->complete($recipe, 60, $this->admin, now());
        $this->assertBalance($cap, 40000, 10000);
        $this->assertBalance($finished, 60000, 15000);
        $this->assertSame(0, (int) $run->stockMovements()->sum('value_delta_cents'));
        $this->assertSame(25000, app(Cost::class)->ledgerCents());
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_sale_posts_material_cogs_in_one_journal_and_flows_into_profit_loss(): void
    {
        $item = $this->item('finished_product', 10);
        $this->activate([$item->id => '25.00']);
        $sale = $this->sale($item, 2);
        $entry = $sale->journalEntries()->sole();
        $this->assertCount(4, $entry->lines);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $entry->id, 'accounting_account_id' => $this->account('5100'), 'debit' => 5, 'credit' => 0]);
        $this->assertDatabaseHas('journal_lines', ['journal_entry_id' => $entry->id, 'accounting_account_id' => $this->account('1300'), 'debit' => 0, 'credit' => 5]);
        $this->assertBalance($item, 8000, 2000);
        $report = app(ProfitLossService::class)->report('2026-09-27', '2026-09-27');
        $this->assertSame(20000, $report['revenueCents']);
        $this->assertSame(500, $report['expenseCents']);
        $this->assertSame(19500, $report['netProfitCents']);
        $this->assertTrue(app(TrialBalanceService::class)->report('2026-09-27')['isBalanced']);
    }

    public function test_customer_payment_still_uses_receivable_from_single_costed_sale_journal(): void
    {
        $item = $this->item('finished_product', 10);
        $this->activate([$item->id => '25.00']);
        $sale = $this->sale($item, 2, true);
        $this->assertEquals(200, app(CustomerPaymentService::class)->outstandingSales()->sole()->outstanding_amount);
        app(CustomerPaymentService::class)->record($sale, $this->admin, [
            'payment_account_id' => $this->account('1100'), 'payment_date' => '2026-09-27', 'amount' => '50',
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->assertEquals(150, app(CustomerPaymentService::class)->outstandingSales()->sole()->outstanding_amount);
        $this->assertSame(1, $sale->journalEntries()->count());
        $this->assertBalance($item, 8000, 2000);
    }

    public function test_sale_reversal_restores_original_cost_after_average_changes(): void
    {
        $item = $this->item('finished_product', 10);
        $this->activate([$item->id => '25.00']);
        $sale = $this->sale($item, 2);
        // Simulate another approved receipt at a different cost (same transaction path as receiving).
        [$request, $line] = $this->request($item, 10, '5.00');
        app(PurchaseReceiptService::class)->receive($request, $this->admin, now()->toImmutable(), [$line->id => 10]);
        $reversal = app(SalesOrderReversalService::class)->reverse($sale, $this->admin, 'Wrong sale', now());
        $this->assertBalance($item, 20000, 7500);
        $this->assertSame(500, $reversal->stockMovements()->sole()->value_delta_cents);
        $this->assertSame(0, app(ProfitLossService::class)->report('2026-09-27', '2026-09-27')['netProfitCents']);
        $this->reject(fn () => app(SalesOrderReversalService::class)->reverse($sale, $this->admin, 'Again', now()));
        $this->assertBalance($item, 20000, 7500);
    }

    public function test_production_reversal_restores_exact_values_when_no_later_activity_exists(): void
    {
        [$recipe, $water, $cap, $finished] = $this->recipe();
        $this->activate([$water->id => '0', $cap->id => '250', $finished->id => '0']);
        $run = app(ProductionRunService::class)->complete($recipe, 60, $this->admin, now());
        app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Incorrect batch', now());
        $this->assertBalance($cap, 100000, 25000);
        $this->assertBalance($finished, 0, 0);
        $this->assertBalance($water, 100000, 0);
        $this->assertSame(25000, app(Cost::class)->ledgerCents());
    }

    public function test_production_reversal_is_blocked_after_later_material_or_output_activity(): void
    {
        [$recipe, $water, $cap, $finished] = $this->recipe();
        $this->activate([$water->id => '0', $cap->id => '250', $finished->id => '0']);
        $run = app(ProductionRunService::class)->complete($recipe, 60, $this->admin, now());
        $this->movement($cap, 10, 'stock_correction_increase');
        $count = StockMovement::count();
        $this->reject(fn () => app(ProductionRunReversalService::class)->reverse($run, $this->admin, 'Incorrect batch', now()));
        $this->assertSame($count, StockMovement::count());
        $this->assertBalance($finished, 60000, 15000);
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_missing_cogs_account_rolls_back_sale_stock_and_value(): void
    {
        $item = $this->item('finished_product', 10);
        $this->activate([$item->id => '25']);
        AccountingAccount::where('code', '5100')->update(['is_active' => false]);
        $this->reject(fn () => $this->sale($item, 2));
        $this->assertBalance($item, 10000, 2500);
        $this->assertSame('draft', SalesOrder::sole()->status);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_backdated_movements_are_rejected_and_rolled_back(): void
    {
        $item = $this->item('finished_product', 10);
        $this->activate([$item->id => '25']);
        $this->reject(fn () => $this->movement($item, -1, 'damage', '2026-09-27 07:59:59'));
        $this->assertBalance($item, 10000, 2500);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_adjustments_post_inventory_difference_at_average_cost(): void
    {
        $item = $this->item('cap', 10);
        $this->activate([$item->id => '25']);
        $this->movement($item, -2, 'damage');
        $this->assertBalance($item, 8000, 2000);
        $this->assertSame(500, app(ProfitLossService::class)->report('2026-09-27', '2026-09-27')['expenseCents']);
        $this->movement($item, 1, 'stock_correction_increase');
        $this->assertBalance($item, 9000, 2250);
        $this->assertSame(2250, app(Cost::class)->ledgerCents());
    }

    public function test_unknown_receipts_are_blocked_after_activation(): void
    {
        $item = $this->item('cap', 10);
        $this->activate([$item->id => '25']);
        $this->reject(fn () => $this->movement($item, 2, 'stock_received'));
        $this->assertBalance($item, 10000, 2500);
    }

    public function test_zero_cost_borehole_extraction_preserves_zero_acquisition_value(): void
    {
        $water = $this->item('raw_water', 10);
        $this->activate([$water->id => '0']);
        $this->movement($water, '2.125', 'borehole_extraction');
        $this->assertBalance($water, 12125, 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_final_issue_takes_rounding_residual_without_negative_value(): void
    {
        $item = $this->item('finished_product', 3);
        $this->activate([$item->id => '0.10']);
        $this->sale($item, 1);
        $this->assertBalance($item, 2000, 7);
        $this->sale($item, 1);
        $this->assertBalance($item, 1000, 3);
        $this->sale($item, 1);
        $this->assertBalance($item, 0, 0);
        $this->assertSame(10, (int) -StockMovement::where('movement_type', 'sale')->sum('value_delta_cents'));
        $this->assertSame(0, app(Cost::class)->ledgerCents());
    }

    public function test_historical_sale_cannot_be_reversed_after_costing_activation(): void
    {
        $item = $this->item('finished_product', 10);
        $sale = $this->sale($item, 2);
        $this->activate([$item->id => '20']);
        $this->reject(fn () => app(SalesOrderReversalService::class)->reverse($sale, $this->admin, 'Old sale', now()));
        $this->assertSame('completed', $sale->fresh()->status);
        $this->assertBalance($item, 8000, 2000);
    }

    public function test_guests_and_non_admins_cannot_view_preview_or_activate(): void
    {
        $item = $this->item('cap', 10);
        // Valid input ensures authorization, rather than input validation, blocks activation.
        $data = $this->setupData([$item->id => '25.00']);
        $this->get(route('accounting.inventory-costing'))->assertRedirect(route('login'));
        $this->post(route('accounting.inventory-costing.preview'), $data)->assertRedirect(route('login'));
        $this->post(route('accounting.inventory-costing.activate'), $data)->assertRedirect(route('login'));
        foreach (['user', 'staff', 'driver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('accounting.inventory-costing'))
                ->assertRedirect('/dashboard')->assertSessionHas('error', 'Access denied.');
            $this->post(route('accounting.inventory-costing.preview'), $data)
                ->assertRedirect('/dashboard')->assertSessionHas('error', 'Access denied.');
            $this->post(route('accounting.inventory-costing.activate'), $data)
                ->assertRedirect('/dashboard')->assertSessionHas('error', 'Access denied.');
        }
        $setup = InventoryCostingSetup::findOrFail(1);
        $this->assertFalse($setup->is_active);
        $this->assertNull($setup->activated_at);
        $this->assertNull($setup->approved_by);
        $this->assertDatabaseCount('inventory_cost_balances', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(10000, Cost::quantityMilli($item->id));
    }

    public function test_exact_decimal_math_handles_fractional_quantities_and_large_products(): void
    {
        $this->assertSame(1, Cost::receiptValue('0.500', '0.01'));
        $this->assertSame(123456789012, Cost::receiptValue('1000000.000', '1234.56789012'));
        $this->assertSame(33333333333333, Cost::issueValue(1000000000000, 3000000000000, 99999999999999));
    }

    public function test_receipt_production_sale_pipeline_keeps_subledger_equal_to_inventory(): void
    {
        [$recipe, $water, $cap, $finished] = $this->recipe();
        $this->activate([$water->id => '0', $cap->id => '200', $finished->id => '0']);
        [$request, $line] = $this->request($cap, 100, '3.00');
        app(PurchaseReceiptService::class)->receive($request, $this->admin, now()->toImmutable(), [$line->id => 100]);
        app(ProductionRunService::class)->complete($recipe, 60, $this->admin, now());
        $this->sale($finished, 20);
        $this->assertBalance($cap, 140000, 35000);
        $this->assertBalance($finished, 40000, 10000);
        $this->assertSame(45000, app(Cost::class)->ledgerCents());
        $this->assertSame(45000, (int) DB::table('inventory_cost_balances')->sum('value_cents'));
        $report = app(ProfitLossService::class)->report('2026-09-27', '2026-09-27');
        $this->assertSame(5000, $report['expenseCents']);
        $this->assertSame(195000, $report['netProfitCents']);
    }

    public function test_stale_ledger_future_stock_and_missing_opening_items_are_rejected(): void
    {
        $item = $this->item('cap', 10);
        $data = $this->setupData([$item->id => '25']);
        app(Cost::class)->postPair($this->admin, now(), 'Later journal', '1300', '3100', 100, $item);
        $this->reject(fn () => app(Cost::class)->activate($this->admin, $data));
        $this->assertDatabaseCount('inventory_cost_balances', 0);
        $this->movement($item, 1, 'stock_received', '2026-09-28 08:00:00');
        $this->reject(fn () => $this->activate([$item->id => '25']));
        $this->assertFalse(InventoryCostingSetup::find(1)->is_active);
        $this->assertDatabaseCount('inventory_cost_balances', 0);
    }

    public function test_costed_movements_are_immutable_and_failed_adjustment_rolls_back(): void
    {
        $item = $this->item('cap', 10);
        $this->activate([$item->id => '25']);
        $movement = $this->movement($item, -1, 'damage');
        try {
            $movement->update(['quantity_delta' => -2]);
            $this->fail('Costed stock must be immutable.');
        } catch (\LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
        AccountingAccount::where('code', '5500')->update(['is_active' => false]);
        $this->reject(fn () => $this->movement($item, -1, 'damage'));
        $this->assertBalance($item, 9000, 2250);
        $this->assertSame(2250, app(Cost::class)->ledgerCents());
    }

    public function test_failed_production_and_purchase_accounting_roll_back_all_costs(): void
    {
        [$recipe, $water, $cap, $finished] = $this->recipe();
        $this->activate([$water->id => '0', $cap->id => '200', $finished->id => '0']);
        $this->reject(fn () => app(ProductionRunService::class)->complete($recipe, 101, $this->admin, now()));
        $this->assertDatabaseCount('production_runs', 0);
        AccountingAccount::where('code', '2100')->update(['is_active' => false]);
        [$request, $line] = $this->request($cap, 100, '3.00');
        $this->reject(fn () => app(PurchaseReceiptService::class)->receive($request, $this->admin, now()->toImmutable(), [$line->id => 100]));
        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertBalance($cap, 100000, 20000);
        $this->assertSame('approved', $request->fresh()->status);
    }

    private function item(string $category, int $quantity): InventoryItem
    {
        $item = InventoryItem::create(['sku' => 'TEST-'.Str::random(8), 'name' => 'Costing '.$category, 'category' => $category,
            'unit' => $category === 'raw_water' ? 'litre' : 'unit', 'is_active' => true, 'is_sellable' => $category === 'finished_product']);
        if ($quantity > 0) {
            $this->movement($item, $quantity, 'opening_balance');
        }
        return $item;
    }

    private function movement(InventoryItem $item, mixed $quantity, string $type, ?string $at = null): StockMovement
    {
        return DB::transaction(fn () => StockMovement::create(['inventory_item_id' => $item->id, 'quantity_delta' => $quantity,
            'movement_type' => $type, 'created_by' => $this->admin->id, 'occurred_at' => $at ?? now(), 'notes' => 'Costing test']));
    }

    private function setupData(array $values): array
    {
        return ['values' => $values, 'quantities' => InventoryItem::all()->mapWithKeys(fn ($item) => [$item->id => number_format($item->stockBalance(), 3, '.', '')])->all(),
            'ledger_cents' => app(Cost::class)->ledgerCents(), 'offset_code' => '3100', 'notes' => 'Approved opening stock', 'acknowledgement' => '1'];
    }

    private function activate(array $values): void
    {
        app(Cost::class)->activate($this->admin, $this->setupData($values));
    }

    private function assertBalance(InventoryItem $item, int $quantity, int $value): void
    {
        $row = DB::table('inventory_cost_balances')->where('inventory_item_id', $item->id)->first();
        $this->assertSame($quantity, (int) $row->quantity_milli);
        $this->assertSame($value, (int) $row->value_cents);
        $this->assertSame($quantity, Cost::quantityMilli($item->id));
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

    private function account(string $code): int
    {
        return AccountingAccount::where('code', $code)->firstOrFail()->id;
    }

    private function request(InventoryItem $item, int $quantity, string $price): array
    {
        $supplier = Supplier::create(['name' => 'Cost supplier', 'is_active' => true]);
        $request = PurchaseRequest::create(['reference' => 'PREQ-'.Str::random(8), 'status' => PurchaseRequest::STATUS_APPROVED,
            'supplier_id' => $supplier->id, 'requested_by' => $this->admin->id, 'requested_at' => now(),
            'approved_by' => $this->admin->id, 'approved_at' => now()]);
        return [$request, $request->items()->create(['inventory_item_id' => $item->id, 'quantity' => $quantity, 'approved_unit_cost' => $price])];
    }

    private function recipe(): array
    {
        $water = $this->item('raw_water', 100);
        $cap = $this->item('cap', 100);
        $finished = $this->item('finished_product', 0);
        $recipe = ProductionRecipe::create(['name' => 'Cost recipe', 'finished_product_id' => $finished->id, 'output_quantity' => 1, 'is_active' => true]);
        $recipe->components()->createMany([['inventory_item_id' => $water->id, 'quantity_required' => '0.5'], ['inventory_item_id' => $cap->id, 'quantity_required' => '1']]);
        return [$recipe, $water, $cap, $finished];
    }

    private function sale(InventoryItem $item, int $quantity, bool $business = false): SalesOrder
    {
        $customer = $business ? Customer::create(['name' => 'Cost customer', 'customer_type' => 'supermarket', 'is_active' => true]) : null;
        $sale = SalesOrder::create(['sale_type' => $business ? SalesOrder::TYPE_BUSINESS : SalesOrder::TYPE_WALK_IN,
            'customer_id' => $customer?->id, 'status' => SalesOrder::STATUS_DRAFT, 'sale_at' => now(), 'total_amount' => $quantity * 100, 'created_by' => $this->admin->id]);
        $sale->items()->create(['inventory_item_id' => $item->id, 'quantity' => $quantity, 'unit_price' => 100, 'line_total' => $quantity * 100]);
        return app(SalesOrderCompletionService::class)->complete($sale, $this->admin);
    }
}
