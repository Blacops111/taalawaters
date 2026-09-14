<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finished_product_id')
                ->unique()
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->string('name');
            $table->decimal('output_quantity', 16, 3)->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('production_recipe_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_recipe_id')
                ->constrained('production_recipes')
                ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->decimal('quantity_required', 16, 3);
            $table->timestamps();

            $table->unique([
                'production_recipe_id',
                'inventory_item_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_recipe_components');
        Schema::dropIfExists('production_recipes');
    }
};
