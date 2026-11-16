<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monitoring network. A station always belongs to a basin and may optionally
 * sit inside a sub-basin and a watershed.
 *
 * Latitude/longitude and the POINT location are nullable because stations are
 * registered before survey coordinates are confirmed. A SPATIAL INDEX requires
 * NOT NULL, so proximity queries use the (latitude, longitude) composite index
 * until coordinates become mandatory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_stations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('name_alt', 150)->nullable();

            $table->foreignId('basin_id')->constrained()->restrictOnDelete();
            $table->foreignId('sub_basin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('watershed_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->geometry('location', 'point')->nullable();
            $table->decimal('elevation_m', 8, 2)->nullable();

            $table->enum('station_type', [
                'river_gauge',
                'rainfall',
                'meteorological',
                'water_quality',
                'reservoir',
                'spring',
                'other',
            ])->default('river_gauge');

            $table->string('operator', 150)->nullable();
            $table->date('established_on')->nullable();

            $table->foreignId('data_source_id')->nullable()->constrained('data_sources')->nullOnDelete();
            $table->enum('data_status', ['pending', 'provisional', 'verified'])->default('pending');

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['latitude', 'longitude']);
            $table->index(['is_active', 'station_type']);
            $table->index('sub_basin_id');
            $table->index('watershed_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_stations');
    }
};
