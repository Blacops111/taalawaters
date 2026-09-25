<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_expense_reversals', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('operating_expense_id')->constrained('operating_expenses')->restrictOnDelete();
            $table->unique('operating_expense_id', 'expense_reversal_expense_unique');
            $table->foreignId('original_journal_id')->constrained('journal_entries')->restrictOnDelete();
            $table->unique('original_journal_id', 'expense_reversal_journal_unique');
            $table->date('reversal_date')->index();
            $table->string('reason', 300);
            $table->foreignId('reversed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_expense_reversals');
    }
};
