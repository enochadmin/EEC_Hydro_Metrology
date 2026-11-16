<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Top level of the geographic hierarchy.
 *
 * `boundary` holds an optional simplified display geometry. Authoritative,
 * versioned polygons live in `basin_boundaries` where a MySQL SPATIAL INDEX is
 * possible (MySQL/MariaDB require spatial index columns to be NOT NULL).
 *
 * Coordinates and area are nullable on purpose: the registry must be able to
 * hold a basin before verified geography is supplied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basins', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('name_alt', 150)->nullable();
            $table->text('description')->nullable();
            $table->decimal('area_km2', 12, 2)->nullable();
            $table->decimal('centroid_lat', 10, 7)->nullable();
            $table->decimal('centroid_lng', 10, 7)->nullable();
            $table->geometry('boundary')->nullable();

            $table->foreignId('data_source_id')->nullable()->constrained('data_sources')->nullOnDelete();

            $table->enum('data_status', ['pending', 'provisional', 'verified'])->default('pending');
            $table->text('source_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('data_status');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basins');
    }
};
