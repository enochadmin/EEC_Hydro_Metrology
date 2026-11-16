<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which parameter a station is equipped to record, with its unit and sensor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitoring_station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parameter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_unit_id')->nullable()->constrained('measurement_units')->nullOnDelete();

            $table->string('sensor_reference', 100)->nullable();
            $table->decimal('depth_m', 6, 2)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['monitoring_station_id', 'parameter_id'], 'station_parameter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_parameters');
    }
};
