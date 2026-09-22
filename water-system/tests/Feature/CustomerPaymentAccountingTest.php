<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CustomerPaymentService;
use App\Services\JournalPostingService;
use App\Services\SalesOrderCompletionService;
use App\Services\SalesOrderReversalService;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerPaymentAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccountingAccountSeeder::class);
    }

    public function test_partial_payment_posts_cash_and_receivable_without_changing_stock_or_revenue(): void
    {
        [$admin, $sale] = $this->sale();
        $movements = StockMovement::count();
        $stock = StockMovement::sum('quantity_delta');
        $original = $sale->journalEntries()->with('lines')->firstOrFail()->toArray();

        $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale, '100.25'))
            ->assertRedirect(route('accounting.customer-payments'))->assertSessionHasNoErrors();

        $payment = CustomerPayment::sole();
        $entry = $payment->journalEntries()->sole();
        $this->assertSame('CPAY-00000001', $payment->reference);
        $this->assertSame($sale->customer_id, $payment->customer_id);
        $this->assertSame($admin->id, $payment->created_by);
        $this->assertSame('BANK-REF-001', $payment->external_reference);
        $this->assertSame('2026-09-22', $entry->entry_date->toDateString());
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertCount(2, $entry->lines);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id, 'accounting_account_id' => $this->account('1100')->id,
            'debit' => 100.25, 'credit' => 0,
        ]);
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $entry->id, 'accounting_account_id' => $this->account('1200')->id,
            'debit' => 0, 'credit' => 100.25,
        ]);
        $this->assertSame($movements, StockMovement::count());
        $this->assertEquals($stock, StockMovement::sum('quantity_delta'));
        $this->assertSame($original, $sale->journalEntries()->with('lines')->firstOrFail()->toArray());
        $this->assertEquals(199.75, app(CustomerPaymentService::class)->outstandingSales()->sole()->outstanding_amount);
        $this->actingAs($admin)->get(route('accounting.customer-payments'))->assertOk()
            ->assertSee('199.75')->assertSee($payment->reference);
        $this->get(route('accounting.journals', ['search' => $payment->reference]))->assertOk()
            ->assertSee($payment->reference)->assertSee('Accounts Receivable');
    }

    public function test_cash_and_bank_payments_can_settle_sale_and_further_payment_is_blocked(): void
    {
        [$admin, $sale] = $this->sale();
        $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale, '100.25'))
            ->assertSessionHasNoErrors();
        $this->post(route('accounting.customer-payments.store'), $this->payload($sale, '199.75', '1110'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_payments', 2);
        $this->assertEquals(300, CustomerPayment::sum('amount'));
        $this->assertSame(0, app(CustomerPaymentService::class)->outstandingSales()->count());
        $bankPayment = CustomerPayment::latest('id')->firstOrFail();
        $this->assertDatabaseHas('journal_lines', [
            'journal_entry_id' => $bankPayment->journalEntries()->sole()->id,
            'accounting_account_id' => $this->account('1110')->id, 'debit' => 199.75, 'credit' => 0,
        ]);
        $this->post(route('accounting.customer-payments.store'), $this->payload($sale, '0.01'))
            ->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('customer_payments', 2);
        $this->assertDatabaseCount('journal_entries', 3);
        $this->get(route('accounting.customer-payments'))->assertOk()->assertSee('No outstanding business sales.');
    }

    public function test_overpayment_is_rejected_without_a_payment_or_journal(): void
    {
        [$admin, $sale] = $this->sale();
        $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale, '300.01'))
            ->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_repeated_form_submission_posts_once_even_after_full_settlement(): void
    {
        [$admin, $sale] = $this->sale();
        $data = $this->payload($sale, '300.00');
        $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('accounting.customer-payments.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_payments', 1);
        $this->assertDatabaseCount('journal_entries', 2);
        $data['amount'] = '100.00';
        $this->post(route('accounting.customer-payments.store'), $data)->assertSessionHasErrors('idempotency_key');
        $this->assertDatabaseCount('customer_payments', 1);
    }

    public function test_draft_reversed_and_walk_in_sales_cannot_receive_customer_payments(): void
    {
        [$admin, $sale] = $this->sale();
        foreach ([SalesOrder::STATUS_DRAFT, SalesOrder::STATUS_REVERSED] as $status) {
            $sale->update(['status' => $status]);
            $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale))
                ->assertSessionHasErrors('sales_order_id');
        }
        $sale->update(['status' => SalesOrder::STATUS_COMPLETED, 'sale_type' => SalesOrder::TYPE_WALK_IN]);
        $this->post(route('accounting.customer-payments.store'), $this->payload($sale))
            ->assertSessionHasErrors('sales_order_id');
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame(0, app(CustomerPaymentService::class)->outstandingSales()->count());
    }

    public function test_missing_or_ambiguous_sales_journals_are_rejected(): void
    {
        [$admin, $sale] = $this->sale();
        $original = $sale->journalEntries()->sole();
        // Simulate a historic sale whose journal source link is missing.
        JournalEntry::whereKey($original->id)->update(['source_id' => null]);
        $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale))
            ->assertSessionHasErrors('accounting');
        $this->assertSame(0, app(CustomerPaymentService::class)->outstandingSales()->count());
        JournalEntry::whereKey($original->id)->update(['source_id' => $sale->id]);
        app(JournalPostingService::class)->post($admin, '2026-09-22', 'Duplicate test journal', [
            ['account_id' => $this->account('1200')->id, 'debit' => 300],
            ['account_id' => $this->account('4100')->id, 'credit' => 300],
        ], $sale);
        $this->post(route('accounting.customer-payments.store'), $this->payload($sale))->assertSessionHasErrors('accounting');
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertSame(0, app(CustomerPaymentService::class)->outstandingSales()->count());
    }

    public function test_reversed_sales_journal_is_not_payable_even_if_sale_status_is_stale(): void
    {
        [$admin, $sale] = $this->sale();
        $original = $sale->journalEntries()->sole();
        app(JournalPostingService::class)->post($admin, '2026-09-22', 'Journal reversal', [
            ['account_id' => $this->account('1200')->id, 'credit' => 300],
            ['account_id' => $this->account('4100')->id, 'debit' => 300],
        ], null, $original);
        $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale))
            ->assertSessionHasErrors('accounting');
        $this->assertSame(0, app(CustomerPaymentService::class)->outstandingSales()->count());
        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_invalid_or_inactive_accounts_are_rejected(): void
    {
        [$admin, $sale] = $this->sale();
        $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale, '100', '4100'))
            ->assertSessionHasErrors('payment_account_id');
        $this->account('1100')->update(['is_active' => false]);
        $this->post(route('accounting.customer-payments.store'), $this->payload($sale))->assertSessionHasErrors('payment_account_id');
        $this->account('1200')->update(['is_active' => false]);
        $this->post(route('accounting.customer-payments.store'), $this->payload($sale, '100', '1110'))
            ->assertSessionHasErrors('accounting');
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_payment_rolls_back_if_journal_posting_fails(): void
    {
        [$admin, $sale] = $this->sale();
        $this->mock(JournalPostingService::class)->shouldReceive('post')->once()
            ->andThrow(ValidationException::withMessages(['accounting' => 'Posting unavailable.']));
        $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale))
            ->assertSessionHasErrors('accounting');
        $this->assertDatabaseCount('customer_payments', 0);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertEquals(300, app(CustomerPaymentService::class)->outstandingSales()->sole()->outstanding_amount);
    }

    public function test_sale_with_payment_cannot_be_reversed(): void
    {
        [$admin, $sale] = $this->sale();
        $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale))->assertSessionHasNoErrors();
        $stock = StockMovement::sum('quantity_delta');
        try {
            app(SalesOrderReversalService::class)->reverse($sale, $admin, 'Incorrect sale');
            $this->fail('A paid sale must not be reversed without a refund workflow.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sales_order', $e->errors());
        }
        $this->assertSame(SalesOrder::STATUS_COMPLETED, $sale->fresh()->status);
        $this->assertDatabaseCount('sales_order_reversals', 0);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertEquals($stock, StockMovement::sum('quantity_delta'));
    }

    public function test_non_admin_and_guest_cannot_read_or_record_payments(): void
    {
        [$admin, $sale] = $this->sale();
        $this->get(route('accounting.customer-payments'))->assertRedirect(route('login'));
        $this->post(route('accounting.customer-payments.store'), $this->payload($sale))->assertRedirect(route('login'));
        foreach (['user', 'driver'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('accounting.customer-payments'))->assertRedirect('/dashboard');
            $this->post(route('accounting.customer-payments.store'), $this->payload($sale))->assertRedirect('/dashboard');
        }
        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_invalid_amounts_are_not_rounded_into_payments(): void
    {
        [$admin, $sale] = $this->sale();
        foreach (['0', '-1', '0.001', '100.999', '1e2', '10000000000000', 'abc'] as $amount) {
            $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale, $amount))
                ->assertSessionHasErrors('amount');
        }
        $this->assertDatabaseCount('customer_payments', 0);
    }

    public function test_outstanding_list_uses_posted_amount_and_includes_inactive_customers_debts(): void
    {
        [$admin, $sale] = $this->sale();
        $sale->update(['total_amount' => 1]);
        $sale->customer->update(['is_active' => false]);
        $this->assertEquals(300, app(CustomerPaymentService::class)->outstandingSales()->sole()->outstanding_amount);
        $this->actingAs($admin)->post(route('accounting.customer-payments.store'), $this->payload($sale, '300'))
            ->assertSessionHasNoErrors();
        $this->assertSame(0, app(CustomerPaymentService::class)->outstandingSales()->count());
    }

    public function test_outstanding_sales_remain_reachable_across_pages(): void
    {
        [$admin, $sale] = $this->sale();
        // More than one page of historic receivables must not disappear behind a fixed limit.
        for ($i = 0; $i < 25; $i++) {
            $other = $sale->replicate(['reference']);
            $other->reference = 'SALE-PAGE-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $other->save();
            app(\App\Services\SalesAccountingService::class)->postCompletedSale($other, $admin);
        }
        $this->actingAs($admin)->get(route('accounting.customer-payments'))
            ->assertOk()->assertViewHas('outstandingSales', fn ($sales) => $sales->total() === 26 && $sales->count() === 25);
        $this->get(route('accounting.customer-payments', ['sales_page' => 2]))
            ->assertOk()->assertSee('SALE-PAGE-024')
            ->assertViewHas('outstandingSales', fn ($sales) => $sales->count() === 1);
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::where('code', $code)->firstOrFail();
    }

    private function payload(SalesOrder $sale, string $amount = '100', string $accountCode = '1100'): array
    {
        return [
            'sales_order_id' => $sale->id, 'payment_account_id' => $this->account($accountCode)->id,
            'payment_date' => '2026-09-22', 'amount' => $amount, 'idempotency_key' => (string) Str::uuid(),
            'external_reference' => 'BANK-REF-001', 'notes' => 'Customer settlement',
        ];
    }

    private function sale(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = Customer::create(['name' => 'Customer Payment Test Business', 'customer_type' => 'supermarket', 'is_active' => true]);
        $product = InventoryItem::create([
            'sku' => 'FW-CPAY', 'name' => 'Customer Payment Water', 'category' => 'finished_product',
            'unit' => 'unit', 'reorder_level' => 0, 'retail_price' => 100, 'wholesale_price' => 100,
            'is_sellable' => true, 'is_active' => true,
        ]);
        StockMovement::create([
            'inventory_item_id' => $product->id, 'movement_type' => 'opening_balance',
            'quantity_delta' => 10, 'created_by' => $admin->id, 'occurred_at' => now(), 'reference' => 'OPEN-CPAY',
        ]);
        $sale = SalesOrder::create([
            'customer_id' => $customer->id, 'sale_type' => SalesOrder::TYPE_BUSINESS,
            'status' => SalesOrder::STATUS_DRAFT, 'sale_at' => '2026-09-22 09:00:00',
            'total_amount' => 300, 'created_by' => $admin->id,
        ]);
        $sale->items()->create(['inventory_item_id' => $product->id, 'quantity' => 3, 'unit_price' => 100, 'line_total' => 300]);

        return [$admin, app(SalesOrderCompletionService::class)->complete($sale, $admin)];
    }
}
