<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->string('category', 50)->index();
            $table->string('unit', 30);
            $table->decimal('reorder_level', 16, 3)->default(0);
            $table->boolean('is_sellable')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->string('movement_type', 50)->index();
            $table->decimal('quantity_delta', 16, 3);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('occurred_at')->index();
            $table->string('reference', 150)->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['inventory_item_id', 'occurred_at']);
        });

        Schema::create('water_meters', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->string('name');
            $table->string('serial_number')->nullable()->unique();
            $table->string('protocol', 50)->default('http_push');
            $table->string('reading_unit', 30)->default('litre');
            $table->string('location')->nullable();
            $table->string('token_hash', 64);
            $table->decimal('max_flow_litres_per_minute', 16, 3)->nullable();
            $table->decimal('last_reading_litres', 18, 3)->nullable();
            $table->dateTime('last_reading_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('water_meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_meter_id')
                ->constrained('water_meters')
                ->cascadeOnDelete();
            $table->string('idempotency_key', 120);
            $table->decimal('reading_value', 18, 3);
            $table->decimal('normalized_litres', 18, 3);
            $table->decimal('delta_litres', 18, 3)->nullable();
            $table->string('status', 30)->index();
            $table->string('review_reason')->nullable();
            $table->dateTime('reading_at')->index();
            $table->dateTime('received_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['water_meter_id', 'idempotency_key']);
            $table->index(['water_meter_id', 'reading_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_meter_readings');
        Schema::dropIfExists('water_meters');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_items');
    }
};
