<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'suppliers'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->unsignedSmallInteger('payment_terms_days')->nullable());
        }
        // Deliberately no backfill: current terms cannot establish historic contractual due dates.
        foreach (['sales_orders', 'purchase_receipts'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->date('due_date')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['sales_orders', 'purchase_receipts'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('due_date'));
        }
        foreach (['customers', 'suppliers'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('payment_terms_days'));
        }
    }
};
