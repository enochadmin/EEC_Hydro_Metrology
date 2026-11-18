<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('data_import_batches', function (Blueprint $table) {
            $table->foreignId('monitoring_station_id')
                ->nullable()
                ->after('data_source_id')
                ->constrained('monitoring_stations')
                ->nullOnDelete();
            $table->index(['monitoring_station_id', 'created_at'], 'import_batches_station_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_import_batches', function (Blueprint $table) {
            $table->dropIndex('import_batches_station_created_index');
            $table->dropConstrainedForeignId('monitoring_station_id');
        });
    }
};
