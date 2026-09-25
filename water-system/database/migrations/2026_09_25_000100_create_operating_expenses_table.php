<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->foreignId('expense_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->foreignId('payment_account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $table->date('expense_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('description', 300);
            $table->string('payee', 150)->nullable();
            $table->string('external_reference', 150)->nullable()->index();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->timestamps();
            $table->unique(['created_by', 'idempotency_key'], 'expense_user_submission_unique');
            $table->index(['expense_account_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_expenses');
    }
};
