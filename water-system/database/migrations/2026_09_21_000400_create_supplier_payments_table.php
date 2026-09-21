<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->foreignId('purchase_receipt_id')
                ->constrained('purchase_receipts')
                ->restrictOnDelete();

            $table->foreignId('payment_account_id')
                ->constrained('accounting_accounts')
                ->restrictOnDelete();

            $table->date('payment_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('external_reference', 150)->nullable()->index();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'payment_date']);
            $table->index(['purchase_receipt_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
