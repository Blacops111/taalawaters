<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('phone', 50)->nullable();
            $table->string('license_number', 100)->nullable()->unique();
            $table->date('license_expiry')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number', 50)->unique();
            $table->string('name')->nullable();
            $table->string('vehicle_type', 50)->index();
            $table->decimal('capacity_quantity', 16, 3)->nullable();
            $table->string('capacity_unit', 30)->nullable();
            $table->string('status', 30)->default('available')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vehicle_type', 'status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('drivers');
    }
};
