import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { formatNumber } from '@/Utils/format';
import { Head } from '@inertiajs/react';
import { AlertTriangle, GitCompareArrows, Info } from 'lucide-react';

export default function Comparison({ batch, comparison }) {
    return (
        <AppLayout breadcrumbs={[{ label: 'Excel', href: '/excel' }, { label: batch.file_name, href: `/excel/${batch.uuid}/transformation` }, { label: 'Comparison' }]}>
            <Head title="Sheet comparison" />

            <PageHeader
                title="Matrix vs date series"
                description={`${batch.sheet_name ?? 'Worksheet'} · the monthly matrix and the date/value series are compared by calendar date, not by row order.`}
                actions={<Button href={`/excel/${batch.uuid}/transformation`} variant="secondary" size="sm">Back to transformation</Button>}
            />

            {!comparison?.available ? (
                <Card className="mt-6">
                    <CardBody className="text-center">
                        <GitCompareArrows className="mx-auto h-6 w-6 text-muted" strokeWidth={1.75} />
                        <p className="mt-2 text-[13px] font-medium text-ink">No comparison has been run</p>
                        <p className="mt-1 text-[11.5px] text-muted">
                            {comparison?.message ?? 'Run a comparison from the transformation page while the sheet has both a matrix and a date series.'}
                        </p>
                        <Button href={`/excel/${batch.uuid}/transformation`} size="sm" className="mt-3">Go to transformation</Button>
                    </CardBody>
                </Card>
            ) : (
                <>
                    <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        {[
                            ['Matching', comparison.matching, 'text-emerald-600 dark:text-emerald-400'],
                            ['Different values', comparison.different, 'text-rose-600 dark:text-rose-400'],
                            ['Missing in daily', comparison.missing_in_daily, 'text-amber-600 dark:text-amber-400'],
                            ['Missing in matrix', comparison.missing_in_matrix, 'text-amber-600 dark:text-amber-400'],
                            ['Missing values', comparison.missing_values, 'text-muted'],
                            ['Alignment errors', comparison.alignment_errors, 'text-rose-600 dark:text-rose-400'],
                        ].map(([label, value, tone]) => (
                            <Card key={label} className="px-4 py-3">
                                <p className="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</p>
                                <p className={`mt-1 text-xl font-semibold tabular ${tone}`}>{formatNumber(value ?? 0)}</p>
                            </Card>
                        ))}
                    </div>

                    {comparison.formula_mirror && (
                        <div className="mt-4 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[11.5px] leading-relaxed text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" strokeWidth={2} />
                            <p>
                                <span className="font-semibold">The date series is formula-linked to the matrix.</span> {comparison.note}
                                {' '}Alignment errors mean a row's date does not match the matrix cell its formula references.
                            </p>
                        </div>
                    )}

                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        <Card className="px-4 py-3">
                            <p className="text-[10.5px] uppercase tracking-[0.12em] text-muted">Matrix</p>
                            <p className="mt-1 text-[12px] text-ink">
                                {formatNumber(comparison.matrix?.dates)} dates · {formatNumber(comparison.matrix?.valid)} valid · {formatNumber(comparison.matrix?.missing)} missing
                            </p>
                        </Card>
                        <Card className="px-4 py-3">
                            <p className="text-[10.5px] uppercase tracking-[0.12em] text-muted">Date series</p>
                            <p className="mt-1 text-[12px] text-ink">
                                {formatNumber(comparison.daily?.dates)} dates · {formatNumber(comparison.daily?.valid)} valid · {formatNumber(comparison.daily?.formula_rows)} formula cells
                            </p>
                        </Card>
                    </div>

                    {comparison.value_differences?.length > 0 && (
                        <Card className="mt-4 overflow-hidden">
                            <CardHeader icon={GitCompareArrows} title="Value differences" description="First 100 dates where both sides hold a number but the values differ." />
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-[11.5px]">
                                    <thead className="bg-canvas/60 text-[9.5px] uppercase tracking-wide text-muted">
                                        <tr>
                                            <th className="px-4 py-2">Date</th>
                                            <th className="px-4 py-2 text-right">Matrix</th>
                                            <th className="px-4 py-2 text-right">Date series</th>
                                            <th className="px-4 py-2">Matrix cell</th>
                                            <th className="px-4 py-2">Series row</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {comparison.value_differences.map((difference) => (
                                            <tr key={difference.date}>
                                                <td className="px-4 py-1.5 tabular text-ink">{difference.date}</td>
                                                <td className="px-4 py-1.5 text-right tabular text-ink">{formatNumber(difference.matrix, { maximumFractionDigits: 3 })}</td>
                                                <td className="px-4 py-1.5 text-right tabular text-ink">{formatNumber(difference.daily, { maximumFractionDigits: 3 })}</td>
                                                <td className="px-4 py-1.5 font-mono text-[10px] text-muted">{difference.matrix_cell}</td>
                                                <td className="px-4 py-1.5 tabular text-muted">{difference.daily_row}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    )}

                    {comparison.alignment_samples?.length > 0 && (
                        <Card className="mt-4 overflow-hidden">
                            <CardHeader icon={Info} title="Date alignment samples" description="Rows whose column A date disagrees with the matrix cell referenced by column B." />
                            <div className="eec-scrollbar max-h-[320px] overflow-auto">
                                <table className="w-full text-left text-[11.5px]">
                                    <thead className="bg-canvas/60 text-[9.5px] uppercase tracking-wide text-muted">
                                        <tr>
                                            <th className="px-4 py-2">Date in column A</th>
                                            <th className="px-4 py-2">Formula</th>
                                            <th className="px-4 py-2">Expected date</th>
                                            <th className="px-4 py-2">Row</th>
                                            <th className="px-4 py-2">Message</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {comparison.alignment_samples.map((sample, index) => (
                                            <tr key={`${sample.row}-${index}`}>
                                                <td className="px-4 py-1.5 tabular text-ink">{sample.date}</td>
                                                <td className="px-4 py-1.5 font-mono text-[10px] text-muted">{sample.formula}</td>
                                                <td className="px-4 py-1.5 tabular text-muted">{sample.expected_date ?? '—'}</td>
                                                <td className="px-4 py-1.5 tabular text-muted">{sample.row}</td>
                                                <td className="px-4 py-1.5 text-muted">{sample.message}</td>
                                            </tr>
                                        ))}
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
