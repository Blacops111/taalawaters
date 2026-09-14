# Taala Water System

Taala Water System is a Laravel-based business management system for Taala's bottled and bulk water operations. The project is being rebuilt incrementally on the `taala-v2-development` branch so that each module can be tested and stabilized before the next one is added.

> **Current development status:** Phase 2 (Inventory & SKU Foundation) is functionally complete and tested. Phase 3 (Production Management) has started with the production recipe database foundation. The latest production migration received a MySQL index-name compatibility fix and still needs local verification after pulling that fix.

## Project goals

The system is intended to connect the full Taala operational flow:

```text
Borehole / Purchases
        ↓
Raw Materials & Inventory
        ↓
Production
        ↓
Finished Product Inventory
        ↓
Sales
        ↓
Delivery
        ↓
Accounting & Reports
```

The long-term goal is to automate routine work as much as practical, keep an audit trail of stock changes, reduce manual stock manipulation, and provide reliable business reporting.

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

## Current project status

### Phase 1 — Requirements & Architecture

Status: **Mostly complete**

The main business flow and module roadmap have been agreed:

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

Important architecture decisions already made:

- Inventory balances are derived from stock movements rather than manually edited totals.
- Borehole raw water is intended to be meter-driven, not manually entered during normal operation.
- Finished products will be created through Production rather than normal stock receiving.
- Manual adjustments must be auditable.
- Existing legacy modules remain in place during the V2 transition instead of being destroyed prematurely.

### Phase 2 — Inventory & SKU Foundation

Status: **Functionally complete and tested**

#### Inventory master SKUs

The V2 inventory catalog currently contains the following master items:

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

Caps, seals and outer packaging are intentionally generic for now because Taala has not yet confirmed whether they vary by bottle size.

#### Stock movement ledger

Inventory balances come from the signed sum of records in `stock_movements`.

Example:

```text
Opening Balance     +1000
Damage                -25
Stock Received       +500
-------------------------
Live Balance         1475
```

This makes inventory changes traceable and avoids directly overwriting a stock total.

#### Inventory page

The V2 Inventory page shows:

- SKU
- item name
- category
- unit
- live stock balance
- reorder level
- stock status

Balances are calculated using database aggregation rather than one query per inventory item.

#### Opening balances and stock receiving

Admins can record:

- Opening Balance
- Stock Received

Safeguards include:

- Raw borehole water cannot be manually received through this form.
- Finished products cannot be normally received as purchased stock; they must eventually come from Production.
- An inventory item can only have one opening balance.
- Normal stock receipts require a reference.
- Duplicate stock-receipt references for the same item are rejected.

#### Stock movement history

The Movement History page provides an audit trail containing:

- date/time
- SKU
- inventory item
- movement type
- signed quantity change
- reference
- user/system source
- notes

The history can be filtered by inventory item, movement type and date range.

#### Reorder levels and low-stock detection

Admins can define reorder levels for inventory items.

The system automatically identifies items whose live stock balance is at or below the configured reorder level.

Raw borehole water is excluded from purchasing reorder logic because it is meter-managed.

#### Stock adjustments

Manual inventory adjustments are supported for:

- damage / breakage
- wastage
- stock count correction increase
- stock count correction decrease

Every adjustment records the responsible user, date/time, quantity, optional reference and a required reason/notes field.

A stock decrease cannot remove more stock than the current live balance.

### Automated borehole water inventory

Status: **Software foundation complete and tested; physical meter connection pending**

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

The software foundation includes:

- secure meter registration
- UUID public meter IDs
- hashed meter authentication tokens
- reading history
- unit normalization to litres
- baseline readings
- automatic positive delta calculation
- idempotency protection
- anomaly review for backwards/invalid readings
- optional maximum-flow sanity checks
- database locking during ingestion
- automatic raw-water stock movements

Supported reading units currently include litres and cubic metres.

The live hardware integration cannot be finalized until the exact meter model/interface is known. Possible interfaces include:

