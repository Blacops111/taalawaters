<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('driver_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('assigned_at')->index();
            $table->dateTime('unassigned_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(
                ['driver_id', 'unassigned_at'],
                'vehicle_assignments_driver_active_lookup'
            );

            $table->index(
                ['vehicle_id', 'unassigned_at'],
                'vehicle_assignments_vehicle_active_lookup'
            );

            $table->index(
                ['driver_id', 'vehicle_id', 'assigned_at'],
                'vehicle_assignments_history_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_assignments');
    }
};
