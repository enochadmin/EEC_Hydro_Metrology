<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable catchment level. `parent_id` allows a watershed tree of
 * arbitrary depth beneath a sub-basin, while `sub_basin_id` anchors the tree
 * to the administrative hierarchy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watersheds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_basin_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('watersheds')->nullOnDelete();

            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->string('name_alt', 150)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('level')->default(3);

            $table->decimal('area_km2', 12, 2)->nullable();
            $table->decimal('centroid_lat', 10, 7)->nullable();
            $table->decimal('centroid_lng', 10, 7)->nullable();
            $table->geometry('boundary')->nullable();

            $table->foreignId('data_source_id')->nullable()->constrained('data_sources')->nullOnDelete();

            $table->enum('data_status', ['pending', 'provisional', 'verified'])->default('pending');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_basin_id', 'is_active']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watersheds');
    }
};
