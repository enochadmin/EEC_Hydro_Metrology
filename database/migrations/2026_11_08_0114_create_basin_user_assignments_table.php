<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional basin-scoped access: a user can be limited to specific basins even
 * when their global role would allow more. Phase 2 enforces global roles; this
 * table is the hook for basin-level scoping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('basin_user_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basin_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('access_level', ['view', 'manage'])->default('view');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['basin_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('basin_user_assignments');
    }
};
