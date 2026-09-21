<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\SalesOrder;
use App\Models\SalesOrderReversal;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SalesOrderCompletionService;
use App\Services\SalesOrderReversalService;
use Carbon\CarbonImmutable;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesOrderAccountingReversalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingAccountSeeder::class)->run();
    }

    public function test_walk_in_sale_reversal_posts_exact_opposite_journal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);

        $order = $this->createDraftWithItem(
            $admin,
            $product,
            2,
            75,
            SalesOrder::TYPE_WALK_IN,
        );

        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);
        $originalJournal = $completed->journalEntries()->firstOrFail();

        $reversal = app(SalesOrderReversalService::class)->reverse(
            $completed,
            $admin,
            'Walk-in accounting reversal test.',
            CarbonImmutable::parse('2026-09-21 12:00:00', 'UTC'),
        );

        $reversalJournal = $reversal->journalEntries()->firstOrFail();
        $cash = AccountingAccount::query()->where('code', '1100')->firstOrFail();
        $revenue = AccountingAccount::query()->where('code', '4100')->firstOrFail();

        $this->assertSame(JournalEntry::STATUS_POSTED, $reversalJournal->status);
        $this->assertSame($originalJournal->id, $reversalJournal->reversal_of_id);
        $this->assertSame('2026-09-21', $reversalJournal->entry_date->toDateString());
        $this->assertSame(SalesOrderReversal::class, $reversalJournal->source_type);
        $this->assertSame($reversal->id, $reversalJournal->source_id);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $reversalJournal->id,
            'accounting_account_id' => $revenue->id,
            'debit' => 150,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $reversalJournal->id,
            'accounting_account_id' => $cash->id,
            'debit' => 0,
            'credit' => 150,
        ]);

        $this->assertSame(
            SalesOrder::STATUS_REVERSED,
            $completed->fresh()->status
        );
        $this->assertSame(10.0, $this->stockBalance($product));
    }

    public function test_business_sale_reversal_credits_accounts_receivable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Accounting Reversal Business',
            'customer_type' => 'supermarket',
            'is_active' => true,
        ]);

        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);

        $order = $this->createDraftWithItem(
            $admin,
            $product,
            3,
            60,
            SalesOrder::TYPE_BUSINESS,
            $customer->id,
        );

        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);

        $reversal = app(SalesOrderReversalService::class)->reverse(
            $completed,
            $admin,
            'Business accounting reversal test.',
        );

        $entry = $reversal->journalEntries()->firstOrFail();
        $receivable = AccountingAccount::query()->where('code', '1200')->firstOrFail();
        $revenue = AccountingAccount::query()->where('code', '4100')->firstOrFail();

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $revenue->id,
            'debit' => 180,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $receivable->id,
            'debit' => 0,
            'credit' => 180,
        ]);
    }

    public function test_sale_reversal_rolls_back_when_original_accounting_journal_is_missing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);

        $order = $this->createDraftWithItem(
            $admin,
            $product,
            2,
            75,
            SalesOrder::TYPE_WALK_IN,
        );

        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);
        $originalJournal = $completed->journalEntries()->firstOrFail();

        DB::table('journal_lines')
            ->where('journal_entry_id', $originalJournal->id)
            ->delete();

        DB::table('journal_entries')
            ->where('id', $originalJournal->id)
            ->delete();

        try {
            app(SalesOrderReversalService::class)->reverse(
                $completed,
                $admin,
                'Must fail without original accounting audit.',
            );

            $this->fail('Expected missing-accounting-journal validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('accounting', $exception->errors());
        }

        $this->assertSame(
            SalesOrder::STATUS_COMPLETED,
            $completed->fresh()->status
        );
        $this->assertDatabaseCount('sales_order_reversals', 0);
        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'sale_reversal_restore',
            'source_type' => SalesOrderReversal::class,
        ]);
        $this->assertSame(8.0, $this->stockBalance($product));
    }

    private function createFinishedProduct(): InventoryItem
    {
        return InventoryItem::create([
            'sku' => 'FW-ACCOUNTING-REVERSAL',
            'name' => 'Finished Water Accounting Reversal',
            'category' => 'finished_product',
            'unit' => 'unit',
            'reorder_level' => 0,
            'retail_price' => 75,
            'wholesale_price' => 60,
            'is_sellable' => true,
            'is_active' => true,
        ]);
    }

    private function addStock(InventoryItem $product, int $quantity, User $user): void
    {
        StockMovement::create([
            'inventory_item_id' => $product->id,
            'movement_type' => 'opening_balance',
            'quantity_delta' => $quantity,
            'created_by' => $user->id,
            'occurred_at' => '2026-09-21 07:00:00',
            'reference' => 'OPEN-ACCOUNTING-REVERSAL',
        ]);
    }

    private function createDraftWithItem(
        User $user,
        InventoryItem $product,
        int $quantity,
        int $unitPrice,
        string $saleType,
        ?int $customerId = null,
    ): SalesOrder {
        $order = SalesOrder::create([
            'customer_id' => $customerId,
            'sale_type' => $saleType,
            'reference' => null,
            'status' => SalesOrder::STATUS_DRAFT,
            'sale_at' => '2026-09-21 09:00:00',
            'total_amount' => $quantity * $unitPrice,
            'created_by' => $user->id,
        ]);

        $order->items()->create([
            'inventory_item_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
        ]);

        return $order;
    }

    private function stockBalance(InventoryItem $product): float
    {
        return (float) StockMovement::query()
            ->where('inventory_item_id', $product->id)
            ->sum('quantity_delta');
    }
}
