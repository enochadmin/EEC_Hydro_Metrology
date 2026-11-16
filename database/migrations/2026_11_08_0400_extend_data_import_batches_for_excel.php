<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Excel workflow state on top of the Phase 3 batch table.
 *
 * `status` keeps its original meaning (processing outcome); `stage` tracks the
 * wizard step so a half-finished upload can be resumed or audited. Transformed
 * rows, validation findings and comparisons live on the private disk and only
 * their paths/summaries are stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_import_batches', function (Blueprint $table) {
            $table->string('stage', 30)->default('uploaded')->after('status');
            $table->string('sheet_name', 150)->nullable()->after('stage');
            $table->string('structure_type', 60)->nullable()->after('sheet_name');
            $table->json('mapping')->nullable()->after('structure_type');
            $table->json('summary')->nullable()->after('mapping');
            $table->json('validation_summary')->nullable()->after('summary');
            $table->json('comparison')->nullable()->after('validation_summary');
            $table->json('station_map')->nullable()->after('comparison');
            $table->string('transformed_path', 500)->nullable()->after('station_map');
            $table->string('validation_path', 500)->nullable()->after('transformed_path');
            $table->unsignedInteger('original_rows')->nullable()->after('validation_path');
            $table->unsignedInteger('original_columns')->nullable()->after('original_rows');
            $table->timestamp('approved_at')->nullable()->after('original_columns');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_at');
            $table->timestamp('imported_at')->nullable()->after('approved_by');

            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::table('data_import_batches', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['stage']);
            $table->dropColumn([
                'stage',
                'sheet_name',
                'structure_type',
                'mapping',
                'summary',
                'validation_summary',
                'comparison',
                'station_map',
                'transformed_path',
                'validation_path',
                'original_rows',
                'original_columns',
                'approved_at',
                'approved_by',
                'imported_at',
            ]);
        });
    }
};
