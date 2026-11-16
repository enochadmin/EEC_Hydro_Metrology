<?php

namespace App\Services\Excel;

use App\Models\DataImportBatch;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;

/**
 * Bounded access to the uploaded workbook.
 *
 * The second sheet in the supplied file is 18,303 rows × 603 columns. Loading
 * it whole would exhaust PHP memory, so every read declares the rows and
 * columns it needs and PhpSpreadsheet discards the rest while parsing. Parsed
 * slices are cached as JSON under the batch directory for the later transform,
 * validation and comparison steps.
 */
class ExcelFileService
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $memo = [];

    /**
     * @return list<array{worksheetName: string, lastColumnLetter: string, lastColumnIndex: int, totalRows: int, totalColumns: int, sheetState: string}>
     */
    public function sheetInfo(DataImportBatch $batch): array
    {
        $reader = $this->reader($batch);

        return $reader->listWorksheetInfo($this->absolutePath($batch));
    }

    /**
     * @return list<string>
     */
    public function sheetNames(DataImportBatch $batch): array
    {
        return array_map(
            fn (array $sheet): string => $sheet['worksheetName'],
            $this->sheetInfo($batch),
        );
    }

    public function absolutePath(DataImportBatch $batch): string
    {
        if (! $batch->file_path) {
            throw new RuntimeException('The batch has no stored workbook.');
        }

        $path = Storage::disk('local')->path($batch->file_path);

        if (! is_file($path)) {
            throw new RuntimeException('The stored workbook is missing from disk.');
        }

        return $path;
    }

    /**
     * Excel-style preview of a sheet, limited to the first rows/columns.
     *
     * @return array{
     *     sheet: string,
     *     total_rows: int,
     *     total_columns: int,
     *     shown_rows: int,
     *     shown_columns: int,
     *     columns: list<string>,
     *     rows: list<array{row: int, cells: array<string, mixed>}>,
     *     truncated_rows: bool,
     *     truncated_columns: bool
     * }
     */
    public function preview(DataImportBatch $batch, string $sheetName, int $rowLimit = 40, int $columnLimit = 40): array
    {
        $info = $this->infoFor($batch, $sheetName);
        $rowLimit = max(1, min($rowLimit, 200));
        $columnLimit = max(1, min($columnLimit, 60));

        // Read every column once; the detector has already memoised this window,
        // so the preview reuses it instead of parsing the sheet a second time.
        $rows = $this->readRows($batch, $sheetName, 1, min($rowLimit, max(1, $info['totalRows'])), null, null);

        $columns = [];

        for ($index = 1; $index <= min($columnLimit, max(1, $info['totalColumns'])); $index++) {
            $columns[] = $this->columnLetter($index);
        }

        $formatted = [];

        foreach ($rows as $rowNumber => $cells) {
            $formatted[] = ['row' => $rowNumber, 'cells' => array_intersect_key($cells, array_flip($columns))];
        }

        return [
            'sheet' => $sheetName,
            'total_rows' => $info['totalRows'],
            'total_columns' => $info['totalColumns'],
            'shown_rows' => count($formatted),
            'shown_columns' => count($columns),
            'columns' => $columns,
            'rows' => $formatted,
            'truncated_rows' => $info['totalRows'] > $rowLimit,
            'truncated_columns' => $info['totalColumns'] > $columnLimit,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readRows(
        DataImportBatch $batch,
        string $sheetName,
        int $fromRow = 1,
        ?int $toRow = null,
        ?int $maxColumnIndex = null,
        ?array $columns = null,
    ): array {
        $info = $this->infoFor($batch, $sheetName);
        $toRow = $toRow ?? $info['totalRows'];
        $toRow = min($toRow, max(1, $info['totalRows']));

        $cacheKey = sprintf(
            '%s|%d|%d|%s|%s',
            $sheetName,
            $fromRow,
            $toRow,
            $maxColumnIndex ?? 'all',
            $columns ? implode(',', $columns) : 'all',
        );

        if (isset($this->memo[$cacheKey])) {
            return $this->memo[$cacheKey];
        }

        $reader = $this->reader($batch, $sheetName);
        $reader->setReadFilter(new BoundedReadFilter($fromRow, $toRow, $maxColumnIndex, $columns));

        $spreadsheet = $reader->load($this->absolutePath($batch));
        $sheet = $spreadsheet->getSheet(0);

        $rows = [];

        for ($row = $fromRow; $row <= $toRow; $row++) {
            $cells = [];
            $empty = true;

            $highestColumnIndex = $columns !== null
                ? $this->columnIndex(max($columns))
                : min($maxColumnIndex ?? $info['totalColumns'], $info['totalColumns']);

            for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                $letter = $this->columnLetter($columnIndex);

                if ($columns !== null && ! in_array($letter, $columns, true)) {
                    continue;
                }

                $value = $this->cellValue($sheet->getCell($letter.$row));

                if ($value !== null && $value !== '') {
                    $empty = false;
                }

                $cells[$letter] = $value;
            }

            if (! $empty) {
                $rows[$row] = $cells;
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $this->memo[$cacheKey] = $rows;

        return $rows;
    }

    /**
     * Full sheet (used for the 433-row monthly sheet only).
     *
     * @return array<int, array<string, mixed>>
     */
    public function readWholeSheet(DataImportBatch $batch, string $sheetName): array
    {
        $info = $this->infoFor($batch, $sheetName);

        return $this->readRows($batch, $sheetName, 1, $info['totalRows'], $info['totalColumns']);
    }

    /**
     * Read only the date/value columns across every row of a long sheet.
     *
     * @return array<int, array{date: mixed, value: mixed, formula: string|null}>
     */
    public function readDailyColumns(DataImportBatch $batch, string $sheetName, string $dateColumn = 'A', string $valueColumn = 'B'): array
    {
        $info = $this->infoFor($batch, $sheetName);
        $rows = $this->readRows(
            $batch,
            $sheetName,
            1,
            $info['totalRows'],
            null,
            [$dateColumn, $valueColumn],
        );

        $series = [];

        foreach ($rows as $rowNumber => $cells) {
            $date = $cells[$dateColumn] ?? null;
            $value = $cells[$valueColumn] ?? null;

            if (($date === null || $date === '') && ($value === null || $value === '')) {
                continue;
            }

            $formula = is_string($value) && str_starts_with(trim($value), '=') ? trim($value) : null;

            $series[$rowNumber] = ['date' => $date, 'value' => $value, 'formula' => $formula];
        }

        return $series;
    }

    public function infoFor(DataImportBatch $batch, string $sheetName): array
    {
        foreach ($this->sheetInfo($batch) as $info) {
            if ($info['worksheetName'] === $sheetName) {
                return $info;
            }
        }

        throw new RuntimeException('Worksheet "'.$sheetName.'" does not exist in this workbook.');
    }

    public function columnLetter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    public function columnIndex(string $letter): int
    {
        return Coordinate::columnIndexFromString($letter);
    }

    private function reader(DataImportBatch $batch, ?string $sheetName = null): IReader
    {
        $reader = IOFactory::createReaderForFile($this->absolutePath($batch));
        $reader->setReadDataOnly(true);

        if ($sheetName !== null) {
            $reader->setLoadSheetsOnly([$sheetName]);
        }

        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        return $reader;
    }

    private function cellValue(Cell $cell): mixed
    {
        $value = $cell->getValue();

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}

/**
 * Row/column window for PhpSpreadsheet's streaming reader.
 */
final class BoundedReadFilter implements IReadFilter
{
    /**
     * @param  list<string>|null  $columns
     */
    public function __construct(
        private readonly int $fromRow,
        private readonly int $toRow,
        private readonly ?int $maxColumnIndex = null,
        private readonly ?array $columns = null,
    ) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row < $this->fromRow || $row > $this->toRow) {
            return false;
        }

        if ($this->columns !== null) {
            return in_array($columnAddress, $this->columns, true);
        }

        if ($this->maxColumnIndex === null) {
            return true;
        }

        return Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumnIndex;
    }
}
