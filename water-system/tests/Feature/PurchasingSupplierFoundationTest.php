<?php

namespace Tests\Feature;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingSupplierFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_record_can_store_core_purchasing_contact_details(): void
    {
        $supplier = Supplier::create([
            'name' => 'Test Packaging Supplier',
            'contact_person' => 'Jane Supplier',
            'phone' => '0712345678',
            'email' => 'orders@supplier.test',
            'address' => 'Nairobi, Kenya',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Test Packaging Supplier',
            'contact_person' => 'Jane Supplier',
            'phone' => '0712345678',
            'email' => 'orders@supplier.test',
            'address' => 'Nairobi, Kenya',
            'is_active' => true,
        ]);

        $this->assertTrue($supplier->fresh()->is_active);
    }

    public function test_supplier_can_be_deactivated_without_deleting_the_record(): void
    {
        $supplier = Supplier::create([
            'name' => 'Inactive Supplier Test',
            'is_active' => true,
        ]);

        $supplier->update(['is_active' => false]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Inactive Supplier Test',
            'is_active' => false,
        ]);

        $this->assertNotNull(Supplier::find($supplier->id));
        $this->assertFalse($supplier->fresh()->is_active);
    }
}
