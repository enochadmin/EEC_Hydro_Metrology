import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import { Field, inputClasses, selectClasses } from '@/Components/FormField';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, formatNumber } from '@/Utils/format';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Compass, FileSpreadsheet, Info, RadioTower, UploadCloud } from 'lucide-react';
import { useRef, useState } from 'react';

const statusTone = { imported: 'success', previewed: 'warning', pending: 'neutral', failed: 'danger' };

export default function Import({ defaults = {}, limits = {}, classes = [], basins = [], subBasins = [], parameters = [], dataSources = [], recent = [] }) {
    const inputRef = useRef(null);
    const [dragging, setDragging] = useState(false);
    const [fileName, setFileName] = useState('');

    const { data, setData, post, processing, errors, progress } = useForm({
        file: null,
        code_prefix: defaults.code_prefix ?? 'MET',
        utm_zone: String(defaults.utm_zone ?? 37),
        coordinates: defaults.coordinates ?? 'auto',
        basin_id: '',
        sub_basin_id: '',
        data_source_id: '',
        data_status: defaults.data_status ?? 'provisional',
        parameters: defaults.parameters ?? [],
        operator: '',
    });

    const choose = (file) => {
        if (file) {
            setData('file', file);
            setFileName(file.name);
        }
    };

    const toggleParameter = (code) => {
        setData('parameters', data.parameters.includes(code)
            ? data.parameters.filter((item) => item !== code)
            : [...data.parameters, code]);
    };

    const submit = (event) => {
        event.preventDefault();
        post('/stations/import', { forceFormData: true, preserveScroll: true });
    };

    const selectedSubBasins = subBasins.filter((subBasin) => String(subBasin.basin_id) === String(data.basin_id));

    return (
        <AppLayout breadcrumbs={[{ label: 'Monitoring stations', href: '/stations' }, { label: 'Import stations' }]}>
            <Head title="Import monitoring stations" />
            <div className="mb-4">
                <Link href="/stations" className="inline-flex items-center gap-1.5 text-[12px] font-medium text-muted hover:text-ink">
                    <ArrowLeft className="h-3.5 w-3.5" />All monitoring stations
                </Link>
            </div>

            <PageHeader
                eyebrow="Station registry"
                title="Import stations"
                description="Upload a station list with names, coordinates and network class. Nothing is created until you review the staged preview."
            />

            <form onSubmit={submit} className="mt-5 grid gap-4 lg:grid-cols-[1.35fr_1fr]">
                <Card className="overflow-hidden">
                    <CardHeader
                        icon={FileSpreadsheet}
                        title="Station list workbook"
                        description={`XLSX, XLS or CSV · up to ${limits.max_kb ? Math.round(limits.max_kb / 1024) : 20} MB · ${formatNumber(limits.max_rows ?? 20000)} rows`}
                    />
                    <CardBody className="space-y-4">
                        <div
                            onDragOver={(event) => { event.preventDefault(); setDragging(true); }}
                            onDragLeave={() => setDragging(false)}
                            onDrop={(event) => {
                                event.preventDefault();
                                setDragging(false);
                                choose(event.dataTransfer.files?.[0] ?? null);
                            }}
                            className={`rounded-xl border-2 border-dashed px-5 py-9 text-center transition-colors ${
                                dragging ? 'border-brand-500 bg-brand-50/60 dark:bg-brand-500/10' : 'border-line bg-canvas/30'
                            }`}
                        >
                            <UploadCloud className="mx-auto h-7 w-7 text-brand-700 dark:text-brand-300" strokeWidth={1.5} />
                            <p className="mt-3 text-[13px] font-medium text-ink">{fileName || 'Drop the station list here'}</p>
                            <p className="mt-1 text-[11.5px] text-muted">The workbook is kept unchanged; parsed rows are staged separately.</p>
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
                        {errors.file && <p className="text-xs text-rose-600 dark:text-rose-400">{errors.file}</p>}

                        {processing && (
                            <div className="rounded-lg border border-line bg-canvas/40 px-3 py-2.5">
                                <div className="flex items-center justify-between text-[11.5px] text-muted">
                                    <span>Reading and converting the workbook…</span>
                                    <span className="tabular">{progress?.percentage ?? 0}%</span>
                                </div>
                                <div className="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-line">
                                    <div className="h-full rounded-full bg-brand-600 transition-all" style={{ width: `${progress?.percentage ?? 0}%` }} />
                                </div>
                            </div>
                        )}

                        <div className="rounded-lg border border-line bg-canvas/40 px-3 py-2.5">
                            <p className="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.1em] text-muted">
                                <Info className="h-3.5 w-3.5" />Expected columns
                            </p>
                            <p className="mt-1.5 text-[11.5px] leading-relaxed text-muted">
                                A name column is required. Coordinates may be decimal degrees or projected metres — both are detected
                                automatically. An optional class column is normalised to
                                {' '}{classes.map((option) => option.label).join(' / ')}. Codes are generated when the file has none.
                            </p>
                        </div>
                    </CardBody>
                </Card>

                <div className="space-y-4">
                    <Card className="overflow-hidden">
                        <CardHeader icon={Compass} title="Coordinates and class" description="How the file should be interpreted." />
                        <CardBody className="grid gap-4 sm:grid-cols-2">
                            <Field label="Coordinate system" htmlFor="coordinates" error={errors.coordinates}>
                                <select id="coordinates" value={data.coordinates} onChange={(event) => setData('coordinates', event.target.value)} className={selectClasses}>
                                    <option value="auto">Detect automatically</option>
                                    <option value="projected">Projected metres (UTM)</option>
                                    <option value="degrees">Decimal degrees (WGS84)</option>
                                </select>
                            </Field>
                            <Field label="UTM zone" htmlFor="utm_zone" error={errors.utm_zone} hint="Ethiopia uses zone 37N (39°E).">
                                <input id="utm_zone" type="number" min="1" max="60" value={data.utm_zone} onChange={(event) => setData('utm_zone', event.target.value)} className={inputClasses} />
                            </Field>
                            <Field label="Station code prefix" htmlFor="code_prefix" error={errors.code_prefix} hint="Used when the file carries no code.">
                                <input id="code_prefix" maxLength={10} value={data.code_prefix} onChange={(event) => setData('code_prefix', event.target.value.toUpperCase())} className={inputClasses + ' font-mono uppercase'} />
                            </Field>
                            <Field label="Managing organization" htmlFor="operator" error={errors.operator}>
                                <input id="operator" maxLength={150} value={data.operator} onChange={(event) => setData('operator', event.target.value)} placeholder="Applied to every imported row" className={inputClasses} />
                            </Field>
                        </CardBody>
                    </Card>

                    <Card className="overflow-hidden">
                        <CardHeader icon={RadioTower} title="Attribution" description="Applied to every imported station." />
                        <CardBody className="grid gap-4 sm:grid-cols-2">
                            <Field label="Basin" htmlFor="basin_id" error={errors.basin_id} hint="Optional — can be assigned later.">
                                <select id="basin_id" value={data.basin_id} onChange={(event) => setData({ ...data, basin_id: event.target.value, sub_basin_id: '' })} className={selectClasses}>
                                    <option value="">No basin assigned</option>
                                    {basins.map((basin) => <option key={basin.id} value={basin.id}>{basin.code} — {basin.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Sub-basin" htmlFor="sub_basin_id" error={errors.sub_basin_id}>
                                <select id="sub_basin_id" value={data.sub_basin_id} onChange={(event) => setData('sub_basin_id', event.target.value)} className={selectClasses} disabled={!data.basin_id}>
                                    <option value="">Not assigned</option>
                                    {selectedSubBasins.map((subBasin) => <option key={subBasin.id} value={subBasin.id}>{subBasin.code} — {subBasin.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Data source" htmlFor="data_source_id" error={errors.data_source_id}>
                                <select id="data_source_id" value={data.data_source_id} onChange={(event) => setData('data_source_id', event.target.value)} className={selectClasses}>
                                    <option value="">Not recorded</option>
                                    {dataSources.map((source) => <option key={source.id} value={source.id}>{source.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Data status" htmlFor="data_status" error={errors.data_status}>
                                <select id="data_status" value={data.data_status} onChange={(event) => setData('data_status', event.target.value)} className={selectClasses}>
                                    <option value="pending">Pending confirmation</option>
                                    <option value="provisional">Provisional</option>
                                    <option value="verified">Verified</option>
                                </select>
                            </Field>
                            <div className="sm:col-span-2">
                                <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">Parameters for new stations</p>
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {parameters.map((parameter) => {
                                        const selected = data.parameters.includes(parameter.code);

                                        return (
                                            <button
                                                key={parameter.code}
                                                type="button"
                                                onClick={() => toggleParameter(parameter.code)}
                                                className={`rounded-full border px-2.5 py-1 text-[11px] font-medium transition-colors ${
                                                    selected
                                                        ? 'border-brand-500 bg-brand-50 text-brand-800 dark:bg-brand-500/15 dark:text-brand-200'
                                                        : 'border-line text-muted hover:border-brand-300 hover:text-ink'
                                                }`}
                                            >
                                                {parameter.name}{parameter.symbol ? ` · ${parameter.symbol}` : ''}
                                            </button>
                                        );
                                    })}
                                </div>
                                <p className="mt-1.5 text-[11px] text-muted">These become the station's configured measurements; uploads can add more later.</p>
                            </div>
                        </CardBody>
                    </Card>

                    <div className="flex items-center justify-end gap-2">
                        <Link href="/stations" className="rounded-lg px-3 py-2 text-[13px] font-medium text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring">Cancel</Link>
                        <Button type="submit" disabled={processing || !data.file}>{processing ? 'Converting…' : 'Upload and preview'}</Button>
                    </div>
                </div>
            </form>

            {recent.length > 0 && (
                <Card className="mt-4 overflow-hidden">
                    <CardHeader icon={FileSpreadsheet} title="Recent registry imports" description="Previous station lists, newest first." />
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-line bg-canvas/50">
                                <tr>
                                    <th className="px-4 py-2 text-[10px] font-semibold uppercase text-muted">Workbook</th>
                                    <th className="px-4 py-2 text-[10px] font-semibold uppercase text-muted">Uploaded</th>
                                    <th className="px-4 py-2 text-[10px] font-semibold uppercase text-muted">Status</th>
                                    <th className="px-4 py-2 text-[10px] font-semibold uppercase text-muted">Rows</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {recent.map((batch) => (
                                    <tr key={batch.uuid}>
                                        <td className="px-4 py-2.5">
                                            <Link href={`/stations/import/${batch.uuid}`} className="text-[12px] font-medium text-brand-700 hover:text-brand-900 dark:text-brand-300">{batch.file_name}</Link>
                                            <span className="block text-[10px] text-muted">{batch.uploaded_by || 'Unknown user'}</span>
                                        </td>
                                        <td className="px-4 py-2.5 text-[11px] text-muted">{formatDateTime(batch.created_at)}</td>
                                        <td className="px-4 py-2.5"><Badge tone={statusTone[batch.status] ?? 'neutral'} size="sm">{batch.status}</Badge></td>
                                        <td className="px-4 py-2.5 tabular text-[11px] text-muted">{formatNumber(batch.imported_rows)} imported / {formatNumber(batch.total_rows)} staged</td>
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
