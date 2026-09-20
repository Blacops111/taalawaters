<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_record_can_store_core_logistics_details(): void
    {
        $driver = Driver::create([
            'name' => 'Test Driver',
            'phone' => '+254700000001',
            'license_number' => 'DL-TEST-001',
            'license_expiry' => '2027-12-31',
            'is_active' => true,
            'notes' => 'Phase 6 foundation driver.',
        ]);

        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'name' => 'Test Driver',
            'license_number' => 'DL-TEST-001',
            'is_active' => true,
        ]);

        $this->assertTrue($driver->is_active);
        $this->assertSame('2027-12-31', $driver->license_expiry->format('Y-m-d'));
    }

    public function test_flexible_vehicle_records_support_motorbike_and_tanker_truck(): void
    {
        $motorbike = Vehicle::create([
            'registration_number' => 'KME-001A',
            'name' => 'Bottle Delivery Bike',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'capacity_quantity' => 6,
            'capacity_unit' => 'crates',
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $tanker = Vehicle::create([
            'registration_number' => 'KTA-001A',
            'name' => 'Bulk Water Tanker',
            'vehicle_type' => Vehicle::TYPE_TANKER_TRUCK,
            'capacity_quantity' => 10000,
            'capacity_unit' => 'litre',
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $motorbike->id,
            'vehicle_type' => 'motorbike',
            'capacity_unit' => 'crates',
        ]);

        $this->assertDatabaseHas('vehicles', [
            'id' => $tanker->id,
            'vehicle_type' => 'tanker_truck',
            'capacity_unit' => 'litre',
        ]);

        $this->assertNotSame($motorbike->vehicle_type, $tanker->vehicle_type);
    }

    public function test_vehicle_registration_number_and_driver_license_are_unique(): void
    {
        Driver::create([
            'name' => 'Driver One',
            'license_number' => 'DL-UNIQUE-001',
            'is_active' => true,
        ]);

        Vehicle::create([
            'registration_number' => 'KUN-001A',
            'vehicle_type' => Vehicle::TYPE_MOTORBIKE,
            'status' => Vehicle::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Driver::create([
            'name' => 'Driver Two',
            'license_number' => 'DL-UNIQUE-001',
            'is_active' => true,
        ]);
    }
}
