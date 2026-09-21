<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('type', 30)->index();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('accounting_accounts')
                ->restrictOnDelete();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->date('entry_date')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->string('description', 500);
            $table->nullableMorphs('source');
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->constrained('journal_entries')
                ->nullOnDelete();
            $table->foreignId('posted_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('posted_at')->nullable()->index();
            $table->timestamps();

            $table->index(['source_type', 'source_id', 'status']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')
                ->constrained('journal_entries')
                ->cascadeOnDelete();
            $table->foreignId('accounting_account_id')
                ->constrained('accounting_accounts')
                ->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('memo', 500)->nullable();
            $table->timestamps();

            $table->index(['accounting_account_id', 'journal_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounting_accounts');
    }
};
