<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('retail_price', 16, 2)->nullable()->after('reorder_level');
            $table->decimal('wholesale_price', 16, 2)->nullable()->after('retail_price');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['retail_price', 'wholesale_price']);
        });
    }
};
