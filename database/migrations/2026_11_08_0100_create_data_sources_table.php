<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance register: every geographic record, observation and import can
 * point at the organisation or system it originated from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->enum('type', ['manual', 'excel_import', 'api', 'field_sheet', 'legacy', 'other'])->default('manual');
            $table->string('organisation', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};
