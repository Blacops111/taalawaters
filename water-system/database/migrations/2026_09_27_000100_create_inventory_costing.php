<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_costing_setups', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
        });
        DB::table('inventory_costing_setups')->insert(['id' => 1, 'is_active' => false]);
        Schema::create('inventory_cost_balances', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->primary()->constrained();
            $table->bigInteger('opening_quantity_milli');
            $table->bigInteger('opening_value_cents');
            $table->bigInteger('quantity_milli');
            $table->bigInteger('value_cents');
            $table->dateTime('last_occurred_at');
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->bigInteger('value_delta_cents')->nullable();
            $table->foreignId('reverses_movement_id')->nullable()->unique()->constrained('stock_movements');
        });
        Schema::table('purchase_receipt_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->bigInteger('receipt_value_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_receipt_items', fn (Blueprint $table) => $table->dropColumn(['unit_cost', 'receipt_value_cents']));
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['reverses_movement_id']);
            $table->dropUnique(['reverses_movement_id']);
            $table->dropColumn(['value_delta_cents', 'reverses_movement_id']);
        });
        Schema::dropIfExists('inventory_cost_balances');
        Schema::dropIfExists('inventory_costing_setups');
    }
};
