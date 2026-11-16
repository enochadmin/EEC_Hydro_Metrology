<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per uploaded workbook. The checksum is indexed (not unique) so the
 * import screen can warn about re-uploads while still allowing a corrected
 * file with an identical hash to be re-processed deliberately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('file_name', 255);
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('checksum', 64)->nullable();

            $table->foreignId('data_source_id')->nullable()->constrained('data_sources')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', ['pending', 'processing', 'completed', 'completed_with_errors', 'failed'])
                ->default('pending');

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('checksum');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_batches');
    }
};
