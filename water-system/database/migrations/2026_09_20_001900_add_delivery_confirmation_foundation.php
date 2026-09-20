<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 50)->nullable();

            $table->string('confirmation_code_hash')->nullable();
            $table->dateTime('confirmation_code_generated_at')->nullable();
            $table->dateTime('confirmation_code_expires_at')
                ->nullable()
                ->index('delivery_confirmation_expiry_idx');
            $table->dateTime('confirmation_code_last_sent_at')->nullable();
            $table->unsignedTinyInteger('confirmation_code_failed_attempts')->default(0);
            $table->dateTime('confirmation_code_locked_at')
                ->nullable()
                ->index('delivery_confirmation_lock_idx');
            $table->dateTime('confirmation_code_verified_at')
                ->nullable()
                ->index('delivery_confirmation_verified_idx');

            $table->foreignId('confirmation_code_verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropForeign(['confirmation_code_verified_by']);
            $table->dropIndex('delivery_confirmation_expiry_idx');
            $table->dropIndex('delivery_confirmation_lock_idx');
            $table->dropIndex('delivery_confirmation_verified_idx');

            $table->dropColumn([
                'recipient_name',
                'recipient_phone',
                'confirmation_code_hash',
                'confirmation_code_generated_at',
                'confirmation_code_expires_at',
                'confirmation_code_last_sent_at',
                'confirmation_code_failed_attempts',
                'confirmation_code_locked_at',
                'confirmation_code_verified_at',
                'confirmation_code_verified_by',
            ]);
        });
    }
};
