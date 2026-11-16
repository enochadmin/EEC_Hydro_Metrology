import Button from '@/Components/Button';
import { Field, inputClasses, selectClasses } from '@/Components/FormField';
import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { Info, UploadCloud } from 'lucide-react';

const LAYER_TYPES = {
    national_boundary: 'national_boundary',
    rivers: 'river',
    lakes: 'lake',
};

const GEOMETRY_HINT = {
    national_boundary: 'One Polygon or MultiPolygon feature.',
    rivers: 'LineString or MultiLineString features.',
    lakes: 'Polygon or MultiPolygon features.',
};

export default function ImportGeoJsonDialog({ open, onClose, layers = [], basins = [] }) {
    const { data, setData, post, processing, errors, reset, progress } = useForm({
        layer_type: 'national_boundary',
        name: '',
        basin_id: '',
        source_note: '',
        file: null,
    });

    const selectedLayer = layers.find((layer) => LAYER_TYPES[layer.key] === data.layer_type);

    const submit = (event) => {
        event.preventDefault();

        post('/gis/features', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Transition show={open} leave="duration-150">
            <Dialog as="div" className="relative z-[1200]" onClose={onClose}>
                <TransitionChild
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-ink/45" />
                </TransitionChild>

                <div className="fixed inset-0 flex items-end justify-center px-4 py-6 sm:items-center">
                    <TransitionChild
                        enter="ease-out duration-200"
                        enterFrom="opacity-0 translate-y-3 sm:scale-95"
                        enterTo="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-150"
                        leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                        leaveTo="opacity-0 translate-y-3 sm:scale-95"
                    >
                        <DialogPanel className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-line bg-surface shadow-lift">
                            <div className="border-b border-line px-5 py-3.5">
                                <DialogTitle className="text-sm font-semibold text-ink">Import a GeoJSON layer</DialogTitle>
                                <p className="mt-0.5 text-[11.5px] text-muted">
                                    Geometry type, coordinate range and duplicate geometry are validated before anything is stored.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-4 px-5 py-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Target layer" htmlFor="import-layer" error={errors.layer_type} required>
                                        <select
                                            id="import-layer"
                                            value={data.layer_type}
                                            onChange={(event) => setData('layer_type', event.target.value)}
                                            className={selectClasses}
                                        >
                                            {layers.map((layer) => (
                                                <option key={layer.key} value={LAYER_TYPES[layer.key]}>{layer.label}</option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field label="Feature name (optional)" htmlFor="import-name" error={errors.name} hint="Used for single-feature imports; multi-feature files fall back to each feature's name property.">
                                        <input
                                            id="import-name"
                                            type="text"
                                            value={data.name}
                                            onChange={(event) => setData('name', event.target.value)}
                                            placeholder="e.g. Ethiopia boundary"
                                            className={inputClasses}
                                        />
                                    </Field>
                                </div>

                                <Field
                                    label="GeoJSON file"
                                    htmlFor="import-file"
                                    error={errors.file}
                                    hint={`EPSG:4326 only · ${GEOMETRY_HINT[data.layer_type] ?? ''} · up to 20 MB.`}
                                    required
                                >
                                    <input
                                        id="import-file"
                                        type="file"
                                        required
                                        accept=".geojson,.json,.txt,application/geo+json,application/json"
                                        onChange={(event) => setData('file', event.target.files?.[0] ?? null)}
                                        className="block w-full rounded-lg border border-line bg-surface px-3 py-2 text-[12.5px] text-ink file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-[12px] file:font-medium file:text-brand-700 dark:file:bg-brand-500/10 dark:file:text-brand-200"
                                    />
                                </Field>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Parent basin (optional)" htmlFor="import-basin" error={errors.basin_id} hint="Associates the feature with a basin record.">
                                        <select
                                            id="import-basin"
                                            value={data.basin_id}
                                            onChange={(event) => setData('basin_id', event.target.value)}
                                            className={selectClasses}
                                        >
                                            <option value="">Not associated</option>
                                            {basins.map((basin) => (
                                                <option key={basin.id} value={basin.id}>{basin.code} — {basin.name}</option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field label="Source note (optional)" htmlFor="import-note" error={errors.source_note}>
                                        <input
                                            id="import-note"
                                            type="text"
                                            value={data.source_note}
                                            onChange={(event) => setData('source_note', event.target.value)}
                                            placeholder="Survey or licence reference"
                                            className={inputClasses}
                                        />
                                    </Field>
                                </div>

                                {progress && (
                                    <div className="h-1.5 overflow-hidden rounded-full bg-canvas">
                                        <div className="h-full rounded-full bg-brand-600 transition-all" style={{ width: `${progress.percentage}%` }} />
                                    </div>
                                )}

                                <div className="flex items-start gap-2 rounded-lg border border-line bg-canvas/50 px-3 py-2.5">
                                    <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-700 dark:text-brand-300" strokeWidth={2} />
                                    <p className="text-[10.5px] leading-relaxed text-muted">
                                        {selectedLayer?.label ?? 'Selected layer'} · {selectedLayer?.description}
                                        {' '}Shapefile and KML are not accepted yet; the import service is structured so a GDAL conversion step can be added later.
                                    </p>
                                </div>

                                <div className="flex items-center justify-end gap-2 border-t border-line pt-3">
                                    <Button type="button" variant="secondary" size="sm" onClick={onClose} disabled={processing}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" size="sm" disabled={processing}>
                                        <UploadCloud className="h-3.5 w-3.5" strokeWidth={1.75} />
                                        {processing ? 'Importing…' : 'Import layer'}
                                    </Button>
                                </div>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}
