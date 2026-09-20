<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->string('recipient_email')->nullable();

            $table->dateTime('confirmation_code_sms_sent_at')
                ->nullable()
                ->index('delivery_confirmation_sms_sent_idx');

            $table->dateTime('confirmation_code_email_sent_at')
                ->nullable()
                ->index('delivery_confirmation_email_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropIndex('delivery_confirmation_sms_sent_idx');
            $table->dropIndex('delivery_confirmation_email_sent_idx');

            $table->dropColumn([
                'recipient_email',
                'confirmation_code_sms_sent_at',
                'confirmation_code_email_sent_at',
            ]);
        });
    }
};
