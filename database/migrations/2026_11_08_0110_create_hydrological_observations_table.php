<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-series core.
 *
 * Duplicate strategy: a reading is identified by
 * (station, parameter, measured_at, series_no). `series_no` exists because a
 * station can legitimately hold more than one reading for the same parameter
 * and timestamp — replicate gaugings, co-located sensors or a second intake.
 * A second reading must be given series_no = 2, so accidental duplicates are
 * rejected by the database instead of silently doubling the series.
 *
 * `measured_at` stores Ethiopian local time (EAT, UTC+3, no daylight saving).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hydrological_observations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('monitoring_station_id')->constrained()->restrictOnDelete();
            $table->foreignId('parameter_id')->constrained()->restrictOnDelete();
            $table->foreignId('measurement_unit_id')->nullable()->constrained('measurement_units')->restrictOnDelete();

            $table->dateTime('measured_at');
            $table->decimal('value', 18, 6);
            $table->unsignedTinyInteger('series_no')->default(1);
            $table->enum('record_type', ['observed', 'derived', 'estimated'])->default('observed');
            $table->enum('quality_status', ['pending', 'approved', 'flagged', 'rejected'])->default('pending');

            $table->foreignId('data_source_id')->nullable()->constrained('data_sources')->nullOnDelete();
            $table->foreignId('data_import_batch_id')->nullable()->constrained('data_import_batches')->nullOnDelete();
            $table->unsignedInteger('import_row_number')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['monitoring_station_id', 'parameter_id', 'measured_at', 'series_no'],
                'observation_unique_reading',
            );
            $table->index(['monitoring_station_id', 'parameter_id', 'measured_at'], 'observation_series_index');
            $table->index(['parameter_id', 'measured_at'], 'observation_parameter_index');
            $table->index('measured_at');
            $table->index('quality_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hydrological_observations');
    }
};
