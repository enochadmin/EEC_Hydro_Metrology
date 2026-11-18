import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import { Field, inputClasses, selectClasses } from '@/Components/FormField';
import PageHeader from '@/Components/PageHeader';
import { useAuthorization } from '@/Hooks/useAuthorization';
import AppLayout from '@/Layouts/AppLayout';
import { severityTone, structureLabel } from '@/Utils/excel';
import { formatNumber } from '@/Utils/format';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ClipboardCheck, Columns3, Download, MapPin, RadioTower, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';

export default function Validation({ batch, report = {}, stations = [], existingStations = [], basins = [], subBasins = [] }) {
    const { can } = useAuthorization();
    const summary = report.summary ?? {};
    const issues = report.issues ?? [];

    const initialMap = useMemo(() => {
        const map = {};

        stations.forEach((station) => {
            const saved = batch.station_map?.[station.name];

            if (batch.station) {
                map[station.name] = {
                    mode: 'existing',
                    station_id: String(batch.station.id),
                    code: '',
                    basin_id: '',
                    sub_basin_id: '',
                };

                return;
            }

            if (saved) {
                map[station.name] = {
                    mode: saved.mode ?? 'existing',
                    station_id: saved.station_id ? String(saved.station_id) : '',
                    code: saved.code ?? '',
                    basin_id: saved.basin_id ? String(saved.basin_id) : '',
                    sub_basin_id: saved.sub_basin_id ? String(saved.sub_basin_id) : '',
                };

                return;
            }

            map[station.name] = {
                mode: station.suggested_station_id ? 'existing' : 'create',
                station_id: station.suggested_station_id ? String(station.suggested_station_id) : '',
                code: '',
                basin_id: '',
                sub_basin_id: '',
            };
        });

        return map;
    }, [stations, batch.station_map, batch.station]);

    const { data, setData, post, processing, errors } = useForm({ station_map: initialMap });
    const [busy, setBusy] = useState(false);

    const update = (name, key, value) => {
        setData('station_map', {
            ...data.station_map,
            [name]: { ...data.station_map[name], [key]: value },
        });
    };

    const canImport = batch.station
        ? stations.length > 0
            && (summary.station_mismatches ?? 0) === 0
            && (summary.station_parameter_errors ?? 0) === 0
            && (summary.unknown_parameter_errors ?? 0) === 0
        : stations.length > 0 && stations.every((station) => {
        const entry = data.station_map[station.name];

        if (!entry) return false;
        if (entry.mode === 'existing') return Boolean(entry.station_id);

        return Boolean(entry.code && entry.basin_id);
        });

    const runValidation = () => {
        setBusy(true);
        router.post(`/excel/${batch.uuid}/validate`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    const importData = () => {
        post(`/excel/${batch.uuid}/import`, { preserveScroll: true });
    };

    const imported = batch.stage === 'imported';

    return (
        <AppLayout breadcrumbs={batch.station
            ? [{ label: 'Monitoring stations', href: '/stations' }, { label: batch.station.code, href: `/stations/${batch.station.id}` }, { label: batch.file_name }, { label: 'Validation' }]
            : [{ label: 'Excel', href: '/excel' }, { label: batch.file_name, href: `/excel/${batch.uuid}/preview?sheet=${encodeURIComponent(batch.sheet_name ?? '')}` }, { label: 'Validation' }]}>
            <Head title="Validation" />

            <PageHeader
                title="Validation and import"
                description={`${batch.sheet_name} · ${structureLabel(batch.structure_type)}. Missing values stay missing; invalid rows are excluded from import but remain in the report.`}
                actions={
                    <>
                        <Button variant="secondary" size="sm" href={`/excel/${batch.uuid}/transformation`}>
                            <Columns3 className="h-3.5 w-3.5" strokeWidth={1.75} />
                            Review transformed rows
                        </Button>
                        {can('export data') && (
                            <Button variant="secondary" size="sm" onClick={() => window.location.assign(`/excel/${batch.uuid}/export/column-based`)}>
                                <Download className="h-3.5 w-3.5" strokeWidth={1.75} />
                                Download column-based
                            </Button>
                        )}
                        <Button variant="secondary" size="sm" onClick={() => window.location.assign(`/excel/${batch.uuid}/export/errors`)}>
                            <Download className="h-3.5 w-3.5" strokeWidth={1.75} />
                            Error report
                        </Button>
                        {imported && (
                            <Button variant="secondary" size="sm" onClick={() => window.location.assign(`/excel/${batch.uuid}/export/geojson`)}>
                                <Download className="h-3.5 w-3.5" strokeWidth={1.75} />
                                Export GeoJSON
                            </Button>
                        )}
                        <Button size="sm" onClick={runValidation} disabled={busy}>
                            <ShieldCheck className="h-4 w-4" strokeWidth={1.75} />
                            {busy ? 'Validating…' : summary.rows ? 'Re-run validation' : 'Validate dataset'}
                        </Button>
                    </>
                }
            />

            {imported && (
                <div className="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-[12.5px] text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                    <div>
                        <span className="font-semibold">Import Complete:</span> {formatNumber(batch.imported_rows)} observations committed to the database.
                    </div>
                    <Link
                        href="/gis?from=1973-01-01&to=2022-12-31&parameter=rainfall"
                        className="inline-flex items-center gap-1.5 rounded-md bg-emerald-700 px-3 py-1.5 text-[11.5px] font-medium text-white hover:bg-emerald-800 transition-colors shrink-0 shadow-sm"
                    >
                        <MapPin className="h-3.5 w-3.5" />
                        View on GIS Map →
                    </Link>
                </div>
            )}

            <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                {[
                    ['Rows', summary.rows],
                    ['Valid', summary.valid_rows, 'text-emerald-600 dark:text-emerald-400'],
                    ['Missing', summary.missing_rows, 'text-amber-600 dark:text-amber-400'],
                    ['Invalid', summary.invalid_rows, 'text-rose-600 dark:text-rose-400'],
                    ['Duplicates', summary.duplicate_rows],
                    ['Warnings', summary.warnings],
                    ['Errors', summary.errors, 'text-rose-600 dark:text-rose-400'],
                ].map(([label, value, tone]) => (
                    <Card key={label} className="px-4 py-3">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</p>
                        <p className={`mt-1 text-xl font-semibold tabular ${tone ?? 'text-ink'}`}>{formatNumber(value ?? 0)}</p>
                    </Card>
                ))}
            </div>

            <div className="mt-4 grid gap-4 xl:grid-cols-[1.2fr_1fr]">
                <Card className="overflow-hidden">
                    <CardHeader
                        icon={AlertTriangle}
                        title="Validation findings"
                        description={`Showing ${issues.length} of ${formatNumber(report.issues_total ?? issues.length)} findings.`}
                    />
                    {issues.length === 0 ? (
                        <CardBody>
                            <p className="text-[12px] text-muted">No findings yet. Run validation to build the report.</p>
                        </CardBody>
                    ) : (
                        <div className="eec-scrollbar max-h-[520px] overflow-auto">
                            <table className="w-full text-left text-[11.5px]">
                                <thead className="sticky top-0 bg-canvas text-[9.5px] uppercase tracking-wide text-muted">
                                    <tr>
                                        <th className="px-3 py-2">Row</th>
                                        <th className="px-3 py-2">Station</th>
                                        <th className="px-3 py-2">Date</th>
                                        <th className="px-3 py-2">Code</th>
                                        <th className="px-3 py-2">Message</th>
                                        <th className="px-3 py-2">Severity</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {issues.map((issue, index) => (
                                        <tr key={`${issue.code}-${issue.row_number}-${index}`} className="hover:bg-canvas/40">
                                            <td className="px-3 py-1.5 tabular text-muted">{issue.row_number || '—'}</td>
                                            <td className="max-w-[130px] truncate px-3 py-1.5 text-ink">{issue.station || '—'}</td>
                                            <td className="whitespace-nowrap px-3 py-1.5 tabular text-muted">{issue.date || '—'}</td>
                                            <td className="whitespace-nowrap px-3 py-1.5 font-mono text-[10px] text-muted">{issue.code}</td>
                                            <td className="px-3 py-1.5 text-muted">{issue.message}</td>
                                            <td className="px-3 py-1.5"><Badge tone={severityTone(issue.severity)} size="sm">{issue.severity}</Badge></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader
                        icon={RadioTower}
                        title={batch.station ? 'Selected station' : 'Station matching'}
                        description={batch.station
                            ? 'This workbook is bound to the selected station. Rows naming another station are blocked from import.'
                            : 'Existing stations are matched by name. Create a station only when it is genuinely new; parameters are never created automatically.'}
                    />
                    <CardBody className="space-y-3">
                        {errors.station_map && <p className="text-[12px] text-rose-600 dark:text-rose-400">{errors.station_map}</p>}
                        {errors.parameter_map && <p className="text-[12px] text-rose-600 dark:text-rose-400">{errors.parameter_map}</p>}

                        {stations.length === 0 && <p className="text-[12px] text-muted">No station names were found in the transformed rows.</p>}

                        {batch.station ? (
                            <div className="space-y-2 rounded-lg border border-line bg-canvas/40 p-3">
                                <p className="text-[12.5px] font-semibold text-ink">{batch.station.code} — {batch.station.name}</p>
                                <p className="text-[10.5px] text-muted">Station identified in workbook:</p>
                                {stations.map((sourceStation) => (
                                    <div key={sourceStation.name} className="flex items-center justify-between gap-2 text-[11px]">
                                        <span className="truncate text-ink">{sourceStation.name} · {formatNumber(sourceStation.rows)} rows</span>
                                        <Badge tone={(summary.station_mismatches ?? 0) === 0 ? 'success' : 'danger'} size="sm">
                                            {(summary.station_mismatches ?? 0) === 0 ? 'Matches selected station' : 'Check validation finding'}
                                        </Badge>
                                    </div>
                                ))}
                                {(summary.station_mismatches ?? 0) > 0 && (
                                    <p className="pt-1 text-[11px] font-medium text-rose-700 dark:text-rose-300">
                                        Resolve station mismatches by correcting the workbook and uploading it again.
                                    </p>
                                )}
                            </div>
                        ) : stations.map((station) => {
                            const entry = data.station_map[station.name] ?? { mode: 'existing', station_id: '' };
                            const filteredSubBasins = subBasins.filter((sub) => String(sub.basin_id) === String(entry.basin_id));

                            return (
                                <div key={station.name} className="rounded-lg border border-line p-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <p className="text-[12.5px] font-medium text-ink">{station.name}</p>
                                            <p className="text-[10.5px] text-muted">
                                                {station.latitude !== null && station.longitude !== null
                                                    ? `${Number(station.latitude).toFixed(5)}, ${Number(station.longitude).toFixed(5)}`
                                                    : 'No coordinates in file'}
                                                {' · '}{formatNumber(station.rows)} rows
                                            </p>
                                        </div>
                                        <select
                                            value={entry.mode}
                                            onChange={(event) => update(station.name, 'mode', event.target.value)}
                                            className="h-8 rounded-md border-line bg-surface py-0 pl-2 pr-7 text-[11px] text-ink"
                                            aria-label={`Match mode for ${station.name}`}
                                        >
                                            <option value="existing">Use existing</option>
                                            <option value="create">Create station</option>
                                        </select>
                                    </div>

                                    {entry.mode === 'existing' ? (
                                        <Field label="Registered station" htmlFor={`station-${station.name}`} className="mt-2">
                                            <select
                                                id={`station-${station.name}`}
                                                value={entry.station_id}
                                                onChange={(event) => update(station.name, 'station_id', event.target.value)}
                                                className={`${selectClasses} py-1.5 text-[11.5px]`}
                                            >
                                                <option value="">Select a station…</option>
                                                {existingStations.map((existing) => (
                                                    <option key={existing.id} value={existing.id}>
                                                        {existing.code} — {existing.name}{existing.basin ? ` (${existing.basin.code})` : ''}
                                                    </option>
                                                ))}
                                            </select>
                                        </Field>
                                    ) : (
                                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                            <Field label="New station code" htmlFor={`code-${station.name}`}>
                                                <input
                                                    id={`code-${station.name}`}
                                                    value={entry.code}
                                                    onChange={(event) => update(station.name, 'code', event.target.value.toUpperCase())}
                                                    placeholder="e.g. ARB-01"
                                                    className={`${inputClasses} py-1.5 text-[11.5px]`}
                                                />
                                            </Field>
                                            <Field label="Basin" htmlFor={`basin-${station.name}`}>
                                                <select
                                                    id={`basin-${station.name}`}
                                                    value={entry.basin_id}
                                                    onChange={(event) => {
                                                        setData('station_map', {
                                                            ...data.station_map,
                                                            [station.name]: {
                                                                ...entry,
                                                                basin_id: event.target.value,
                                                                sub_basin_id: '',
                                                            },
                                                        });
                                                    }}
                                                    className={`${selectClasses} py-1.5 text-[11.5px]`}
                                                >
                                                    <option value="">Select basin…</option>
                                                    {basins.map((basin) => (
                                                        <option key={basin.id} value={basin.id}>{basin.code} — {basin.name}</option>
                                                    ))}
                                                </select>
                                            </Field>
                                            <Field label="Sub-basin (optional)" htmlFor={`sub-${station.name}`} className="sm:col-span-2">
                                                <select
                                                    id={`sub-${station.name}`}
                                                    value={entry.sub_basin_id}
                                                    onChange={(event) => update(station.name, 'sub_basin_id', event.target.value)}
                                                    className={`${selectClasses} py-1.5 text-[11.5px]`}
                                                    disabled={!entry.basin_id}
                                                >
                                                    <option value="">No sub-basin</option>
                                                    {filteredSubBasins.map((sub) => (
                                                        <option key={sub.id} value={sub.id}>{sub.code} — {sub.name}</option>
                                                    ))}
                                                </select>
                                            </Field>
                                        </div>
                                    )}
                                </div>
                            );
                        })}

                        {can('approve data') && (
                            <Button className="w-full" onClick={importData} disabled={processing || imported || !canImport || !summary.rows}>
                                <ClipboardCheck className="h-4 w-4" strokeWidth={1.75} />
                                {processing ? 'Importing…' : imported ? 'Already imported' : 'Approve and import observations'}
                            </Button>
                        )}

                        <p className="text-[10.5px] leading-relaxed text-muted">
                            Only rows with status <span className="font-medium text-ink">valid</span> are written. Duplicate station/date/parameter keys are skipped and counted.
                        </p>
                    </CardBody>
                </Card>
            </div>
        </AppLayout>
    );
}
