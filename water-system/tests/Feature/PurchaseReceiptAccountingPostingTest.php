<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseAccountingService;
use App\Services\PurchaseReceiptService;
use Carbon\CarbonImmutable;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchaseReceiptAccountingPostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingAccountSeeder::class)->run();
    }

    public function test_partial_goods_receipt_debits_inventory_and_credits_accounts_payable(): void
    {
        [$admin, $purchaseRequest, $requestItem] = $this->approvedRequest(
            500,
            '1.25',
        );

        $receipt = app(PurchaseReceiptService::class)->receive(
            $purchaseRequest,
            $admin,
            CarbonImmutable::parse('2026-09-21 09:00:00', 'UTC'),
            [$requestItem->id => 300],
            'SUP-DN-001',
        );

        $entry = $receipt->journalEntries()->firstOrFail();
        $inventory = AccountingAccount::query()->where('code', '1300')->firstOrFail();
        $payable = AccountingAccount::query()->where('code', '2100')->firstOrFail();

        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertSame(PurchaseReceipt::class, $entry->source_type);
        $this->assertSame($receipt->id, $entry->source_id);
        $this->assertSame('2026-09-21', $entry->entry_date->toDateString());

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $inventory->id,
            'debit' => 375,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $payable->id,
            'debit' => 0,
            'credit' => 375,
        ]);

        $this->assertSame(
            PurchaseRequest::STATUS_PARTIALLY_RECEIVED,
            $purchaseRequest->fresh()->status
        );

        $this->actingAs($admin)
            ->get(route('purchase-requests.receipts.create', $purchaseRequest))
            ->assertOk()
            ->assertSee('KES 1.25')
            ->assertSee('KES 375.00');
    }

    public function test_each_partial_goods_receipt_posts_only_the_value_received_now(): void
    {
        [$admin, $purchaseRequest, $requestItem] = $this->approvedRequest(
            500,
            '1.25',
        );

        $firstReceipt = app(PurchaseReceiptService::class)->receive(
            $purchaseRequest,
            $admin,
            CarbonImmutable::parse('2026-09-21 09:00:00', 'UTC'),
            [$requestItem->id => 300],
        );

        $secondReceipt = app(PurchaseReceiptService::class)->receive(
            $purchaseRequest->fresh(),
            $admin,
            CarbonImmutable::parse('2026-09-21 11:00:00', 'UTC'),
            [$requestItem->id => 200],
        );

        $firstJournal = $firstReceipt->journalEntries()->firstOrFail();
        $secondJournal = $secondReceipt->journalEntries()->firstOrFail();

        $this->assertSame(375.0, (float) $firstJournal->lines->sum('debit'));
        $this->assertSame(375.0, (float) $firstJournal->lines->sum('credit'));
        $this->assertSame(250.0, (float) $secondJournal->lines->sum('debit'));
        $this->assertSame(250.0, (float) $secondJournal->lines->sum('credit'));
        $this->assertSame(2, JournalEntry::count());
        $this->assertSame(
            PurchaseRequest::STATUS_RECEIVED,
            $purchaseRequest->fresh()->status
        );
    }

    public function test_goods_receipt_rolls_back_when_approved_unit_cost_is_missing(): void
    {
        [$admin, $purchaseRequest, $requestItem, $item] = $this->approvedRequest(
            500,
            null,
        );

        try {
            app(PurchaseReceiptService::class)->receive(
                $purchaseRequest,
                $admin,
                CarbonImmutable::parse('2026-09-21 09:00:00', 'UTC'),
                [$requestItem->id => 100],
            );

            $this->fail('Expected missing approved unit cost validation error was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('accounting', $exception->errors());
        }

        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertDatabaseCount('purchase_receipt_items', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseMissing('stock_movements', [
            'inventory_item_id' => $item->id,
            'movement_type' => 'purchase_received',
        ]);
        $this->assertSame(0.0, $item->stockBalance());
        $this->assertSame(
            PurchaseRequest::STATUS_APPROVED,
            $purchaseRequest->fresh()->status
        );
    }

    public function test_goods_receipt_accounting_post_is_idempotent(): void
    {
        [$admin, $purchaseRequest, $requestItem] = $this->approvedRequest(
            100,
            '2.50',
        );

        $receipt = app(PurchaseReceiptService::class)->receive(
            $purchaseRequest,
            $admin,
            CarbonImmutable::parse('2026-09-21 09:00:00', 'UTC'),
            [$requestItem->id => 100],
        );

        $first = $receipt->journalEntries()->firstOrFail();
        $second = app(PurchaseAccountingService::class)->postReceipt(
            $receipt->fresh(),
            $admin,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            1,
            JournalEntry::query()
                ->where('source_type', PurchaseReceipt::class)
                ->where('source_id', $receipt->id)
                ->count()
        );
    }

    private function approvedRequest(
        float $quantity,
        ?string $unitCost,
    ): array {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Accounting Goods Supplier',
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'sku' => 'CAP-ACCOUNTING-GRN',
            'name' => 'Accounting Goods Receipt Cap',
            'category' => 'cap',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-ACCOUNTING-GRN',
            'status' => PurchaseRequest::STATUS_APPROVED,
            'supplier_id' => $supplier->id,
            'requested_by' => $admin->id,
            'requested_at' => now()->subDay(),
            'submitted_by' => $admin->id,
            'submitted_at' => now()->subHours(4),
            'approved_by' => $admin->id,
            'approved_at' => now()->subHours(2),
        ]);

        $requestItem = $purchaseRequest->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => $quantity,
            'approved_unit_cost' => $unitCost,
        ]);

        return [$admin, $purchaseRequest, $requestItem, $item];
    }
}
