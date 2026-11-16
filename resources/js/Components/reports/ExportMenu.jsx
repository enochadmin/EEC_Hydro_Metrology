import { Download } from 'lucide-react';

const LABELS = {
    csv: 'CSV',
    xlsx: 'Excel',
    pdf: 'PDF',
    geojson: 'GeoJSON',
};

const TITLES = {
    csv: 'Comma-separated values',
    xlsx: 'Excel workbook',
    pdf: 'Printable PDF',
    geojson: 'Geographic features as GeoJSON (EPSG:4326)',
};

/**
 * Download links for one report. Exports are plain GET requests so the browser
 * handles the file; the backend re-checks the export permissions on every call.
 */
export default function ExportMenu({ baseUrl, formats = ['csv', 'xlsx', 'pdf'], disabled = false, disabledNote, query = '' }) {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <span className="inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                <Download className="h-3 w-3" strokeWidth={2} />
                Export
            </span>

            {formats.map((format) =>
                disabled ? (
                    <span
                        key={format}
                        title={disabledNote ?? 'You do not hold the export permission.'}
                        className="cursor-not-allowed rounded-md border border-line px-2 py-1 text-[11px] font-medium text-muted/60"
                    >
                        {LABELS[format] ?? format}
                    </span>
                ) : (
                    <a
                        key={format}
                        href={`${baseUrl}/${format}${query ? `?${query}` : ''}`}
                        title={TITLES[format]}
                        className="rounded-md border border-line px-2 py-1 text-[11px] font-medium text-ink transition-colors hover:bg-canvas focus-ring"
                    >
                        {LABELS[format] ?? format}
                    </a>
                ),
            )}
        </div>
    );
}
