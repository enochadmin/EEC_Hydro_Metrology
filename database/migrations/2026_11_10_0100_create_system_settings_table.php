<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrator-managed settings.
 *
 * Only values that differ from the shipped default are stored. The defaults stay
 * in config/settings.php, so a cleared table returns the application to its
 * documented behaviour instead of leaving keys undefined.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('group', 50)->index();
            $table->string('label', 150);
            $table->enum('type', ['string', 'text', 'integer', 'decimal', 'boolean', 'select'])->default('string');
            $table->json('value')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
