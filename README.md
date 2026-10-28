# Taala Crystal

Taala Crystal is a Laravel-based business management system for the bottled and bulk water operations of Uholo Fresh Springs Co. Ltd. V2 is being rebuilt incrementally on the `taala-v2-development` branch so that each module is tested and stabilized before the next phase begins.

## Production launch plan

Follow [the implementation and production launch plan](docs/production-readiness-plan.md) in numbered increments. It covers accounting completion; the agreed Super Admin, Admin, Customer, Stock Manager, Sales Manager, Accountant, Driver and public Guest access; password recovery and one-minute inactivity logout; the V2 dashboard, permission-aware search and settings/themes; new-user tours and Help; responsive public Home/About/Contact/map pages and chatbot-to-agent handoff; a separately restricted payroll payments module for one designated verified account; meter/data cutover; staging/security/performance/recovery checks; and pilot/deployment.

Completed increment: **1.1 Historical inventory valuation — owner-verified**. Next: **1.2 Customer and supplier statements**. Payroll details, the designated email, customer portal scope, production/purchase approval permissions, device details and contact handoff arrangements will be confirmed before their respective increments. General administrator roles will not automatically grant payroll access.

## Current checkpoint — 28 October 2026

**Phase 7 (Accounting) is in progress.** Moving weighted-average material costing and automatic sales COGS are now **owner-verified**. Implementation commit `e41beff` and access-test correction `56a552f` passed 25 costing tests / 210 assertions plus 129 related regression tests / 799 assertions: **154 tests / 1,009 assertions total**. The owner confirmed the requested browser workflow with `inventory costing ok`. This confirms the local milestone; production deployment and a current full-suite run are separate checks. On another database, costing remains inactive until opening values are explicitly approved. Production expense allocation is now also owner-verified: 154 tests / 1,216 assertions across its focused and regression groups, followed by `production cost allocation ok`.

| Area | Latest confirmed status |
| --- | --- |
| Inventory, production, sales and customers | Earlier verified V2 milestones; related costing regressions now passed |
| Purchasing and delivery workflows | Implemented; suppliers, purchase approvals/receiving, drivers, vehicles, assignments and delivery notes are present |
| Goods receipt accounting | Owner confirmed 4 tests / 25 assertions and browser verification |
| Supplier payments | Owner confirmed 4 tests / 33 assertions and browser verification |
| Customer payments | Owner confirmed 14 tests / 121 assertions and browser verification |
| Trial Balance | Owner confirmed 10 tests / 69 assertions and browser verification |
| Balance Sheet | Owner confirmed 10 tests / 75 assertions and browser verification |
| Operating expenses | Owner confirmed 12 tests / 138 assertions and browser verification |
| Expense reversals | Owner confirmed 10 tests / 100 assertions and browser verification |
| Profit & Loss | Owner confirmed 10 tests / 65 assertions and browser verification |
| Inventory material costing and automatic COGS | Owner confirmed 25 tests / 210 assertions, related regressions and browser verification before the new allocation increment |
| Production labour/electricity/overhead allocation | Owner confirmed 27 tests / 222 assertions, 127 regression tests / 994 assertions and browser verification |
| Historical inventory valuation | Owner confirmed 14 tests / 117 assertions, 72 regression tests / 576 assertions and browser verification |

These are focused milestone results, not a combined current full-suite result. The 123-test / 615-assertion result documented later is the historical **16 September** baseline.

### Resume here

The previous inventory costing milestone is complete. Its local verification reported:

| Test group | Tests passed | Assertions |
| --- | ---: | ---: |
| InventoryCosting | 25 | 210 |
| Purchasing, production, sales and customer/supplier payment regressions | 89 | 538 |
| Inventory adjustments/receipts, water meter and financial report regressions | 40 | 261 |
| **Total across these runs** | **154** | **1,009** |

Browser verification was confirmed after the requested opening setup → partial receipt → production → sale checks, including the COGS journal, inventory reconciliation and Profit & Loss. Production labour/electricity/overhead allocation is now implemented, preserving expense/payment history and limiting allocation to the source expense balance. The owner confirmed its focused and regression tests and browser workflow with `production cost allocation ok`.

The commands below reproduce the verified test groups from `water-system/`; they are not outstanding tasks for this milestone:

```bash
git fetch origin taala-v2-development
git merge --ff-only FETCH_HEAD
php artisan migrate
php artisan test --filter=InventoryCosting
php artisan test --filter='PurchaseReceipt|ProductionRun|SalesOrder|CustomerPaymentAccounting|SupplierPaymentAccounting'
php artisan test --filter='InventoryReceipt|InventoryAdjustment|WaterMeter|ProfitLoss|TrialBalance|BalanceSheet'
```

## Latest verified increment — Historical inventory valuation

Accounting → Historical Inventory Valuation reconstructs closing quantity and value per item from the approved opening snapshot plus recorded costed movements through the selected UTC calendar day. It compares total value to posted account 1300 Inventory on the same date, showing the signed difference. Today's mutable cost balances are not used to reconstruct earlier dates. Average costs are derived for display only; totals use recorded integer cents.