- HTTP/HTTPS
- Modbus TCP
- Modbus RTU / RS485
- pulse output
- MQTT
- PLC / IoT gateway integration

A purely mechanical meter with no electronic output would require an external sensor/gateway or a compatible digital flow meter.

### Phase 3 — Production Management

Status: **Started**

#### Phase 3A — Production recipe foundation

The production recipe data model has been added so each finished-water SKU can be linked to the materials required to produce it.

New production tables:

```text
production_recipes
production_recipe_components
```

The design supports a structure such as:

```text
Finished Water 500 ml
    ├── Raw Water
    ├── Empty Bottle 500 ml
    ├── Cap
    ├── Label 500 ml
    └── Seal
```

The actual Taala recipe quantities have deliberately **not** been invented yet. They must be confirmed before automatic production deductions are enabled.

The production migration originally hit MySQL's identifier-length limit because Laravel generated an overly long composite unique-index name. It has now been changed to the shorter explicit index name:

```text
prod_recipe_component_unique
```

Local migration/test verification should be completed after pulling the latest branch update.

## Existing legacy modules

The original Taala system already contains working or partially working modules that remain available while V2 is developed.

### Authentication and admin access

- login/authentication
- admin middleware
- protected admin routes
- profile routes

### Products

Existing product CRUD supports:

- create
- list
- edit
- delete
- selling price
- cost price

### Legacy stocks

The original `stocks` module still exists. V2 inventory is being built around `inventory_items` and `stock_movements`, so the old stock module is not yet being removed.

### Sales

Existing sales functionality includes:

- sales entry
- product selection
- quantity sold
- sale price
- total amount
- sale date
- stock deduction stabilization
- PDF export
- Excel export

Sales transactions use database transactions and stock locking to reduce inconsistent deductions.

### Dashboard

The dashboard currently includes cards and charts for:

- products
- stock
- sales
- revenue
- profit
- sales over time
- product sales distribution
- stock levels
- monthly profit
- low-stock information

### Profit calculation

Current profit reporting uses:

```text
(sale price - current product cost price) × quantity sold
```

This is acceptable for the current legacy module but is not yet a historical costing system. Future accounting/production work should preserve the cost basis that applied at the time of sale or production.

### Trucks / logistics

A starter Truck module exists from the original system, but the future V2 logistics design is expected to use a more flexible vehicle structure so Taala can support both:

- motorbikes for bottled-water deliveries
- tanker trucks for bulk-water deliveries

Drivers, delivery notes and delivery logs are planned later.

## Database areas currently introduced by V2

The V2 foundation currently includes these main tables:

```text
inventory_items
stock_movements
water_meters
water_meter_readings
production_recipes
production_recipe_components
```

### `inventory_items`

Stores the inventory/SKU master catalog.

Important fields include:

- SKU
- name
- category
- unit
- reorder level
- sellable flag
- active flag

### `stock_movements`

Stores every signed stock change.

Important fields include:

- inventory item
- movement type
- quantity delta
- source type / source ID
- user who created it
- occurrence time
- reference
- notes

### `water_meters`

Stores registered borehole meter devices and their secure configuration.

### `water_meter_readings`

Stores meter readings, normalized litre values, computed deltas and review status.

### `production_recipes`

Stores one production recipe for a finished inventory product.

### `production_recipe_components`

Stores the input inventory items and quantities required by a recipe.

## Installation / local setup

Clone the repository and switch to the V2 branch:

```bash
git clone https://github.com/Blacops111/taalawaters.git
cd taalawaters
git checkout taala-v2-development
cd water-system
```

Install PHP dependencies:

```bash
composer install
```

Create the environment file if needed:

```bash
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`.

