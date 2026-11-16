import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { structureLabel } from '@/Utils/excel';
import { formatNumber } from '@/Utils/format';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Columns3, Download, GitCompareArrows, ShieldCheck, Sparkles } from 'lucide-react';
import { useState } from 'react';

function SummaryTile({ label, value, tone = 'text-ink', hint }) {
    return (
        <Card className="px-4 py-3">
            <p className="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</p>
            <p className={`mt-1 text-xl font-semibold tabular ${tone}`}>{value}</p>
            {hint && <p className="mt-0.5 text-[10.5px] text-muted">{hint}</p>}
        </Card>
    );
}

const STATUS_TONE = { valid: 'success', missing: 'warning', invalid: 'danger' };

export default function Transformation({ batch, transformed = [], original = null }) {
    const [busy, setBusy] = useState(null);
    const summary = batch.summary ?? {};
    const validation = batch.validation_summary;

    const run = (action, url) => {
        setBusy(action);
        router.post(url, {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Excel', href: '/excel' }, { label: batch.file_name, href: `/excel/${batch.uuid}/preview?sheet=${encodeURIComponent(batch.sheet_name ?? '')}` }, { label: 'Transformation' }]}>
            <Head title="Transformation preview" />

            <PageHeader
                title="Transformation preview"
                description={`${batch.sheet_name} · ${structureLabel(batch.structure_type)}. One row per valid calendar date; nothing is committed yet.`}
                actions={
                    <>
                        <Button variant="secondary" size="sm" onClick={() => window.location.assign(`/excel/${batch.uuid}/export/transformed`)}>
                            <Download className="h-3.5 w-3.5" strokeWidth={1.75} />
                            Download Excel
                        </Button>
                        <Button variant="secondary" size="sm" onClick={() => window.location.assign(`/excel/${batch.uuid}/comparison`)}>
                            <GitCompareArrows className="h-3.5 w-3.5" strokeWidth={1.75} />
                            Comparison
                        </Button>
                    </>
                }
            />

            <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                <SummaryTile label="Original rows" value={formatNumber(summary.original_rows ?? 0)} />
                <SummaryTile label="Original columns" value={formatNumber(summary.original_columns ?? 0)} />
                <SummaryTile label="Valid dates" value={formatNumber(summary.valid_calendar_dates ?? 0)} />
                <SummaryTile label="Numeric values" value={formatNumber(summary.numeric_measurements ?? 0)} />
                <SummaryTile label="Zero values" value={formatNumber(summary.zero_measurements ?? 0)} tone="text-brand-700 dark:text-brand-300" hint="Kept as measurements" />
                <SummaryTile label="Missing" value={formatNumber(summary.missing_measurements ?? 0)} tone="text-amber-600 dark:text-amber-400" />
                <SummaryTile label="Generated rows" value={formatNumber(summary.generated_observations ?? 0)} tone="text-emerald-600 dark:text-emerald-400" />
            </div>

            {validation && (
                <div className="mt-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-line bg-canvas/50 px-4 py-3">
                    <p className="text-[12px] text-muted">
                        Validation: <span className="font-medium text-ink">{formatNumber(validation.valid_rows ?? 0)} valid</span>,{' '}
                        {formatNumber(validation.missing_rows ?? 0)} missing, {formatNumber(validation.invalid_rows ?? 0)} invalid,{' '}
                        {formatNumber(validation.duplicate_rows ?? 0)} duplicates.
                    </p>
                    <Link href={`/excel/${batch.uuid}/validation`} className="text-[12px] font-medium text-brand-700 hover:underline dark:text-brand-300">
                        Open validation report
                    </Link>
                </div>
            )}

            <div className="mt-4 flex flex-wrap items-center gap-2">
                <Button size="sm" onClick={() => run('validate', `/excel/${batch.uuid}/validate`)} disabled={busy !== null}>
                    <ShieldCheck className="h-4 w-4" strokeWidth={1.75} />
                    {busy === 'validate' ? 'Validating…' : validation ? 'Re-run validation' : 'Validate dataset'}
                </Button>
                <Button variant="secondary" size="sm" onClick={() => run('compare', `/excel/${batch.uuid}/compare`)} disabled={busy !== null}>
                    <GitCompareArrows className="h-4 w-4" strokeWidth={1.75} />
                    {busy === 'compare' ? 'Comparing…' : 'Compare sheet structures'}
                </Button>
                <Link href={`/excel/${batch.uuid}/mapping?sheet=${encodeURIComponent(batch.sheet_name ?? '')}&structure=${batch.structure_type}`} className="text-[11.5px] font-medium text-muted hover:text-ink">
                    Adjust mapping
                </Link>
            </div>

            <Card className="mt-4 overflow-hidden">
                <CardHeader
                    icon={Sparkles}
                    title="Transformed observations"
                    description={`Previewing the first ${transformed.length} generated rows. Missing and invalid rows stay visible with their flag.`}
                    action={<Badge tone="brand" size="sm">{formatNumber(summary.rows_written ?? 0)} rows generated</Badge>}
                />
                <div className="eec-scrollbar max-h-[460px] overflow-auto">
                    <table className="min-w-full text-left text-[11.5px]">
                        <thead className="sticky top-0 bg-canvas text-[9.5px] uppercase tracking-wide text-muted">
                            <tr>
                                <th className="px-3 py-2">Row</th>
                                <th className="px-3 py-2">Station</th>
                                <th className="px-3 py-2">Date</th>
                                <th className="px-3 py-2">Time</th>
                                <th className="px-3 py-2">Parameter</th>
                                <th className="px-3 py-2 text-right">Value</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Source</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {transformed.map((row, index) => (
                                <tr key={`${row.source_cell ?? row.row_number}-${index}`} className="hover:bg-canvas/40">
                                    <td className="px-3 py-1.5 tabular text-muted">{row.row_number}</td>
                                    <td className="max-w-[160px] truncate px-3 py-1.5 text-ink">{row.station_name}</td>
                                    <td className="whitespace-nowrap px-3 py-1.5 tabular text-ink">{row.date ?? '—'}</td>
                                    <td className="px-3 py-1.5 tabular text-muted">{row.time}</td>
                                    <td className="px-3 py-1.5 text-muted">{row.parameter}</td>
                                    <td className="px-3 py-1.5 text-right tabular text-ink">{row.value === null ? '—' : formatNumber(row.value, { maximumFractionDigits: 3 })}</td>
                                    <td className="px-3 py-1.5"><Badge tone={STATUS_TONE[row.status] ?? 'neutral'} size="sm">{row.status}</Badge></td>
                                    <td className="px-3 py-1.5 font-mono text-[10px] text-muted">{row.source_cell ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            {original && (
                <Card className="mt-4 overflow-hidden">
                    <CardHeader icon={Columns3} title="Original worksheet" description={`First ${original.shown_rows} rows, for visual comparison with the transformed records.`} />
                    <div className="eec-scrollbar max-h-[320px] overflow-auto">
                        <table className="min-w-full text-left text-[10.5px]">
                            <thead className="sticky top-0 bg-canvas text-[9px] uppercase tracking-wide text-muted">
                                <tr>
                                    <th className="px-2 py-1.5">#</th>
                                    {original.columns.map((column) => <th key={column} className="px-2 py-1.5">{column}</th>)}
                                </tr>
                            </thead>
                            <tbody>
                                {original.rows.map((row) => (
                                    <tr key={row.row} className="odd:bg-surface even:bg-canvas/30">
                                        <td className="px-2 py-1 text-muted">{row.row}</td>
                                        {original.columns.map((column) => (
                                            <td key={column} className="max-w-[140px] truncate px-2 py-1 text-ink">{row.cells[column] ?? ''}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            <div className="mt-4 flex items-center justify-between">
                <Link href={`/excel/${batch.uuid}/preview?sheet=${encodeURIComponent(batch.sheet_name ?? '')}`} className="inline-flex items-center gap-1 text-[11.5px] font-medium text-muted hover:text-ink">
                    <ArrowLeft className="h-3.5 w-3.5" /> Back to inspection
                </Link>
                <Link href={`/excel/${batch.uuid}/validation`} className="text-[11.5px] font-medium text-brand-700 hover:underline dark:text-brand-300">
                    Continue to validation
                </Link>
            </div>
        </AppLayout>
    );
}
