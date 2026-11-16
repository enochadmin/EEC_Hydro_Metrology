<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Units are reference data, not hardcoded strings: parameters and observations
 * reference them so conversions and reporting stay consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('symbol', 20);
            $table->enum('dimension', ['length', 'temperature', 'ratio', 'speed', 'time', 'volumetric_flow', 'other'])
                ->default('other');
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_units');
    }
};
