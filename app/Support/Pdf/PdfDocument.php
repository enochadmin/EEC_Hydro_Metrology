<?php

namespace App\Support\Pdf;

use RuntimeException;

/**
 * Minimal, dependency-free PDF writer for tabular reports.
 *
 * The project has no PDF package installed, and report output is deliberately
 * plain: a title block, a metadata strip and a sequence of tables. That is a
 * small, stable subset of PDF, so it is written here rather than pulling in a
 * renderer that would ship an HTML engine and a font stack. Only the two core
 * Helvetica faces are used, which every reader provides, and text is
 * transliterated to WinAnsi-safe ASCII.
 */
final class PdfDocument
{
    private const WIDTH = 842.0;   // A4 landscape, points

    private const HEIGHT = 595.0;

    private const MARGIN = 36.0;

    private const FOOTER_Y = 22.0;

    /** @var list<string> */
    private array $pages = [];

    private string $buffer = '';

    private float $y = 0.0;

    /**
     * @param  array<string, mixed>  $document
     */
    public function render(array $document): string
    {
        $this->pages = [];
        $this->buffer = '';
        $this->y = self::HEIGHT - self::MARGIN;

        $this->titleBlock($document);
        $this->metaBlock($document['meta'] ?? []);

        foreach ($document['sections'] ?? [] as $section) {
            $this->section($section);
        }

        if ($this->buffer !== '') {
            $this->flushPage();
        }

        if ($this->pages === []) {
            $this->flushPage();
        }

        return $this->assemble($document);
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function titleBlock(array $document): void
    {
        $this->text(self::MARGIN, $this->y - 4, $this->ascii($document['title'] ?? 'Report'), 16, true);
        $this->y -= 20;

        $subtitle = $this->ascii($document['subtitle'] ?? '');
        if ($subtitle !== '') {
            $this->text(self::MARGIN, $this->y, $subtitle, 9, false, 0.35);
            $this->y -= 12;
        }

        $generated = $this->ascii($document['generated_at'] ?? '');
        if ($generated !== '') {
            $this->text(self::MARGIN, $this->y, 'Generated '.$generated, 8, false, 0.45);
            $this->y -= 10;
        }

        $this->rule(self::MARGIN, $this->y - 2, self::WIDTH - self::MARGIN, 0.6);
        $this->y -= 14;
    }

    /**
     * @param  list<array{label: string, value: string}>  $meta
     */
    private function metaBlock(array $meta): void
    {
        if ($meta === []) {
            return;
        }

        $columns = 3;
        $width = (self::WIDTH - self::MARGIN * 2) / $columns;
        $index = 0;

        foreach ($meta as $item) {
            $column = $index % $columns;

            if ($column === 0 && $index > 0) {
                $this->y -= 24;
            }

            $x = self::MARGIN + $width * $column;

            $this->text($x, $this->y, $this->ascii(strtoupper($item['label'])), 6.5, false, 0.5);
            $this->text($x, $this->y - 10, $this->ascii($item['value']), 9, false, 0.1);

            $index++;
        }

        $this->y -= 22;
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function section(array $section): void
    {
        $rows = $section['rows'] ?? [];
        $columns = $section['columns'] ?? [];

        if ($this->y < self::MARGIN + 90) {
            $this->flushPage();
        }

        $this->text(self::MARGIN, $this->y, $this->ascii($section['heading'] ?? ''), 11, true);
        $this->y -= 6;
        $this->rule(self::MARGIN, $this->y, self::WIDTH - self::MARGIN, 0.4);
        $this->y -= 14;

        if ($rows === []) {
            $this->text(self::MARGIN, $this->y, $this->ascii($section['empty'] ?? 'No records.'), 8.5, false, 0.45);
            $this->y -= 18;

            return;
        }

        $widths = $this->columnWidths($columns);

        $this->tableHeader($columns, $widths);

        foreach ($rows as $index => $row) {
            if ($this->y < self::MARGIN + 30) {
                $this->flushPage();
                $this->tableHeader($columns, $widths);
            }

            $this->tableRow($columns, $widths, $row, $index % 2 === 1);
        }

        $this->y -= 12;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<float>  $widths
     */
    private function tableHeader(array $columns, array $widths): void
    {
        $height = 15.0;
        $this->fill(self::MARGIN, $this->y - $height + 4, self::WIDTH - self::MARGIN * 2, $height, 0.93, 0.95, 0.97);

        $x = self::MARGIN + 4;

        foreach ($columns as $index => $column) {
            $this->text($x, $this->y - 7, $this->ascii(strtoupper($column['label'] ?? '')), 7, true, 0.25);
            $x += $widths[$index];
        }

        $this->y -= $height;
        $this->rule(self::MARGIN, $this->y + 4, self::WIDTH - self::MARGIN, 0.5);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<float>  $widths
     * @param  array<int, mixed>  $row
     */
    private function tableRow(array $columns, array $widths, array $row, bool $zebra): void
    {
        $height = 14.0;

        if ($zebra) {
            $this->fill(self::MARGIN, $this->y - $height + 4, self::WIDTH - self::MARGIN * 2, $height, 0.975, 0.98, 0.985);
        }

        $x = self::MARGIN + 4;

        foreach ($columns as $index => $column) {
            $value = $row[$index] ?? '';
            $text = $this->ascii(is_scalar($value) ? (string) $value : '');
            $align = $column['align'] ?? (is_numeric(str_replace([',', ' ', '%'], '', $text)) ? 'right' : 'left');
            $maxWidth = $widths[$index] - 8;

            if ($align === 'right') {
                $this->textRight($x + $widths[$index] - 8, $this->y - 6, $this->truncate($text, $maxWidth, 8), 8, false, 0.15);
            } else {
                $this->text($x, $this->y - 6, $this->truncate($text, $maxWidth, 8), 8, false, 0.15);
            }

            $x += $widths[$index];
        }

        $this->y -= $height;
        $this->rule(self::MARGIN, $this->y + 4, self::WIDTH - self::MARGIN, 0.15);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @return list<float>
     */
    private function columnWidths(array $columns): array
    {
        $available = self::WIDTH - self::MARGIN * 2 - 8;
        $declared = 0.0;
        $flexible = 0;

        foreach ($columns as $column) {
            if (! empty($column['width'])) {
                $declared += (float) $column['width'];
            } else {
                $flexible++;
            }
        }

        $rest = max(0.0, $available - $declared);
        $each = $flexible > 0 ? $rest / $flexible : 0.0;

        $widths = [];

        foreach ($columns as $column) {
            $widths[] = ! empty($column['width']) ? (float) $column['width'] : $each;
        }

        if ($flexible === 0) {
            $scale = $available / max(1.0, $declared);
            $widths = array_map(fn (float $width) => $width * $scale, $widths);
        }

        return $widths;
    }

    private function text(float $x, float $y, string $text, float $size, bool $bold = false, float $gray = 0.0): void
    {
        if ($text === '') {
            return;
        }

        $font = $bold ? 'F2' : 'F1';
        $this->buffer .= sprintf(
            "BT %.1f %.1f %.1f rg /%s %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
            $gray, $gray, $gray, $font, $size, $x, $y, $this->escape($text)
        );
    }

    private function textRight(float $xRight, float $y, string $text, float $size, bool $bold = false, float $gray = 0.0): void
    {
        $width = strlen($text) * $size * 0.5;
        $this->text($xRight - $width, $y, $text, $size, $bold, $gray);
    }

    private function rule(float $x, float $y, float $x2, float $gray): void
    {
        $this->buffer .= sprintf(
            "%.2f %.2f %.2f RG 0.5 w %.2f %.2f m %.2f %.2f l S\n",
            $gray, $gray, $gray, $x, $y, $x2, $y
        );
    }

    private function fill(float $x, float $y, float $width, float $height, float $r, float $g, float $b): void
    {
        $this->buffer .= sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n", $r, $g, $b, $x, $y, $width, $height);
    }

    private function truncate(string $text, float $maxWidth, float $size): string
    {
        $perCharacter = $size * 0.5;
        $limit = max(3, (int) floor($maxWidth / $perCharacter));

        if (strlen($text) <= $limit) {
            return $text;
        }

        return substr($text, 0, max(1, $limit - 3)).'...';
    }

    private function flushPage(): void
    {
        $this->pages[] = $this->buffer;
        $this->buffer = '';
        $this->y = self::HEIGHT - self::MARGIN;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function assemble(array $document): string
    {
        $total = count($this->pages);
        $footer = $this->ascii($document['footer'] ?? 'EEC-BHDMS · report');

        foreach ($this->pages as $index => $content) {
            $this->pages[$index] = $content
                .$this->footerOps($footer, $index + 1, $total);
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $kids = [];
        $nextId = 5;

        foreach ($this->pages as $content) {
            $pageId = $nextId++;
            $contentId = $nextId++;

            $kids[] = $pageId.' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::WIDTH.' '.self::HEIGHT.'] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentId.' 0 R >>';
            $objects[$contentId] = '<< /Length '.strlen($content)." >>\nstream\n".$content."endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$total.' >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= 'xref'."\n".'0 '.$count."\n".'0000000000 65535 f '."\n";

        for ($id = 1; $id < $count; $id++) {
            if (! isset($offsets[$id])) {
                throw new RuntimeException('Missing PDF object '.$id);
            }

            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $pdf .= 'trailer'."\n".'<< /Size '.$count.' /Root 1 0 R >>'."\n".'startxref'."\n".$xrefOffset."\n".'%%EOF';

        return $pdf;
    }

    private function footerOps(string $text, int $page, int $total): string
    {
        $this->buffer = '';

        $this->text(self::MARGIN, self::FOOTER_Y, $text, 7, false, 0.5);
        $this->textRight(self::WIDTH - self::MARGIN, self::FOOTER_Y, 'Page '.$page.' of '.$total, 7, false, 0.5);

        $ops = $this->buffer;
        $this->buffer = '';

        return $ops;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
    }

    /**
     * Core fonts are WinAnsi; transliterate the typographic characters used in
     * the reports so nothing renders as a missing glyph.
     */
    private function ascii(string $text): string
    {
        $text = str_replace(
            ['—', '–', '·', '×', '²', '³', '°', 'µ', '…', '’', '“', '”'],
            ['-', '-', '-', 'x', '2', '3', 'deg', 'u', '...', "'", '"', '"'],
            $text
        );

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $converted;
    }
}