Dates before costing activation show an explicit unavailable state rather than invented values. Future dates are rejected. Inactive and zero-value items remain included, later-created items are excluded from earlier dates, and reversals affect their own movement/journal dates. Missing snapshots, quantity inconsistencies, invalid balances or a missing/invalid Inventory account prevent a green reconciliation state. Current item names/units/status label historical balances; this is not a frozen period-close snapshot and later backdated journals can change prior reports. The report is read-only and uses the existing admin authorization until the planned role rollout.

No migration is required. From `water-system/`:

```bash
git fetch origin taala-v2-development
git merge --ff-only FETCH_HEAD
php artisan view:clear
php artisan test --filter=InventoryValuation
php artisan test --filter='InventoryCosting|ProductionCostAllocation|TrialBalance|BalanceSheet'
```

14 focused tests cover unavailable dates, opening snapshots, same-second activation/movement boundaries, inclusive cutoff, receipts/averages, draft exclusion, inactive items/accounts, escaping, newly added items, immutable history reconstruction, missing snapshots, inconsistent quantities, fractional rounding, production allocations, sales and reversals, read-only behavior and authorization. Static PHP parsing, Blade expression/control-block checks and diff checks were performed; **Laravel tests have not run here because PHP is unavailable**. The first owner run stopped during test discovery because the private journal helper `post()` conflicted with Laravel’s public HTTP test method. The helper is renamed `postInventoryJournal()`; no application posting behavior changed. After correction `9e6be76`, the owner confirmed **14 valuation tests / 117 assertions** and **72 related regression tests / 576 assertions** passing: **86 tests / 693 assertions total**. The owner then confirmed the date-based quantities/values, account 1300 reconciliation, transaction-date effects and pre-activation unavailable state with `historical inventory valuation ok`. Plan increment 1.1 is complete.

Browser check on the test database: choose an activation-or-later day with known activity and compare quantities/value with the recorded transactions and Trial Balance account 1300. Choose a day before and after a known receipt, sale or reversal and verify only the appropriate activity is included. A date before activation must show unavailable. This check requires no new live transactions or changes to opening values.

## Previous verified increment — Production expense allocation (owner-verified)

When completing a **new** production run, an admin can optionally select up to 20 existing operating expenses, enter the amount attributable to that batch and record its allocation basis. Record production labour under 5500 General Operating Expense, electricity under 5300 Utilities, and eligible production overhead under 5400 Repairs or 5500 General Operating Expense. The form lists unallocated balances; Accounting → Production Costs provides searchable, paginated allocation history linked to batch journals. Expense history shows amounts currently allocated to production.

Each allocation posts **Dr 1300 Inventory / Cr the original expense account** and adds that value to the batch output before updating the moving average. The original expense, original journal and Cash/Bank payment remain unchanged. Later sales include the allocated cost in COGS. Unallocated portions remain expenses. Material-only production continues to work when the optional allocation rows are blank.

Controls and scope:

- Inventory Costing must be active. Only expenses recorded after activation, dated on or after activation and no later than the batch date, are eligible. This prevents reusing old costs that may already be in opening stock. Future-dated allocated production is rejected.
- Only eligible production-related portions of accounts 5300/5400/5500 can be selected; 5200 delivery/fuel costs are excluded. The admin supplies a supported allocation basis, such as measured production hours or electricity usage. Exclude office administration, delivery, abnormal waste and idle-capacity costs. This is manual allocation of recorded paid expenses, not payroll, accrued bills or an automatic normal-capacity overhead calculation.
- Each expense needs exactly one unreversed posted journal matching its recorded amount/accounts/date. Missing, duplicate, mismatched or reversed source journals and inactive/wrong-type accounts are rejected.
- Amounts are validated as positive exact cents. An expense can be split across batches, but active allocations cannot exceed its original amount. Duplicate expense rows in one batch are rejected.
- A production form submission key prevents repeated submissions creating a second batch/journal; changed payloads and replay of reversed batches are rejected. Stock, allocation audit rows, and journals commit or roll back together under the existing costing lock.
- Allocations are immutable and are entered only during completion. Existing batches are not retroactively repriced.
- Reversing a batch reverses its exact original allocation journal and stock costs, restores the expense balance and releases allocation capacity. The existing rule blocking production reversals after subsequent input/output stock movements remains. An expense with active allocations cannot be reversed independently. Reallocation and expense reversal cannot be backdated before the batch reversal that released the amount.
- Reversal requires matching audit, original journal and stock-value history. Failed journal posting or stock validation rolls back the whole operation. Original accounts may be reversed after deactivation.

