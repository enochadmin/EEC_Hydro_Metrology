import Badge from '@/Components/Badge';
import EmptyState from '@/Components/EmptyState';
import DeleteExcelBatchAction from '@/Components/excel/DeleteExcelBatchAction';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import AppLayout from '@/Layouts/AppLayout';
import { batchTone, stageLabel, stageTone, structureLabel } from '@/Utils/excel';
import { formatNumber } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';

export default function ImportHistory({ batches }) {
    return (
        <AppLayout breadcrumbs={[{ label: 'Excel', href: '/excel' }, { label: 'Import history' }]}>
            <Head title="Import history" />

            <PageHeader title="Import history" description="Uploads and committed records." />

            <div className="mt-6 overflow-hidden rounded-xl border border-line bg-surface">
                {batches.data.length === 0 ? (
                    <EmptyState
                        icon={FileSpreadsheet}
                        title="No imports yet"
                        description="Uploaded workbooks appear here with their worksheet, structure, counts and approval state."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-canvas/60 text-[10px] uppercase tracking-[0.12em] text-muted">
                                <tr>
                                    <th className="px-5 py-2.5 font-semibold">Workbook</th>
                                    <th className="px-4 py-2.5 font-semibold">Worksheet</th>
                                    <th className="px-4 py-2.5 font-semibold">Uploaded by</th>
                                    <th className="px-4 py-2.5 font-semibold">Structure</th>
                                    <th className="px-4 py-2.5 text-right font-semibold">Observations</th>
                                    <th className="px-4 py-2.5 text-right font-semibold">Missing</th>
                                    <th className="px-4 py-2.5 text-right font-semibold">Invalid</th>
                                    <th className="px-4 py-2.5 font-semibold">Status</th>
                                    <th className="px-4 py-2.5 text-right font-semibold">Stage</th>
                                    <th className="px-4 py-2.5 pr-5 text-right font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {batches.data.map((batch) => (
                                    <tr key={batch.uuid} className="hover:bg-canvas/40">
                                        <td className="px-5 py-3">
                                            <Link
                                                href={`/excel/${batch.uuid}/preview`}
                                                className="max-w-[230px] truncate text-[12.5px] font-medium text-ink hover:text-brand-700 dark:hover:text-brand-300"
                                            >
                                                {batch.file_name}
                                            </Link>
                                            <p className="text-[10.5px] text-muted">{new Date(batch.created_at).toLocaleString()}</p>
                                        </td>
                                        <td className="max-w-[160px] truncate px-4 py-3 text-[11.5px] text-muted">{batch.sheet_name ?? '—'}</td>
                                        <td className="px-4 py-3 text-[11.5px] text-muted">{batch.uploaded_by ?? '—'}</td>
                                        <td className="px-4 py-3 text-[11px] text-muted">{structureLabel(batch.structure_type)}</td>
                                        <td className="tabular px-4 py-3 text-right text-[12px] text-ink">{formatNumber(batch.imported_rows)}</td>
                                        <td className="tabular px-4 py-3 text-right text-[12px] text-amber-600 dark:text-amber-400">
                                            {formatNumber(batch.summary?.missing_measurements ?? 0)}
                                        </td>
                                        <td className="tabular px-4 py-3 text-right text-[12px] text-rose-600 dark:text-rose-400">
                                            {formatNumber(batch.rejected_rows)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={batchTone(batch.status)} size="sm">
                                                {batch.status.replaceAll('_', ' ')}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Badge tone={stageTone(batch.stage)} size="sm">
                                                {stageLabel(batch.stage)}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 pr-5 text-right">
                                            <DeleteExcelBatchAction batch={batch} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={batches} />
            </div>
        </AppLayout>
    );
}
