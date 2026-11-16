import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/Utils/cn';
import { Head, Link, useForm } from '@inertiajs/react';
import { Check, LockKeyhole, ShieldCheck } from 'lucide-react';

export default function Edit({ role, permissionGroups = {} }) {
    const groups = Object.entries(permissionGroups);
    const allPermissions = groups.flatMap(([, permissions]) => permissions);

    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        permissions: role.permissions ?? [],
    });

    const togglePermission = (permission) => {
        setData(
            'permissions',
            data.permissions.includes(permission)
                ? data.permissions.filter((value) => value !== permission)
                : [...data.permissions, permission],
        );
    };

    const toggleGroup = (permissions) => {
        const granted = permissions.every((permission) => data.permissions.includes(permission));

        setData(
            'permissions',
            granted
                ? data.permissions.filter((permission) => !permissions.includes(permission))
                : Array.from(new Set([...data.permissions, ...permissions])),
        );
    };

    const submit = (event) => {
        event.preventDefault();

        put(`/access-control/roles/${role.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Administration' }, { label: 'Roles & permissions', href: '/access-control/roles' }, { label: role.name }]}>
            <Head title={`${role.name} permissions`} />

            <PageHeader
                eyebrow="Administration"
                title={role.name}
                description={role.description ?? 'Role permissions'}
                actions={<Badge tone={role.is_locked ? 'brand' : 'neutral'}>{role.permissions.length} of {allPermissions.length} granted</Badge>}
            />

            {role.is_locked && (
                <div className="mt-6 flex items-start gap-3 rounded-xl border border-brand-200/70 bg-brand-50/60 px-4 py-3 dark:border-brand-500/25 dark:bg-brand-500/10">
                    <LockKeyhole className="mt-0.5 h-4 w-4 shrink-0 text-brand-700 dark:text-brand-300" strokeWidth={1.75} />
                    <p className="text-[13px] leading-relaxed text-brand-900 dark:text-brand-100">
                        Super Admin holds every permission through an application-level bypass. This role cannot be edited.
                    </p>
                </div>
            )}

            <form onSubmit={submit} className="mt-4 max-w-4xl">
                <div className="space-y-4">
                    {groups.map(([group, permissions]) => {
                        const granted = permissions.filter((permission) => data.permissions.includes(permission)).length;
                        const allGranted = granted === permissions.length;

                        return (
                            <Card key={group} className="overflow-hidden">
                                <CardHeader
                                    icon={ShieldCheck}
                                    title={group}
                                    description={`${granted} of ${permissions.length} permissions granted`}
                                    action={
                                        !role.is_locked && (
                                            <button
                                                type="button"
                                                onClick={() => toggleGroup(permissions)}
                                                className="rounded-lg px-2.5 py-1.5 text-[12px] font-medium text-brand-700 transition-colors hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-500/10 focus-ring"
                                            >
                                                {allGranted ? 'Clear group' : 'Grant all'}
                                            </button>
                                        )
                                    }
                                />
                                <CardBody className="grid gap-2 sm:grid-cols-2">
                                    {permissions.map((permission) => {
                                        const checked = data.permissions.includes(permission);

                                        return (
                                            <label
                                                key={permission}
                                                className={cn(
                                                    'flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2 text-[12.5px] transition-colors',
                                                    checked
                                                        ? 'border-brand-500 bg-brand-50/50 text-ink dark:bg-brand-500/10'
                                                        : 'border-line text-muted hover:bg-canvas',
                                                    role.is_locked && 'cursor-default',
                                                )}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={checked}
                                                    disabled={role.is_locked}
                                                    onChange={() => togglePermission(permission)}
                                                    className="rounded border-line text-brand-600 focus:ring-brand-500"
                                                />
                                                {permission}
                                                {checked && <Check className="ml-auto h-3.5 w-3.5 text-brand-600 dark:text-brand-300" strokeWidth={2.5} />}
                                            </label>
                                        );
                                    })}
                                </CardBody>
                            </Card>
                        );
                    })}
                </div>

                {errors.permissions && (
                    <p className="mt-3 text-xs text-rose-600 dark:text-rose-400">{errors.permissions}</p>
                )}

                {!role.is_locked && (
                    <div className="sticky bottom-4 z-10 mt-4 flex flex-col gap-3 rounded-xl border border-line bg-surface/95 px-4 py-3 shadow-lift backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                        <span className="text-[12px] text-muted">
                            <span className="font-medium text-ink tabular">{data.permissions.length}</span> of{' '}
                            <span className="tabular">{allPermissions.length}</span> permissions selected
                        </span>

                        <div className="flex items-center justify-end gap-3">
                            {recentlySuccessful && (
                                <span className="text-[12px] font-medium text-emerald-600 dark:text-emerald-400">Saved</span>
                            )}
                            <Link
                                href="/access-control/roles"
                                className="rounded-lg px-3 py-2 text-[13px] font-medium text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring"
                            >
                                Cancel
                            </Link>
                            <Button type="submit" disabled={processing}>
                                Save permissions
                            </Button>
                        </div>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