Accounting context: inventory costs can include direct labour and production overhead; see the [IFRS Foundation IAS 2 overview](https://www.ifrs.org/issued-standards/list-of-standards/ias-2-inventories/). This increment supplies an auditable manual allocation workflow; it does not determine which business costs qualify or claim full standards compliance.

New migration: `water-system/database/migrations/2026_10_28_000100_create_production_cost_allocations.php`. It adds immutable allocation records and nullable production submission keys without rewriting existing batches.

From `water-system/`:

```bash
git fetch origin taala-v2-development
git merge --ff-only FETCH_HEAD
php artisan migrate
php artisan test --filter=ProductionCostAllocation
php artisan test --filter='InventoryCosting|ProductionRun|OperatingExpense'
php artisan test --filter='SalesOrderAccounting|SalesOrderReversal|CustomerPaymentAccounting|ProfitLoss|TrialBalance|BalanceSheet'
```

27 focused tests cover allocation journals, exact cents, shared expense limits, submission replay, sales COGS, reports, reversal release/date ordering, original-record preservation, permissions, audit immutability, pagination and rollback. Static PHP parsing and diff checks were performed; **PHP is unavailable in the authoring environment, so Laravel tests and the migration have not been executed here**. Owner run on 28 October: allocation tests **26 passed / 1 failed (193 assertions)**; inventory/production/expense regressions **62 passed / 1 failed (546 assertions)**; sales/payment/report regressions **64 passed (445 assertions)**. Both failures were the same production-form Blade parse error: inline `@php(...)` assignments were captured together with a later block `@php` directive, swallowing intervening template control flow. The assignments now use a closed `@php ... @endphp` block. After fix `a0ae8f3`, the owner confirmed **27 allocation tests / 222 assertions** and **63 inventory/production/expense tests / 549 assertions** passing. Together with the earlier **64 sales/payment/report tests / 445 assertions**, this is **154 tests / 1,216 assertions** across the requested groups. The owner then confirmed the browser allocation, journal, valuation/reconciliation and batch-reversal checks with `production cost allocation ok`. This is local verification, not a current full-suite or deployment claim.

After tests pass, use a test database with costing active. Record a KES 100 production electricity expense after activation, then allocate KES 50 when producing a batch whose material cost is KES 20. With 10 units and no existing finished stock, output value should be KES 70 and average cost KES 7. Check Dr Inventory / Cr Utilities KES 50, the original Cash/Bank payment still KES 100, and KES 50 unallocated expense remaining. Selling 2 units should post KES 14 COGS. Test batch reversal on a separate unsold batch with no later input/output movements; it should restore expense value and release the allocation while preserving original history. Confirm account 1300 still reconciles with inventory valuation.

## Latest verified increment — Moving weighted-average inventory material costing

Accounting → Inventory Costing adds an explicit, admin-only activation workflow. Before activation, existing operations retain their previous accounting behavior. New goods receipts preserve approved unit cost and rounded receipt-value snapshots immediately. No historical receipt costs are guessed or backfilled.

The setup lists all current items, including inactive items, and requires approved opening **total values**, a valuation reason, and an explicit choice of opening reconciliation account (3100 Owner Capital or 3200 Retained Earnings). A confirmation screen previews the exact difference from existing account 1300. Activation snapshots quantities/values and posts only that difference, atomically. Unchanged values produce no journal. Changed stock/items/ledger, negative stock, future-dated inventory, missing accounts and duplicate activation are rejected. Existing quantities, journals and histories remain intact. Activation is prospective and cannot be undone or overwritten through the UI.

After activation:

- Approved partial goods receipts add their recorded quantity/value to the weighted average; purchase journals remain Dr 1300 / Cr 2100 without a duplicate debit.
- Production consumes materials at current average cost and transfers the same value to finished stock. No net journal is required for a transfer within account 1300.
- Sales include Dr 5100 COGS / Cr 1300 Inventory in the **same journal** as revenue. Business receivables and customer payments retain the one-journal invariant.
- Sale reversals restore the original quantity/value and reverse all original journal lines. Production reversals restore original costs only when no subsequent movements exist for any input/output item; otherwise a reviewed correction is required.
- Damage/wastage/decreases post Dr 5500 / Cr 1300. Positive corrections use the existing average and post Dr 1300 / Cr 5500; increases from empty stock are blocked because no cost is known. New physical supplies use approved Goods Receiving; legacy manual opening/receipt forms cannot bypass costing.
- Costs use Brick Math decimal arithmetic (already installed through the locked Laravel dependencies), integer thousandths for quantities and integer cents for money. The final issue takes residual cents. Costed movements are immutable. A consistent transaction lock serializes stock writes and setup; backdating before an item's latest costed movement is rejected.
- The page shows current recorded quantity, average cost, valuation, costed movement history and the reconciliation to account 1300. That page remains the current ledger view; the separate Historical Inventory Valuation increment above adds an as-of report.

**Scope and cutover:** This is material costing, not full absorption costing. Setup explicitly approves zero acquisition cost for meter-managed borehole water. That verified material-only increment left labour, pumping electricity and overhead separately expensed. The newer allocation increment above adds explicit conversion costs to new batches and is now locally owner-verified. Resolve corrections to old sales/production before activation: pre-costing movements have no cost snapshot and cannot be automatically reversed after cutover. Approved zero opening values are accepted deliberately; missing values are rejected. A new item with no prior stock starts at zero and acquires value through approved receipts/production. Legacy data is never erased.

New migration: `water-system/database/migrations/2026_09_27_000100_create_inventory_costing.php`.

Run from `water-system/`:

```bash
php artisan migrate
php artisan test --filter=InventoryCosting
php artisan test --filter='PurchaseReceipt|ProductionRun|SalesOrder|CustomerPaymentAccounting|SupplierPaymentAccounting'
php artisan test --filter='InventoryReceipt|InventoryAdjustment|WaterMeter|ProfitLoss|TrialBalance|BalanceSheet'
```

The owner reported the migration was already applied (`Nothing to migrate`). On 28 October, the first local costing run returned 24 passed and 1 failed (177 assertions); the access-control test expected 403 instead of the established denial redirect. That test expectation was corrected in `56a552f`; the owner subsequently confirmed all 25 tests / 210 assertions passing, both related regression groups passing, and browser verification.

25 focused costing tests were added, including the complete purchase → production → sale path, opening reconciliation, rollback, rounded residuals, cost snapshots, customer payments, reversals and access control. Static PHP parsing and diff checks passed in the authoring environment. PHP is unavailable there, so the authoring agent did not execute Laravel tests. Runtime verification is based on the owner-provided results recorded above.

After tests pass, use a test database to review/activate a known opening valuation. Example: 100 caps worth KES 200; receive another 100 at KES 3. The average becomes KES 2.50. A recipe using one cap per finished unit transfers KES 150 for 60 units (assuming other components have approved zero cost for this example). Selling 20 units posts KES 50 COGS; remaining caps are KES 350 and finished goods KES 100, matching Inventory KES 450. Verify the sale journal, customer payment, reversal, Profit & Loss and the valuation reconciliation. Do not enter invented opening costs in the live database.

## Profit & Loss — verified

Accounting → Profit & Loss displays posted revenue, expenses (including COGS if posted) and net profit/loss for an inclusive date range, defaulting to month-to-date. It aggregates by account in integer cents, includes inactive accounts and direct parent postings without rollup duplication, preserves negative balances, and applies reversals on their journal dates. Payments, capital and inventory purchases do not become revenue/expenses merely because money changed hands. A link opens posted journals for the same period.

This read-only on-screen report includes all posted revenue/expense movements, including any manual closing entries; no fiscal closing classification exists yet. Profit is provisional while required costs, particularly COGS, are not fully posted. PDF/Excel exports and period-closing workflows are separate increments.

No migration is needed. Run from `water-system/`:

```bash
php artisan test --filter=ProfitLoss
php artisan test --filter=OperatingExpenseReversal
php artisan test --filter=BalanceSheet
```

The project owner confirmed 10 tests / 65 assertions and browser verification. In the browser, compare a known sale and expense against period journals, check revenue minus expenses equals net result, and check that changing the range includes/excludes a transaction on its journal date.

## Expense Reversal — verified

Expense History now offers a confirmation page to reverse a mistaken expense with a required reason and reversal date. It posts the exact opposite of the original journal, including its account IDs and amounts, and links both journals. It preserves the immutable expense and adds an immutable `REV-EXP-########` audit record with the user, timestamp and reason. A reversal cannot precede the original expense/journal date. Earlier reports retain the expense; reports from the reversal date include the offset.

Duplicate reversals, missing/ambiguous or mismatched original journals are rejected. Posting and audit creation roll back together. Original accounts can be reversed after deactivation. This corrects accounting only; it does not move money or issue a refund. Expense history shows the reversal date and reason and links to the reversal journal.

New migration: `water-system/database/migrations/2026_09_25_000200_create_operating_expense_reversals_table.php`.

```bash
php artisan migrate
php artisan test --filter=OperatingExpenseReversal
php artisan test --filter=OperatingExpenseAccounting
php artisan test --filter=TrialBalance
php artisan test --filter=BalanceSheet
```

The project owner confirmed 10 reversal tests / 100 assertions and browser verification after the persisted-snapshot test correction. Browser verification: reverse a test expense with a reason, inspect the opposite journal and unchanged original, confirm its Reversed status, then compare reports before and on the reversal date.

## Operating Expenses — verified

Accounting → Expenses records immediately paid operating costs against existing accounts 5200 Fuel and Transport, 5300 Utilities, 5400 Repairs and Maintenance, or 5500 General Operating Expense. Each expense posts **Dr selected expense / Cr 1100 Cash on Hand or 1110 Bank Account** atomically, with an `EXP-########` reference and links to its journal. Date, description, payee, receipt/payment reference, notes and recording user are retained in paginated history.

The form has duplicate-submission protection scoped to the recording user. Fresh submissions can record separate legitimate expenses with identical amounts or descriptions. Recorded expenses are read-only; corrections now use the audited expense reversal workflow. Inventory purchases, supplier credit bills and COGS are not entered through this form. No inventory movement or second revenue posting is created. The existing report calculations include the new journals automatically.

New migration: `water-system/database/migrations/2026_09_25_000100_create_operating_expenses_table.php`.

From `water-system/`, after syncing:

```bash
php artisan migrate
php artisan test --filter=OperatingExpenseAccounting
php artisan test --filter=TrialBalance
php artisan test --filter=BalanceSheet
```

The project owner confirmed the expense migration, 32 tests / 282 assertions including report regressions, and browser verification. In the browser, record an example such as a KES 125.75 fuel expense, inspect its expense debit and Cash/Bank credit in Journal Entries, and compare report balances before and on its date. Earnings and Cash/Bank should decrease by the expense amount while the accounting equation remains balanced. An expense does not consume inventory.

## Balance Sheet — verified

Accounting → Balance Sheet groups posted balances into assets, liabilities and equity at an inclusive journal date, reusing the verified Trial Balance calculation. It adds unclosed revenue less expenses to recorded equity and checks assets equal liabilities plus equity. Negative balances/losses are preserved. Posted closing entries reduce unclosed earnings while increasing recorded equity, avoiding double counting. No fiscal-year boundary is assumed.

This is a read-only, on-screen report. It does not create closing entries, opening balances, inventory valuation or missing expense/COGS postings. Completeness depends on the recorded journals.

No migration or seeding is needed. Run from `water-system/`:

```bash
php artisan test --filter=BalanceSheet
php artisan test --filter=TrialBalance
php artisan test --filter=JournalEntryHistory
```

The project owner confirmed Balance Sheet tests (10 / 75 assertions), related report checks (23 / 159 assertions total) and browser verification. A local page timeout stopped after clearing cached application state. Check the same date in Balance Sheet and Trial Balance, verify the equation and earnings, then move the cutoff before a known transaction.

## Trial Balance — verified

Accounting → Trial Balance shows net debit or credit balances per account as of an inclusive journal date, plus totals and an imbalance indicator. Only posted journals are included. Reversals offset their originals on their own entry dates. Inactive accounts and direct postings to parent accounts remain included; child balances are not rolled into parent rows or double-counted. Accounts with posted activity and a zero net balance remain visible.

No migration or seeding is required. Run from `water-system/`:

```bash
php artisan test --filter=TrialBalance
php artisan test --filter=JournalEntryHistory
php artisan test --filter=CustomerPaymentAccounting
php artisan test --filter=SupplierPaymentAccounting
```

The project owner confirmed all 10 Trial Balance tests (69 assertions) and browser verification after the inclusive SQLite date-cutoff fix. In the browser, check a date with posted sales/purchases/payments, confirm equal debit and credit totals, and move the date before a transaction to check the cutoff. This increment adds the on-screen report; financial completeness still depends on all required transactions being posted.

## Customer Payments / Accounts Receivable — verified


Accounting → Customer Payments records receipts against completed business sales with one posted, unreversed sales journal. Each payment posts **Dr 1100 Cash on Hand or 1110 Bank Account / Cr 1200 Accounts Receivable**. It does not post revenue again or change inventory.

- Partial and multiple payments are supported, with overpayment protection based on the original posted receivable.
- Payments record a `CPAY-########` reference, sale, customer, receiving account, date, external reference, user and notes.
- Payment and journal creation are atomic. Sale locking serializes payment recording with other payments and sale reversal.
- Repeating the same form submission returns the original payment rather than posting it twice.
- Outstanding sales and payment history are paginated. Existing debts remain payable if a customer is deactivated.
- Sales with customer payments cannot be reversed in this increment. Refunds/payment reversals require a separate audited workflow and are not implemented yet.

New migration: `water-system/database/migrations/2026_09_22_000100_create_customer_payments_table.php`.

From `water-system/`, after syncing the development branch:

```bash
php artisan migrate
php artisan test --filter=CustomerPaymentAccounting
php artisan test --filter=SalesOrderAccounting
php artisan test --filter=SalesOrderReversal
php artisan test --filter=SupplierPaymentAccounting
```

The repository's PHPUnit configuration uses an in-memory SQLite database. These tests do not substitute for applying the migration and checking the workflow on local MySQL. The project owner confirmed the migration, all four test groups (38 tests / 269 assertions), and customer-payment browser verification.

For browser verification, complete a fresh business sale and record a partial payment under Accounting → Customer Payments. Check its remaining balance and linked journal under Accounting → Journal Entries, searching for the `CPAY` reference. Settle the remainder using the other receiving account; confirm it leaves the outstanding list. Check that an overpayment is rejected and a paid sale cannot be reversed. Stock and revenue must remain unchanged by payments.


## Project goals

The target operational flow is:

```text
Borehole / Purchases
        ↓
Raw Materials & Inventory
        ↓
Production
        ↓
Finished Product Inventory
        ↓
Sales & Customers
        ↓
Purchasing / Deliveries
        ↓
Accounting & Reports
```

The system is designed to automate routine work where practical, preserve an audit trail, reduce direct stock manipulation, and support reliable business reporting.

## Technology stack

- PHP 8.2+
- Laravel 12
- MySQL
- Laravel Breeze authentication
- Blade
- Bootstrap 5
- Vite
- Chart.js
- Laravel DOMPDF
- Laravel Excel 3.1.68
- PhpSpreadsheet 1.30+
- PHPUnit 11

The Laravel application lives in:

```text
water-system/
```

## Development branch

Active V2 development is done on:

```text
taala-v2-development
```

The stable `main` branch should not be used for experimental V2 work.

## Roadmap

1. Requirements & Architecture
2. Inventory & SKU Foundation
3. Production Management
4. Sales & Customers
5. Purchasing
6. Deliveries / Vehicles / Drivers
7. Accounting
8. Dashboard & Reports
9. Security Hardening
10. Performance / Testing / Deployment

## Phase 1 — Requirements & Architecture

Status: **Mostly complete**

Important architecture decisions already made:

- Inventory balances are derived from `stock_movements`; they are not directly overwritten.
- Borehole raw water is meter-driven during normal operation.
- Finished products are created through Production rather than normal receiving.
- Manual adjustments and reversals must remain auditable.
- Existing legacy modules remain available during the V2 transition until their replacements are proven.
- Critical stock-changing workflows use validation, transactions and row locking.
- Completed financial/stock records are corrected by reversal rather than destructive edits.

## Phase 2 — Inventory & SKU Foundation

Status: **Functionally complete and tested**

### Inventory master SKUs

| SKU | Item | Category |
|---|---|---|
| `RAW-WATER` | Raw Borehole Water | Raw water |
| `EB-500ML` | Empty Bottle 500 ml | Empty bottle |
| `EB-1L` | Empty Bottle 1 Litre | Empty bottle |
| `EB-10L` | Empty Bottle 10 Litre | Empty bottle |
| `EB-20L` | Empty Bottle 20 Litre | Empty bottle |
| `CAP` | Bottle Cap | Cap |
| `LABEL-500ML` | Bottle Label 500 ml | Label |
| `LABEL-1L` | Bottle Label 1 Litre | Label |
| `LABEL-10L` | Bottle Label 10 Litre | Label |
| `LABEL-20L` | Bottle Label 20 Litre | Label |
| `SEAL` | Bottle Seal | Seal |
| `PACKAGING` | Outer Packaging Material | Packaging material |
| `FW-500ML` | Finished Water 500 ml | Finished product |
| `FW-1L` | Finished Water 1 Litre | Finished product |
| `FW-10L` | Finished Water 10 Litre | Finished product |
| `FW-20L` | Finished Water 20 Litre | Finished product |

Caps, seals and outer packaging remain generic until the business confirms whether they vary by bottle size.

### Stock movement ledger

Inventory balances are the signed sum of stock movements.

```text
Opening Balance     +1000
Damage                -25
Stock Received       +500
-------------------------
Live Balance         1475
```

### Inventory capabilities

The V2 Inventory module includes:

- live SKU balances
- opening balances
- stock receiving
- movement history and filtering
- reorder levels
- low-stock detection
- manual damage / wastage / correction adjustments
- validation preventing stock decreases below the current balance
- audit information for user, reference, date and notes

Raw borehole water cannot be manually received or adjusted during normal operation, and finished products are not received as ordinary purchased stock.

### Automated borehole water inventory

Status: **Software foundation complete and locally verified; physical meter integration pending**

The intended flow is:

```text
Borehole
   ↓
Flow Meter / Meter Box
   ↓
Device or Gateway
   ↓
Taala Meter API
   ↓
Meter Reading
   ↓
Stock Movement
   ↓
RAW-WATER live balance
```

The software foundation includes secure meter registration, UUID meter IDs, hashed tokens, baseline readings, idempotency protection, litre normalization, positive-delta calculation, anomaly review, locking and automatic raw-water stock movements.

## Phase 3 — Production Management

Status: **Functionally complete and tested**

### Production recipe setup

Production recipes link each finished-water SKU to the exact inventory components required to produce it. The four finished-water recipes have been configured locally:

- `FW-500ML`
- `FW-1L`
- `FW-10L`
- `FW-20L`

### Production runs

Production runs:

- accept whole finished units only
- validate the recipe and finished product
- check all required components before changing stock
- calculate component usage from the configured recipe
- block the entire run if any component is insufficient
- deduct raw water and packaging materials atomically
- add finished product stock atomically
- create a unique `PROD-########` reference
- keep all movements linked to the production run

### Production run reversal

Incorrect completed production runs can be reversed without deleting history. A reversal:

- requires a reason
- uses the original production movements rather than recalculating from the current recipe
- restores the exact consumed components
- removes the exact finished-product output
- blocks duplicate reversal
- blocks reversal if insufficient finished stock remains
- records linked reversal movements
- marks the original run as `reversed`
- creates a `REV-PROD-########` reference

## Phase 4 — Sales & Customers

Status: **Functionally complete and tested**

Phase 4 introduced a V2 sales flow that uses the V2 inventory ledger as the source of truth instead of the legacy `stocks` table.

### Business customers

Registered business customers support:

- supermarket
- distributor / wholesaler
- hotel
- restaurant
- office / company
- institution
- shop / retailer
- other business

Customer records include name, customer type, contact person, phone, email, address and active/inactive status. Customers are deactivated rather than deleted so historical sales remain intact.

Walk-in sales do not require a registered customer.

### Sales pricing

Finished products have server-managed retail and wholesale prices:

- walk-in sale → retail price
- business customer sale → wholesale price

Sales staff cannot submit arbitrary unit prices. The server derives the price and line total from the configured inventory item price.

### Draft sales

The V2 sales flow supports:

- walk-in and business-customer drafts
- whole-unit quantities only
- eligible active/sellable finished products only
- adding/removing draft items
- automatic total recalculation
- no inventory deduction while the order is still a draft

### Sale completion

Completing a sale:

- uses a transaction with row locking
- rechecks the sale is still a draft
- requires at least one item
- aggregates duplicate product lines before stock validation
- verifies active/sellable finished-product eligibility
- checks live ledger balance
- blocks insufficient stock
- generates `SALE-########`
- recomputes the stored total from historical line totals
- writes negative `sale` stock movements
- prevents a second completion/double stock deduction

### Completed sales history and details

Completed and reversed V2 sales are read-only audit records. The sales history supports filters for:

- sale reference
- sale type
- business customer
- date range

The sale detail page preserves:

- sale reference and status
- sale type and customer
- sale date
- recorded user
- historical unit prices and line totals
- notes
- original stock deductions
- reversal details and stock restoration movements when applicable

### Sales Data Report

The V2 Sales Data Report summarizes **completed, non-reversed sales only** and supports date filtering.

It includes:

- completed sales count
- total sales value
- total units sold
- walk-in sales value
- business sales value
- per-product units sold
- per-product sales value

The report is available on-screen and can be exported as:

- PDF
- Excel workbook with `Summary` and `Product Breakdown` sheets

Date filters are preserved in both export formats.

### Completed-sale reversal / correction

Completed sales are corrected through an audit-safe reversal rather than editing or deleting the original transaction.

A reversal:

- requires a reason
- locks the original sale before changing anything
- verifies the stored sale lines match the original sale stock movements
- restores the exact quantity originally deducted
- creates `sale_reversal_restore` stock movements
- creates a unique `REV-SALE-########` reference
- records who reversed the sale and when
- marks the original sale as `reversed`
- prevents a second reversal
- leaves the original sale and values visible for audit history
- removes the reversed sale from active sales-report revenue totals

The reversal flow has been verified both automatically and manually against real local stock balances.

## Phase 5 — Purchasing

Status: **Implemented; receipt accounting and supplier settlements locally verified**

Supplier records, purchase requests/items, submission and approval/rejection, approved unit costs, partial goods receiving and receipt history are implemented. Receiving updates the V2 stock ledger and posts Inventory / Accounts Payable. Supplier payments settle the payable through Cash or Bank. The owner has also confirmed the related costing regression checks listed above.

## Phase 6 — Deliveries / Vehicles / Drivers

Status: **Workflows implemented**

V2 includes drivers, vehicles, driver–vehicle assignments, delivery notes/items, dispatch, linked driver login accounts and driver delivery confirmation. These replace the earlier plan to extend only the legacy Truck module. This checkpoint does not claim physical deployment or a new full logistics verification run.

## Phase 7 — Accounting

Status: **In progress**

Chart of Accounts, journal posting/history, sales and goods receipt accounting, supplier/customer payments, operating expenses and expense reversals are implemented. Trial Balance, Balance Sheet and Profit & Loss have passed the owner's focused tests and browser checks. Material costing and automatic COGS are now owner-verified; implementation details, limitations and activation instructions are above.

## Taala Crystal branding

The visible system branding now uses **Taala Crystal** rather than the earlier “Taala Water System” name.

The dashboard uses the Taala Crystal water-themed identity and company details for Uholo Fresh Springs Co. Ltd. PDF and Excel sales reports also use the Taala Crystal branding.

## Existing legacy modules

Legacy Products, Stocks, Sales and parts of the dashboard remain present during the V2 transition. They should not be treated as the V2 inventory source of truth.

### Legacy sales

The original sales module still uses legacy `products`, `sales` and `stocks` data. V2 sales now has its own complete ledger-backed workflow. Legacy removal/migration will be handled later after the wider V2 system is complete and deployment migration rules are defined.

### Dashboard

The dashboard still contains legacy commercial metrics/charts in addition to the new Taala Crystal visual identity. A deeper V2 dashboard/reporting rebuild is planned for Phase 8.

### Trucks / logistics

The legacy Truck module remains present. V2 now has separate drivers, vehicles, assignments and delivery-note workflows; use those for V2 logistics.

## V2 database areas introduced so far

```text
inventory_items
stock_movements
water_meters
water_meter_readings
production_recipes
production_recipe_components
production_runs
production_run_reversals
production_cost_allocations
customers
sales_orders
sales_order_items
sales_order_reversals
suppliers
purchase_requests
purchase_request_items
purchase_receipts
purchase_receipt_items
drivers
vehicles
vehicle_assignments
delivery_notes
delivery_note_items
accounting_accounts
journal_entries
journal_lines
supplier_payments
customer_payments
operating_expenses
operating_expense_reversals
inventory_costing_setups
inventory_cost_balances
```

## Installation / local setup

```bash
git clone https://github.com/Blacops111/taalawaters.git
cd taalawaters
git checkout taala-v2-development
cd water-system
composer install
cp .env.example .env
php artisan key:generate
npm install
php artisan migrate
```

Configure MySQL in `.env` and keep real credentials out of source control.

Seed the V2 inventory master only when required:

```bash
php artisan db:seed --class=InventoryItemSeeder
```

Do not run the general database seeder blindly on an existing environment because it may create test data.

Start development services with:

```bash
php artisan serve
npm run dev
```

## Focused tests used during V2 development

Examples:

```bash
php artisan test --filter=WaterMeterReadingTest
php artisan test --filter=InventoryAdjustmentTest
php artisan test --filter=ProductionRun
php artisan test --filter=ProductionRunReversal
php artisan test --filter=CustomerManagementTest
php artisan test --filter=SalesOrderAutomaticPricingTest
php artisan test --filter=SalesOrderCompletion
php artisan test --filter=SalesDataReport
php artisan test --filter=SalesOrderReversal
php artisan test --filter=TaalaCrystalBranding
```

### Historical full regression baseline — 16 September 2026

Verified locally on **16 September 2026**:

```text
Tests:      123 passed
Assertions: 615
Duration:   30.23s
```

That baseline predates purchasing, logistics, accounting and inventory costing. It is not evidence that the current branch passes its full suite. The baseline covered authentication, inventory, water-meter ingestion, production, production reversal, customer management, sales pricing, draft sales, stock deduction, sales history/details, PDF/Excel reporting, sale reversal and Taala Crystal branding.

To run the complete suite:

```bash
php artisan test
```

## Borehole meter registration

Register a meter with:

```bash
php artisan taala:register-water-meter
```

The registration command displays the plaintext meter token once and stores only its SHA-256 hash. Treat the displayed token as a secret and do not commit or share it.

Do not register/configure the real production meter until its communication method, unit and flow characteristics are known.

## Security principles

Current and planned controls include:

- Laravel authentication
- role-based access control
- CSRF protection
- request validation
- Eloquent / query builder instead of unsafe SQL concatenation
- password hashing
- restricted admin routes
- transactions for critical stock operations
- row locking around concurrent inventory changes
- meter API rate limiting
- hashed meter tokens
- meter-reading idempotency protection
- append-only inventory audit trails
- safe reversal workflows instead of destructive edits
- server-authoritative sales prices and totals
- production `APP_DEBUG=false`
- environment-based secrets
- backups and recovery planning

No software can be guaranteed to be literally unhackable. The goal is to minimize attack surface, use secure defaults and test important authorization/data-integrity paths before deployment.

## Performance principles

Current/planned practices include indexed foreign keys and common filters, pagination, aggregate SQL for stock balances, eager loading, avoiding N+1 queries, efficient dashboard queries, queued heavy exports where useful, justified caching, avoiding unnecessary polling, and load testing before deployment.

## Development rules

- Make small, focused changes.
- Inspect existing models, migrations, routes and controllers before modifying behavior.
- Do not invent business rules that have not been confirmed.
- Preserve working data and audit history.
- Do not directly overwrite inventory balances; create stock movements.
- Do not manually enter routine borehole raw-water quantities.
- Do not bypass authentication, authorization, CSRF or validation to make a feature work.
- Keep secrets out of source control.
- Avoid unrelated refactors while adding a feature.
- Add focused tests for stock, financial and authorization behavior.
- Fix errors before moving to the next module.

## Current checkpoint — Production expense allocation verified

Material costing is verified: 154 tests / 1,009 assertions across the focused and related regression groups, followed by the owner’s browser confirmation. The allocation workflow is now also owner-verified: 154 tests / 1,216 assertions across its requested groups, followed by browser confirmation. Historical inventory valuation (plan increment 1.1) is now owner-verified: 86 tests / 693 assertions across its requested groups plus browser confirmation. Next is increment 1.2: customer and supplier statements.

The implementation uses moving weighted average. Existing history is preserved; opening values are entered explicitly. Borehole water retains its explicitly approved zero acquisition cost. Eligible recorded labour, power and overhead can now be allocated when completing new batches; unallocated costs remain expenses. Automatic overhead rates, payroll/accrual integration and repricing existing batches remain outside this increment.

## Remaining roadmap

The authoritative sequence and completion checks are in [the production launch plan](docs/production-readiness-plan.md):

1. Complete accounting reports, statements, correction controls and period locking; continue with customer and supplier statements after verified historical inventory valuation.
2. Roll out agreed roles, customer isolation, password recovery and one-minute inactivity logout.
3. Build the V2 dashboard, permission-aware search and personal/company settings.
4. Add role-aware onboarding, Help documentation and cross-system responsive/accessibility review.
5. Build the public website, approved map/contact information and FAQ chatbot with human handoff.
6. Build restricted payroll payments after receiving the owner's details and designated verified email.
7. Verify operational scenarios, physical meter integration, real data and legacy cutover.
8. Complete full-suite/MySQL staging, security, performance, hosting, monitoring and tested backup recovery.
9. Train users, run a controlled pilot, approve cutover and deploy a tagged release.

## Project status note

This README reflects the `taala-v2-development` checkpoint as of **28 October 2026**. Moving weighted-average material costing and automatic COGS were verified with 154 tests / 1,009 assertions and browser confirmation. Production conversion-cost allocation was owner-verified with: 154 tests / 1,216 assertions across its requested test groups and browser confirmation. These overlapping checkpoint totals must not be added together as a unique-test count. Historical inventory valuation is the latest owner-verified milestone: 86 tests / 693 assertions across its focused and regression groups plus browser confirmation. No current full-suite pass or production readiness is claimed.
