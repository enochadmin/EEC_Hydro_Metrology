<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_import_batch_id')->constrained('data_import_batches')->cascadeOnDelete();

            $table->unsignedInteger('row_number');
            $table->string('column_name', 100)->nullable();
            $table->string('error_code', 50);
            $table->text('message');
            $table->text('raw_value')->nullable();
            $table->timestamps();

            $table->index(['data_import_batch_id', 'row_number'], 'import_error_row_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_errors');
    }
};