Example keys:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=water_system
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password
```

Do not commit `.env` or real credentials.

Install frontend dependencies:

```bash
npm install
```

Run migrations:

```bash
php artisan migrate
```

Seed only the V2 inventory master catalog when required:

```bash
php artisan db:seed --class=InventoryItemSeeder
```

Do **not** use the general database seeder blindly on an existing environment, because `DatabaseSeeder` may create test data.

Start the Laravel development server:

```bash
php artisan serve
```

For frontend development:

```bash
npm run dev
```

## Tests completed during V2 development

Focused tests have been added for the work completed so far.

```bash
php artisan test --filter=WaterMeterReadingTest
php artisan test --filter=InventoryItemSeederTest
php artisan test --filter=InventoryPageTest
php artisan test --filter=InventoryReceiptTest
php artisan test --filter=InventoryMovementHistoryTest
php artisan test --filter=InventoryLowStockTest
php artisan test --filter=InventoryAdjustmentTest
php artisan test --filter=ProductionRecipeFoundationTest
```

The inventory and borehole tests above have been confirmed locally during development.

The Production Recipe Foundation test should be rerun after the latest MySQL migration compatibility fix is pulled and migrated successfully.

To run the complete test suite:

```bash
php artisan test
```

## Borehole meter registration

A meter can be registered using:

```bash
php artisan taala:register-water-meter
```

Available options include meter name, serial number, protocol, unit and maximum flow.

Do not register the real production meter until its hardware communication method and reading unit have been confirmed.

The registration command displays the meter token once. The system stores only its SHA-256 hash, so the plaintext token must be stored securely outside the database.

## Security principles

Security is being treated as a core project requirement.

Current and planned controls include:

- Laravel authentication
- role-based access control
- CSRF protection
- request validation
- Eloquent / query builder instead of unsafe SQL concatenation
- password hashing
- restricted admin routes
- database transactions for critical stock operations
- row locking where concurrent inventory changes could conflict
- rate limiting on the water-meter API
- hashed meter tokens
- idempotency protection for meter readings
- audit trails for inventory changes
- safe upload handling when file modules are added
- production `APP_DEBUG=false`
- environment-based secrets
- backups and recovery planning

No software system can be guaranteed to be literally unhackable. The goal is to reduce attack surface, follow secure defaults and test important authorization/data-integrity paths before deployment.

## Performance principles

The project should remain efficient as data volume grows.

Current/planned practices include:

- indexed foreign keys and frequently queried fields
- pagination
- aggregate SQL queries for stock balances
- eager loading where appropriate
- avoiding N+1 queries
- efficient dashboard queries
- queues for heavy reports/exports when needed
- caching only where justified
- avoiding unnecessary polling
- load/performance testing before production deployment

## Development rules

When extending Taala V2:

- Make small, focused changes.
- Inspect existing models, migrations, routes and controllers before modifying schema or behavior.
- Do not invent business rules when Taala has not confirmed them.
- Preserve existing working data.
- Do not directly overwrite inventory balances; use stock movements.
- Do not manually enter routine borehole raw-water quantities.
- Do not bypass authentication, authorization, CSRF or validation to make a feature work.
- Keep secrets out of source control.
- Avoid unrelated refactors while adding a feature.
- Add focused tests for important stock, financial and authorization behavior.
- Fix errors before moving to the next module.

## Next planned work

The immediate next step after verifying the production recipe migration is:

### Phase 3B — Production Recipe Setup

This will provide an admin interface to configure the actual materials and quantities required for each finished product.

After recipes are confirmed, later production steps will cover:

```text
Start production batch
        ↓
Validate material availability
        ↓
Consume raw water / bottles / caps / labels / seals
        ↓
Create finished-product stock
        ↓
Record full movement history
```

Production deductions will only be enabled after the real Taala recipe rules have been confirmed.

## Future roadmap

Planned work after Production includes:

- customer management
- improved sales flow
- automatic selling-price selection
- purchasing / purchase requests
- supplier management
- delivery notes
- drivers
- vehicles / motorbikes / tanker trucks
- delivery tracking
- accounting integration
- trial balance
- balance sheet
- richer reports
- expanded role permissions
- security hardening
- performance testing
- backups / monitoring
- production deployment

## Project status note

This README reflects the project state on the `taala-v2-development` branch as of **15 September 2026**. It should be updated as each phase is completed and verified.
