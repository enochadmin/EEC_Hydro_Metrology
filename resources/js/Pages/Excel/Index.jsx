import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Can from '@/Components/Can';
import { Card, CardHeader } from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import DeleteExcelBatchAction from '@/Components/excel/DeleteExcelBatchAction';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { stageLabel, stageTone, structureLabel } from '@/Utils/excel';
import { formatNumber } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Clock3, FileSpreadsheet, Layers3, UploadCloud } from 'lucide-react';

export default function Index({ stats = {}, recent = [] }) {
    const summary = [
        { label: 'Workbooks', value: stats.uploads ?? 0, icon: FileSpreadsheet },
        { label: 'Imported batches', value: stats.imported_batches ?? 0, icon: CheckCircle2 },
        { label: 'Observations', value: stats.observations ?? 0, icon: Layers3 },
        { label: 'Rejected rows', value: stats.rejected ?? 0, icon: Clock3 },
    ];

    return (
        <AppLayout breadcrumbs={[{ label: 'Excel' }]}>
            <Head title="Excel import" />

            <PageHeader
                title="Excel import"
                description="Upload, transform and import workbook data."
                actions={
                    <>
                        <Button href="/excel/history" variant="secondary" size="sm">
                            History
                        </Button>
                        <Can permission="upload data">
                            <Button href="/excel/upload" size="sm">
                                <UploadCloud className="h-4 w-4" strokeWidth={1.75} />
                                Upload
                            </Button>
                        </Can>
                    </>
                }
            />

            <div className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {summary.map((item) => (
                    <Card key={item.label} className="px-5 py-4">
                        <div className="flex items-start justify-between">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">{item.label}</p>
                            <item.icon className="h-4 w-4 text-brand-700/70 dark:text-brand-300/80" strokeWidth={1.75} />
                        </div>
                        <p className="tabular mt-2 text-2xl font-semibold text-ink">{formatNumber(item.value)}</p>
                    </Card>
                ))}
            </div>

            <Card className="mt-4 overflow-hidden">
                <CardHeader
                    icon={FileSpreadsheet}
                    title="Recent workbooks"
                    action={
                        <Link href="/excel/history" className="text-[11.5px] font-medium text-brand-700 hover:underline dark:text-brand-300">
                            View all
                        </Link>
                    }
                />
                {recent.length === 0 ? (
                    <EmptyState icon={FileSpreadsheet} title="No workbooks yet" description="Upload a workbook to begin." />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-canvas/60 text-[10.5px] uppercase tracking-[0.12em] text-muted">
                                <tr>
                                    <th className="px-5 py-2.5 font-semibold">Workbook</th>
                                    <th className="px-4 py-2.5 font-semibold">Worksheet</th>
                                    <th className="px-4 py-2.5 font-semibold">Structure</th>
                                    <th className="px-4 py-2.5 text-right font-semibold">Imported</th>
                                    <th className="px-4 py-2.5 font-semibold">Stage</th>
                                    <th className="px-4 py-2.5 pr-5 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {recent.map((batch) => (
                                    <tr key={batch.uuid} className="hover:bg-canvas/40">
                                        <td className="px-5 py-3">
                                            <p className="max-w-[220px] truncate text-[13px] font-medium text-ink">{batch.file_name}</p>
                                            <p className="text-[11px] text-muted">
                                                {batch.uploaded_by ?? 'Unknown'} · {new Date(batch.created_at).toLocaleDateString()}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-[12px] text-muted">{batch.sheet_name ?? '—'}</td>
                                        <td className="px-4 py-3 text-[12px] text-muted">{structureLabel(batch.structure_type)}</td>
                                        <td className="tabular px-4 py-3 text-right text-[12.5px] text-ink">{formatNumber(batch.imported_rows)}</td>
                                        <td className="px-4 py-3">
                                            <Badge tone={stageTone(batch.stage)} size="sm">
                                                {stageLabel(batch.stage)}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 pr-5 text-right">
                                            <div className="inline-flex items-center justify-end gap-2">
                                                <Link
                                                    href={`/excel/${batch.uuid}/preview`}
                                                    className="inline-flex items-center gap-1 text-[12px] font-medium text-brand-700 hover:underline dark:text-brand-300"
                                                >
                                                    Open <ArrowRight className="h-3.5 w-3.5" strokeWidth={2} />
                                                </Link>
                                                <DeleteExcelBatchAction batch={batch} />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </AppLayout>
    );
}
