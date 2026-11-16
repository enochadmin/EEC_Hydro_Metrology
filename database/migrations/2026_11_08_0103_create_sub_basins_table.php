<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_basins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basin_id')->constrained()->restrictOnDelete();

            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('name_alt', 150)->nullable();
            $table->text('description')->nullable();

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

            $table->index(['basin_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_basins');
    }
};
