<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_recipe_id')
                ->constrained('production_recipes')
                ->restrictOnDelete();
            $table->foreignId('finished_product_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->decimal('quantity_produced', 16, 3);
            $table->decimal('recipe_output_quantity', 16, 3);
            $table->string('status', 30)->default('completed')->index();
            $table->dateTime('occurred_at')->index();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('reference', 100)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['finished_product_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_runs');
    }
};
