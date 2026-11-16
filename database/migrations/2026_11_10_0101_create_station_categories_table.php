<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presentation metadata for the station types the schema already accepts.
 *
 * `code` mirrors the monitoring_stations.station_type enum, so an administrator
 * can rename, describe, reorder or hide a category without ever writing a value
 * the column would reject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('label', 100);
            $table->text('description')->nullable();
            $table->string('colour', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_categories');
    }
};
