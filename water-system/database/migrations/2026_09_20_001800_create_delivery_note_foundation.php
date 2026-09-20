<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->nullable()->unique();

            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->restrictOnDelete();

            $table->foreignId('vehicle_assignment_id')
                ->nullable()
                ->constrained('vehicle_assignments')
                ->restrictOnDelete();

            $table->string('status', 30)->default('draft')->index();
            $table->text('delivery_address')->nullable();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('dispatched_at')->nullable()->index();
            $table->dateTime('delivered_at')->nullable()->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sales_order_id', 'status']);
            $table->index(['vehicle_assignment_id', 'status']);
        });

        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_note_id')
                ->constrained('delivery_notes')
                ->cascadeOnDelete();

            $table->foreignId('sales_order_item_id')
                ->constrained('sales_order_items')
                ->restrictOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();

            $table->decimal('quantity', 16, 3);
            $table->timestamps();

            $table->unique(
                ['delivery_note_id', 'sales_order_item_id'],
                'delivery_note_sale_item_unique'
            );

            $table->index(
                ['inventory_item_id', 'delivery_note_id'],
                'delivery_note_inventory_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
        Schema::dropIfExists('delivery_notes');
    }
};
