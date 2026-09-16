# Taala Water System

Taala Water System is a Laravel-based business management system for Taala's bottled and bulk water operations. V2 is being rebuilt incrementally on the `taala-v2-development` branch so each module is tested and stabilized before the next one is added.

> **Current development status:** Phase 2 (Inventory & SKU Foundation) and Phase 3 (Production Management) are functionally complete and locally verified. The next planned module is Phase 4 (Sales & Customers).

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
Delivery
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

Caps, seals and outer packaging remain generic until Taala confirms whether they vary by bottle size.

### Stock movement ledger

Inventory balances are the signed sum of stock movements.

```text
Opening Balance     +1000
Damage                -25
Stock Received       +500
-------------------------
Live Balance         1475
```

This keeps inventory traceable and avoids silently changing a stored total.

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

A local simulated meter was used to verify that a baseline reading creates no stock and that a later cumulative increase automatically adds the matching litre delta to `RAW-WATER`.

The production hardware connection still depends on the real meter model and interface. Possible interfaces include HTTP/HTTPS, Modbus TCP, RS485/Modbus RTU, pulse output, MQTT or a PLC/IoT gateway.

## Phase 3 — Production Management

Status: **Functionally complete and tested**

### Phase 3A — Production recipe foundation

Production recipes link each finished-water SKU to the materials required to produce it.

Main tables:

```text
production_recipes
production_recipe_components
```

The recipe foundation is migrated and verified on MySQL. The shortened composite index name `prod_recipe_component_unique` avoids MySQL's identifier-length limit.

### Phase 3B — Production recipe setup

An admin interface allows the real materials and quantities for each finished product to be configured.

The four finished-water recipes have been configured locally:

- `FW-500ML`
- `FW-1L`
- `FW-10L`
- `FW-20L`

Each configured recipe uses the inventory ledger items rather than a separate stock source.

### Phase 3C — Production runs

Production runs now:

- accept whole finished units only
- validate the recipe and finished product
- check every required component before changing stock
- calculate component usage from the configured recipe
- block the entire run when any component is insufficient
- deduct raw water and packaging materials atomically
- add finished product stock atomically
- create a unique `PROD-########` reference
- keep all input/output movements linked to the production run

Whole-unit validation exists both in the HTTP form and in the production service, so fractional finished bottles cannot be created by bypassing the UI.

The local production workflow was verified end-to-end using meter-created raw-water inventory.

### Phase 3D — Production run reversal

Incorrect completed production runs can be reversed without deleting history.

A reversal:

- requires a reason
- uses the original production movements rather than recalculating from the current recipe
- restores the exact consumed components
- removes the exact finished-product output
- blocks a second reversal of the same run
- blocks reversal when insufficient finished stock remains to remove the original output safely
- records linked reversal stock movements
- marks the original run as `reversed`
- creates a `REV-PROD-########` reference

This workflow was manually verified by reversing the earlier fractional test run `PROD-00000001` as `REV-PROD-00000001`.

## Existing legacy modules

The original Taala system still contains working or partially working modules while V2 replacements are developed.

### Authentication and admin access

- login/authentication
- admin middleware
- protected admin routes
- profile routes

### Products

Legacy product CRUD supports create, list, edit, delete, selling price and cost price.

### Legacy stocks

The original `stocks` module remains in place. V2 inventory uses `inventory_items` and `stock_movements`, so the legacy stock module should not be used as the V2 ledger source of truth.

### Legacy sales

Existing sales functionality includes sales entry, product selection, quantity, sale price, total amount, sale date, stock deduction, PDF export and Excel export.

Phase 4 will replace/bridge this carefully so V2 sales deduct finished-product inventory from the V2 stock ledger instead of creating a second competing stock system.

### Dashboard

The legacy dashboard currently includes product, stock, sales, revenue, profit, sales-over-time, product-distribution, stock-level and low-stock views.

Current legacy profit uses:

```text
(sale price - current product cost price) × quantity sold
```

This is not yet historical costing and will be revisited during later accounting work.

### Trucks / logistics

