<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_runs', function (Blueprint $table) {
            $table->uuid('completion_key')->nullable()->unique();
            $table->string('completion_fingerprint', 64)->nullable();
        });
        Schema::create('production_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_run_id')->constrained();
            $table->foreignId('operating_expense_id')->constrained();
            $table->foreignId('original_journal_id')->constrained('journal_entries');
            $table->foreignId('expense_account_id')->constrained('accounting_accounts');
            $table->bigInteger('amount_cents');
            $table->string('basis', 300);
            $table->foreignId('allocated_by')->constrained('users');
            $table->timestamps();
            // Explicit short names remain within MySQL's identifier limit.
            $table->unique(['production_run_id', 'operating_expense_id'], 'production_cost_run_expense_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_cost_allocations');
        Schema::table('production_runs', function (Blueprint $table) {
            $table->dropUnique(['completion_key']);
            $table->dropColumn(['completion_key', 'completion_fingerprint']);
        });
    }
};
