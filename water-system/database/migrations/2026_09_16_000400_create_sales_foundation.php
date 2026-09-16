<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 50)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->string('reference', 100)->nullable()->unique();
            $table->string('status', 30)->default('draft')->index();
            $table->dateTime('sale_at')->index();
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'sale_at']);
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->cascadeOnDelete();
            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();
            $table->decimal('quantity', 16, 3);
            $table->decimal('unit_price', 16, 2);
            $table->decimal('line_total', 16, 2);
            $table->timestamps();

            $table->index(['sales_order_id', 'inventory_item_id'], 'sale_order_item_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('customers');
    }
};