A starter Truck module exists, but future V2 logistics will use a more flexible vehicle structure supporting motorbikes and tanker trucks.

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
```

### `inventory_items`

Stores the SKU master catalog, including SKU, name, category, unit, reorder level, sellable flag and active flag.

### `stock_movements`

Stores every signed inventory change, including movement type, quantity delta, source linkage, user, occurrence time, reference and notes.

### `water_meters` / `water_meter_readings`

Store meter configuration and normalized cumulative reading history used to create automated raw-water stock movements.

### `production_recipes` / `production_recipe_components`

Store finished-product recipes and their component requirements.

### `production_runs`

Stores completed/reversed production transactions and their audit metadata.

### `production_run_reversals`

Stores safe reversals of completed production runs and links the correcting movements back to the original run.

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

```bash
php artisan test --filter=WaterMeterReadingTest
php artisan test --filter=InventoryItemSeederTest
php artisan test --filter=InventoryPageTest
php artisan test --filter=InventoryReceiptTest
php artisan test --filter=InventoryMovementHistoryTest
php artisan test --filter=InventoryLowStockTest
php artisan test --filter=InventoryAdjustmentTest
php artisan test --filter=ProductionRecipeFoundationTest
php artisan test --filter=ProductionRecipeSetupTest
php artisan test --filter=ProductionRun
php artisan test --filter=ProductionRunReversal
php artisan test --filter=ProductionRunReversalController
```

Recent verified production results include:

- `ProductionRun` tests: **8 passed / 49 assertions**
- `ProductionRunReversal` service tests: **5 passed / 26 assertions**
- `ProductionRunReversalController` tests: **3 passed / 26 assertions**

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
- production `APP_DEBUG=false`
- environment-based secrets
- backups and recovery planning

No software can be guaranteed to be literally unhackable. The goal is to minimize attack surface, use secure defaults and test important authorization/data-integrity paths before deployment.

## Performance principles

Current/planned practices include indexed foreign keys and common filters, pagination, aggregate SQL for stock balances, eager loading, avoiding N+1 queries, efficient dashboard queries, queued heavy exports where useful, justified caching, avoiding unnecessary polling, and load testing before deployment.

## Development rules

- Make small, focused changes.
- Inspect existing models, migrations, routes and controllers before modifying behavior.
- Do not invent business rules that Taala has not confirmed.
- Preserve working data and audit history.
- Do not directly overwrite inventory balances; create stock movements.
- Do not manually enter routine borehole raw-water quantities.
- Do not bypass authentication, authorization, CSRF or validation to make a feature work.
- Keep secrets out of source control.
- Avoid unrelated refactors while adding a feature.
- Add focused tests for stock, financial and authorization behavior.
- Fix errors before moving to the next module.

## Next planned work — Phase 4: Sales & Customers

Phase 4 will be built incrementally rather than replacing the legacy sales module in one large change.

The first planned step is **Phase 4A — Sales foundation**, beginning with the V2 sales/customer data model and clear rules for how a sale deducts finished-product inventory.

Key principles for Phase 4:

- only sell active sellable finished inventory items
- server-side authoritative prices and totals
- prevent sales that exceed available finished stock
- use stock movements as the inventory source of truth
- preserve sale price/cost information needed for later historical reporting
- keep customer support flexible for walk-in and named customers
- make sale corrections/reversals auditable rather than deleting financial history
- keep the legacy sales module isolated until the V2 flow is proven

Later Phase 4 work will cover customer management, sales entry, receipts/invoices, sales history, reports and controlled sale reversal/correction.

## Future roadmap after Sales

- purchasing / purchase requests
- supplier management
- delivery notes
- drivers
- vehicles / motorbikes / tanker trucks
- delivery tracking
- accounting integration
- trial balance
- balance sheet
- richer dashboard/reports
- expanded role permissions
- security hardening
- performance testing
- backups / monitoring
- production deployment

## Project status note

This README reflects the `taala-v2-development` branch status as of **16 September 2026**. Phase 2 and Phase 3 are verified milestones; Phase 4 is next.
