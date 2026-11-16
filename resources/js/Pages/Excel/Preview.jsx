import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { structureLabel } from '@/Utils/excel';
import { formatBytes, formatNumber } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Columns3, FileSpreadsheet, Rows3, TriangleAlert } from 'lucide-react';

function CandidateCard({ batch, candidate, sheet }) {
    const details = [];

    if (candidate.key === 'monthly_daily_columns') {
        details.push(['Header row', candidate.header_row]);
        details.push(['Day columns', candidate.day_column_count]);
        details.push(['Station column', candidate.columns?.station ?? '—']);
        details.push(['Year / month', `${candidate.columns?.year ?? '—'} / ${candidate.columns?.month ?? '—'}`]);
    } else if (candidate.key === 'multi_header_matrix') {
        details.push(['Periods', candidate.period_count]);
        details.push(['Year row', candidate.year_row]);
        details.push(['Day rows', `${candidate.day_rows?.start}–${candidate.day_rows?.end}`]);
        details.push(['Day column', candidate.day_column]);
    } else if (candidate.key === 'daily_series') {
        details.push(['Date column', candidate.date_column]);
        details.push(['Value column', candidate.value_column]);
        details.push(['First row', candidate.start_row]);
        details.push(['Formula driven', candidate.formula_based ? 'Yes' : 'No']);
    }

    return (
        <Card className="flex h-full flex-col">
            <CardBody className="flex-1">
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <p className="text-[13px] font-semibold text-ink">{structureLabel(candidate.key)}</p>
                        <p className="mt-0.5 font-mono text-[10.5px] text-muted">{candidate.key}</p>
                    </div>
                    <Badge tone={candidate.confidence >= 0.9 ? 'success' : candidate.confidence >= 0.75 ? 'warning' : 'neutral'} size="sm">
                        {Math.round(candidate.confidence * 100)}% match
                    </Badge>
                </div>

                <dl className="mt-3 grid grid-cols-2 gap-2">
                    {details.map(([label, value]) => (
                        <div key={label} className="rounded-lg bg-canvas/60 px-2.5 py-1.5">
                            <dt className="text-[9.5px] uppercase tracking-wide text-muted">{label}</dt>
                            <dd className="mt-0.5 truncate text-[11.5px] font-medium text-ink">{value}</dd>
                        </div>
                    ))}
                </dl>

                {candidate.issues?.length > 0 && (
                    <ul className="mt-3 space-y-1">
                        {candidate.issues.map((issue) => (
                            <li key={issue} className="flex items-start gap-1.5 text-[10.5px] leading-relaxed text-amber-700 dark:text-amber-300">
                                <TriangleAlert className="mt-0.5 h-3 w-3 shrink-0" strokeWidth={2} />
                                {issue}
                            </li>
                        ))}
                    </ul>
                )}
            </CardBody>

            <div className="border-t border-line px-4 py-2.5">
                <Link
                    href={`/excel/${batch.uuid}/mapping?sheet=${encodeURIComponent(sheet)}&structure=${candidate.key}`}
                    className="inline-flex items-center gap-1 text-[12px] font-medium text-brand-700 hover:underline dark:text-brand-300"
                >
                    Use this structure <ArrowRight className="h-3.5 w-3.5" strokeWidth={2} />
                </Link>
            </div>
        </Card>
    );
}

