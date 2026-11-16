<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parameter catalogue. The list lives in the database (see ParameterSeeder),
 * never in application code, so new measurements can be added without a
 * deployment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('name_alt', 100)->nullable();
            $table->string('symbol', 20)->nullable();

            $table->enum('category', ['meteorological', 'hydrological', 'water_quality', 'other'])
                ->default('hydrological');

            $table->foreignId('default_unit_id')->nullable()->constrained('measurement_units')->nullOnDelete();

            $table->enum('value_type', [
                'instantaneous',
                'daily_mean',
                'daily_total',
                'monthly_total',
                'annual_total',
            ])->default('instantaneous');

            $table->decimal('min_expected', 18, 6)->nullable();
            $table->decimal('max_expected', 18, 6)->nullable();
            $table->unsignedTinyInteger('decimals')->default(2);

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameters');
    }
};
