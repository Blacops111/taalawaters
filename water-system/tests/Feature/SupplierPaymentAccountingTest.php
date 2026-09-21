<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\PurchaseReceiptService;
use Carbon\CarbonImmutable;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccountingAccountSeeder::class)->run();
    }

    public function test_admin_can_record_partial_supplier_payment_from_bank_and_post_accounts_payable(): void
    {
        [$admin, $receipt] = $this->createReceipt(300, '1.25');

        $bank = AccountingAccount::query()
            ->where('code', '1110')
            ->firstOrFail();

        $payable = AccountingAccount::query()
            ->where('code', '2100')
            ->firstOrFail();

        $this->actingAs($admin)
            ->get(route('accounting.supplier-payments'))
            ->assertOk()
            ->assertSee($receipt->reference)
            ->assertSee('KES 375.00')
            ->assertSee('1110')
            ->assertSee('Bank Account');

        $this->actingAs($admin)
            ->post(route('accounting.supplier-payments.store'), [
                'purchase_receipt_id' => $receipt->id,
                'payment_account_id' => $bank->id,
                'payment_date' => '2026-09-21',
                'amount' => 200,
                'external_reference' => 'BANK-TXN-001',
                'notes' => 'Part payment to supplier.',
            ])
            ->assertRedirect(route('accounting.supplier-payments'))
            ->assertSessionHas('success');

        $payment = SupplierPayment::firstOrFail();
        $entry = $payment->journalEntries()->firstOrFail();

        $this->assertSame('SPAY-00000001', $payment->reference);
        $this->assertSame('200.00', $payment->amount);
        $this->assertSame($receipt->id, $payment->purchase_receipt_id);
        $this->assertSame($bank->id, $payment->payment_account_id);
        $this->assertSame('BANK-TXN-001', $payment->external_reference);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $payable->id,
            'debit' => 200,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $bank->id,
            'debit' => 0,
            'credit' => 200,
        ]);

        $this->actingAs($admin)
            ->get(route('accounting.supplier-payments'))
            ->assertOk()
            ->assertSee('KES 175.00')
            ->assertSee('BANK-TXN-001')
            ->assertSee('SPAY-00000001');
    }

    public function test_supplier_payment_cannot_exceed_receipt_outstanding_balance(): void
    {
        [$admin, $receipt] = $this->createReceipt(100, '2.50');

        $cash = AccountingAccount::query()
            ->where('code', '1100')
            ->firstOrFail();

        $journalCountBefore = JournalEntry::count();

        $this->actingAs($admin)
            ->from(route('accounting.supplier-payments'))
            ->post(route('accounting.supplier-payments.store'), [
                'purchase_receipt_id' => $receipt->id,
                'payment_account_id' => $cash->id,
                'payment_date' => '2026-09-21',
                'amount' => 250.01,
            ])
            ->assertRedirect(route('accounting.supplier-payments'))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertSame($journalCountBefore, JournalEntry::count());
    }

    public function test_supplier_receipt_can_be_settled_with_multiple_payments_without_overpaying(): void
    {
        [$admin, $receipt] = $this->createReceipt(100, '2.50');

        $cash = AccountingAccount::query()
            ->where('code', '1100')
            ->firstOrFail();

        $bank = AccountingAccount::query()
            ->where('code', '1110')
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('accounting.supplier-payments.store'), [
                'purchase_receipt_id' => $receipt->id,
                'payment_account_id' => $cash->id,
                'payment_date' => '2026-09-21',
                'amount' => 100,
            ])
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->post(route('accounting.supplier-payments.store'), [
                'purchase_receipt_id' => $receipt->id,
                'payment_account_id' => $bank->id,
                'payment_date' => '2026-09-21',
                'amount' => 150,
            ])
            ->assertSessionHas('success');

        $this->assertSame(2, SupplierPayment::count());
        $this->assertSame(250.0, (float) SupplierPayment::sum('amount'));

        $this->actingAs($admin)
            ->get(route('accounting.supplier-payments'))
            ->assertOk()
            ->assertDontSee(
                $receipt->reference.' — Accounting Goods Supplier — Outstanding',
                false
            );
    }

    public function test_non_admin_cannot_access_supplier_payments(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('accounting.supplier-payments'))
            ->assertRedirect('/dashboard');
    }

    private function createReceipt(
        float $quantity,
        string $unitCost,
    ): array {
        $admin = User::factory()->create(['role' => 'admin']);

        $supplier = Supplier::create([
            'name' => 'Accounting Goods Supplier',
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'sku' => 'CAP-SPAY',
            'name' => 'Supplier Payment Test Cap',
            'category' => 'cap',
            'unit' => 'unit',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $purchaseRequest = PurchaseRequest::create([
            'reference' => 'PREQ-SPAY-001',
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

        $receipt = app(PurchaseReceiptService::class)->receive(
            $purchaseRequest,
            $admin,
            CarbonImmutable::parse('2026-09-21 09:00:00', 'UTC'),
            [$requestItem->id => $quantity],
            'SUP-DN-SPAY',
        );

        return [$admin, $receipt];
    }
}
