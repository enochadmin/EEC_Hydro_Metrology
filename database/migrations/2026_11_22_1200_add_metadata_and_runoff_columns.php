<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage for the provenance carried by the national geodata sets: per-element
 * availability strings on a station, and the published runoff figure on a
 * basin. Both are descriptive extras rather than measurements, so they stay
 * out of the observation tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_stations', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('notes');
        });

        Schema::table('basins', function (Blueprint $table) {
            $table->decimal('annual_runoff', 10, 3)->nullable()->after('area_km2');
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_stations', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        Schema::table('basins', function (Blueprint $table) {
            $table->dropColumn('annual_runoff');
        });
    }
};
