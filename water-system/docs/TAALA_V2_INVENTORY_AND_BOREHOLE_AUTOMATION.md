# Taala V2 Inventory and Borehole Automation

## Goal

Taala V2 should avoid routine manual stock entry wherever reliable automation is possible. Raw water comes from the borehole, so the normal source of truth for raw-water intake will be the borehole flow meter rather than a person typing litres into a form.

## Automated raw-water flow

Borehole -> flow meter / meter box -> device or gateway -> Taala API -> water meter reading -> stock movement -> raw-water balance

The first cumulative meter reading is stored as a baseline and does not add historical water to stock. Every later accepted increase is converted to litres and the difference is automatically posted as a `borehole_extraction` stock movement.

Example:

- First reading: 10,000 L -> baseline only
- Next reading: 10,420 L -> +420 L raw-water stock
- Next reading: 10,900 L -> +480 L raw-water stock

No daily manual raw-water entry is required during normal operation.

## Meter safety and data quality

The integration includes:

- a unique meter ID and high-entropy API token;
- only the token hash is stored in the database;
- rate limiting on the meter-reading endpoint;
- idempotency keys so a retried reading cannot add stock twice;
- database locking and transactions so concurrent readings cannot double-count;
- automatic conversion from m3 to litres when required;
- backwards/reset readings are flagged for review and do not change stock;
- an optional maximum-flow limit can flag physically impossible jumps;
- the first reading is treated as a baseline.

## Hardware integration

The Laravel side is protocol-neutral. The physical flow meter still determines how readings reach Taala. Common possibilities are:

- HTTP/HTTPS API directly from a smart meter;
- Modbus TCP;
- Modbus RTU / RS485 through an industrial gateway;
- pulse output through an ESP32, PLC, Raspberry Pi, or industrial IoT gateway;
- MQTT through a gateway.

Once the exact meter make/model or communication interface is known, the correct adapter can be added without changing the inventory ledger.

## Inventory foundation

The new inventory ledger uses `inventory_items` and `stock_movements`.

Inventory items can represent:

- raw water;
- empty bottles;
- caps;
- labels;
- seals and other packaging;
- finished water products.

Each item has a SKU/code, category, unit of measure, reorder level, and active/sellable flags. Stock is derived from signed stock movements rather than silently overwriting a quantity.

This gives Taala an auditable history for purchases, borehole extraction, production consumption, finished production, sales, wastage, returns, and approved adjustments.

## Phase position

Completed foundation in this step:

- unified inventory-item ledger schema;
- stock-movement ledger;
- secure borehole meter registry;
- meter-reading history;
- automated raw-water stock posting;
- duplicate/replay protection;
- anomaly review handling;
- automated tests for normal and backwards readings.

Next step: connect Taala's finished products and packaging materials to the inventory ledger, assign/confirm SKUs, and then build production consumption/output automation.
