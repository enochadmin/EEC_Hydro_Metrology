<?php

namespace App\Http\Controllers\Excel;

use App\Http\Controllers\Controller;
use App\Models\DataImportBatch;
use App\Services\Excel\ExcelFileService;
use App\Services\Excel\ExcelStructureDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExcelPreviewController extends Controller
{
    public function __construct(
        private readonly ExcelFileService $files,
        private readonly ExcelStructureDetectionService $detector,
    ) {}

    public function show(Request $request, DataImportBatch $batch): Response|RedirectResponse
    {
        try {
            $sheets = $this->files->sheetInfo($batch);
        } catch (\Throwable $exception) {
            return redirect()->route('excel.index')->with('error', $exception->getMessage());
        }

        $names = array_map(fn (array $sheet): string => $sheet['worksheetName'], $sheets);
        $selected = (string) $request->query('sheet', $names[0] ?? '');

        if (! in_array($selected, $names, true)) {
            $selected = $names[0] ?? '';
        }

        $detection = $selected !== '' ? $this->detector->detect($batch, $selected) : null;
        $preview = $selected !== ''
            ? $this->files->preview($batch, $selected, (int) config('excel.limits.preview_rows', 40), (int) config('excel.limits.preview_columns', 40))
            : null;

        return Inertia::render('Excel/Preview', [
            'batch' => [
                'uuid' => $batch->uuid,
                'file_name' => $batch->file_name,
                'file_size' => $batch->file_size,
                'mime_type' => $batch->mime_type,
                'created_at' => $batch->created_at?->toIso8601String(),
                'stage' => $batch->stage,
                'status' => $batch->status,
            ],
            'sheets' => array_map(fn (array $sheet): array => [
                'name' => $sheet['worksheetName'],
                'rows' => $sheet['totalRows'],
                'columns' => $sheet['totalColumns'],
                'letter' => $sheet['lastColumnLetter'],
            ], $sheets),
            'selectedSheet' => $selected,
            'detection' => $detection,
            'preview' => $preview,
        ]);
    }

    public function sheet(DataImportBatch $batch, string $sheet): JsonResponse
    {
        return response()->json($this->files->preview(
            $batch,
            $sheet,
            (int) config('excel.limits.preview_rows', 40),
            (int) config('excel.limits.preview_columns', 40),
        ));
    }

    public function comparison(DataImportBatch $batch): Response
    {
        return Inertia::render('Excel/Comparison', [
            'batch' => [
                'uuid' => $batch->uuid,
                'file_name' => $batch->file_name,
                'sheet_name' => $batch->sheet_name,
            ],
            'comparison' => $batch->comparison,
        ]);
    }
}
