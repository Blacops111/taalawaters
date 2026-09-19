<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('submitted_by')
                ->nullable()
                ->after('requested_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('submitted_at')
                ->nullable()
                ->after('submitted_by')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropColumn(['submitted_by', 'submitted_at']);
        });
    }
};
