import Badge from '@/Components/Badge';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, formatNumber } from '@/Utils/format';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, GitCompare, Scale } from 'lucide-react';

const structureLabel = {
    monthly_daily_columns: 'Row-based · monthly records',
    multi_header_matrix: 'Column-based · month matrix',
    daily_series: 'Row-based · daily series',
};

function Tile({ label, value, hint, tone = 'default' }) {
    const toneClass = {
        default: 'text-ink',
        success: 'text-emerald-700 dark:text-emerald-300',
        warning: 'text-amber-700 dark:text-amber-300',
        danger: 'text-rose-700 dark:text-rose-300',
        brand: 'text-brand-700 dark:text-brand-300',
    }[tone];

    return (
        <div className="rounded-xl border border-line bg-surface px-4 py-3">
            <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</p>
            <p className={`mt-1.5 text-2xl font-semibold tabular ${toneClass}`}>{typeof value === 'number' ? formatNumber(value) : value}</p>
            <p className="mt-0.5 text-[11px] text-muted">{hint}</p>
        </div>
    );
}

export default function Compare({ station, batches = [], selected = {}, comparison = null, error = null }) {
    const update = (key, value) => {
        router.get(`/stations/${station.id}/compare`, { ...selected, [key]: value }, { preserveScroll: true, preserveState: true });
    };

    const summary = comparison?.summary;

    return (
        <AppLayout breadcrumbs={[{ label: 'Monitoring stations', href: '/stations' }, { label: station.code, href: `/stations/${station.id}` }, { label: 'Compare uploads' }]}>
            <Head title={`Compare uploads · ${station.name}`} />
            <div className="mb-4">
                <Link href={`/stations/${station.id}`} className="inline-flex items-center gap-1.5 text-[12px] font-medium text-muted hover:text-ink">
                    <ArrowLeft className="h-3.5 w-3.5" />Back to station
                </Link>
            </div>

            <PageHeader
                eyebrow={station.code}
                title="Compare station uploads"
                description="Pick a row-based file and a column-based matrix. Both are compared by calendar date after transformation; differences are reported, never reconciled."
            />

            <Card className="mt-4">
                <CardBody className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label htmlFor="left-batch" className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">Upload A</label>
                        <select
                            id="left-batch"
                            value={selected.left ?? ''}
                            onChange={(event) => update('left', event.target.value)}
                            className="mt-1.5 h-9 w-full rounded-lg border-line bg-surface px-3 text-[12px] text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">Choose an upload</option>
                            {batches.map((batch) => (
                                <option key={batch.uuid} value={batch.uuid}>
                                    {batch.file_name} · {structureLabel[batch.structure_type] ?? batch.structure_type ?? 'not transformed'}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label htmlFor="right-batch" className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">Upload B</label>
                        <select
                            id="right-batch"
                            value={selected.right ?? ''}
                            onChange={(event) => update('right', event.target.value)}
                            className="mt-1.5 h-9 w-full rounded-lg border-line bg-surface px-3 text-[12px] text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500"
                        >
                            <option value="">Choose an upload</option>
                            {batches.map((batch) => (
                                <option key={batch.uuid} value={batch.uuid}>
                                    {batch.file_name} · {structureLabel[batch.structure_type] ?? batch.structure_type ?? 'not transformed'}
                                </option>
                            ))}
                        </select>
                    </div>
                </CardBody>
            </Card>

            {error && (
                <div className="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-[12px] text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                    {error}
                </div>
            )}

            {batches.length < 2 && (
                <div className="mt-4">
                    <EmptyState
                        icon={GitCompare}
                        title="Two transformed uploads are needed"
                        description="Upload the row-based workbook and the column-based matrix for this station, transform both, then return here to compare them."
                    />
                </div>
            )}

            {comparison && (
                <>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <Tile label="Matching values" value={summary.matching} hint={`${summary.agreement_pct}% of compared days`} tone="success" />
                        <Tile label="Different values" value={summary.different} hint="Both numeric, values differ" tone="danger" />
                        <Tile label="Only in A / B" value={`${summary.only_left} / ${summary.only_right}`} hint="Date missing on one side" tone="warning" />
                        <Tile label="Max difference" value={summary.max_difference} hint={`Mean abs. ${summary.mean_absolute_difference}`} tone="brand" />
                    </div>

                    <div className="mt-3 flex flex-wrap items-center gap-1.5">
                        <Badge tone="brand" size="sm">{comparison.left.file_name}</Badge>
                        <span className="text-[11px] text-muted">
                            {structureLabel[comparison.left.structure_type] ?? comparison.left.structure_type} · {comparison.left.from} → {comparison.left.to}
                        </span>
                        <Scale className="h-3.5 w-3.5 text-muted" />
                        <Badge tone="brand" size="sm">{comparison.right.file_name}</Badge>
                        <span className="text-[11px] text-muted">
                            {structureLabel[comparison.right.structure_type] ?? comparison.right.structure_type} · {comparison.right.from} → {comparison.right.to}
                        </span>
                    </div>

                    <div className="mt-4 grid gap-4 xl:grid-cols-2">
                        <Card className="overflow-hidden">
                            <CardHeader icon={GitCompare} title="Dated differences" description={`${formatNumber(comparison.differences_total)} dates differ or exist on one side.`} />
                            {comparison.differences.length === 0 ? (
                                <CardBody>
                                    <p className="rounded-lg border border-dashed border-line px-3 py-4 text-[12px] text-muted">The two uploads agree on every compared date.</p>
                                </CardBody>
                            ) : (
                                <div className="max-h-[460px] overflow-y-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="sticky top-0 border-b border-line bg-canvas">
                                            <tr>
                                                <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Date</th>
                                                <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">A</th>
                                                <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">B</th>
                                                <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">A − B</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-line">
                                            {comparison.differences.map((row) => (
                                                <tr key={row.date}>
                                                    <td className="px-3 py-1.5 tabular text-[11.5px] text-ink">{row.date}</td>
                                                    <td className="px-3 py-1.5 tabular text-[11.5px] text-ink">{row.left ?? '—'}</td>
                                                    <td className="px-3 py-1.5 tabular text-[11.5px] text-ink">{row.right ?? '—'}</td>
                                                    <td className={`px-3 py-1.5 tabular text-[11.5px] ${row.difference === null ? 'text-muted' : row.difference === 0 ? 'text-muted' : 'text-rose-700 dark:text-rose-300'}`}>
                                                        {row.difference ?? '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Card>

                        <Card className="overflow-hidden">
                            <CardHeader icon={Scale} title="Monthly totals" description="Accumulated per calendar month; difference = A − B." />
                            <div className="max-h-[460px] overflow-y-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="sticky top-0 border-b border-line bg-canvas">
                                        <tr>
                                            <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Month</th>
                                            <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">A</th>
                                            <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">B</th>
                                            <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Difference</th>
                                            <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Dates</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {comparison.monthly.map((row) => (
                                            <tr key={row.month}>
                                                <td className="px-3 py-1.5 tabular text-[11.5px] text-ink">{row.month}</td>
                                                <td className="px-3 py-1.5 tabular text-[11.5px] text-ink">{row.left}</td>
                                                <td className="px-3 py-1.5 tabular text-[11.5px] text-ink">{row.right}</td>
                                                <td className={`px-3 py-1.5 tabular text-[11.5px] ${Math.abs(row.difference) < 0.005 ? 'text-muted' : 'text-rose-700 dark:text-rose-300'}`}>{row.difference}</td>
                                                <td className="px-3 py-1.5 tabular text-[11px] text-muted">{row.differences}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </div>
                </>
            )}

            {batches.length > 0 && (
                <Card className="mt-4 overflow-hidden">
                    <CardHeader icon={GitCompare} title="Uploads available for this station" description="Only transformed workbooks can be compared." />
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-line bg-canvas/50">
                                <tr>
                                    <th className="px-4 py-2 text-[10px] font-semibold uppercase text-muted">Workbook</th>
                                    <th className="px-4 py-2 text-[10px] font-semibold uppercase text-muted">Structure</th>
                                    <th className="px-4 py-2 text-[10px] font-semibold uppercase text-muted">Sheet</th>
                                    <th className="px-4 py-2 text-[10px] font-semibold uppercase text-muted">Imported</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {batches.map((batch) => (
                                    <tr key={batch.uuid}>
                                        <td className="px-4 py-2.5 text-[12px] text-ink">{batch.file_name}</td>
                                        <td className="px-4 py-2.5 text-[11px] text-muted">{structureLabel[batch.structure_type] ?? batch.structure_type ?? '—'}</td>
                                        <td className="px-4 py-2.5 text-[11px] text-muted">{batch.sheet_name ?? '—'}</td>
                                        <td className="px-4 py-2.5 text-[11px] text-muted">{batch.imported_at ? formatDateTime(batch.imported_at) : batch.stage}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}
        </AppLayout>
    );
}
