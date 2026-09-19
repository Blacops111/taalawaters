<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->nullable()->unique();

            $table->foreignId('purchase_request_id')
                ->constrained('purchase_requests')
                ->restrictOnDelete();

            $table->string('supplier_delivery_reference', 150)->nullable()->index();

            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('received_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_request_id', 'received_at']);
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_receipt_id')
                ->constrained('purchase_receipts')
                ->cascadeOnDelete();

            $table->foreignId('purchase_request_item_id')
                ->constrained('purchase_request_items')
                ->restrictOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();

            $table->decimal('quantity', 16, 3);
            $table->timestamps();

            $table->unique(
                ['purchase_receipt_id', 'purchase_request_item_id'],
                'purchase_receipt_request_item_unique'
            );

            $table->index(
                ['inventory_item_id', 'purchase_receipt_id'],
                'purchase_receipt_inventory_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
    }
};
