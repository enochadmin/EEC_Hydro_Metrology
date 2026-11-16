<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative, source-specific basin polygons.
 *
 * `geom` is NOT NULL and carries a SPATIAL INDEX: MySQL/MariaDB reject spatial
 * indexes on nullable columns. SRID 4326 (WGS84) is recorded per row because
 * MariaDB 10.x has no SRID column attribute; writes use ST_GeomFromText(..., 4326)
 * and the stored SRID can be verified with ST_SRID(geom).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basin_boundaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basin_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->foreignId('source_id')->nullable()->constrained('data_sources')->nullOnDelete();

            $table->geometry('geom');
            $table->unsignedSmallInteger('srid')->default(4326);
            $table->decimal('accuracy_m', 10, 2)->nullable();

            $table->boolean('is_primary')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['basin_id', 'is_primary']);
            $table->spatialIndex('geom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basin_boundaries');
    }
};
