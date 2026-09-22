<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->restrictOnDelete();

            $table->foreignId('payment_account_id')
                ->constrained('accounting_accounts')
                ->restrictOnDelete();

            $table->uuid('idempotency_key')->unique();
            $table->date('payment_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('external_reference', 150)->nullable()->index();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'payment_date']);
            $table->index(['sales_order_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};
