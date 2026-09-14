<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\WaterMeter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RegisterWaterMeter extends Command
{
    protected $signature = 'taala:register-water-meter
        {--name=Borehole Main Flow Meter}
        {--serial=}
        {--protocol=http_push}
        {--unit=litre}
        {--max-flow=}';

    protected $description = 'Register the Taala borehole water meter and generate its API token';

    public function handle(): int
    {
        $unit = strtolower((string) $this->option('unit'));
        $supportedUnits = ['litre', 'liter', 'litres', 'liters', 'l', 'm3', 'm³', 'cubic_meter', 'cubic_metre'];

        if (!in_array($unit, $supportedUnits, true)) {
            $this->error('Unsupported meter unit. Use litre or m3.');
            return self::FAILURE;
        }

        $serial = $this->option('serial') ?: null;

        if ($serial && WaterMeter::where('serial_number', $serial)->exists()) {
            $this->error('A water meter with that serial number already exists.');
            return self::FAILURE;
        }

        $rawWater = InventoryItem::firstOrCreate(
            ['sku' => 'RAW-WATER'],
            [
                'name' => 'Raw Borehole Water',
                'category' => 'raw_water',
                'unit' => 'litre',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ]
        );

        $token = bin2hex(random_bytes(32));
        $maxFlow = $this->option('max-flow');

        if ($maxFlow !== null && $maxFlow !== '' && !is_numeric($maxFlow)) {
            $this->error('--max-flow must be a numeric litres-per-minute value.');
            return self::FAILURE;
        }

        $meter = WaterMeter::create([
            'public_id' => (string) Str::uuid(),
            'inventory_item_id' => $rawWater->id,
            'name' => (string) $this->option('name'),
            'serial_number' => $serial,
            'protocol' => (string) $this->option('protocol'),
            'reading_unit' => $unit,
            'token_hash' => hash('sha256', $token),
            'max_flow_litres_per_minute' => ($maxFlow === null || $maxFlow === '')
                ? null
                : (float) $maxFlow,
            'is_active' => true,
        ]);

        $this->newLine();
        $this->info('Water meter registered successfully.');
        $this->line('Meter ID: '.$meter->public_id);
        $this->line('API token: '.$token);
        $this->warn('Save the API token now. Only its hash is stored in the database.');
        $this->newLine();

        return self::SUCCESS;
    }
}
