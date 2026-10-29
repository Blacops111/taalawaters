<?php

namespace Tests\Feature;

use App\Models\{AccountingAccount, Customer, CustomerPayment, InventoryItem, JournalEntry, PurchaseReceipt, PurchaseRequest, SalesOrder, StockMovement, Supplier, SupplierPayment, User};
use App\Services\{PartyAgeingService, PaymentTermsService, SalesOrderDraftService, CustomerPaymentService, JournalPostingService, PartyStatementService, PurchaseReceiptService, SalesOrderCompletionService, SalesOrderReversalService, SupplierPaymentService, TrialBalanceService};
use Carbon\CarbonImmutable;
use Database\Seeders\AccountingAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartyAgeingTest extends TestCase
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


    public function test_customer_terms_can_be_saved_changed_cleared_and_zero_is_valid(): void
    {
        $this->actingAs($this->admin)->post(route('customers.store'), [
            'name' => 'Terms customer', 'customer_type' => 'other', 'is_active' => 1, 'payment_terms_days' => 30,
        ])->assertSessionHasNoErrors();
        $customer = Customer::where('name', 'Terms customer')->sole();
        $this->assertSame(30, $customer->payment_terms_days);
        foreach ([0, 3650, null] as $days) {
            $this->put(route('customers.update', $customer), ['name' => $customer->name, 'customer_type' => 'other',
                'is_active' => 1, 'payment_terms_days' => $days])->assertSessionHasNoErrors();
            $this->assertSame($days, $customer->fresh()->payment_terms_days);
        }
        foreach ([-1, '1.5', 3651, 'tomorrow', [30]] as $days) {
            $this->put(route('customers.update', $customer), ['name' => $customer->name, 'customer_type' => 'other',
                'is_active' => 1, 'payment_terms_days' => $days])->assertSessionHasErrors('payment_terms_days');
        }
        $this->get(route('customers.edit', $customer))->assertOk()->assertSee('Payment terms (calendar days)');
    }

    public function test_supplier_terms_are_validated_and_editable(): void
    {
        $this->actingAs($this->admin)->post(route('suppliers.store'), ['name' => 'Terms supplier', 'is_active' => 1,
            'payment_terms_days' => 14])->assertSessionHasNoErrors();
        $supplier = Supplier::where('name', 'Terms supplier')->sole();
        $this->assertSame(14, $supplier->payment_terms_days);
        foreach ([0, null] as $days) {
            $this->put(route('suppliers.update', $supplier), ['name' => $supplier->name, 'is_active' => 1,
                'payment_terms_days' => $days])->assertSessionHasNoErrors();
            $this->assertSame($days, $supplier->fresh()->payment_terms_days);
        }
        $this->put(route('suppliers.update', $supplier), ['name' => $supplier->name, 'payment_terms_days' => -1])->assertSessionHasErrors('payment_terms_days');
        $this->get(route('suppliers.edit', $supplier))->assertOk()->assertSee('Payment terms (calendar days)');
    }

    public function test_sales_snapshot_terms_on_draft_creation_and_allow_explicit_override(): void
    {
        $this->customer->update(['payment_terms_days' => 30]);
        $service = app(SalesOrderDraftService::class);
        $sale = $service->createDraft('business', $this->customer, $this->admin, CarbonImmutable::parse('2026-09-30'));
        $this->assertSame('2026-10-30', $sale->due_date->toDateString());
        $this->customer->update(['payment_terms_days' => 7]);
        $sale->items()->create(['inventory_item_id' => $this->finished->id, 'quantity' => 1, 'unit_price' => 100, 'line_total' => 100]);
        $sale = app(SalesOrderCompletionService::class)->complete($sale, $this->admin);
        $this->assertSame('2026-10-30', $sale->due_date->toDateString());
        $override = $service->createDraft('business', $this->customer, $this->admin, CarbonImmutable::parse('2026-10-01'), null, '2026-10-20');
        $this->assertSame('2026-10-20', $override->due_date->toDateString());
        $this->actingAs($this->admin)->get(route('sales-orders.show', $sale))->assertOk()->assertSee('2026-10-30');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $sale->update(['due_date' => '2026-11-01']);
    }

    public function test_unknown_terms_stay_unknown_and_walk_in_due_dates_are_rejected(): void
    {
        $sale = app(SalesOrderDraftService::class)->createDraft('business', $this->customer, $this->admin);
        $this->assertNull($sale->due_date);
        $this->customer->update(['payment_terms_days' => 0]);
        $this->assertNull($sale->fresh()->due_date);
        $today = app(SalesOrderDraftService::class)->createDraft('business', $this->customer, $this->admin);
        $this->assertSame('2026-10-29', $today->due_date->toDateString());
        $this->actingAs($this->admin)->post(route('sales-orders.store'), ['sale_type' => 'walk_in',
            'sale_at' => '2026-10-01', 'due_date' => '2026-10-10'])->assertSessionHasErrors('due_date');
        foreach (['2026-02-30', '2026-09-30'] as $date) {
            $this->post(route('sales-orders.store'), ['sale_type' => 'business', 'customer_id' => $this->customer->id,
                'sale_at' => '2026-10-01', 'due_date' => $date])->assertSessionHasErrors('due_date');
        }
    }

    public function test_each_partial_receipt_snapshots_supplier_terms_or_override(): void
    {
        $this->supplier->update(['payment_terms_days' => 14]);
        $request = $this->approvedRequest();
        $first = $this->receiveGoods($request, 20, '2026-10-01');
        $this->supplier->update(['payment_terms_days' => 30]);
        $second = $this->receiveGoods($request, 20, '2026-10-02');
        $third = app(PurchaseReceiptService::class)->receive($request, $this->admin, CarbonImmutable::parse('2026-10-03'),
            [$request->items()->sole()->id => 20], null, null, '2026-10-20');
        $this->assertSame('2026-10-15', $first->fresh()->due_date->toDateString());
        $this->assertSame('2026-11-01', $second->due_date->toDateString());
        $this->assertSame('2026-10-20', $third->due_date->toDateString());
        $this->actingAs($this->admin)->get(route('purchase-requests.receipts.create', $request))->assertOk()->assertSee('2026-10-15');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $first->update(['due_date' => '2026-11-20']);
    }

    public function test_invalid_receipt_due_date_rolls_back_receipt_stock_and_journal(): void
    {
        $request = $this->approvedRequest();
        $before = [PurchaseReceipt::count(), StockMovement::count(), JournalEntry::count()];
        $this->actingAs($this->admin)->post(route('purchase-requests.receipts.store', $request), [
            'received_at' => '2026-10-03', 'quantities' => [$request->items()->sole()->id => 20], 'due_date' => '2026-10-02',
        ])->assertSessionHasErrors('due_date');
        $this->assertSame($before, [PurchaseReceipt::count(), StockMovement::count(), JournalEntry::count()]);
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_due_dates_use_utc_calendar_days_and_service_validates_overrides(): void
    {
        $service = app(PaymentTermsService::class);
        $this->assertSame('2026-10-31', $service->dueDate(CarbonImmutable::parse('2026-11-01 01:00:00', 'Africa/Nairobi'), 0, null));
        $this->assertSame('2028-03-01', $service->dueDate(CarbonImmutable::parse('2028-02-28'), 2, null));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->dueDate(CarbonImmutable::parse('2026-10-01'), null, '2026-02-30');
    }

    public function test_every_ageing_boundary_unknown_date_and_due_today_are_classified_exactly(): void
    {
        $expected = [];
        foreach ([[-1, 'current'], [0, 'current'], [1, '1_30'], [30, '1_30'], [31, '31_60'],
            [60, '31_60'], [61, '61_90'], [90, '61_90'], [91, '91_plus'], [null, 'unknown']] as [$days, $bucket]) {
            $due = $days === null ? null : CarbonImmutable::parse('2026-10-29')->subDays($days)->toDateString();
            $sale = $this->ledgerSale('10.01', $due);
            $expected[$sale->id] = $bucket;
        }
        $report = $this->ageing();
        $this->assertSame(10010, $report['outstandingCents']);
        $this->assertSame(10010, array_sum($report['totals']));
        foreach ($report['rows'] as $row) {
            $this->assertSame($expected[$row->document_id], $row->bucket);
        }
        $this->assertSame(2002, $report['totals']['current']);
        $this->assertSame(1001, $report['totals']['unknown']);
        $this->assertSame(10010, $report['controlBalanceCents']);
        $this->assertSame([], $report['issues']);
    }

    public function test_partial_customer_payments_use_journal_date_and_amount_and_match_statement(): void
    {
        $sale = $this->ledgerSale('100.25', '2026-10-01', '2026-10-01');
        $payment = $this->receiveCustomerPayment($sale, '30.10', '2026-10-15');
        $this->assertSame(10025, $this->ageing(true, '2026-10-14')['netCents']);
        $this->assertSame(7015, $this->ageing(true, '2026-10-15')['netCents']);
        $payment->update(['amount' => '99.99', 'payment_date' => '2026-10-01']);
        $sale->update(['total_amount' => 999]);
        $report = $this->ageing();
        $this->assertSame(7015, $report['totals']['1_30']);
        $this->assertSame(app(PartyStatementService::class)->report($this->customer, '2026-01-01', '2026-10-29')['closingCents'], $report['netCents']);
        $this->assertSame(10025, $this->ageing(true, '2026-10-14')['netCents']);
    }

    public function test_supplier_partial_receipts_and_payments_are_aged_per_receipt(): void
    {
        $this->supplier->update(['payment_terms_days' => 14]);
        $request = $this->approvedRequest();
        $first = $this->receiveGoods($request, 40, '2026-10-01');
        $second = $this->receiveGoods($request, 40, '2026-10-20');
        $this->paySupplier($first, '25.25', '2026-10-29');
        $before = $this->ageing(false, '2026-10-28');
        $this->assertSame(20000, $before['netCents']);
        $report = $this->ageing(false);
        $this->assertSame(7475, $report['totals']['1_30']);
        $this->assertSame(10000, $report['totals']['current']);
        $this->assertSame(17475, $report['netCents']);
        $this->assertSame(2, $report['rows']->total());
        $this->assertSame(app(PartyStatementService::class)->report($this->supplier, '2026-01-01', '2026-10-29')['closingCents'], $report['netCents']);
    }

    public function test_settled_documents_disappear_only_from_settlement_date(): void
    {
        $sale = $this->ledgerSale('10', '2026-10-01', '2026-10-01');
        $this->receiveCustomerPayment($sale, '10', '2026-10-20');
        $this->assertSame(1, $this->ageing(true, '2026-10-19')['rows']->total());
        $this->assertSame(0, $this->ageing(true, '2026-10-20')['rows']->total());
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-10-01');
        $this->paySupplier($receipt, '100', '2026-10-20');
        $this->assertSame(10000, $this->ageing(false, '2026-10-19')['totals']['unknown']);
        $this->assertSame(0, $this->ageing(false, '2026-10-20')['netCents']);
    }

    public function test_payment_reversal_chain_restores_and_resettles_same_document_on_own_dates(): void
    {
        $sale = $this->ledgerSale('100', '2026-10-01', '2026-10-01');
        $payment = $this->receiveCustomerPayment($sale, '30', '2026-10-10');
        $reversal = $this->reverseJournal($payment->journalEntries()->sole(), '2026-10-20');
        $this->reverseJournal($reversal, '2026-10-25');
        $this->assertSame(7000, $this->ageing(true, '2026-10-19')['netCents']);
        $this->assertSame(10000, $this->ageing(true, '2026-10-20')['netCents']);
        $this->assertSame(7000, $this->ageing()['netCents']);
        $this->assertSame(1, $this->ageing()['rows']->total());
    }

    public function test_sale_and_supplier_journal_reversal_preserve_history_until_reversal_date(): void
    {
        $sale = $this->completeBusinessSale('100', '2026-10-01');
        app(SalesOrderReversalService::class)->reverse($sale, $this->admin, 'Incorrect sale');
        $this->assertSame(10000, $this->ageing(true, '2026-10-28')['netCents']);
        $this->assertSame(0, $this->ageing()['netCents']);
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-10-01');
        $this->reverseJournal($receipt->journalEntries()->sole(), '2026-10-20');
        $this->assertSame(10000, $this->ageing(false, '2026-10-19')['netCents']);
        $this->assertSame(0, $this->ageing(false)['netCents']);
    }

    public function test_credits_are_signed_separate_and_never_offset_other_overdue_documents(): void
    {
        $sale = $this->ledgerSale('100', '2026-10-21', '2026-10-20');
        $this->receiveCustomerPayment($sale, '25.25', '2026-10-10');
        $this->ledgerSale('50', '2026-10-01', '2026-10-01');
        $report = $this->ageing(true, '2026-10-15');
        $this->assertSame(-2525, $report['creditCents']);
        $this->assertSame(5000, $report['totals']['1_30']);
        $this->assertSame(2475, $report['netCents']);
        $this->assertSame('credit', $report['rows']->first()->bucket);
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-10-20');
        $this->paySupplier($receipt, '30.30', '2026-10-10');
        $this->assertSame(-3030, $this->ageing(false, '2026-10-15')['creditCents']);
        $this->assertSame(0, $this->ageing(false, '2026-10-15')['outstandingCents']);
    }

    public function test_drafts_future_entries_and_walk_in_sales_do_not_create_receivables(): void
    {
        $draft = $this->ledgerSale('100', null);
        DB::table('journal_entries')->where('id', $draft->journalEntries()->sole()->id)->update(['status' => 'draft']);
        $this->ledgerSale('20', null, '2026-11-01');
        $walk = SalesOrder::create(['sale_type' => 'walk_in', 'status' => 'completed', 'sale_at' => '2026-10-01', 'created_by' => $this->admin->id]);
        $this->postManualEntry('1100', '4100', '50', '2026-10-01', $walk);
        $this->assertSame(0, $this->ageing()['netCents']);
        $this->assertSame(0, $this->ageing()['rows']->total());
    }

    public function test_conflicting_document_links_even_for_same_customer_stay_unassigned(): void
    {
        $one = $this->ledgerSale('100', null);
        $two = $this->ledgerSale('20', null);
        $reverse = $this->reverseJournal($one->journalEntries()->sole(), '2026-10-20');
        DB::table('journal_entries')->where('id', $reverse->id)->update(['source_type' => $two->getMorphClass(), 'source_id' => $two->id]);
        $report = $this->ageing();
        $this->assertSame(12000, $report['netCents']);
        $this->assertSame(-10000, $report['unassignedBalanceCents']);
        $this->assertSame(2000, $report['controlBalanceCents']);
        $this->assertSame(1, $report['unassignedCount']);
        $this->assertNotEmpty($report['issues']);
    }

    public function test_manual_zero_net_unassigned_entries_and_invalid_accounts_are_flagged(): void
    {
        $entry = $this->postManualEntry('1200', '4100', '10', '2026-10-01');
        $this->reverseJournal($entry, '2026-10-02');
        $report = $this->ageing();
        $this->assertSame(2, $report['unassignedCount']);
        $this->assertSame(0, $report['unassignedBalanceCents']);
        $this->assertNotEmpty($report['issues']);
        $this->actingAs($this->admin)->get(route('accounting.customer-ageing'))->assertOk()->assertSee('Control-account review required');
        $this->account('2100')->update(['type' => 'expense']);
        $this->assertNotEmpty($this->ageing(false)['issues']);
    }

    public function test_pagination_and_filter_keep_full_totals_and_global_control_coverage(): void
    {
        for ($i = 0; $i < 26; $i++) $this->ledgerSale('1.01', '2026-10-01');
        $other = Customer::create(['name' => 'Other', 'customer_type' => 'other', 'is_active' => true]);
        $sale = $this->ledgerSale('20', null, '2026-10-01', $other);
        $report = app(PartyAgeingService::class)->ageing(true, '2026-10-29', $this->customer->id, 2);
        $this->assertCount(1, $report['rows']);
        $this->assertSame(26, $report['rows']->total());
        $this->assertSame(2626, $report['netCents']);
        $this->assertSame(4626, $report['controlBalanceCents']);
        $this->actingAs($this->admin)->get(route('accounting.customer-ageing', ['party_id' => $this->customer->id, 'as_of' => '2026-10-29', 'page' => 2]))
            ->assertOk()->assertSee('26.26')->assertDontSee($sale->reference)->assertSee('as_of=2026-10-29', false);
    }

    public function test_inactive_accounts_and_parties_remain_visible_and_html_is_escaped(): void
    {
        $this->ledgerSale('100', null);
        $this->customer->update(['is_active' => false, 'name' => '<script>alert(1)</script>']);
        $this->account('1200')->update(['is_active' => false]);
        $this->actingAs($this->admin)->get(route('accounting.customer-ageing'))->assertOk()
            ->assertSee('Inactive')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame(10000, $this->ageing()['totals']['unknown']);
        $this->assertSame([], $this->ageing()['issues']);
    }

    public function test_empty_report_defaults_to_today_and_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->admin);
        foreach (['customer', 'supplier'] as $type) {
            $url = route('accounting.'.$type.'-ageing');
            $this->get($url)->assertOk()->assertViewHas('asOf', '2026-10-29')->assertViewHas('netCents', 0)->assertSee('No outstanding posted balances');
            foreach ([['as_of' => '2026-02-30'], ['as_of' => '2026-10-30'], ['page' => 0], ['party_id' => 999999], ['party_id' => ['1']]] as $query) {
                $this->from($url)->get($url.'?'.http_build_query($query))->assertRedirect($url)->assertSessionHasErrors();
            }
        }
    }

    public function test_guests_and_non_admins_cannot_access_ageing(): void
    {
        $urls = [route('accounting.customer-ageing'), route('accounting.supplier-ageing')];
        foreach ($urls as $url) $this->get($url)->assertRedirect(route('login'));
        foreach (['user', 'staff', 'driver'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach ($urls as $url) $this->get($url)->assertRedirect('/dashboard')->assertSessionHas('error', 'Access denied.');
        }
    }

    public function test_reports_are_read_only_and_term_edits_do_not_backfill_legacy_documents(): void
    {
        $sale = $this->ledgerSale('100', null);
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-10-01');
        $this->customer->update(['payment_terms_days' => 30]);
        $this->supplier->update(['payment_terms_days' => 30]);
        $tables = ['sales_orders', 'purchase_receipts', 'journal_entries', 'journal_lines', 'stock_movements', 'customer_payments', 'supplier_payments'];
        foreach ($tables as $table) $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        $this->assertSame(10000, $this->ageing()['totals']['unknown']);
        $this->assertSame(10000, $this->ageing(false)['totals']['unknown']);
        $this->assertNull($sale->fresh()->due_date);
        $this->assertNull($receipt->fresh()->due_date);
        foreach ($tables as $table) $this->assertSame($before[$table], DB::table($table)->orderBy('id')->get()->toJson());
    }


    public function test_document_totals_span_ledger_chunks_and_reversal_ancestors(): void
    {
        $sale = $this->ledgerSale('1.01', '2026-10-01');
        $first = $sale->journalEntries()->sole();
        for ($i = 0; $i < 501; $i++) $this->postManualEntry('1200', '4100', '0.01', '2026-10-01', $sale);
        $this->reverseJournal($first, '2026-10-29');
        $report = $this->ageing();
        $this->assertSame(501, $report['netCents']);
        $this->assertSame(501, $report['totals']['1_30']);
        $this->assertSame(1, $report['rows']->total());
        $this->assertSame([], $report['issues']);
    }

    public function test_supplier_payment_party_conflict_and_cyclic_journals_are_flagged(): void
    {
        $receipt = $this->receiveGoods($this->approvedRequest(), 40, '2026-10-01');
        $payment = $this->paySupplier($receipt, '20', '2026-10-10');
        $other = Supplier::create(['name' => 'Wrong supplier', 'is_active' => true]);
        $payment->update(['supplier_id' => $other->id]);
        $report = $this->ageing(false);
        $this->assertSame(10000, $report['netCents']);
        $this->assertSame(-2000, $report['unassignedBalanceCents']);
        $this->assertSame(8000, $report['controlBalanceCents']);
        $original = $receipt->journalEntries()->sole();
        $reverse = $this->reverseJournal($original, '2026-10-20');
        DB::table('journal_entries')->where('id', $original->id)->update(['reversal_of_id' => $reverse->id]);
        $this->assertSame(3, $this->ageing(false)['unassignedCount']);
        $this->assertSame(0, $this->ageing(false)['rows']->total());
    }

    public function test_valid_due_date_overrides_flow_from_both_http_forms(): void
    {
        $this->actingAs($this->admin)->post(route('sales-orders.store'), ['sale_type' => 'business',
            'customer_id' => $this->customer->id, 'sale_at' => '2026-10-01', 'due_date' => '2026-10-15'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-10-15', SalesOrder::latest('id')->firstOrFail()->due_date->toDateString());
        $request = $this->approvedRequest();
        $this->post(route('purchase-requests.receipts.store', $request), ['received_at' => '2026-10-01',
            'quantities' => [$request->items()->sole()->id => 20], 'due_date' => '2026-10-16'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-16', PurchaseReceipt::latest('id')->firstOrFail()->due_date->toDateString());
        $this->post(route('sales-orders.store'), ['sale_type' => 'business', 'customer_id' => $this->customer->id,
            'sale_at' => '2026-10-01', 'due_date' => ['2026-10-15']])->assertSessionHasErrors('due_date');
        $this->get(route('sales-orders.create'))->assertOk();
        $this->post(route('purchase-requests.receipts.store', $request), ['received_at' => '2026-10-01',
            'quantities' => [$request->items()->sole()->id => 20], 'due_date' => ['2026-10-16']])->assertSessionHasErrors('due_date');
        $this->get(route('purchase-requests.receipts.create', $request))->assertOk();
    }

    private function ageing(bool $customer = true, string $date = '2026-10-29'): array
    {
        return app(PartyAgeingService::class)->ageing($customer, $date);
    }

    // Ledger fixtures deliberately avoid stock operations for report-only tests.
    private function ledgerSale(string $amount, ?string $due, string $date = '2026-06-01', ?Customer $customer = null): SalesOrder
    {
        $sale = SalesOrder::create(['customer_id' => ($customer ?? $this->customer)->id, 'sale_type' => 'business',
            'status' => 'completed', 'sale_at' => $date, 'due_date' => $due, 'total_amount' => $amount, 'created_by' => $this->admin->id]);
        $sale->update(['reference' => 'SALE-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT)]);
        $this->postManualEntry('1200', '4100', $amount, $date, $sale);
        return $sale;
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
