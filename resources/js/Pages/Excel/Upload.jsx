import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import ExcelUploadOverlay from '@/Components/excel/ExcelUploadOverlay';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { formatBytes } from '@/Utils/format';
import { Head, useForm } from '@inertiajs/react';
import { FileSpreadsheet, Info, UploadCloud, X } from 'lucide-react';
import { useRef, useState } from 'react';

export default function Upload({ limits = {} }) {
    const inputRef = useRef(null);
    const [dragging, setDragging] = useState(false);

    const { data, setData, post, processing, errors, progress } = useForm({ file: null });

    const choose = (file) => {
        if (file) {
            setData('file', file);
        }
    };

    const submit = (event) => {
        event.preventDefault();
        post('/excel/upload', { forceFormData: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Excel', href: '/excel' }, { label: 'Upload' }]}>
            <Head title="Upload workbook" />
            {processing && <ExcelUploadOverlay fileName={data.file?.name} progress={progress} />}

            <PageHeader
                title="Upload a workbook"
                description="The original file is stored unchanged; detection uses cell content, not sheet names."
            />

            <div className="mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                <Card className="overflow-hidden">
                    <CardHeader
                        icon={FileSpreadsheet}
                        title="Workbook file"
                        description={`XLSX, XLS or CSV · up to ${limits.max_kb ? Math.round(limits.max_kb / 1024) : 30} MB`}
                    />
                    <form onSubmit={submit}>
                        <CardBody className="space-y-4">
                            <div
                                onDragOver={(event) => {
                                    event.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={(event) => {
                                    event.preventDefault();
                                    setDragging(false);
                                    choose(event.dataTransfer.files?.[0] ?? null);
                                }}
                                className={`rounded-xl border-2 border-dashed px-5 py-10 text-center transition-colors ${
                                    dragging ? 'border-brand-500 bg-brand-50/60 dark:bg-brand-500/10' : 'border-line bg-canvas/30'
                                }`}
                            >
                                <UploadCloud className="mx-auto h-7 w-7 text-brand-700 dark:text-brand-300" strokeWidth={1.5} />
                                <p className="mt-3 text-[13px] font-medium text-ink">Drop the workbook here</p>
                                <input
                                    ref={inputRef}
                                    type="file"
                                    className="hidden"
                                    accept=".xlsx,.xls,.csv"
                                    onChange={(event) => choose(event.target.files?.[0] ?? null)}
                                />
                                <Button type="button" variant="secondary" size="sm" className="mt-3" onClick={() => inputRef.current?.click()}>
                                    Browse files
                                </Button>
                            </div>

                            {data.file && (
                                <div className="flex items-center gap-3 rounded-lg border border-line bg-surface px-3 py-2.5">
                                    <span className="grid h-9 w-9 place-items-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                        <FileSpreadsheet className="h-4 w-4" strokeWidth={1.75} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-[12.5px] font-medium text-ink">{data.file.name}</p>
                                        <p className="text-[11px] text-muted">{formatBytes(data.file.size)}</p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setData('file', null)}
                                        className="text-muted hover:text-ink"
                                        aria-label="Remove file"
                                    >
                                        <X className="h-4 w-4" strokeWidth={2} />
                                    </button>
                                </div>
                            )}

                            {errors.file && <p className="text-[12px] text-rose-600 dark:text-rose-400">{errors.file}</p>}
                        </CardBody>

                        <div className="flex justify-end border-t border-line px-5 py-3.5">
                            <Button type="submit" disabled={processing || !data.file}>
                                {processing ? 'Uploading…' : 'Upload and inspect'}
                            </Button>
                        </div>
                    </form>
                </Card>

                <div className="space-y-4">
                    <Card>
                        <CardHeader icon={Info} title="What gets detected" />
                        <CardBody className="space-y-3 text-[12px] leading-relaxed text-muted">
                            <div>
                                <p className="font-medium text-ink">Monthly records with daily columns</p>
                                <p>Station metadata followed by days 1–31.</p>
                            </div>
                            <div>
                                <p className="font-medium text-ink">Multi-row matrix</p>
                                <p>Year, month and day headers; each cell becomes one date.</p>
                            </div>
                            <div>
                                <p className="font-medium text-ink">Date/value series</p>
                                <p>Two columns: date and value.</p>
                            </div>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader icon={FileSpreadsheet} title="Data rules applied" />
                        <CardBody className="space-y-2 text-[11.5px] leading-relaxed text-muted">
                            <p>
                                <span className="font-medium text-ink">Zero is data.</span> A 0 mm day stays a valid measurement.
                            </p>
                            <p>
                                <span className="font-medium text-ink">Hyphen and blank are missing.</span> They are reported, never converted to
                                zero.
                            </p>
                            <p>
                                <span className="font-medium text-ink">Impossible dates are rejected.</span>
                            </p>
                            <p>
                                <span className="font-medium text-ink">Invalid rows are kept in the report.</span> Nothing is silently deleted.
                            </p>
                        </CardBody>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
