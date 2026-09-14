<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use Illuminate\Database\Seeder;

class InventoryItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'sku' => 'RAW-WATER',
                'name' => 'Raw Borehole Water',
                'category' => 'raw_water',
                'unit' => 'litre',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'EB-500ML',
                'name' => 'Empty Bottle 500 ml',
                'category' => 'empty_bottle',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'EB-1L',
                'name' => 'Empty Bottle 1 Litre',
                'category' => 'empty_bottle',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'EB-10L',
                'name' => 'Empty Bottle 10 Litre',
                'category' => 'empty_bottle',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'EB-20L',
                'name' => 'Empty Bottle 20 Litre',
                'category' => 'empty_bottle',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'CAP',
                'name' => 'Bottle Cap',
                'category' => 'cap',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'LABEL-500ML',
                'name' => 'Bottle Label 500 ml',
                'category' => 'label',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'LABEL-1L',
                'name' => 'Bottle Label 1 Litre',
                'category' => 'label',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'LABEL-10L',
                'name' => 'Bottle Label 10 Litre',
                'category' => 'label',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'LABEL-20L',
                'name' => 'Bottle Label 20 Litre',
                'category' => 'label',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'SEAL',
                'name' => 'Bottle Seal',
                'category' => 'seal',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'PACKAGING',
                'name' => 'Outer Packaging Material',
                'category' => 'packaging_material',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => false,
                'is_active' => true,
            ],
            [
                'sku' => 'FW-500ML',
                'name' => 'Finished Water 500 ml',
                'category' => 'finished_product',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => true,
                'is_active' => true,
            ],
            [
                'sku' => 'FW-1L',
                'name' => 'Finished Water 1 Litre',
                'category' => 'finished_product',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => true,
                'is_active' => true,
            ],
            [
                'sku' => 'FW-10L',
                'name' => 'Finished Water 10 Litre',
                'category' => 'finished_product',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => true,
                'is_active' => true,
            ],
            [
                'sku' => 'FW-20L',
                'name' => 'Finished Water 20 Litre',
                'category' => 'finished_product',
                'unit' => 'unit',
                'reorder_level' => 0,
                'is_sellable' => true,
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            InventoryItem::updateOrCreate(
                ['sku' => $item['sku']],
                $item
            );
        }
    }
}