export default function Preview({ batch, sheets = [], selectedSheet, detection, preview }) {
    return (
        <AppLayout breadcrumbs={[{ label: 'Excel', href: '/excel' }, { label: 'Inspect' }]}>
            <Head title={`Inspect ${batch.file_name}`} />

            <PageHeader
                title={batch.file_name}
                description={`Uploaded ${new Date(batch.created_at).toLocaleString()} · ${formatBytes(batch.file_size)} · original preserved unchanged.`}
                actions={<Button href="/excel" variant="secondary" size="sm">All workbooks</Button>}
            />

            <div className="mt-5 grid gap-3 sm:grid-cols-3">
                <Card className="px-4 py-3">
                    <p className="text-[10.5px] uppercase tracking-[0.12em] text-muted">Worksheets</p>
                    <p className="mt-1 text-lg font-semibold text-ink">{sheets.length}</p>
                </Card>
                <Card className="px-4 py-3">
                    <p className="text-[10.5px] uppercase tracking-[0.12em] text-muted">Selected sheet size</p>
                    <p className="mt-1 text-lg font-semibold tabular text-ink">
                        {formatNumber(preview?.total_rows ?? 0)} × {formatNumber(preview?.total_columns ?? 0)}
                    </p>
                </Card>
                <Card className="px-4 py-3">
                    <p className="text-[10.5px] uppercase tracking-[0.12em] text-muted">Detected structures</p>
                    <p className="mt-1 text-lg font-semibold text-ink">{detection?.candidates?.length ?? 0}</p>
                </Card>
            </div>

            <Card className="mt-4 overflow-hidden">
                <div className="flex flex-wrap gap-1 border-b border-line px-4 py-2">
                    {sheets.map((sheet) => (
                        <Link
                            key={sheet.name}
                            href={`/excel/${batch.uuid}/preview?sheet=${encodeURIComponent(sheet.name)}`}
                            className={`rounded-lg px-3 py-1.5 text-[12px] font-medium transition-colors ${
                                sheet.name === selectedSheet
                                    ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200'
                                    : 'text-muted hover:bg-canvas hover:text-ink'
                            }`}
                        >
                            {sheet.name}
                            <span className="ml-1.5 text-[10px] tabular text-muted">{formatNumber(sheet.rows)}×{formatNumber(sheet.columns)}</span>
                        </Link>
                    ))}
                </div>

                <CardBody className="space-y-4">
                    <div>
                        <h2 className="text-sm font-semibold tracking-tight text-ink">Detected structures</h2>
                        <p className="mt-1 text-[11.5px] leading-relaxed text-muted">
                            Detection uses cell content and header patterns only. A sheet can match more than one structure; choose the one that matches the data you intend to transform.
                        </p>
                    </div>

                    {detection?.candidates?.length ? (
                        <div className="grid gap-3 lg:grid-cols-2">
                            {detection.candidates.map((candidate) => (
                                <CandidateCard key={candidate.key} batch={batch} candidate={candidate} sheet={selectedSheet} />
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[12px] text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                            No supported structure was detected on this worksheet. Check that the header row is present and that day or date columns contain values.
                        </div>
                    )}
                </CardBody>
            </Card>

            <Card className="mt-4 overflow-hidden">
                <CardHeader
                    icon={Columns3}
                    title="Original worksheet preview"
                    description={`First ${preview?.shown_rows ?? 0} rows and ${preview?.shown_columns ?? 0} columns, exactly as stored.`}
                    action={<span className="inline-flex items-center gap-1 text-[10.5px] text-muted"><Rows3 className="h-3 w-3" />{formatNumber(preview?.total_rows ?? 0)} rows</span>}
                />
                {preview ? (
                    <div className="eec-scrollbar max-h-[460px] overflow-auto">
                        <table className="min-w-full border-collapse text-left text-[11px]">
                            <thead className="sticky top-0 bg-canvas text-[9.5px] uppercase tracking-wide text-muted">
                                <tr>
                                    <th className="sticky left-0 z-10 border-b border-line bg-canvas px-2 py-1.5">#</th>
                                    {preview.columns.map((column) => (
                                        <th key={column} className="border-b border-line px-2 py-1.5 font-semibold">{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {preview.rows.map((row) => (
                                    <tr key={row.row} className="odd:bg-surface even:bg-canvas/30">
                                        <td className="sticky left-0 z-10 border-b border-line bg-inherit px-2 py-1 text-[9.5px] tabular text-muted">{row.row}</td>
                                        {preview.columns.map((column) => (
                                            <td key={column} className="max-w-[160px] truncate border-b border-line px-2 py-1 text-ink" title={String(row.cells[column] ?? '')}>
                                                {row.cells[column] === null || row.cells[column] === undefined || row.cells[column] === '' ? '' : String(row.cells[column])}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <CardBody>
                        <p className="text-[12px] text-muted">No preview available.</p>
                    </CardBody>
                )}
                {preview?.truncated_columns && (
                    <p className="border-t border-line px-4 py-2 text-[10.5px] text-muted">
                        Showing {preview.shown_columns} of {formatNumber(preview.total_columns)} columns; the full sheet is preserved on disk.
                    </p>
                )}
            </Card>

            <div className="mt-4 flex items-center gap-2 rounded-lg border border-line bg-canvas/40 px-4 py-3 text-[11.5px] text-muted">
                <FileSpreadsheet className="h-4 w-4 text-brand-700 dark:text-brand-300" strokeWidth={1.75} />
                Next: choose a structure above, confirm the column mapping, then preview the transformed observations.
            </div>
        </AppLayout>
    );
}
