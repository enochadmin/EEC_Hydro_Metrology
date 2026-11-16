import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import { Field, inputClasses, selectClasses } from '@/Components/FormField';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { structureLabel } from '@/Utils/excel';
import { Head, Link, useForm } from '@inertiajs/react';
import { Columns3, Info } from 'lucide-react';

const MONTHLY_FIELDS = [
    ['station', 'Station name', true],
    ['elevation', 'Elevation', false],
    ['longitude', 'Longitude', false],
    ['latitude', 'Latitude', false],
    ['parameter', 'Element / parameter', false],
    ['year', 'Year', true],
    ['month', 'Month', true],
    ['time', 'Observation time', false],
];

export default function ColumnMapping({ batch, sheet, structure, candidate, mapping, parameters = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        sheet_name: sheet,
        structure_type: structure,
        mapping,
    });

    const setMapping = (key, value) => setData('mapping', { ...data.mapping, [key]: value });
    const setColumn = (key, value) => setData('mapping', {
        ...data.mapping,
        columns: { ...(data.mapping.columns ?? {}), [key]: value },
    });

    const headerOptions = Object.entries(candidate.headers ?? {}).map(([column, label]) => ({
        column,
        label: label === null || label === undefined || label === '' ? `Column ${column}` : `${column} — ${label}`,
    }));

    const submit = (event) => {
        event.preventDefault();
        post(`/excel/${batch.uuid}/mapping`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Excel', href: '/excel' }, { label: batch.file_name, href: `/excel/${batch.uuid}/preview?sheet=${encodeURIComponent(sheet)}` }, { label: 'Column mapping' }]}>
            <Head title="Column mapping" />

            <PageHeader
                title="Confirm the column mapping"
                description={`${sheet} · ${structureLabel(structure)}. Correct any field the detector guessed wrong before transforming.`}
                actions={<Badge tone="brand" size="sm">{candidate.confidence ? `${Math.round(candidate.confidence * 100)}% detection` : 'Manual mapping'}</Badge>}
            />

            <form onSubmit={submit} className="mt-6 space-y-4">
                <Card className="overflow-hidden">
                    <CardHeader icon={Columns3} title="Field mapping" description="Required fields are marked; the rest can stay empty when the sheet does not contain them." />
                    <CardBody className="space-y-4">
                        {errors.mapping && (
                            <div className="rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-[12px] text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                                {errors.mapping}
                            </div>
                        )}

                        {structure === 'monthly_daily_columns' && (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {MONTHLY_FIELDS.map(([key, label, required]) => (
                                    <Field key={key} label={label} htmlFor={`column-${key}`} required={required}>
                                        <select
                                            id={`column-${key}`}
                                            value={data.mapping.columns?.[key] ?? ''}
                                            onChange={(event) => setColumn(key, event.target.value || null)}
                                            className={selectClasses}
                                        >
                                            <option value="">Not mapped</option>
                                            {headerOptions.map((option) => (
                                                <option key={option.column} value={option.column}>{option.label}</option>
                                            ))}
                                        </select>
                                    </Field>
                                ))}
                            </div>
                        )}

                        {structure === 'multi_header_matrix' && (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Field label="Year header row" htmlFor="year-row"><input id="year-row" readOnly value={data.mapping.year_row ?? ''} className={inputClasses} /></Field>
                                <Field label="Month header row" htmlFor="month-row"><input id="month-row" readOnly value={data.mapping.month_row ?? ''} className={inputClasses} /></Field>
                                <Field label="Day rows" htmlFor="day-rows"><input id="day-rows" readOnly value={`${data.mapping.day_rows?.start}–${data.mapping.day_rows?.end}`} className={inputClasses} /></Field>
                                <Field label="Day label column" htmlFor="day-column"><input id="day-column" readOnly value={data.mapping.day_column ?? ''} className={inputClasses} /></Field>
                                <Field label="Detected periods" htmlFor="periods"><input id="periods" readOnly value={`${data.mapping.periods?.length ?? 0} months`} className={inputClasses} /></Field>
                            </div>
                        )}

                        {structure === 'daily_series' && (
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field label="Date column" htmlFor="date-column">
                                    <input id="date-column" maxLength={3} value={data.mapping.date_column ?? ''} onChange={(event) => setMapping('date_column', event.target.value.toUpperCase())} className={inputClasses} />
                                </Field>
                                <Field label="Value column" htmlFor="value-column">
                                    <input id="value-column" maxLength={3} value={data.mapping.value_column ?? ''} onChange={(event) => setMapping('value_column', event.target.value.toUpperCase())} className={inputClasses} />
                                </Field>
                                <Field label="First data row" htmlFor="start-row">
                                    <input id="start-row" type="number" value={data.mapping.start_row ?? ''} onChange={(event) => setMapping('start_row', event.target.value)} className={inputClasses} />
                                </Field>
                            </div>
                        )}

                        {structure === 'monthly_daily_columns' && (
                            <div className="rounded-lg border border-line bg-canvas/40 px-3.5 py-3">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Day columns</p>
                                <div className="mt-2 flex flex-wrap gap-1">
                                    {Object.entries(data.mapping.day_columns ?? {}).map(([day, column]) => (
                                        <span key={day} className="rounded bg-surface px-1.5 py-0.5 font-mono text-[10px] text-ink ring-1 ring-line">
                                            {day}:{column}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardBody>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader icon={Info} title="Station and parameter" description="Matrix and date-series sheets carry no station metadata; name the station here and match it on the validation step." />
                    <CardBody className="grid gap-4 sm:grid-cols-3">
                        <Field label="Station name" htmlFor="station-name" hint={structure === 'monthly_daily_columns' ? 'Leave empty to use the station column in each row.' : 'Required for this structure.'}>
                            <input
                                id="station-name"
                                value={data.mapping.station_name ?? ''}
                                onChange={(event) => setMapping('station_name', event.target.value)}
                                placeholder={structure === 'monthly_daily_columns' ? 'From the sheet' : 'e.g. Arba Minch'}
                                className={inputClasses}
                            />
                        </Field>

                        <Field label="Default parameter" htmlFor="parameter-code" hint="Used when the sheet has no element column.">
                            <select id="parameter-code" value={data.mapping.parameter_code ?? ''} onChange={(event) => setMapping('parameter_code', event.target.value)} className={selectClasses}>
                                <option value="">Not set</option>
                                {parameters.map((parameter) => (
                                    <option key={parameter.id} value={parameter.code}>{parameter.name}{parameter.unit ? ` (${parameter.unit})` : ''}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Observation time" htmlFor="mapping-time" hint="Daily rainfall is normally recorded at 09:00.">
                            <input id="mapping-time" value={data.mapping.time ?? '09:00'} onChange={(event) => setMapping('time', event.target.value)} className={inputClasses} />
                        </Field>
                    </CardBody>
                </Card>

                <div className="flex items-center justify-between gap-2">
                    <Link href={`/excel/${batch.uuid}/preview?sheet=${encodeURIComponent(sheet)}`} className="text-[12px] font-medium text-muted hover:text-ink">
                        Back to inspection
                    </Link>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Transforming…' : 'Generate transformation preview'}
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
