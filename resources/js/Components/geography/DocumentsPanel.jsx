import Button from '@/Components/Button';
import ConfirmDialog from '@/Components/ConfirmDialog';
import { Field, inputClasses } from '@/Components/FormField';
import { formatBytes, formatDate } from '@/Utils/format';
import { router, useForm } from '@inertiajs/react';
import { Download, FileText, Trash2 } from 'lucide-react';
import { useState } from 'react';

/**
 * Supporting documents for a geographic record.
 *
 * Files are served through a permission-checked route and stored on the
 * private disk, so the download link is a plain anchor rather than an Inertia
 * visit.
 */
export default function DocumentsPanel({ documents = [], action, canManage = false }) {
    const [pendingDelete, setPendingDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({ title: '', file: null });

    const submit = (event) => {
        event.preventDefault();

        post(action, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const confirmDelete = () => {
        if (!pendingDelete) {
            return;
        }

        router.delete(`${action}/${pendingDelete.id}`, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setPendingDelete(null);
            },
        });
    };

    return (
        <div className="space-y-5">
            {canManage && (
                <form onSubmit={submit} className="rounded-lg border border-line bg-canvas/40 p-4">
                    <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                        <Field label="Document title (optional)" htmlFor="document-title" error={errors.title}>
                            <input
                                id="document-title"
                                type="text"
                                value={data.title}
                                onChange={(event) => setData('title', event.target.value)}
                                placeholder="Defaults to the file name"
                                className={inputClasses}
                            />
                        </Field>

                        <Field label="File" htmlFor="document-file" error={errors.file} required>
                            <input
                                id="document-file"
                                type="file"
                                required
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.geojson,.zip,.png,.jpg,.jpeg"
                                onChange={(event) => setData('file', event.target.files?.[0] ?? null)}
                                className="block w-full rounded-lg border border-line bg-surface px-3 py-1.5 text-[12.5px] text-ink file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-2.5 file:py-1 file:text-[11.5px] file:font-medium file:text-brand-700 dark:file:bg-brand-500/10 dark:file:text-brand-200"
                            />
                        </Field>

                        <Button type="submit" size="sm" disabled={processing} className="sm:mb-0.5">
                            {processing ? 'Uploading…' : 'Attach'}
                        </Button>
                    </div>
                    <p className="mt-2 text-[11px] text-muted">Maximum 10 MB. Files stay on the private disk and are only served to users who can view this record.</p>
                </form>
            )}

            {documents.length === 0 ? (
                <div className="rounded-lg border border-dashed border-line px-5 py-8 text-center">
                    <FileText className="mx-auto h-5 w-5 text-muted" strokeWidth={1.75} />
                    <p className="mt-2 text-[13px] font-medium text-ink">No documents attached</p>
                    <p className="mt-1 text-xs text-muted">
                        Gazettes, survey reports and signed boundary maps added here become part of the record history.
                    </p>
                </div>
            ) : (
                <ul className="divide-y divide-line">
                    {documents.map((document) => (
                        <li key={document.id} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 items-start gap-3">
                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-[10px] font-semibold uppercase text-brand-700 dark:bg-brand-500/10 dark:text-brand-200">
                                    {document.extension.slice(0, 4)}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-[13px] font-medium text-ink">{document.title}</p>
                                    <p className="truncate text-[11.5px] text-muted">
                                        {document.original_name} · {formatBytes(document.size_bytes)}
                                        {document.uploaded_by ? ` · ${document.uploaded_by}` : ''}
                                    </p>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-1 sm:pl-3">
                                <span className="mr-1 hidden text-[11px] text-muted tabular sm:inline">{formatDate(document.created_at)}</span>
                                <a
                                    href={`${action}/${document.id}/download`}
                                    className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line px-2.5 text-[12px] font-medium text-ink transition-colors hover:bg-canvas focus-ring"
                                >
                                    <Download className="h-3.5 w-3.5" strokeWidth={1.75} />
                                    Download
                                </a>
                                {canManage && (
                                    <Button
                                        variant="danger-ghost"
                                        size="icon"
                                        aria-label={`Remove ${document.title}`}
                                        onClick={() => setPendingDelete(document)}
                                    >
                                        <Trash2 className="h-4 w-4" strokeWidth={1.75} />
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <ConfirmDialog
                open={Boolean(pendingDelete)}
                title="Remove this document?"
                description={
                    pendingDelete
                        ? `"${pendingDelete.title}" will be removed from this record and deleted from storage. The activity entry stays in the audit trail.`
                        : undefined
                }
                confirmLabel="Remove document"
                processing={deleting}
                onConfirm={confirmDelete}
                onClose={() => setPendingDelete(null)}
            />
        </div>
    );
}
