import Badge from '@/Components/Badge';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/Utils/cn';
import { Head, router, useForm } from '@inertiajs/react';
import { Building2, Database, FlaskConical, Ruler, Settings2, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';

const field = 'w-full rounded-lg border border-line bg-surface px-2.5 py-1.5 text-[12px] text-ink focus-ring';
const labelClass = 'mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted';

function GroupForm({ groupKey, group, values }) {
    const initial = {};

    Object.entries(group.settings).forEach(([key, definition]) => {
        initial[key] = values.find((value) => value.key === key)?.value ?? definition.default;
    });

    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({ settings: initial });

    const submit = (event) => {
        event.preventDefault();
        put('/settings/system', { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader
                icon={groupKey === 'branding' ? Building2 : groupKey === 'geography' ? SlidersHorizontal : groupKey === 'imports' ? Database : FlaskConical}
                title={group.label}
                description={group.description}
                action={recentlySuccessful ? <Badge size="sm" tone="success">Saved</Badge> : null}
            />
            <CardBody>
                <form onSubmit={submit} className="space-y-3">
                    {Object.entries(group.settings).map(([key, definition]) => (
                        <div key={key}>
                            <label className={labelClass} htmlFor={`${groupKey}-${key}`}>{definition.label}</label>

                            {definition.type === 'select' ? (
                                <select
                                    id={`${groupKey}-${key}`}
                                    className={field}
                                    value={data.settings[key] ?? ''}
                                    onChange={(event) => setData('settings', { ...data.settings, [key]: event.target.value })}
                                >
                                    {(definition.options ?? []).map((option) => (
                                        <option key={option} value={option}>{option}</option>
                                    ))}
                                </select>
                            ) : (
                                <input
                                    id={`${groupKey}-${key}`}
                                    type={definition.type === 'integer' || definition.type === 'decimal' ? 'number' : 'text'}
                                    step={definition.type === 'decimal' ? '0.000001' : undefined}
                                    className={field}
                                    value={data.settings[key] ?? ''}
                                    onChange={(event) => setData('settings', { ...data.settings, [key]: event.target.value })}
                                />
                            )}

                            {definition.description && <p className="mt-1 text-[10.5px] leading-relaxed text-muted">{definition.description}</p>}
                            {errors[`settings.${key}`] && <p className="mt-1 text-[11px] text-rose-600">{errors[`settings.${key}`]}</p>}
                        </div>
                    ))}

                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center gap-1.5 rounded-lg bg-brand-700 px-3 py-1.5 text-[12px] font-medium text-white transition-colors hover:bg-brand-800 focus-ring disabled:opacity-60 dark:bg-brand-600"
                    >
                        Save {group.label.toLowerCase()}
                    </button>
                </form>
            </CardBody>
        </Card>
    );
}

function ParametersPanel({ parameters, units, valueTypes, categories }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        name: '',
        category: 'hydrological',
        value_type: 'instantaneous',
        default_unit_id: units[0]?.id ?? '',
        decimals: 2,
        description: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post('/settings/system/parameters', { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <Card>
            <CardHeader
                icon={FlaskConical}
                title="Hydrological parameters"
                description="Value type decides how each parameter is aggregated in reports."
                action={
                    <button type="button" onClick={() => setOpen(!open)} className="rounded-lg border border-line px-2.5 py-1.5 text-[11.5px] font-medium text-ink hover:bg-canvas focus-ring">
                        {open ? 'Cancel' : 'Add parameter'}
                    </button>
                }
            />

            {open && (
                <CardBody className="border-b border-line bg-canvas/40">
                    <form onSubmit={submit} className="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
                        <div>
                            <label className={labelClass} htmlFor="p-code">Code</label>
                            <input id="p-code" className={field} value={data.code} onChange={(event) => setData('code', event.target.value)} placeholder="PRECIP" />
                            {errors.code && <p className="mt-1 text-[11px] text-rose-600">{errors.code}</p>}
                        </div>
                        <div>
                            <label className={labelClass} htmlFor="p-name">Name</label>
                            <input id="p-name" className={field} value={data.name} onChange={(event) => setData('name', event.target.value)} />
                            {errors.name && <p className="mt-1 text-[11px] text-rose-600">{errors.name}</p>}
                        </div>
                        <div>
                            <label className={labelClass} htmlFor="p-category">Category</label>
                            <select id="p-category" className={field} value={data.category} onChange={(event) => setData('category', event.target.value)}>
                                {categories.map((category) => <option key={category} value={category}>{category}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass} htmlFor="p-type">Value type</label>
                            <select id="p-type" className={field} value={data.value_type} onChange={(event) => setData('value_type', event.target.value)}>
                                {valueTypes.map((type) => <option key={type} value={type}>{type.replace(/_/g, ' ')}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass} htmlFor="p-unit">Default unit</label>
                            <select id="p-unit" className={field} value={data.default_unit_id} onChange={(event) => setData('default_unit_id', event.target.value)}>
                                {units.map((unit) => <option key={unit.id} value={unit.id}>{unit.symbol} — {unit.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className={labelClass} htmlFor="p-decimals">Decimals</label>
                            <input id="p-decimals" type="number" min="0" max="6" className={field} value={data.decimals} onChange={(event) => setData('decimals', event.target.value)} />
                        </div>
                        <div className="flex items-end">
                            <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand-700 px-3 py-2 text-[12px] font-medium text-white hover:bg-brand-800 disabled:opacity-60">
                                Create parameter
                            </button>
                        </div>
                    </form>
                </CardBody>
            )}

            <div className="overflow-x-auto">
                <table className="w-full text-left">
                    <thead>
                        <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                            <th className="px-5 py-2.5">Parameter</th>
                            <th className="px-5 py-2.5">Value type</th>
                            <th className="px-5 py-2.5">Unit</th>
                            <th className="px-5 py-2.5 text-right">Observations</th>
                            <th className="px-5 py-2.5">State</th>
                            <th className="px-5 py-2.5" />
                        </tr>
                    </thead>
                    <tbody>
                        {parameters.map((parameter) => (
                            <tr key={parameter.id} className="border-b border-line/70 last:border-0">
                                <td className="px-5 py-2.5">
                                    <p className="text-[12.5px] font-medium text-ink">{parameter.name}</p>
                                    <p className="text-[10.5px] text-muted">{parameter.code} · {parameter.category}</p>
                                </td>
                                <td className="px-5 py-2.5 text-[11.5px] text-muted">{parameter.value_type.replace(/_/g, ' ')}</td>
                                <td className="px-5 py-2.5 text-[11.5px] text-muted">{parameter.unit ?? '—'}</td>
                                <td className="px-5 py-2.5 text-right text-[11.5px] tabular text-muted">{parameter.observations}</td>
                                <td className="px-5 py-2.5">
                                    <Badge size="sm" tone={parameter.is_active ? 'success' : 'neutral'}>{parameter.is_active ? 'Active' : 'Inactive'}</Badge>
                                </td>
                                <td className="px-5 py-2.5 text-right">
                                    <div className="inline-flex items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => router.patch(`/settings/system/parameters/${parameter.id}`, {
                                                name: parameter.name,
                                                category: parameter.category,
                                                value_type: parameter.value_type,
                                                default_unit_id: parameter.default_unit_id,
                                                decimals: parameter.decimals,
                                                min_expected: parameter.min_expected,
                                                max_expected: parameter.max_expected,
                                                description: parameter.description,
                                                is_active: !parameter.is_active,
                                            }, { preserveScroll: true })}
                                            className="text-[11px] font-medium text-brand-700 hover:underline dark:text-brand-300"
                                        >
                                            {parameter.is_active ? 'Deactivate' : 'Activate'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => router.delete(`/settings/system/parameters/${parameter.id}`, { preserveScroll: true })}
                                            className="text-[11px] font-medium text-rose-600 hover:underline disabled:opacity-40"
                                            disabled={parameter.observations > 0}
                                            title={parameter.observations > 0 ? 'Parameters with observations cannot be deleted.' : 'Delete parameter'}
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

function UnitsPanel({ units, dimensions }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
        name: '',
        symbol: '',
        dimension: 'length',
        decimals: 2,
    });

    const submit = (event) => {
        event.preventDefault();
        post('/settings/system/units', { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <Card>
            <CardHeader
                icon={Ruler}
                title="Measurement units"
                description="Units referenced by parameters and observations cannot be removed."
                action={
                    <button type="button" onClick={() => setOpen(!open)} className="rounded-lg border border-line px-2.5 py-1.5 text-[11.5px] font-medium text-ink hover:bg-canvas focus-ring">
                        {open ? 'Cancel' : 'Add unit'}
                    </button>
                }
            />

            {open && (
                <CardBody className="border-b border-line bg-canvas/40">
                    <form onSubmit={submit} className="grid gap-3 md:grid-cols-3 xl:grid-cols-5">
                        <div>
                            <label className={labelClass} htmlFor="u-code">Code</label>
                            <input id="u-code" className={field} value={data.code} onChange={(event) => setData('code', event.target.value)} placeholder="MM" />
                            {errors.code && <p className="mt-1 text-[11px] text-rose-600">{errors.code}</p>}
                        </div>
                        <div>
                            <label className={labelClass} htmlFor="u-name">Name</label>
                            <input id="u-name" className={field} value={data.name} onChange={(event) => setData('name', event.target.value)} placeholder="Millimetre" />
                        </div>
                        <div>
                            <label className={labelClass} htmlFor="u-symbol">Symbol</label>
                            <input id="u-symbol" className={field} value={data.symbol} onChange={(event) => setData('symbol', event.target.value)} placeholder="mm" />
                        </div>
                        <div>
                            <label className={labelClass} htmlFor="u-dim">Dimension</label>
                            <select id="u-dim" className={field} value={data.dimension} onChange={(event) => setData('dimension', event.target.value)}>
                                {dimensions.map((dimension) => <option key={dimension} value={dimension}>{dimension.replace(/_/g, ' ')}</option>)}
                            </select>
                        </div>
                        <div className="flex items-end">
                            <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand-700 px-3 py-2 text-[12px] font-medium text-white hover:bg-brand-800 disabled:opacity-60">
                                Create unit
                            </button>
                        </div>
                    </form>
                </CardBody>
            )}

            <div className="overflow-x-auto">
                <table className="w-full text-left">
                    <thead>
                        <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                            <th className="px-5 py-2.5">Code</th>
                            <th className="px-5 py-2.5">Name</th>
                            <th className="px-5 py-2.5">Symbol</th>
                            <th className="px-5 py-2.5">Dimension</th>
                            <th className="px-5 py-2.5">State</th>
                            <th className="px-5 py-2.5" />
                        </tr>
                    </thead>
                    <tbody>
                        {units.map((unit) => (
                            <tr key={unit.id} className="border-b border-line/70 last:border-0">
                                <td className="px-5 py-2.5 text-[12px] font-medium text-ink">{unit.code}</td>
                                <td className="px-5 py-2.5 text-[12px] text-muted">{unit.name}</td>
                                <td className="px-5 py-2.5 text-[12px] text-muted">{unit.symbol}</td>
                                <td className="px-5 py-2.5 text-[11.5px] text-muted">{unit.dimension.replace(/_/g, ' ')}</td>
                                <td className="px-5 py-2.5">
                                    <Badge size="sm" tone={unit.is_active ? 'success' : 'neutral'}>{unit.is_active ? 'Active' : 'Inactive'}</Badge>
                                </td>
                                <td className="px-5 py-2.5 text-right">
                                    <button
                                        type="button"
                                        onClick={() => router.delete(`/settings/system/units/${unit.id}`, { preserveScroll: true })}
                                        className="text-[11px] font-medium text-rose-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

function CategoriesPanel({ categories }) {
    return (
        <Card>
            <CardHeader icon={Settings2} title="Station categories" description="Labels and colours for the station types the schema accepts." />
            <div className="overflow-x-auto">
                <table className="w-full text-left">
                    <thead>
                        <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                            <th className="px-5 py-2.5">Code</th>
                            <th className="px-5 py-2.5">Label</th>
                            <th className="px-5 py-2.5">Colour</th>
                            <th className="px-5 py-2.5">Active</th>
                            <th className="px-5 py-2.5" />
                        </tr>
                    </thead>
                    <tbody>
                        {categories.map((category) => (
                            <CategoryRow key={category.id} category={category} />
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}

function CategoryRow({ category }) {
    const { data, setData, patch, processing } = useForm({
        label: category.label,
        colour: category.colour ?? '',
        description: category.description ?? '',
        is_active: category.is_active,
        sort_order: category.sort_order,
    });

    return (
        <tr className="border-b border-line/70 last:border-0">
            <td className="px-5 py-2.5 text-[11.5px] font-medium text-ink">{category.code}</td>
            <td className="px-5 py-2">
                <input className={field} value={data.label} onChange={(event) => setData('label', event.target.value)} />
            </td>
            <td className="px-5 py-2">
                <input className={cn(field, 'w-24')} value={data.colour} onChange={(event) => setData('colour', event.target.value)} placeholder="#0f766e" />
            </td>
            <td className="px-5 py-2">
                <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} className="h-4 w-4 rounded border-line" />
            </td>
            <td className="px-5 py-2 text-right">
                <button
                    type="button"
                    disabled={processing}
                    onClick={() => patch(`/settings/system/categories/${category.id}`, { preserveScroll: true })}
                    className="text-[11px] font-medium text-brand-700 hover:underline disabled:opacity-50 dark:text-brand-300"
                >
                    Save
                </button>
            </td>
        </tr>
    );
}

export default function SystemSettings({ groups, values, parameters, units, categories, valueTypes, parameterCategories, unitDimensions }) {
    const [tab, setTab] = useState('application');

    const tabs = [
        ['application', 'Application'],
        ['parameters', 'Parameters'],
        ['units', 'Units'],
        ['categories', 'Station categories'],
    ];

    return (
        <AppLayout breadcrumbs={[{ label: 'Administration' }, { label: 'System settings' }]}>
            <Head title="System settings" />

            <PageHeader title="System settings" description="Branding, map defaults, import limits, quality targets and reference data." />

            <div className="mt-6 flex flex-wrap items-center gap-1.5 border-b border-line pb-2">
                {tabs.map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        onClick={() => setTab(key)}
                        className={cn(
                            'rounded-lg px-3 py-1.5 text-[12px] font-medium transition-colors focus-ring',
                            tab === key ? 'bg-brand-50 text-brand-800 dark:bg-brand-500/10 dark:text-brand-100' : 'text-muted hover:bg-canvas hover:text-ink',
                        )}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {tab === 'application' && (
                <div className="mt-4 grid gap-4 lg:grid-cols-2">
                    {Object.entries(groups).map(([key, group]) => (
                        <GroupForm key={key} groupKey={key} group={group} values={values} />
                    ))}
                </div>
            )}

            {tab === 'parameters' && (
                <div className="mt-4">
                    <ParametersPanel parameters={parameters} units={units} valueTypes={valueTypes} categories={parameterCategories} />
                </div>
            )}

            {tab === 'units' && (
                <div className="mt-4">
                    <UnitsPanel units={units} dimensions={unitDimensions} />
                </div>
            )}

            {tab === 'categories' && (
                <div className="mt-4">
                    <CategoriesPanel categories={categories} />
                </div>
            )}
        </AppLayout>
    );
}
