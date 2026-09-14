<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\WaterMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaterMeterReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_meter_readings_automatically_create_raw_water_stock_movements(): void
    {
        [$meter, $token] = $this->makeMeter();

        $this->withHeader('X-Meter-Token', $token)
            ->postJson('/api/water-meters/readings', [
                'meter_id' => $meter->public_id,
                'reading_value' => 1000,
                'reading_at' => '2026-09-14T10:00:00+03:00',
                'idempotency_key' => 'reading-001',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'baseline');

        $this->assertSame(0, StockMovement::count());

        $this->withHeader('X-Meter-Token', $token)
            ->postJson('/api/water-meters/readings', [
                'meter_id' => $meter->public_id,
                'reading_value' => 1250,
                'reading_at' => '2026-09-14T10:05:00+03:00',
                'idempotency_key' => 'reading-002',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'accepted')
            ->assertJsonPath('delta_litres', 250);

        $this->assertEquals(250.0, (float) StockMovement::sum('quantity_delta'));

        $this->withHeader('X-Meter-Token', $token)
            ->postJson('/api/water-meters/readings', [
                'meter_id' => $meter->public_id,
                'reading_value' => 1250,
                'reading_at' => '2026-09-14T10:05:00+03:00',
                'idempotency_key' => 'reading-002',
            ])
            ->assertOk();

        $this->assertSame(1, StockMovement::count());
    }

    public function test_backwards_meter_reading_is_flagged_without_changing_stock(): void
    {
        [$meter, $token] = $this->makeMeter();

        $this->withHeader('X-Meter-Token', $token)
            ->postJson('/api/water-meters/readings', [
                'meter_id' => $meter->public_id,
                'reading_value' => 1000,
                'reading_at' => '2026-09-14T10:00:00+03:00',
                'idempotency_key' => 'reading-001',
            ])
            ->assertCreated();

        $this->withHeader('X-Meter-Token', $token)
            ->postJson('/api/water-meters/readings', [
                'meter_id' => $meter->public_id,
                'reading_value' => 900,
                'reading_at' => '2026-09-14T10:05:00+03:00',
                'idempotency_key' => 'reading-002',
            ])
            ->assertStatus(202)
            ->assertJsonPath('status', 'needs_review');

        $this->assertSame(0, StockMovement::count());
    }

    private function makeMeter(): array
    {
        $item = InventoryItem::create([
            'sku' => 'RAW-WATER',
            'name' => 'Raw Borehole Water',
            'category' => 'raw_water',
            'unit' => 'litre',
            'reorder_level' => 0,
            'is_sellable' => false,
            'is_active' => true,
        ]);

        $token = bin2hex(random_bytes(32));

        $meter = WaterMeter::create([
            'public_id' => (string) Str::uuid(),
            'inventory_item_id' => $item->id,
            'name' => 'Borehole Main Flow Meter',
            'protocol' => 'http_push',
            'reading_unit' => 'litre',
            'token_hash' => hash('sha256', $token),
            'is_active' => true,
        ]);

        return [$meter, $token];
    }
}
