<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExcelImportBatchDeletionService
{
    /**
     * Permanently remove an uploaded workbook and records imported by it.
     *
     * @return array{observations_deleted: int, files_removed: bool}
     */
    public function delete(DataImportBatch $batch): array
    {
        $directory = 'excel/'.$batch->uuid;

        $observationsDeleted = DB::transaction(function () use ($batch): int {
            $lockedBatch = DataImportBatch::query()
                ->lockForUpdate()
                ->findOrFail($batch->getKey());

            $observationCount = $lockedBatch->observations()->count();

            $lockedBatch->qualityIssues()->delete();
            $lockedBatch->errors()->delete();
            $lockedBatch->observations()->delete();
            $lockedBatch->delete();

            return $observationCount;
        });

        $storage = Storage::disk('local');
        $filesRemoved = $storage->deleteDirectory($directory) || ! $storage->exists($directory);

        if (! $filesRemoved) {
            Log::warning('A deleted Excel import batch still has private files on disk.', [
                'batch_id' => $batch->getKey(),
                'batch_uuid' => $batch->uuid,
                'directory' => $directory,
            ]);
        }

        return [
            'observations_deleted' => $observationsDeleted,
            'files_removed' => $filesRemoved,
        ];
    }
}
