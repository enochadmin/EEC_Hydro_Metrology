import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { structureLabel } from '@/Utils/excel';
import { formatNumber } from '@/Utils/format';
import { Head } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, GitCompareArrows } from 'lucide-react';

const CHANGE_META = {
    changed: { label: 'Changed', tone: 'danger' },
    missing: { label: 'Missing', tone: 'warning' },
    invalid: { label: 'Invalid', tone: 'danger' },
    formulas_resolved: { label: 'Formula resolved', tone: 'brand' },
};

function displayValue(value) {
    if (value === null || value === undefined || value === '') {
        return 'Blank';
    }

    return typeof value === 'number' ? formatNumber(value, { maximumFractionDigits: 6 }) : String(value);
}

function SummaryCard({ label, value, tone = 'text-ink' }) {
    return (
        <Card className="px-4 py-3">
            <p className="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</p>
            <p className={`tabular mt-1 text-xl font-semibold ${tone}`}>{formatNumber(value ?? 0)}</p>
        </Card>
    );
}

export default function Comparison({ batch, comparison }) {
    const counts = comparison?.counts ?? {};
    const changes = comparison?.changes ?? [];

    return (
        <AppLayout
            breadcrumbs={[
                { label: 'Excel', href: '/excel' },
                { label: batch.file_name, href: `/excel/${batch.uuid}/transformation` },
                { label: 'Comparison' },
            ]}
        >
            <Head title="Source and generated data comparison" />

            <PageHeader
                title="Source vs generated output"
                description="Each generated observation is checked against the source value it came from. Differences and records needing review are highlighted below."
                actions={
                    <Button href={`/excel/${batch.uuid}/transformation`} variant="secondary" size="sm">
                        <ArrowLeft className="h-3.5 w-3.5" />
                        Back to transformation
                    </Button>
                }
            />

            {!comparison?.available || !comparison?.counts ? (
                <Card className="mt-6">
                    <CardBody className="text-center">
                        <GitCompareArrows className="mx-auto h-6 w-6 text-muted" strokeWidth={1.75} />
                        <p className="mt-2 text-[13px] font-medium text-ink">No comparison is available yet</p>
                        <p className="mt-1 text-[11.5px] text-muted">
                            Generate a transformed preview, then run Compare from that page to create a fresh source-to-output comparison.
                        </p>
                    </CardBody>
                </Card>
            ) : (
                <>
                    <div className="mt-5 rounded-lg border border-line bg-canvas/50 px-4 py-3 text-[12px] text-muted">
                        Source sheet: <span className="font-medium text-ink">{comparison.source_sheet ?? batch.sheet_name ?? '—'}</span>
                        <span className="mx-2">·</span>
                        Generated structure:{' '}
                        <span className="font-medium text-ink">{structureLabel(comparison.generated_structure ?? batch.structure_type)}</span>
                        <span className="mx-2">·</span>
                        Compared {comparison.compared_at ? new Date(comparison.compared_at).toLocaleString() : '—'}
                    </div>

                    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        <SummaryCard label="Records compared" value={counts.total} />
                        <SummaryCard label="Values preserved" value={counts.preserved} tone="text-emerald-600 dark:text-emerald-400" />
                        <SummaryCard label="Changed" value={counts.changed} tone="text-rose-600 dark:text-rose-400" />
                        <SummaryCard label="Missing" value={counts.missing} tone="text-amber-600 dark:text-amber-400" />
                        <SummaryCard label="Invalid" value={counts.invalid} tone="text-rose-600 dark:text-rose-400" />
                        <SummaryCard label="Formulas resolved" value={counts.formulas_resolved} tone="text-brand-700 dark:text-brand-300" />
                    </div>

                    {changes.length === 0 ? (
                        <Card className="mt-4">
                            <CardBody className="flex items-start gap-3">
                                <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" strokeWidth={1.75} />
                                <div>
                                    <p className="text-[13px] font-semibold text-ink">All generated values match their source cells</p>
                                    <p className="mt-1 text-[11.5px] text-muted">
                                        No changed, missing, invalid or formula-derived records were found.
                                    </p>
                                </div>
                            </CardBody>
                        </Card>
                    ) : (
                        <Card className="mt-4 overflow-hidden">
                            <CardHeader
                                icon={GitCompareArrows}
                                title="Records to review"
                                description={`Showing ${changes.length} non-matching, missing, invalid or formula-derived records${comparison.changes_truncated ? ' (sample limited to the first 150)' : ''}.`}
                            />
                            <div className="eec-scrollbar max-h-[560px] overflow-auto">
                                <table className="min-w-full text-left text-[11.5px]">
                                    <thead className="sticky top-0 bg-canvas text-[9.5px] uppercase tracking-wide text-muted">
                                        <tr>
                                            <th className="px-3 py-2">Review status</th>
                                            <th className="px-3 py-2">Source cell</th>
                                            <th className="px-3 py-2 text-right">Source value</th>
                                            <th className="px-3 py-2">Generated date</th>
                                            <th className="px-3 py-2">Station / parameter</th>
                                            <th className="px-3 py-2 text-right">Generated value</th>
                                            <th className="px-3 py-2">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {changes.map((change, index) => {
                                            const meta = CHANGE_META[change.change_type] ?? CHANGE_META.changed;

                                            return (
                                                <tr
                                                    key={`${change.source_cell ?? 'row'}-${index}`}
                                                    className={
                                                        change.change_type === 'changed' || change.change_type === 'invalid'
                                                            ? 'bg-rose-50/60 dark:bg-rose-500/5'
                                                            : 'hover:bg-canvas/40'
                                                    }
                                                >
                                                    <td className="whitespace-nowrap px-3 py-2">
                                                        <Badge tone={meta.tone} size="sm">
                                                            {meta.label}
                                                        </Badge>
                                                    </td>
                                                    <td className="whitespace-nowrap px-3 py-2 font-mono text-[10px] text-muted">
                                                        {change.source_cell ?? '—'}
                                                    </td>
                                                    <td className="tabular max-w-[150px] truncate px-3 py-2 text-right text-ink">
                                                        {displayValue(change.source_value)}
                                                    </td>
                                                    <td className="tabular whitespace-nowrap px-3 py-2 text-ink">{change.date ?? '—'}</td>
                                                    <td className="max-w-[200px] px-3 py-2 text-ink">
                                                        <span className="block truncate">{change.station ?? '—'}</span>
                                                        <span className="block truncate text-[10px] text-muted">{change.parameter ?? '—'}</span>
                                                    </td>
                                                    <td className="tabular whitespace-nowrap px-3 py-2 text-right text-ink">
                                                        {displayValue(change.generated_value)}
                                                    </td>
                                                    <td className="max-w-[260px] px-3 py-2 text-muted">
                                                        {change.message ?? change.generated_status}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    )}
                </>
            )}
        </AppLayout>
    );
}
