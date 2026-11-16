import Button from '@/Components/Button';
import { Field, inputClasses } from '@/Components/FormField';
import { useForm } from '@inertiajs/react';
import { UploadCloud } from 'lucide-react';

/**
 * GeoJSON boundary import form.
 *
 * The server validates the geometry (closed rings, WGS84 ranges, single
 * polygon) and stores it with SRID 4326. The form only collects intent.
 */
export default function BoundaryPanel({ action, description }) {
    const { data, setData, post, processing, errors, reset, progress } = useForm({
        name: '',
        file: null,
        is_primary: true,
        accuracy_m: '',
        notes: '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(action, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <p className="text-[13px] font-medium text-ink">Import a boundary polygon</p>
                <p className="mt-1 text-xs leading-relaxed text-muted">
                    {description ??
                        'Upload one GeoJSON Polygon or MultiPolygon in WGS84 (EPSG:4326). Coordinates are validated before anything is stored; no polygon is ever generated from partial data.'}
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Boundary version name" htmlFor="boundary-name" error={errors.name} required>
                    <input
                        id="boundary-name"
                        type="text"
                        required
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        placeholder="e.g. EEC master boundary 2026"
                        className={inputClasses}
                    />
                </Field>

                <Field label="Accuracy (metres, optional)" htmlFor="boundary-accuracy" error={errors.accuracy_m}>
                    <input
                        id="boundary-accuracy"
                        type="number"
                        min="0"
                        step="0.01"
                        value={data.accuracy_m}
                        onChange={(event) => setData('accuracy_m', event.target.value)}
                        placeholder="e.g. 25"
                        className={inputClasses}
                    />
                </Field>
            </div>

            <Field
                label="GeoJSON file"
                htmlFor="boundary-file"
                error={errors.file}
                hint="Accepted: .geojson, .json or .txt up to 20 MB. A FeatureCollection must contain exactly one feature."
                required
            >
                <input
                    id="boundary-file"
                    type="file"
                    required
                    accept=".geojson,.json,.txt,application/geo+json,application/json"
                    onChange={(event) => setData('file', event.target.files?.[0] ?? null)}
                    className="block w-full rounded-lg border border-line bg-surface px-3 py-2 text-[12.5px] text-ink file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-[12px] file:font-medium file:text-brand-700 hover:file:bg-brand-100 dark:file:bg-brand-500/10 dark:file:text-brand-200"
                />
            </Field>

            <Field label="Notes (optional)" htmlFor="boundary-notes" error={errors.notes}>
                <textarea
                    id="boundary-notes"
                    rows={2}
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                    placeholder="Source, survey date or licence reference"
                    className={inputClasses}
                />
            </Field>

            <label className="flex items-start gap-2.5 rounded-lg border border-line bg-canvas/50 px-3.5 py-3">
                <input
                    type="checkbox"
                    checked={data.is_primary}
                    onChange={(event) => setData('is_primary', event.target.checked)}
                    className="mt-0.5 rounded border-line text-brand-600 focus:ring-brand-500"
                />
                <span className="min-w-0">
                    <span className="block text-[13px] font-medium text-ink">Use as the primary boundary</span>
                    <span className="mt-0.5 block text-[11.5px] leading-relaxed text-muted">
                        The previous primary polygon is kept as a version but no longer drives the map display.
                    </span>
                </span>
            </label>

            {progress && (
                <div className="h-1.5 overflow-hidden rounded-full bg-canvas">
                    <div className="h-full rounded-full bg-brand-600 transition-all" style={{ width: `${progress.percentage}%` }} />
                </div>
            )}

            <div className="flex justify-end">
                <Button type="submit" size="sm" disabled={processing}>
                    <UploadCloud className="h-4 w-4" strokeWidth={1.75} />
                    {processing ? 'Importing…' : 'Import boundary'}
                </Button>
            </div>
        </form>
    );
}
