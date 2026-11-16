<?php

namespace App\Http\Controllers\Excel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Excel\ImportExcelRequest;
use App\Http\Requests\Excel\StoreExcelUploadRequest;
use App\Models\DataImportBatch;
use App\Services\Excel\ExcelFileService;
use App\Services\Excel\ExcelImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExcelImportController extends Controller
{
    public function __construct(private readonly ExcelFileService $files) {}

    public function index(): Response
    {
        $batches = DataImportBatch::query()
            ->with('uploader:id,name')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (DataImportBatch $batch): array => $this->batchRow($batch));

        return Inertia::render('Excel/Index', [
            'stats' => [
                'uploads' => DataImportBatch::query()->count(),
                'imported_batches' => DataImportBatch::query()->where('stage', 'imported')->count(),
                'observations' => (int) DataImportBatch::query()->sum('imported_rows'),
                'rejected' => (int) DataImportBatch::query()->sum('rejected_rows'),
            ],
            'recent' => $batches,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Excel/Upload', [
            'limits' => [
                'max_kb' => (int) config('excel.limits.max_upload_kb'),
                'formats' => ['xlsx', 'xls', 'csv'],
            ],
        ]);
    }

    public function store(StoreExcelUploadRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $directory = 'excel/'.$uuid;

        $path = $file->storeAs($directory, 'source.'.$extension, 'local');

        $batch = DataImportBatch::create([
            'uuid' => $uuid,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize() ?: 0,
            'mime_type' => $file->getClientMimeType(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $request->user()->id,
            'status' => 'pending',
            'stage' => 'uploaded',
            'notes' => 'Original workbook preserved on the private disk.',
        ]);

        try {
            $sheets = $this->files->sheetNames($batch);
        } catch (\Throwable $exception) {
            $batch->forceFill(['status' => 'failed', 'notes' => $exception->getMessage()])->save();

            return back()->with('error', 'The workbook could not be read: '.$exception->getMessage());
        }

        return redirect()
            ->route('excel.preview', ['batch' => $batch->uuid, 'sheet' => $sheets[0] ?? null])
            ->with('success', 'Workbook uploaded. '.count($sheets).' worksheet(s) detected.');
    }

    public function import(ImportExcelRequest $request, DataImportBatch $batch, ExcelImportService $importer): RedirectResponse
    {
        $stationMap = $request->validated('station_map');

        $counts = $importer->import($batch, $stationMap, $request->user());

        return redirect()
            ->route('excel.validation', $batch)
            ->with('success', sprintf(
                '%d observations imported; %d duplicates skipped; %d non-valid rows excluded.',
                $counts['imported'],
                $counts['duplicates'],
                $counts['skipped'],
            ));
    }

    public function history(): Response
    {
        $batches = DataImportBatch::query()
            ->with('uploader:id,name')
            ->latest()
            ->paginate(15)
            ->through(fn (DataImportBatch $batch): array => $this->batchRow($batch));

        return Inertia::render('Excel/ImportHistory', ['batches' => $batches]);
    }

    /**
     * @return array<string, mixed>
     */
    private function batchRow(DataImportBatch $batch): array
    {
        return [
            'uuid' => $batch->uuid,
            'file_name' => $batch->file_name,
            'file_size' => $batch->file_size,
            'sheet_name' => $batch->sheet_name,
            'structure_type' => $batch->structure_type,
            'uploaded_by' => $batch->uploader?->name,
            'created_at' => $batch->created_at?->toIso8601String(),
            'imported_at' => $batch->imported_at?->toIso8601String(),
            'status' => $batch->status,
            'stage' => $batch->stage,
            'total_rows' => (int) $batch->total_rows,
            'imported_rows' => (int) $batch->imported_rows,
            'duplicate_rows' => (int) $batch->duplicate_rows,
            'rejected_rows' => (int) $batch->rejected_rows,
            'summary' => $batch->summary,
        ];
    }
}
