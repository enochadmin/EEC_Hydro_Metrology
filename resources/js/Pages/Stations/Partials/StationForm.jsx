import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import { Field, inputClasses, selectClasses } from '@/Components/FormField';
import { Link, useForm } from '@inertiajs/react';
import { Activity, Compass, Database, RadioTower, Waves } from 'lucide-react';

export default function StationForm({
    action,
    method = 'post',
    station = {},
    categories = [],
    classes = [],
    basins = [],
    subBasins = [],
    parameters = [],
    dataSources = [],
    cancelHref = '/stations',
    submitLabel = 'Save station',
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        code: station.code ?? '',
        name: station.name ?? '',
        name_alt: station.name_alt ?? '',
        station_type: station.station_type ?? categories[0]?.code ?? '',
        station_class: station.station_class ?? '',
        basin_id: station.basin_id ? String(station.basin_id) : '',
        sub_basin_id: station.sub_basin_id ? String(station.sub_basin_id) : '',
        latitude: station.latitude ?? '',
        longitude: station.longitude ?? '',
        elevation_m: station.elevation_m ?? '',
        established_on: station.established_on ?? '',
        operator: station.operator ?? '',
        data_source_id: station.data_source_id ? String(station.data_source_id) : '',
        data_status: station.data_status ?? 'pending',
        is_active: station.is_active ?? true,
        notes: station.notes ?? '',
        parameters: station.parameter_ids ?? [],
    });

    const selectedSubBasins = subBasins.filter((subBasin) => String(subBasin.basin_id) === String(data.basin_id));
    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true };

        if (method === 'patch') {
            patch(action, options);
        } else {
            post(action, options);
        }
    };

    const toggleParameter = (id) => {
        const selected = data.parameters.includes(id);
        setData('parameters', selected ? data.parameters.filter((parameterId) => parameterId !== id) : [...data.parameters, id]);
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <Card>
                <CardHeader icon={RadioTower} title="Station identity" description="Registry details and station classification." />
                <CardBody className="grid gap-4 sm:grid-cols-2">
                    <Field label="Station code" htmlFor="code" error={errors.code} required hint="Unique code used by EEC and source workbooks.">
                        <input id="code" required maxLength={50} value={data.code} onChange={(event) => setData('code', event.target.value.toUpperCase())} placeholder="e.g. ST-ABB-001" className={inputClasses + ' font-mono uppercase'} />
                    </Field>
                    <Field label="Station name" htmlFor="name" error={errors.name} required>
                        <input id="name" required maxLength={150} value={data.name} onChange={(event) => setData('name', event.target.value)} placeholder="Official station name" className={inputClasses} />
                    </Field>
                    <Field label="Alternative name" htmlFor="name_alt" error={errors.name_alt}>
                        <input id="name_alt" maxLength={150} value={data.name_alt} onChange={(event) => setData('name_alt', event.target.value)} placeholder="Local or historical name" className={inputClasses} />
                    </Field>
                    <Field label="Network class" htmlFor="station_class" error={errors.station_class} hint="Observing network the station belongs to. Separate from what it measures.">
                        <select id="station_class" value={data.station_class} onChange={(event) => setData('station_class', event.target.value)} className={selectClasses}>
                            <option value="">Not classified</option>
                            {classes.map((option) => <option key={option.code} value={option.code}>{option.label}</option>)}
                        </select>
                    </Field>
                    <Field label="Station type" htmlFor="station_type" error={errors.station_type} required>
                        <select id="station_type" required value={data.station_type} onChange={(event) => setData('station_type', event.target.value)} className={selectClasses}>
                            {categories.map((category) => <option key={category.code} value={category.code}>{category.label}</option>)}
                        </select>
                    </Field>
                </CardBody>
            </Card>

            <Card>
                <CardHeader icon={Compass} title="Location and basin" description="Coordinates use WGS84 decimal degrees. Leave both empty when they have not been confirmed." />
                <CardBody className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Field label="Basin" htmlFor="basin_id" error={errors.basin_id} hint="Optional until basin boundaries are imported.">
                        <select id="basin_id" value={data.basin_id} onChange={(event) => setData({ ...data, basin_id: event.target.value, sub_basin_id: '' })} className={selectClasses}>
                            <option value="">No basin assigned</option>
                            {basins.map((basin) => <option key={basin.id} value={basin.id}>{basin.code} — {basin.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Sub-basin" htmlFor="sub_basin_id" error={errors.sub_basin_id}>
                        <select id="sub_basin_id" value={data.sub_basin_id} onChange={(event) => setData('sub_basin_id', event.target.value)} className={selectClasses}>
                            <option value="">Not assigned</option>
                            {selectedSubBasins.map((subBasin) => <option key={subBasin.id} value={subBasin.id}>{subBasin.code} — {subBasin.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Latitude" htmlFor="latitude" error={errors.latitude}>
                        <input id="latitude" type="number" min="-90" max="90" step="0.0000001" value={data.latitude} onChange={(event) => setData('latitude', event.target.value)} placeholder="e.g. 9.1450000" className={inputClasses} />
                    </Field>
                    <Field label="Longitude" htmlFor="longitude" error={errors.longitude}>
                        <input id="longitude" type="number" min="-180" max="180" step="0.0000001" value={data.longitude} onChange={(event) => setData('longitude', event.target.value)} placeholder="e.g. 40.4896730" className={inputClasses} />
                    </Field>
                    <Field label="Elevation (m)" htmlFor="elevation_m" error={errors.elevation_m}>
                        <input id="elevation_m" type="number" min="-500" max="9000" step="0.01" value={data.elevation_m} onChange={(event) => setData('elevation_m', event.target.value)} placeholder="Not recorded" className={inputClasses} />
                    </Field>
                    <Field label="Installation date" htmlFor="established_on" error={errors.established_on}>
                        <input id="established_on" type="date" value={data.established_on} onChange={(event) => setData('established_on', event.target.value)} className={inputClasses} />
                    </Field>
                </CardBody>
            </Card>

            <Card>
                <CardHeader icon={Waves} title="Available parameters" description="Select the measurements this station is equipped to record." />
                <CardBody>
                    {parameters.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-4 py-5 text-sm text-muted">No active parameters are available. Add them in System settings first.</p>
                    ) : (
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {parameters.map((parameter) => (
                                <label key={parameter.id} className="flex min-w-0 items-start gap-2.5 rounded-lg border border-line px-3 py-2.5">
                                    <input type="checkbox" checked={data.parameters.includes(parameter.id)} onChange={() => toggleParameter(parameter.id)} className="mt-0.5 rounded border-line text-brand-600 focus:ring-brand-500" />
                                    <span className="min-w-0">
                                        <span className="block truncate text-[13px] font-medium text-ink">{parameter.name}</span>
                                        <span className="mt-0.5 block text-[11px] text-muted">{parameter.code}{parameter.unit ? ' · ' + parameter.unit : ''}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    )}
                    {errors.parameters && <p className="mt-2 text-xs text-rose-600 dark:text-rose-400">{errors.parameters}</p>}
                    {errors['parameters.0'] && <p className="mt-2 text-xs text-rose-600 dark:text-rose-400">{errors['parameters.0']}</p>}
                </CardBody>
            </Card>

            <Card>
                <CardHeader icon={Database} title="Management and provenance" description="Operational state and the source that supports this station record." />
                <CardBody className="grid gap-4 sm:grid-cols-2">
                    <Field label="Managing organization" htmlFor="operator" error={errors.operator}>
                        <input id="operator" maxLength={150} value={data.operator} onChange={(event) => setData('operator', event.target.value)} placeholder="Organization responsible for the station" className={inputClasses} />
                    </Field>
                    <Field label="Data source" htmlFor="data_source_id" error={errors.data_source_id}>
                        <select id="data_source_id" value={data.data_source_id} onChange={(event) => setData('data_source_id', event.target.value)} className={selectClasses}>
                            <option value="">Not recorded</option>
                            {dataSources.map((source) => <option key={source.id} value={source.id}>{source.name}{source.organisation ? ' — ' + source.organisation : ''}</option>)}
                        </select>
                    </Field>
                    <Field label="Data status" htmlFor="data_status" error={errors.data_status} required>
                        <select id="data_status" required value={data.data_status} onChange={(event) => setData('data_status', event.target.value)} className={selectClasses}>
                            <option value="pending">Pending confirmation</option>
                            <option value="provisional">Provisional</option>
                            <option value="verified">Verified</option>
                        </select>
                    </Field>
                    <label className="flex items-start gap-2.5 rounded-lg border border-line px-3.5 py-3">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="mt-0.5 rounded border-line text-brand-600 focus:ring-brand-500" />
                        <span>
                            <span className="block text-[13px] font-medium text-ink"><Activity className="mr-1 inline h-3.5 w-3.5" />Operational station</span>
                            <span className="mt-0.5 block text-[11.5px] text-muted">Inactive stations remain in history but are marked out of operation.</span>
                        </span>
                    </label>
                    <Field label="Description and notes" htmlFor="notes" error={errors.notes} className="sm:col-span-2">
                        <textarea id="notes" rows={4} maxLength={2000} value={data.notes} onChange={(event) => setData('notes', event.target.value)} placeholder="Installation, access, or operating notes" className={inputClasses} />
                    </Field>
                </CardBody>
            </Card>

            <div className="flex items-center justify-end gap-2">
                <Link href={cancelHref} className="rounded-lg px-3 py-2 text-[13px] font-medium text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring">Cancel</Link>
                <Button type="submit" disabled={processing}>{processing ? 'Saving…' : submitLabel}</Button>
            </div>
        </form>
    );
}
