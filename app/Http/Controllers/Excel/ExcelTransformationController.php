<?php

namespace App\Http\Controllers\Excel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Excel\SaveExcelMappingRequest;
use App\Models\DataImportBatch;
use App\Models\Parameter;
use App\Services\Excel\ExcelComparisonService;
use App\Services\Excel\ExcelFileService;
use App\Services\Excel\ExcelStructureDetectionService;
use App\Services\Excel\ExcelTransformationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ExcelTransformationController extends Controller
{
    public function __construct(
        private readonly ExcelFileService $files,
        private readonly ExcelStructureDetectionService $detector,
        private readonly ExcelTransformationService $transformer,
        private readonly ExcelComparisonService $comparison,
    ) {}

    public function mapping(Request $request, DataImportBatch $batch): Response|RedirectResponse
    {
        $sheet = (string) $request->query('sheet', '');
        $structure = (string) $request->query('structure', '');

        try {
            $names = $this->files->sheetNames($batch);
        } catch (\Throwable $exception) {
            return redirect()->route('excel.index')->with('error', $exception->getMessage());
        }

        if (! in_array($sheet, $names, true)) {
            $sheet = $names[0] ?? '';
        }

        if ($sheet === '') {
            return redirect()->route('excel.preview', $batch)->with('error', 'The workbook has no worksheets.');
        }

        $detection = $this->detector->detect($batch, $sheet);
        $candidate = $this->candidate($detection, $structure);

        if ($candidate === null) {
            return redirect()
                ->route('excel.preview', ['batch' => $batch->uuid, 'sheet' => $sheet])
                ->with('error', 'Choose a detected structure before mapping columns.');
        }

        $mapping = $batch->structure_type === $candidate['key'] && $batch->sheet_name === $sheet && $batch->mapping
            ? $batch->mapping
            : $this->mappingFromCandidate($candidate, $sheet);

        return Inertia::render('Excel/ColumnMapping', [
            'batch' => ['uuid' => $batch->uuid, 'file_name' => $batch->file_name],
            'sheet' => $sheet,
            'sheets' => $names,
            'structure' => $candidate['key'],
            'candidate' => $candidate,
            'mapping' => $mapping,
            'parameters' => $this->parameterOptions(),
        ]);
    }

    public function storeMapping(SaveExcelMappingRequest $request, DataImportBatch $batch): RedirectResponse
    {
        $sheet = $request->validated('sheet_name');
        $structure = $request->validated('structure_type');
        $mapping = (array) $request->input('mapping', []);

        try {
            $this->files->infoFor($batch, $sheet);
            $this->transformer->transform($batch, $sheet, $structure, $mapping);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['mapping' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('excel.transformation', $batch)
            ->with('success', 'Transformation preview generated.');
    }

    public function show(DataImportBatch $batch): Response|RedirectResponse
    {
        if (! $batch->transformed_path) {
            return redirect()
                ->route('excel.mapping', ['batch' => $batch->uuid, 'sheet' => $batch->sheet_name])
                ->with('error', 'Choose a structure and mapping before previewing the transformation.');
        }

        return Inertia::render('Excel/Transformation', [
            'batch' => [
                'uuid' => $batch->uuid,
                'file_name' => $batch->file_name,
                'station' => $batch->station?->only(['id', 'code', 'name']),
                'sheet_name' => $batch->sheet_name,
                'structure_type' => $batch->structure_type,
                'mapping' => $batch->mapping,
                'summary' => $batch->summary,
                'validation_summary' => $batch->validation_summary,
                'comparison' => $batch->comparison,
                'stage' => $batch->stage,
            ],
            'transformed' => $this->transformedPreview($batch, 100),
            'original' => $this->originalPreview($batch),
        ]);
    }

    public function transform(DataImportBatch $batch): RedirectResponse
    {
        if (! $batch->transformed_path || ! $batch->sheet_name || ! $batch->structure_type || ! $batch->mapping) {
            return back()->with('error', 'The mapping is incomplete.');
        }

        try {
            $this->transformer->transform($batch, $batch->sheet_name, $batch->structure_type, $batch->mapping);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Transformation regenerated.');
    }

    public function compare(DataImportBatch $batch): RedirectResponse
    {
        if (! $batch->sheet_name || ! $batch->structure_type || ! $batch->mapping) {
            return back()->with('error', 'Choose a worksheet and complete its mapping before comparing.');
        }

        try {
            $this->transformer->transform($batch, $batch->sheet_name, $batch->structure_type, $batch->mapping);
            $result = $this->comparison->compare($batch->refresh());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('excel.comparison', $batch)
            ->with('success', 'Source and generated output compared. '.$result['counts']['changed'].' changed record(s) need review.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function candidate(array $detection, string $structure): ?array
    {
        foreach ($detection['candidates'] as $candidate) {
            if ($candidate['key'] === $structure) {
                return $candidate;
            }
        }

        foreach ($detection['candidates'] as $candidate) {
            if ($candidate['key'] === $detection['primary']) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function mappingFromCandidate(array $candidate, string $sheet): array
    {
        $station = trim(preg_replace('/\b(raw\s*data|column|data|sheet)\b/i', ' ', $sheet) ?? $sheet);

        if ($candidate['key'] === 'monthly_daily_columns') {
            return [
                'header_row' => $candidate['header_row'],
                'data_start_row' => $candidate['data_start_row'],
                'columns' => $candidate['columns'],
                'day_columns' => $candidate['day_columns'],
                'station_name' => null,
                'parameter_code' => 'rainfall',
                'time' => '09:00',
            ];
        }

        if ($candidate['key'] === 'multi_header_matrix') {
            return [
                'header_rows' => $candidate['header_rows'],
                'year_row' => $candidate['year_row'],
                'month_row' => $candidate['month_row'],
                'month_name_row' => $candidate['month_name_row'],
                'day_column' => $candidate['day_column'],
                'day_rows' => $candidate['day_rows'],
                'periods' => $candidate['periods'],
                'station_name' => $station,
                'parameter_label' => 'Rainfall',
                'parameter_code' => 'rainfall',
                'time' => '09:00',
            ];
        }

        return [
            'date_column' => $candidate['date_column'],
            'value_column' => $candidate['value_column'],
            'start_row' => $candidate['start_row'],
            'station_name' => $station,
            'parameter_label' => 'Rainfall',
            'parameter_code' => 'rainfall',
            'time' => '09:00',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function parameterOptions(): Collection
    {
        return Parameter::query()
            ->with('defaultUnit:id,code,symbol')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'default_unit_id'])
            ->map(fn (Parameter $parameter): array => [
                'id' => $parameter->id,
                'code' => $parameter->code,
                'name' => $parameter->name,
                'unit' => $parameter->defaultUnit?->symbol,
            ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function transformedPreview(DataImportBatch $batch, int $limit): array
    {
        $path = Storage::disk('local')->path((string) $batch->transformed_path);

        if (! is_file($path)) {
            return [];
        }

        $rows = [];
        $handle = fopen($path, 'rb');

        while (($line = fgets($handle)) !== false && count($rows) < $limit) {
            $decoded = json_decode(trim($line), true);

            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function originalPreview(DataImportBatch $batch): ?array
    {
        try {
            return $this->files->preview($batch, (string) $batch->sheet_name, 15, 20);
        } catch (\Throwable) {
            return null;
        }
    }
}
