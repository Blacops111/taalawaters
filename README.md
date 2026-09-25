# Taala Crystal

Taala Crystal is a Laravel-based business management system for the bottled and bulk water operations of Uholo Fresh Springs Co. Ltd. V2 is being rebuilt incrementally on the `taala-v2-development` branch so that each module is tested and stabilized before the next phase begins.

> **Current development status (22 September 2026):** Development is in Phase 7 (Accounting). Purchasing and delivery workflows are present; goods receipt accounting and supplier payments have been locally verified by the project owner. Customer payments are locally verified: the migration, 38 focused/regression tests (269 assertions), and browser verification passed. Trial Balance is locally verified: 10 tests / 69 assertions and browser verification passed. Balance Sheet is locally verified: its 10 tests / 75 assertions and related Trial Balance / Journal History checks passed (23 tests / 159 assertions total), followed by browser verification. Operating expenses are locally verified: 12 tests / 138 assertions, with Trial Balance and Balance Sheet regressions giving 32 tests / 282 assertions total, followed by browser verification. Expense reversals now await local verification. The phase-by-phase descriptions and full-suite result below are the historical 16 September baseline.

## Latest increment — Expense Reversal

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

PHP is unavailable in the authoring environment, so Laravel execution remains pending locally. Static PHP syntax and diff checks are performed before publishing. Browser verification: reverse a test expense with a reason, inspect the opposite journal and unchanged original, confirm its Reversed status, then compare reports before and on the reversal date.

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

A starter Truck module exists, but future V2 logistics will use a more flexible structure supporting motorbikes and tanker trucks.

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
customers
sales_orders
sales_order_items
sales_order_reversals
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

### Latest full regression result

Verified locally on **16 September 2026**:

```text
Tests:      123 passed
Assertions: 615
Duration:   30.23s
```

This complete suite covers authentication, inventory, water-meter ingestion, production, production reversal, customer management, sales pricing, draft sales, stock deduction, sales history/details, PDF/Excel reporting, sale reversal and Taala Crystal branding.

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

## Next planned work — Phase 5: Purchasing

Phase 5 will be started only after the current Phase 4 milestone is intentionally closed and development resumes.

Planned purchasing work includes:

- purchase requests
- supplier records
- purchase approval flow
- purchased-material receiving integration
- audit history
- links between purchases and V2 inventory receipts

Exact business rules will be confirmed incrementally before implementation.

## Future roadmap after Purchasing

- delivery notes
- drivers
- vehicles / motorbikes / tanker trucks
- delivery tracking
- accounting integration
- trial balance
- balance sheet
- richer V2 dashboard/reports
- expanded role permissions
- security hardening
- performance testing
- backups / monitoring
- production deployment

## Project status note

This README reflects the `taala-v2-development` branch status as of **16 September 2026**. Phase 2, Phase 3 and Phase 4 are verified milestones. The full regression suite currently passes with **123 tests and 615 assertions**. Phase 5 (Purchasing) is next when development resumes.
