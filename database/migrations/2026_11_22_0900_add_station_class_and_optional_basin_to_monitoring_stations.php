<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Station network classification, and an optional basin attribution.
 *
 * `station_class` records the observing network class a station belongs to
 * (synoptic, principal, ordinary, precipitation) independently of
 * `station_type`, which keeps describing what the station measures. A
 * precipitation station is therefore `station_type = rainfall` and
 * `station_class = precipitation`.
 *
 * `basin_id` becomes nullable: a station can be registered from a network list
 * before basin polygons exist. Basin containment is attached later, when
 * verified boundaries are imported, without blocking the station registry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_stations', function (Blueprint $table): void {
            $table->dropForeign(['basin_id']);
        });

        Schema::table('monitoring_stations', function (Blueprint $table): void {
            $table->unsignedBigInteger('basin_id')->nullable()->change();
            $table->string('station_class', 30)->nullable()->after('station_type');

            $table->index('station_class');
            $table->index(['basin_id', 'is_active']);

            $table->foreign('basin_id')->references('id')->on('basins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_stations', function (Blueprint $table): void {
            $table->dropForeign(['basin_id']);
            $table->dropIndex(['station_class']);
            $table->dropIndex(['basin_id', 'is_active']);
            $table->dropColumn('station_class');
        });

        Schema::table('monitoring_stations', function (Blueprint $table): void {
            $table->unsignedBigInteger('basin_id')->nullable(false)->change();
            $table->foreign('basin_id')->references('id')->on('basins')->restrictOnDelete();
        });
    }
};
