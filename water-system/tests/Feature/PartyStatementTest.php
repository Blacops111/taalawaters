<?php

namespace Tests\Feature;

use App\Models\{AccountingAccount, Customer, CustomerPayment, InventoryItem, JournalEntry, PurchaseReceipt, PurchaseRequest, SalesOrder, StockMovement, Supplier, SupplierPayment, User};
use App\Services\{CustomerPaymentService, JournalPostingService, PartyStatementService, PurchaseReceiptService, SalesOrderCompletionService, SalesOrderReversalService, SupplierPaymentService, TrialBalanceService};
use Carbon\CarbonImmutable;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartyStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;
    private Supplier $supplier;
    private InventoryItem $finished;
    private InventoryItem $material;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-29 12:00:00', 'UTC'));
        $this->seed(AccountingAccountSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = Customer::create(['name' => 'Statement Customer', 'customer_type' => 'shop_retailer', 'is_active' => true]);
        $this->supplier = Supplier::create(['name' => 'Statement Supplier', 'is_active' => true]);
        $this->finished = InventoryItem::create(['sku' => 'FW-STATEMENT', 'name' => 'Statement water', 'category' => 'finished_product', 'unit' => 'unit', 'is_active' => true, 'is_sellable' => true]);
        $this->material = InventoryItem::create(['sku' => 'CAP-STATEMENT', 'name' => 'Statement cap', 'category' => 'cap', 'unit' => 'unit', 'is_active' => true]);
        StockMovement::create(['inventory_item_id' => $this->finished->id, 'movement_type' => 'opening_balance', 'quantity_delta' => 100, 'occurred_at' => '2026-09-01', 'created_by' => $this->admin->id]);
    }

    public function test_customer_statement_carries_opening_and_shows_cash_and_bank_payments(): void
    {
        $old = $this->completeBusinessSale('300.25', '2026-09-30');
        $this->receiveCustomerPayment($old, '100.10', '2026-09-30');
        $sale = $this->completeBusinessSale('200.20', '2026-10-01');
        $payment = $this->receiveCustomerPayment($sale, '50.05', '2026-10-29', '1110');
        $report = $this->statement($this->customer);
        $this->assertSame(20015, $report['openingCents']);
        $this->assertSame(20020, $report['periodDebitCents']);
        $this->assertSame(5005, $report['periodCreditCents']);
        $this->assertSame(35030, $report['closingCents']);
        $this->assertSame([40035, 35030], $report['rows']->getCollection()->pluck('balance_cents')->all());
        $this->assertSame(35030, $report['controlBalanceCents']);
        $this->assertSame([], $report['issues']);
        $this->assertSame(35030, app(TrialBalanceService::class)->report('2026-10-29')['rows']->keyBy('code')['1200']->debit_cents);
        $this->actingAs($this->admin)->get($this->statementUrl($this->customer))->assertOk()
            ->assertSee('Customer Statement')->assertSee($sale->reference)->assertSee($payment->reference)
            ->assertSee('200.15')->assertSee('350.30')->assertSee('BANK-STATEMENT');
    }

    public function test_supplier_statement_handles_partial_receipts_and_settlements(): void
    {
        $request = $this->approvedRequest();
        $old = $this->receiveGoods($request, 40, '2026-09-30');
        $this->paySupplier($old, '25.10', '2026-09-30');
        $receipt = $this->receiveGoods($request->fresh(), 60, '2026-10-01');
        $cash = $this->paySupplier($receipt, '50.05', '2026-10-15');
        $bank = $this->paySupplier($receipt, '99.95', '2026-10-29', '1110');
        $report = $this->statement($this->supplier);
        $this->assertSame(7490, $report['openingCents']);
        $this->assertSame(15000, $report['periodCreditCents']);
        $this->assertSame(15000, $report['periodDebitCents']);
        $this->assertSame(7490, $report['closingCents']);
        $this->assertSame([22490, 17485, 7490], $report['rows']->getCollection()->pluck('balance_cents')->all());
        $this->assertSame(7490, app(TrialBalanceService::class)->report('2026-10-29')['rows']->keyBy('code')['2100']->credit_cents);
        $this->actingAs($this->admin)->get($this->statementUrl($this->supplier))->assertOk()
            ->assertSee('Supplier Statement')->assertSee($receipt->reference)->assertSee($cash->reference)->assertSee($bank->reference)
            ->assertSee('74.90')->assertSee('Balance = opening + credits');
    }

    public function test_cutoffs_use_inclusive_journal_dates_not_created_or_document_dates(): void
    {
        $sale = $this->completeBusinessSale('100.01', '2026-09-30');
        $this->completeBusinessSale('20.02', '2026-10-01');
        $this->completeBusinessSale('30.03', '2026-10-29');
        $this->completeBusinessSale('40.04', '2026-10-30');
        $sale->update(['sale_at' => '2026-10-20']);
        $report = $this->statement($this->customer);
        $this->assertSame(10001, $report['openingCents']);
        $this->assertSame(5005, $report['periodDebitCents']);
        $this->assertSame(15006, $report['closingCents']);
        $this->assertSame(['2026-10-01', '2026-10-29'], $report['rows']->getCollection()->pluck('date')->all());
    }

    public function test_reversed_sale_remains_until_its_reversal_journal_date(): void
    {
        $sale = $this->completeBusinessSale('125.75', '2026-10-01');
        $reversal = app(SalesOrderReversalService::class)->reverse($sale, $this->admin, 'Incorrect sale', CarbonImmutable::parse('2026-10-20', 'UTC'));
        $before = $this->statement($this->customer, '2026-10-01', '2026-10-19');
        $after = $this->statement($this->customer);
        $this->assertSame(12575, $before['closingCents']);
        $this->assertSame(0, $after['closingCents']);
        $this->assertSame(12575, $after['periodDebitCents']);
        $this->assertSame(12575, $after['periodCreditCents']);
        $this->assertSame('Sale reversal', $after['rows']->last()->kind);
        $this->assertSame($reversal->reference, $after['rows']->last()->document_reference);
        $later = $this->statement($this->customer, '2026-10-20', '2026-10-29');
        $this->assertSame(12575, $later['openingCents']);
        $this->assertSame(0, $later['closingCents']);
    }

    public function test_generic_reversals_and_reversal_chains_inherit_original_party(): void
    {
        $sale = $this->completeBusinessSale('100', '2026-09-30');
        $first = $this->reverseJournal($sale->journalEntries()->sole(), '2026-10-01');
        $this->reverseJournal($first, '2026-10-02');
        $report = $this->statement($this->customer);
        $this->assertSame(10000, $report['openingCents']);
        $this->assertSame(10000, $report['closingCents']);
        $this->assertSame([0, 10000], $report['rows']->getCollection()->pluck('balance_cents')->all());
        $this->assertSame(['Journal reversal', 'Journal reversal'], $report['rows']->getCollection()->pluck('kind')->all());
        $this->assertSame(0, $report['unassignedCount']);
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-09-30');
        $this->reverseJournal($receipt->journalEntries()->sole(), '2026-10-01');
        $supplier = $this->statement($this->supplier);
        $this->assertSame(10000, $supplier['openingCents']);
        $this->assertSame(0, $supplier['closingCents']);
    }

    public function test_other_parties_and_walk_in_sales_do_not_leak_into_statement(): void
    {
        $mine = $this->completeBusinessSale('100', '2026-10-01');
        $other = Customer::create(['name' => 'Other customer', 'customer_type' => 'office_company', 'is_active' => true]);
        $otherSale = $this->completeBusinessSale('300', '2026-10-01', $other);
        $walkIn = SalesOrder::create(['sale_type' => 'walk_in', 'status' => 'draft', 'sale_at' => '2026-10-01', 'total_amount' => 20, 'created_by' => $this->admin->id]);
        $walkIn->items()->create(['inventory_item_id' => $this->finished->id, 'quantity' => 1, 'unit_price' => 20, 'line_total' => 20]);
        app(SalesOrderCompletionService::class)->complete($walkIn, $this->admin);
        $report = $this->statement($this->customer);
        $this->assertSame(10000, $report['closingCents']);
        $this->assertSame(40000, $report['controlBalanceCents']);
        $this->assertSame(1, $report['rows']->total());
        $this->actingAs($this->admin)->get($this->statementUrl($this->customer))->assertOk()
            ->assertSee($mine->reference)->assertDontSee($otherSale->reference)->assertDontSee('Other customer');
    }

    public function test_drafts_and_unposted_documents_do_not_invent_balances(): void
    {
        $sale = $this->completeBusinessSale('100', '2026-10-01');
        DB::table('journal_entries')->where('id', $sale->journalEntries()->sole()->id)->update(['status' => 'draft']);
        $this->assertSame(0, $this->statement($this->customer)['closingCents']);
        $this->assertSame(0, $this->statement($this->customer)['rows']->total());
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-10-01');
        DB::table('journal_entries')->where('id', $receipt->journalEntries()->sole()->id)->update(['status' => 'draft']);
        $this->assertSame(0, $this->statement($this->supplier)['closingCents']);
        $this->assertSame(0, $this->statement($this->supplier)['rows']->total());
    }

    public function test_posted_control_amounts_override_changed_document_and_payment_totals(): void
    {
        $sale = $this->completeBusinessSale('100.25', '2026-10-01');
        $payment = $this->receiveCustomerPayment($sale, '30.10', '2026-10-02');
        $sale->update(['total_amount' => '900.99', 'status' => 'draft']);
        $payment->update(['amount' => '999.99', 'payment_date' => '2026-09-01']);
        $report = $this->statement($this->customer);
        $this->assertSame(10025, $report['periodDebitCents']);
        $this->assertSame(3010, $report['periodCreditCents']);
        $this->assertSame(7015, $report['closingCents']);
        $this->assertSame('2026-10-02', $report['rows']->last()->date);
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-10-01');
        $paid = $this->paySupplier($receipt, '20.01', '2026-10-02');
        $receipt->items()->update(['unit_cost' => '99.99']);
        $paid->update(['amount' => '99.99']);
        $this->assertSame(7999, $this->statement($this->supplier)['closingCents']);
    }

    public function test_inactive_accounts_and_parties_keep_history_and_escape_user_text(): void
    {
        $sale = $this->completeBusinessSale('100', '2026-10-01');
        $this->customer->update(['name' => '<script>alert(1)</script>', 'is_active' => false]);
        $this->account('1200')->update(['is_active' => false]);
        $this->assertSame(10000, $this->statement($this->customer)['closingCents']);
        $this->assertSame([], $this->statement($this->customer)['issues']);
        $this->actingAs($this->admin)->get($this->statementUrl($this->customer))->assertOk()
            ->assertSee('Inactive')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee($sale->reference);
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-10-01');
        $this->supplier->update(['is_active' => false]);
        $this->account('2100')->update(['is_active' => false]);
        $this->actingAs($this->admin)->get($this->statementUrl($this->supplier))->assertOk()->assertSee('Inactive')->assertSee($receipt->reference);
    }

    public function test_unassigned_entries_are_visible_even_when_their_net_is_zero(): void
    {
        $this->completeBusinessSale('100', '2026-10-01');
        $manual = $this->postManualEntry('1200', '4100', '55.05', '2026-10-01');
        $this->reverseJournal($manual, '2026-10-02');
        $report = $this->statement($this->customer);
        $this->assertSame(10000, $report['closingCents']);
        $this->assertSame(2, $report['unassignedCount']);
        $this->assertSame(0, $report['unassignedBalanceCents']);
        $this->assertNotEmpty($report['issues']);
        $this->actingAs($this->admin)->get($this->statementUrl($this->customer))->assertOk()->assertSee('Control-account review required')->assertSee('2 unassigned journal entries');
        $this->postManualEntry('1300', '2100', '25.50', '2026-10-01');
        $supplier = $this->statement($this->supplier);
        $this->assertSame(0, $supplier['closingCents']);
        $this->assertSame(2550, $supplier['unassignedBalanceCents']);
        $this->assertSame(2550, $supplier['controlBalanceCents']);
    }

    public function test_conflicting_payment_and_reversal_parties_are_not_guessed(): void
    {
        $sale = $this->completeBusinessSale('100', '2026-10-01');
        $payment = $this->receiveCustomerPayment($sale, '20', '2026-10-02');
        $other = Customer::create(['name' => 'Wrong customer', 'customer_type' => 'other', 'is_active' => true]);
        $payment->update(['customer_id' => $other->id]);
        $this->assertSame(10000, $this->statement($this->customer)['closingCents']);
        $this->assertSame(0, $this->statement($other)['closingCents']);
        $this->assertSame(1, $this->statement($other)['unassignedCount']);
        $otherSale = $this->completeBusinessSale('10', '2026-10-03', $other);
        $reverse = $this->reverseJournal($sale->journalEntries()->sole(), '2026-10-04');
        DB::table('journal_entries')->where('id', $reverse->id)->update(['source_type' => $otherSale->getMorphClass(), 'source_id' => $otherSale->id]);
        $this->assertSame(2, $this->statement($this->customer)['unassignedCount']);
        $this->assertSame(1000, $this->statement($other)['closingCents']);
    }

    public function test_supplier_payment_party_mismatch_is_excluded_and_flagged(): void
    {
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-10-01');
        $payment = $this->paySupplier($receipt, '20', '2026-10-02');
        $other = Supplier::create(['name' => 'Wrong supplier', 'is_active' => true]);
        $payment->update(['supplier_id' => $other->id]);
        $report = $this->statement($this->supplier);
        $this->assertSame(10000, $report['closingCents']);
        $this->assertSame(8000, $report['controlBalanceCents']);
        $this->assertSame(-2000, $report['unassignedBalanceCents']);
        $this->assertSame(0, $this->statement($other)['closingCents']);
    }

    public function test_broken_and_cyclic_reversal_chains_do_not_loop_or_hide_coverage_gaps(): void
    {
        $sale = $this->completeBusinessSale('100', '2026-10-01');
        $original = $sale->journalEntries()->sole();
        $reverse = $this->reverseJournal($original, '2026-10-02');
        DB::table('journal_entries')->where('id', $original->id)->update(['reversal_of_id' => $reverse->id]);
        $report = $this->statement($this->customer);
        $this->assertSame(2, $report['unassignedCount']);
        $this->assertSame(0, $report['unassignedBalanceCents']);
        $this->assertSame(0, $report['rows']->total());
        DB::table('journal_entries')->where('id', $original->id)->update(['reversal_of_id' => null, 'status' => 'draft']);
        $this->assertSame(1, $this->statement($this->customer)['unassignedCount']);
    }

    public function test_empty_period_carries_balance_and_empty_party_defaults_to_month_to_date(): void
    {
        $this->completeBusinessSale('10.01', '2026-09-30');
        $report = $this->statement($this->customer);
        $this->assertSame(1001, $report['openingCents']);
        $this->assertSame(1001, $report['closingCents']);
        $this->assertSame(0, $report['rows']->total());
        $this->actingAs($this->admin)->get($this->statementUrl($this->customer))->assertOk()->assertSee('No posted activity in this period');
        $this->get(route('accounting.supplier-statements.show', $this->supplier))->assertOk()
            ->assertViewHas('dateFrom', '2026-10-01')->assertViewHas('dateTo', '2026-10-29')
            ->assertViewHas('openingCents', 0)->assertViewHas('closingCents', 0);
        $this->get(route('accounting.supplier-statements.show', ['supplier' => $this->supplier->id, 'date_to' => '2026-09-20']))->assertOk()
            ->assertViewHas('dateFrom', '2026-09-01');
    }

    public function test_negative_customer_and_supplier_balances_are_not_clamped(): void
    {
        $sale = $this->completeBusinessSale('100', '2026-10-20');
        $this->receiveCustomerPayment($sale, '25.25', '2026-10-10');
        $this->assertSame(-2525, $this->statement($this->customer, '2026-10-01', '2026-10-15')['closingCents']);
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-10-20');
        $this->paySupplier($receipt, '30.30', '2026-10-10');
        $this->assertSame(-3030, $this->statement($this->supplier, '2026-10-01', '2026-10-15')['closingCents']);
    }

    public function test_pagination_keeps_deterministic_order_running_balance_and_full_totals_across_chunks(): void
    {
        $sale = $this->completeBusinessSale('1.01', '2026-09-30');
        // Additional posted journal fixtures exercise report chunking without 501 stock operations.
        for ($i = 0; $i < 501; $i++) {
            $this->postManualEntry('1200', '4100', '0.01', '2026-10-01', $sale);
        }
        $first = $this->statement($this->customer);
        $last = $this->statement($this->customer, '2026-10-01', '2026-10-29', 21);
        $this->assertSame(501, $first['rows']->total());
        $this->assertCount(25, $first['rows']);
        $this->assertSame(101, $first['pageOpeningCents']);
        $this->assertSame(126, $first['rows']->last()->balance_cents);
        $this->assertSame(602, $first['closingCents']);
        $this->assertCount(1, $last['rows']);
        $this->assertSame(601, $last['pageOpeningCents']);
        $this->assertSame(602, $last['rows']->first()->balance_cents);
        $this->assertSame(501, $last['periodDebitCents']);
        $this->actingAs($this->admin)->get($this->statementUrl($this->customer, ['page' => 21]))->assertOk()
            ->assertSee('Balance carried forward')->assertSee('6.01')->assertSee('6.02')
            ->assertSee('date_from=2026-10-01', false);
        $beyond = $this->statement($this->customer, '2026-10-01', '2026-10-29', 22);
        $this->assertCount(0, $beyond['rows']);
        $this->assertSame(602, $beyond['closingCents']);
    }

    public function test_multiple_control_lines_sum_once_without_revenue_or_inventory_lines(): void
    {
        $sale = $this->completeBusinessSale('100', '2026-10-01');
        $journal = $sale->journalEntries()->sole();
        DB::table('journal_lines')->where('journal_entry_id', $journal->id)->where('accounting_account_id', $this->account('1200')->id)->update(['debit' => '60.25']);
        DB::table('journal_lines')->insert(['journal_entry_id' => $journal->id, 'accounting_account_id' => $this->account('1200')->id, 'debit' => '39.75', 'credit' => 0]);
        $report = $this->statement($this->customer);
        $this->assertSame(1, $report['rows']->total());
        $this->assertSame(10000, $report['closingCents']);
        $this->assertSame(10000, $report['rows']->first()->debit_cents);
    }

    public function test_directories_search_paginate_and_include_inactive_parties(): void
    {
        $this->customer->update(['name' => 'A hidden historic customer', 'is_active' => false, 'phone' => '0700000999']);
        for ($i = 0; $i < 26; $i++) {
            Customer::create(['name' => 'Z Customer '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'customer_type' => 'other', 'is_active' => true]);
        }
        $this->actingAs($this->admin)->get(route('accounting.customer-statements'))->assertOk()
            ->assertViewHas('parties', fn ($rows) => $rows->total() === 27 && $rows->count() === 25)->assertSee('Inactive');
        $this->get(route('accounting.customer-statements', ['page' => 2]))->assertOk()->assertSee('Z Customer 25');
        $this->get(route('accounting.customer-statements', ['search' => '0700000999']))->assertOk()
            ->assertViewHas('parties', fn ($rows) => $rows->total() === 1)->assertSee($this->customer->name);
        $this->supplier->update(['is_active' => false, 'email' => 'history@example.test']);
        $this->get(route('accounting.supplier-statements', ['search' => 'history@example.test']))->assertOk()->assertSee($this->supplier->name)->assertSee('Inactive');
        $this->get(route('accounting.supplier-statements', ['search' => 'unknown']))->assertOk()->assertSee('No matching accounts.');
    }

    public function test_invalid_filters_ranges_and_missing_parties_are_rejected(): void
    {
        $this->actingAs($this->admin);
        foreach ([$this->customer, $this->supplier] as $party) {
            $url = $this->statementUrl($party);
            foreach ([['date_from' => 'bad'], ['date_to' => '2026-02-30'], ['date_from' => '2026-10-30', 'date_to' => '2026-10-01'], ['page' => '0'], ['page' => ['2']]] as $query) {
                $this->from($url)->get($this->statementUrl($party, $query))->assertRedirect($url)->assertSessionHasErrors();
            }
        }
        $this->get(route('accounting.customer-statements.show', 999999))->assertNotFound();
        $this->get(route('accounting.supplier-statements.show', 999999))->assertNotFound();
    }

    public function test_guests_and_non_admins_cannot_view_directories_or_other_party_statements(): void
    {
        $urls = [route('accounting.customer-statements'), route('accounting.supplier-statements'), $this->statementUrl($this->customer), $this->statementUrl($this->supplier)];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        foreach (['user', 'staff', 'driver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ($urls as $url) {
                $this->get($url)->assertRedirect('/dashboard')->assertSessionHas('error', 'Access denied.');
            }
        }
    }

    public function test_reports_are_read_only_and_invalid_control_account_is_flagged(): void
    {
        $this->completeBusinessSale('100', '2026-10-01');
        $this->receiveGoods($this->approvedRequest(), 40, '2026-10-01');
        $before = [];
        foreach (['journal_entries', 'journal_lines', 'stock_movements', 'customer_payments', 'supplier_payments'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $this->actingAs($this->admin)->get($this->statementUrl($this->customer))->assertOk();
        $this->get($this->statementUrl($this->supplier))->assertOk();
        foreach ($before as $table => $snapshot) {
            $this->assertSame($snapshot, DB::table($table)->orderBy('id')->get()->toJson());
        }
        $this->account('1200')->update(['type' => 'expense']);
        $this->assertNotEmpty($this->statement($this->customer)['issues']);
        $this->account('2100')->update(['code' => '2199']);
        $this->assertNotEmpty($this->statement($this->supplier)['issues']);
    }

    private function statement(Customer|Supplier $party, string $from = '2026-10-01', string $to = '2026-10-29', int $page = 1): array
    {
        return app(PartyStatementService::class)->report($party, $from, $to, $page);
    }

    private function statementUrl(Customer|Supplier $party, array $query = []): string
    {
        $customer = $party instanceof Customer;
        return route($customer ? 'accounting.customer-statements.show' : 'accounting.supplier-statements.show',
            array_merge([$customer ? 'customer' : 'supplier' => $party->id, 'date_from' => '2026-10-01', 'date_to' => '2026-10-29'], $query));
    }

    private function account(string $code): AccountingAccount
    {
        return AccountingAccount::where('code', $code)->firstOrFail();
    }

    private function completeBusinessSale(string $amount, string $date, ?Customer $customer = null): SalesOrder
    {
        $sale = SalesOrder::create(['customer_id' => ($customer ?? $this->customer)->id, 'sale_type' => 'business', 'status' => 'draft', 'sale_at' => $date, 'total_amount' => $amount, 'created_by' => $this->admin->id]);
        $sale->items()->create(['inventory_item_id' => $this->finished->id, 'quantity' => 1, 'unit_price' => $amount, 'line_total' => $amount]);
        return app(SalesOrderCompletionService::class)->complete($sale, $this->admin);
    }

    private function receiveCustomerPayment(SalesOrder $sale, string $amount, string $date, string $account = '1100'): CustomerPayment
    {
        return app(CustomerPaymentService::class)->record($sale, $this->admin, ['payment_account_id' => $this->account($account)->id,
            'payment_date' => $date, 'amount' => $amount, 'idempotency_key' => (string) Str::uuid(), 'external_reference' => 'BANK-STATEMENT']);
    }

    private function approvedRequest(): PurchaseRequest
    {
        $request = PurchaseRequest::create(['reference' => 'PREQ-'.Str::random(10), 'status' => 'approved', 'supplier_id' => $this->supplier->id,
            'requested_by' => $this->admin->id, 'requested_at' => '2026-09-01', 'approved_by' => $this->admin->id, 'approved_at' => '2026-09-01']);
        $request->items()->create(['inventory_item_id' => $this->material->id, 'quantity' => 100, 'approved_unit_cost' => '2.50']);
        return $request;
    }

    private function receiveGoods(PurchaseRequest $request, int $quantity, string $date): PurchaseReceipt
    {
        return app(PurchaseReceiptService::class)->receive($request, $this->admin, CarbonImmutable::parse($date, 'UTC'), [$request->items()->sole()->id => $quantity], 'SUPPLIER-DN');
    }

    private function paySupplier(PurchaseReceipt $receipt, string $amount, string $date, string $account = '1100'): SupplierPayment
    {
        return app(SupplierPaymentService::class)->record($receipt, $this->account($account), $this->admin, $date, $amount, 'PAYMENT-REFERENCE');
    }

    private function postManualEntry(string $debit, string $credit, string $amount, string $date, ?SalesOrder $source = null): JournalEntry
    {
        return app(JournalPostingService::class)->post($this->admin, $date, 'Statement test entry', [
            ['account_id' => $this->account($debit)->id, 'debit' => $amount],
            ['account_id' => $this->account($credit)->id, 'credit' => $amount],
        ], $source);
    }

    private function reverseJournal(JournalEntry $entry, string $date): JournalEntry
    {
        return app(JournalPostingService::class)->post($this->admin, $date, 'Statement journal reversal', $entry->lines->map(fn ($line) => [
            'account_id' => $line->accounting_account_id, 'debit' => $line->credit, 'credit' => $line->debit,
        ])->all(), null, $entry);
    }
}
