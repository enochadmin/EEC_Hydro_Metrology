<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplementary GIS layers that are not part of the basin hierarchy:
 * the national boundary, rivers and lakes.
 *
 * Basins, sub-basins and watersheds keep their own tables; this table exists so
 * new line/polygon layers can be added without a schema change per dataset.
 *
 * `content_hash` is the duplicate guard: the same canonical geometry cannot be
 * imported twice for the same layer. The bounding box columns allow cheap
 * viewport filtering on MariaDB 10.4, which has no ST_Simplify/ST_IsValid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_features', function (Blueprint $table) {
            $table->id();

            $table->string('layer_type', 40);
            $table->string('name', 180);
            $table->string('code', 80)->nullable();

            $table->string('geometry_type', 30);
            $table->geometry('geom');

            $table->json('properties')->nullable();

            $table->decimal('min_lng', 11, 7)->nullable();
            $table->decimal('min_lat', 10, 7)->nullable();
            $table->decimal('max_lng', 11, 7)->nullable();
            $table->decimal('max_lat', 10, 7)->nullable();

            $table->string('content_hash', 64)->unique();

            $table->foreignId('basin_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('data_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('source_note')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['layer_type', 'is_active']);
            $table->index(['min_lng', 'min_lat', 'max_lng', 'max_lat']);
            $table->spatialIndex('geom');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_features');
    }
};
