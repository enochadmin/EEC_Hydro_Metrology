<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quality-control findings raised against an observation, an import batch or
 * both. Issues are retained with the resolution trail rather than deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_quality_issues', function (Blueprint $table) {
            $table->id();

            $table->foreignId('hydrological_observation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('data_import_batch_id')->nullable()->constrained('data_import_batches')->cascadeOnDelete();
            $table->foreignId('monitoring_station_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parameter_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('issue_type', [
                'missing',
                'duplicate',
                'outlier',
                'range',
                'consistency',
                'gap',
                'unit_mismatch',
                'other',
            ]);

            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description');

            $table->enum('status', ['open', 'resolved', 'ignored'])->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index('hydrological_observation_id', 'quality_issue_observation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_issues');
    }
};
