<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 additions to the geographic registry.
 *
 * `elevation_m` is the mean elevation reported for the basin or sub-basin, not
 * a station reading. It stays nullable for the same reason area and centroid
 * are nullable: the registry must be able to hold a record before verified
 * geography is supplied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('basins', function (Blueprint $table) {
            $table->decimal('elevation_m', 8, 2)->nullable()->after('centroid_lng');
        });

        Schema::table('sub_basins', function (Blueprint $table) {
            $table->decimal('elevation_m', 8, 2)->nullable()->after('centroid_lng');
            $table->text('source_note')->nullable()->after('data_status');
        });
    }

    public function down(): void
    {
        Schema::table('sub_basins', function (Blueprint $table) {
            $table->dropColumn(['elevation_m', 'source_note']);
        });

        Schema::table('basins', function (Blueprint $table) {
            $table->dropColumn('elevation_m');
        });
    }
};
