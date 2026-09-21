<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SalesAccountingService;
use App\Services\SalesOrderCompletionService;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesOrderAccountingPostingTest extends TestCase
{
    use RefreshDatabase;

    public function test_walk_in_sale_posts_cash_and_sales_revenue_journal(): void
    {
        app(AccountingAccountSeeder::class)->run();

        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);
        $order = $this->createDraftWithItem($admin, $product, 2, 75);

        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);

        $entry = JournalEntry::query()
            ->where('source_type', SalesOrder::class)
            ->where('source_id', $completed->id)
            ->firstOrFail();

        $cash = AccountingAccount::query()->where('code', '1100')->firstOrFail();
        $revenue = AccountingAccount::query()->where('code', '4100')->firstOrFail();

        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertSame('2026-09-21', $entry->entry_date->toDateString());
        $this->assertSame($admin->id, $entry->posted_by);
        $this->assertSame($completed->id, $entry->source->id);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $cash->id,
            'debit' => 150,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $revenue->id,
            'debit' => 0,
            'credit' => 150,
        ]);
    }

    public function test_business_sale_posts_accounts_receivable_and_sales_revenue(): void
    {
        app(AccountingAccountSeeder::class)->run();

        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create([
            'name' => 'Accounting Business Customer',
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

        $entry = $completed->journalEntries()->firstOrFail();
        $receivable = AccountingAccount::query()->where('code', '1200')->firstOrFail();
        $revenue = AccountingAccount::query()->where('code', '4100')->firstOrFail();

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $receivable->id,
            'debit' => 180,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $revenue->id,
            'debit' => 0,
            'credit' => 180,
        ]);
    }

    public function test_sale_completion_rolls_back_when_required_accounting_accounts_are_missing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);
        $order = $this->createDraftWithItem($admin, $product, 2, 75);

        try {
            app(SalesOrderCompletionService::class)->complete($order, $admin);
            $this->fail('Expected accounting validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('accounting', $exception->errors());
        }

        $this->assertSame(SalesOrder::STATUS_DRAFT, $order->fresh()->status);
        $this->assertNull($order->fresh()->reference);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseMissing('stock_movements', [
            'movement_type' => 'sale',
            'source_type' => SalesOrder::class,
            'source_id' => $order->id,
        ]);
        $this->assertSame(10.0, $this->stockBalance($product));
    }

    public function test_sales_accounting_post_is_idempotent_for_completed_sale(): void
    {
        app(AccountingAccountSeeder::class)->run();

        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->createFinishedProduct();
        $this->addStock($product, 10, $admin);
        $order = $this->createDraftWithItem($admin, $product, 1, 75);

        $completed = app(SalesOrderCompletionService::class)->complete($order, $admin);

        $first = $completed->journalEntries()->firstOrFail();
        $second = app(SalesAccountingService::class)->postCompletedSale(
            $completed->fresh(),
            $admin,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            JournalEntry::query()
                ->where('source_type', SalesOrder::class)
                ->where('source_id', $completed->id)
                ->count()
        );
    }

    private function createFinishedProduct(): InventoryItem
    {
        return InventoryItem::create([
            'sku' => 'FW-ACCOUNTING-SALE',
            'name' => 'Finished Water Accounting Sale',
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
            'reference' => 'OPEN-ACCOUNTING-SALE',
        ]);
    }

    private function createDraftWithItem(
        User $user,
        InventoryItem $product,
        int $quantity,
        int $unitPrice,
        string $saleType = SalesOrder::TYPE_WALK_IN,
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
