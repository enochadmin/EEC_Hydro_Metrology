<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry imports are separate from observation imports.
 *
 * A station workbook describes the network (name, coordinates, class) and
 * carries no readings, so it must not reuse `data_import_batches`, whose
 * counters, validation report and observation back-reference describe a
 * time-series upload. The parsed rows are staged on the private disk; only
 * their path and summary are stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('file_name', 255);
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 64)->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', ['pending', 'previewed', 'imported', 'failed'])->default('pending');
            $table->string('stage', 30)->default('uploaded');

            $table->json('options')->nullable();
            $table->json('mapping')->nullable();
            $table->json('summary')->nullable();

            $table->string('staged_path', 500)->nullable();

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('importable_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);

            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index('checksum');
            $table->index(['status', 'created_at']);
        });

        Schema::create('station_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('station_import_batch_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('row_number');
            $table->string('column_name', 40)->nullable();
            $table->string('code', 40);
            $table->string('severity', 12)->default('error');
            $table->string('message', 500);
            $table->string('raw_value', 255)->nullable();
            $table->timestamps();

            $table->index(['station_import_batch_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_import_errors');
        Schema::dropIfExists('station_import_batches');
    }
};
