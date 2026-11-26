import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import { Field, inputClasses, selectClasses } from '@/Components/FormField';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, formatNumber } from '@/Utils/format';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Compass, FileSpreadsheet, SlidersHorizontal, TriangleAlert } from 'lucide-react';

const rowTone = { ready: 'success', duplicate: 'warning', error: 'danger' };
const severityTone = { error: 'danger', warning: 'warning', duplicate: 'warning' };

function Tile({ label, value, hint, tone = 'default' }) {
    const toneClass = {
        default: 'text-ink',
        success: 'text-emerald-700 dark:text-emerald-300',
        warning: 'text-amber-700 dark:text-amber-300',
        danger: 'text-rose-700 dark:text-rose-300',
    }[tone];

    return (
        <div className="rounded-xl border border-line bg-surface px-4 py-3">
            <p className="text-[10.5px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</p>
            <p className={`mt-1.5 text-2xl font-semibold tabular ${toneClass}`}>{formatNumber(value ?? 0)}</p>
            <p className="mt-0.5 text-[11px] text-muted">{hint}</p>
        </div>
    );
}

export default function ImportPreview({ batch, rows, issues = [], classes = [], basins = [], subBasins = [], parameters = [], dataSources = [], abilities = {} }) {
    const summary = batch.summary ?? {};
    const options = batch.options ?? {};

    const { data, setData, post, processing, errors } = useForm({
        code_prefix: options.code_prefix ?? 'MET',
        utm_zone: String(options.utm_zone ?? 37),
        coordinates: options.coordinates ?? 'auto',
        basin_id: options.basin_id ? String(options.basin_id) : '',
        sub_basin_id: options.sub_basin_id ? String(options.sub_basin_id) : '',
        data_source_id: options.data_source_id ? String(options.data_source_id) : '',
        data_status: options.data_status ?? 'provisional',
        parameters: options.parameters ?? [],
        operator: options.operator ?? '',
    });

    const submitTo = (url) => (event) => {
        event.preventDefault();
        post(url, { preserveScroll: true });
    };

    const lastPage = Math.max(1, Math.ceil((rows?.total ?? 0) / (rows?.per_page ?? 25)));
    const imported = batch.status === 'imported';
    const selectedSubBasins = subBasins.filter((subBasin) => String(subBasin.basin_id) === String(data.basin_id));
    const classCounts = summary.classes ?? {};

    const toggleParameter = (code) => {
        setData('parameters', data.parameters.includes(code)
            ? data.parameters.filter((item) => item !== code)
            : [...data.parameters, code]);
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Monitoring stations', href: '/stations' }, { label: 'Import stations', href: '/stations/import' }, { label: batch.file_name }]}>
            <Head title={`Station import · ${batch.file_name}`} />
            <div className="mb-4">
                <Link href="/stations/import" className="inline-flex items-center gap-1.5 text-[12px] font-medium text-muted hover:text-ink">
                    <ArrowLeft className="h-3.5 w-3.5" />Back to station import
                </Link>
            </div>

            <PageHeader
                eyebrow={batch.status === 'imported' ? 'Imported' : 'Preview'}
                title={batch.file_name}
                description={`Uploaded ${formatDateTime(batch.created_at)} · ${formatNumber(batch.total_rows)} rows staged · ${formatNumber(batch.imported_rows)} already imported`}
                actions={
                    abilities.commit && !imported ? (
                        <Button type="button" onClick={submitTo(`/stations/import/${batch.uuid}/commit`)} disabled={processing || (summary.ready ?? 0) === 0}>
                            <CheckCircle2 className="h-4 w-4" />
                            {processing ? 'Importing…' : `Import ${formatNumber(summary.ready ?? 0)} stations`}
                        </Button>
                    ) : (
                        <Badge tone={imported ? 'success' : 'warning'} size="sm">{batch.status}</Badge>
                    )
                }
            />

            <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <Tile label="Staged rows" value={summary.rows} hint="Rows with a station name" />
                <Tile label="Ready" value={summary.ready} hint="Will be created" tone="success" />
                <Tile label="Duplicates" value={summary.duplicates} hint="Already registered" tone="warning" />
                <Tile label="Errors" value={summary.errors} hint="Rejected rows" tone="danger" />
                <Tile label="Warnings" value={summary.warnings} hint="Imported with a note" tone="warning" />
            </div>

            <div className="mt-3 flex flex-wrap items-center gap-1.5">
                <Badge tone="neutral" size="sm">
                    {summary.coordinates === 'projected' ? `UTM ${summary.utm_zone}N → WGS84` : 'Decimal degrees'}
                </Badge>
                {summary.latitude_range && (
                    <Badge tone="neutral" size="sm">
                        {summary.latitude_range[0].toFixed(3)}° – {summary.latitude_range[1].toFixed(3)}° N · {summary.longitude_range[0].toFixed(3)}° – {summary.longitude_range[1].toFixed(3)}° E
                    </Badge>
                )}
                {Object.entries(classCounts).map(([code, count]) => (
                    <Badge key={code} tone="brand" size="sm">
                        {classes.find((option) => option.code === code)?.label ?? code}: {formatNumber(count)}
                    </Badge>
                ))}
            </div>

            {batch.mapping?.detected && (
                <p className="mt-2 text-[11.5px] text-muted">
                    Detected columns: {Object.entries(batch.mapping.detected).map(([role, header]) => `${role} → “${header}”`).join(' · ')}
                </p>
            )}

            <div className="mt-4 grid gap-4 xl:grid-cols-[1.6fr_1fr]">
                <div className="space-y-4">
                    <Card className="overflow-hidden">
                        <CardHeader icon={FileSpreadsheet} title="Staged stations" description="Exactly what will be created, after conversion and classification." />
                        <div className="overflow-x-auto">
                            <table className="min-w-[760px] w-full text-left text-sm">
                                <thead className="border-b border-line bg-canvas/50">
                                    <tr>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Row</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Station</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Code</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Class</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Latitude</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Longitude</th>
                                        <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {(rows?.data ?? []).map((row) => (
                                        <tr key={`${row.row_number}-${row.code}`}>
                                            <td className="px-3 py-2 text-[11px] tabular text-muted">{row.row_number}</td>
                                            <td className="px-3 py-2">
                                                <span className="block text-[12px] font-medium text-ink">{row.name}</span>
                                                {row.class_raw && <span className="block text-[10px] text-muted">{row.class_raw}</span>}
                                            </td>
                                            <td className="px-3 py-2 font-mono text-[11px] text-ink">{row.code}</td>
                                            <td className="px-3 py-2 text-[11px] text-ink">{classes.find((option) => option.code === row.station_class)?.label ?? '—'}</td>
                                            <td className="px-3 py-2 tabular text-[11px] text-ink">{row.latitude ?? '—'}</td>
                                            <td className="px-3 py-2 tabular text-[11px] text-ink">{row.longitude ?? '—'}</td>
                                            <td className="px-3 py-2"><Badge tone={rowTone[row.status] ?? 'neutral'} size="sm">{row.status}</Badge></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {lastPage > 1 && (
                            <div className="flex items-center justify-between border-t border-line px-3 py-2 text-[11px] text-muted">
                                <span>Page {rows.page} of {lastPage}</span>
                                <div className="flex gap-2">
                                    {rows.page > 1 && <Link href={`/stations/import/${batch.uuid}?page=${rows.page - 1}`} className="font-medium text-brand-700 hover:text-brand-900 dark:text-brand-300">Previous</Link>}
                                    {rows.page < lastPage && <Link href={`/stations/import/${batch.uuid}?page=${rows.page + 1}`} className="font-medium text-brand-700 hover:text-brand-900 dark:text-brand-300">Next</Link>}
                                </div>
                            </div>
                        )}
                    </Card>

                    <Card className="overflow-hidden">
                        <CardHeader icon={TriangleAlert} title="Findings" description="Duplicates are skipped; errors are never inserted." />
                        {issues.length === 0 ? (
                            <CardBody>
                                <p className="rounded-lg border border-dashed border-line px-3 py-4 text-[12px] text-muted">No findings for this workbook.</p>
                            </CardBody>
                        ) : (
                            <div className="max-h-[420px] overflow-y-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="sticky top-0 border-b border-line bg-canvas">
                                        <tr>
                                            <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Row</th>
                                            <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Code</th>
                                            <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Message</th>
                                            <th className="px-3 py-2 text-[10px] font-semibold uppercase text-muted">Severity</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {issues.map((issue, index) => (
                                            <tr key={`${issue.row_number}-${issue.code}-${index}`}>
                                                <td className="px-3 py-2 text-[11px] tabular text-muted">{issue.row_number}</td>
                                                <td className="px-3 py-2 font-mono text-[10.5px] text-ink">{issue.code}</td>
                                                <td className="px-3 py-2 text-[11.5px] text-ink">{issue.message}</td>
                                                <td className="px-3 py-2"><Badge tone={severityTone[issue.severity] ?? 'neutral'} size="sm">{issue.severity}</Badge></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Card>
                </div>

                <Card className="overflow-hidden self-start">
                    <CardHeader icon={SlidersHorizontal} title="Import options" description="Changing an option rebuilds the preview, not the source file." />
                    <CardBody className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Basin" htmlFor="preview-basin" error={errors.basin_id}>
                                <select id="preview-basin" value={data.basin_id} onChange={(event) => setData({ ...data, basin_id: event.target.value, sub_basin_id: '' })} className={selectClasses}>
                                    <option value="">No basin assigned</option>
                                    {basins.map((basin) => <option key={basin.id} value={basin.id}>{basin.code} — {basin.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Sub-basin" htmlFor="preview-sub-basin" error={errors.sub_basin_id}>
                                <select id="preview-sub-basin" value={data.sub_basin_id} onChange={(event) => setData('sub_basin_id', event.target.value)} className={selectClasses} disabled={!data.basin_id}>
                                    <option value="">Not assigned</option>
                                    {selectedSubBasins.map((subBasin) => <option key={subBasin.id} value={subBasin.id}>{subBasin.code} — {subBasin.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Data source" htmlFor="preview-source" error={errors.data_source_id}>
                                <select id="preview-source" value={data.data_source_id} onChange={(event) => setData('data_source_id', event.target.value)} className={selectClasses}>
                                    <option value="">Not recorded</option>
                                    {dataSources.map((source) => <option key={source.id} value={source.id}>{source.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Data status" htmlFor="preview-status" error={errors.data_status}>
                                <select id="preview-status" value={data.data_status} onChange={(event) => setData('data_status', event.target.value)} className={selectClasses}>
                                    <option value="pending">Pending confirmation</option>
                                    <option value="provisional">Provisional</option>
                                    <option value="verified">Verified</option>
                                </select>
                            </Field>
                            <Field label="Coordinate system" htmlFor="preview-coordinates" error={errors.coordinates}>
                                <select id="preview-coordinates" value={data.coordinates} onChange={(event) => setData('coordinates', event.target.value)} className={selectClasses}>
                                    <option value="auto">Detect automatically</option>
                                    <option value="projected">Projected metres (UTM)</option>
                                    <option value="degrees">Decimal degrees (WGS84)</option>
                                </select>
                            </Field>
                            <Field label="UTM zone" htmlFor="preview-zone" error={errors.utm_zone}>
                                <input id="preview-zone" type="number" min="1" max="60" value={data.utm_zone} onChange={(event) => setData('utm_zone', event.target.value)} className={inputClasses} />
                            </Field>
                            <Field label="Code prefix" htmlFor="preview-prefix" error={errors.code_prefix}>
                                <input id="preview-prefix" maxLength={10} value={data.code_prefix} onChange={(event) => setData('code_prefix', event.target.value.toUpperCase())} className={inputClasses + ' font-mono uppercase'} />
                            </Field>
                            <Field label="Managing organization" htmlFor="preview-operator" error={errors.operator}>
                                <input id="preview-operator" maxLength={150} value={data.operator} onChange={(event) => setData('operator', event.target.value)} className={inputClasses} />
                            </Field>
                        </div>

                        <div>
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
                                            {parameter.name}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <Button type="button" variant="secondary" onClick={submitTo(`/stations/import/${batch.uuid}/preview`)} disabled={processing}>
                                <Compass className="h-4 w-4" />
                                {processing ? 'Working…' : 'Rebuild preview'}
                            </Button>
                            {abilities.commit && !imported && (
                                <Button type="button" onClick={submitTo(`/stations/import/${batch.uuid}/commit`)} disabled={processing || (summary.ready ?? 0) === 0}>
                                    <CheckCircle2 className="h-4 w-4" />
                                    Import {formatNumber(summary.ready ?? 0)} stations
                                </Button>
                            )}
                            {imported && (
                                <p className="rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2 text-[11.5px] text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300">
                                    This list was imported on {formatDateTime(batch.imported_at)}.
                                </p>
                            )}
                        </div>
                    </CardBody>
                </Card>
            </div>
        </AppLayout>
    );
}
