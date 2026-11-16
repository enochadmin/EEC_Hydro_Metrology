import Button from '@/Components/Button';
import { Field, inputClasses, selectClasses } from '@/Components/FormField';
import { cn } from '@/Utils/cn';
import { Link, useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';

const numberValue = (value) => (value === null || value === undefined ? '' : String(value));

export default function BasinForm({
    action,
    method = 'post',
    initial = {},
    dataSources = [],
    statuses = [],
    submitLabel = 'Save basin',
    cancelHref = '/basins',
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        code: initial.code ?? '',
        name: initial.name ?? '',
        name_alt: initial.name_alt ?? '',
        description: initial.description ?? '',
        area_km2: numberValue(initial.area_km2),
        centroid_lat: numberValue(initial.centroid_lat),
        centroid_lng: numberValue(initial.centroid_lng),
        elevation_m: numberValue(initial.elevation_m),
        data_source_id: initial.data_source_id ?? '',
        data_status: initial.data_status ?? 'pending',
        source_note: initial.source_note ?? '',
        is_active: initial.is_active ?? true,
    });

    const selectedStatus = statuses.find((status) => status.value === data.data_status);

    const submit = (event) => {
        event.preventDefault();

        const options = { preserveScroll: true };

        if (method === 'patch') {
            patch(action, options);
        } else {
            post(action, options);
        }
    };

    return (
        <form onSubmit={submit}>
            <div className="space-y-7 px-5 py-5">
                <section>
                    <h3 className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Identity</h3>
                    <div className="mt-3 grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Basin code"
                            htmlFor="code"
                            error={errors.code}
                            hint="Stored in upper case. Official EEC codes are entered once and never reused, including after deletion."
                            required
                        >
                            <input
                                id="code"
                                type="text"
                                required
                                maxLength={20}
                                autoComplete="off"
                                value={data.code}
                                onChange={(event) => setData('code', event.target.value.toUpperCase())}
                                placeholder="e.g. BH-01"
                                className={cn(inputClasses, 'font-mono uppercase')}
                            />
                        </Field>

                        <Field label="Basin name" htmlFor="name" error={errors.name} required>
                            <input
                                id="name"
                                type="text"
                                required
                                maxLength={150}
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="e.g. Abbay"
                                className={inputClasses}
                            />
                        </Field>

                        <Field
                            label="Alternative name"
                            htmlFor="name_alt"
                            error={errors.name_alt}
                            hint="Local or historical name, if one is officially recognised."
                        >
                            <input
                                id="name_alt"
                                type="text"
                                maxLength={150}
                                value={data.name_alt}
                                onChange={(event) => setData('name_alt', event.target.value)}
                                placeholder="e.g. Blue Nile"
                                className={inputClasses}
                            />
                        </Field>
                    </div>

                    <Field label="Description" htmlFor="description" error={errors.description} className="mt-4">
                        <textarea
                            id="description"
                            rows={3}
                            maxLength={2000}
                            value={data.description}
                            onChange={(event) => setData('description', event.target.value)}
                            placeholder="Short description of the basin and its significance."
                            className={inputClasses}
                        />
                    </Field>
                </section>

                <section>
                    <div className="flex flex-wrap items-end justify-between gap-2">
                        <h3 className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Geography</h3>
                        <p className="text-[11px] text-muted">WGS84 · decimal degrees</p>
                    </div>

                    <div className="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Field label="Geographic area (km²)" htmlFor="area_km2" error={errors.area_km2}>
                            <input
                                id="area_km2"
                                type="number"
                                min="0"
                                step="0.01"
                                value={data.area_km2}
                                onChange={(event) => setData('area_km2', event.target.value)}
                                placeholder="Not recorded"
                                className={inputClasses}
                            />
                        </Field>

                        <Field label="Mean elevation (m)" htmlFor="elevation_m" error={errors.elevation_m}>
                            <input
                                id="elevation_m"
                                type="number"
                                step="0.01"
                                value={data.elevation_m}
                                onChange={(event) => setData('elevation_m', event.target.value)}
                                placeholder="Not recorded"
                                className={inputClasses}
                            />
                        </Field>

                        <Field label="Centroid latitude" htmlFor="centroid_lat" error={errors.centroid_lat}>
                            <input
                                id="centroid_lat"
                                type="number"
                                step="0.0000001"
                                min="-90"
                                max="90"
                                value={data.centroid_lat}
                                onChange={(event) => setData('centroid_lat', event.target.value)}
                                placeholder="e.g. 9.1450000"
                                className={inputClasses}
                            />
                        </Field>

                        <Field label="Centroid longitude" htmlFor="centroid_lng" error={errors.centroid_lng}>
                            <input
                                id="centroid_lng"
                                type="number"
                                step="0.0000001"
                                min="-180"
                                max="180"
                                value={data.centroid_lng}
                                onChange={(event) => setData('centroid_lng', event.target.value)}
                                placeholder="e.g. 40.4896730"
                                className={inputClasses}
                            />
                        </Field>
                    </div>

                    <div className="mt-4 flex items-start gap-2.5 rounded-lg border border-line bg-canvas/50 px-3.5 py-3 text-[11.5px] leading-relaxed text-muted">
                        <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-600 dark:text-brand-300" strokeWidth={2} />
                        <p>
                            Leave area and coordinates empty when no authoritative source has confirmed them. The boundary polygon is imported
                            separately from a GeoJSON file, never drawn from these fields.
                        </p>
                    </div>
                </section>

                <section>
                    <h3 className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">Provenance and status</h3>

                    <div className="mt-3 grid gap-4 sm:grid-cols-2">
                        <Field label="Data source" htmlFor="data_source_id" error={errors.data_source_id} hint="Where the record and its geography come from.">
                            <select
                                id="data_source_id"
                                value={data.data_source_id}
                                onChange={(event) => setData('data_source_id', event.target.value)}
                                className={selectClasses}
                            >
                                <option value="">Not recorded</option>
                                {dataSources.map((source) => (
                                    <option key={source.id} value={source.id}>
                                        {source.name}
                                        {source.organisation ? ` — ${source.organisation}` : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Data status" htmlFor="data_status" error={errors.data_status} hint={selectedStatus?.description}>
                            <select
                                id="data_status"
                                value={data.data_status}
                                onChange={(event) => setData('data_status', event.target.value)}
                                className={selectClasses}
                            >
                                {statuses.map((status) => (
                                    <option key={status.value} value={status.value}>{status.label}</option>
                                ))}
                            </select>
                        </Field>
                    </div>

                    <Field label="Source note" htmlFor="source_note" error={errors.source_note} className="mt-4">
                        <textarea
                            id="source_note"
                            rows={2}
                            maxLength={1000}
                            value={data.source_note}
                            onChange={(event) => setData('source_note', event.target.value)}
                            placeholder="Reference document, survey date or the reason geography is still pending."
                            className={inputClasses}
                        />
                    </Field>

                    <label className="mt-4 flex items-start gap-2.5 rounded-lg border border-line px-3.5 py-3">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(event) => setData('is_active', event.target.checked)}
                            className="mt-0.5 rounded border-line text-brand-600 focus:ring-brand-500"
                        />
                        <span className="min-w-0">
                            <span className="block text-[13px] font-medium text-ink">Active record</span>
                            <span className="mt-0.5 block text-[11.5px] text-muted">
                                Inactive basins stay in the registry for history but are excluded from selection lists.
                            </span>
                        </span>
                    </label>
                </section>
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-line px-5 py-3.5">
                <Link href={cancelHref} className="rounded-lg px-3 py-2 text-[13px] font-medium text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring">
                    Cancel
                </Link>
                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
