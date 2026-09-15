<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_run_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_run_id')
                ->unique()
                ->constrained('production_runs')
                ->restrictOnDelete();
            $table->foreignId('reversed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('reversed_at')->index();
            $table->string('reference', 100)->unique();
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_run_reversals');
    }
};
