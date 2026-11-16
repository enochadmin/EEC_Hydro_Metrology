import Badge from '@/Components/Badge';
import CreateRoleForm from '@/Components/roles/CreateRoleForm';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/Utils/cn';
import { Head } from '@inertiajs/react';
import { KeyRound, LockKeyhole, ShieldCheck, Users } from 'lucide-react';

export default function Index({ roles = [], permissionGroups = {}, totalPermissions = 0 }) {
    const groups = Object.entries(permissionGroups);

    return (
        <AppLayout breadcrumbs={[{ label: 'Administration' }, { label: 'Roles & permissions' }]}>
            <Head title="Roles & permissions" />

            <PageHeader
                eyebrow="Administration"
                title="Roles & permissions"
                description="Each role bundles the permissions its holders receive across every module."
                actions={
                    <Badge tone="brand">
                        {roles.length} roles · {totalPermissions} permissions
                    </Badge>
                }
            />

            <div className="mt-6 flex items-start gap-3 rounded-xl border border-brand-200/70 bg-brand-50/60 px-4 py-3 dark:border-brand-500/25 dark:bg-brand-500/10">
                <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-brand-700 dark:text-brand-300" strokeWidth={1.75} />
                <p className="text-[13px] leading-relaxed text-brand-900 dark:text-brand-100">
                    Super Admin bypasses individual permission checks and always holds every capability. Every other role is limited to the
                    permissions listed here, enforced by route middleware and policies on the server.
                </p>
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {roles.map((role) => (
                    <Card key={role.id} className="flex flex-col">
                        <CardHeader
                            icon={role.is_locked ? LockKeyhole : KeyRound}
                            title={role.name}
                            description={role.description ?? undefined}
                            action={
                                role.is_locked ? (
                                    <Badge size="sm" tone="brand">
                                        Full access
                                    </Badge>
                                ) : undefined
                            }
                        />

                        <CardBody className="flex flex-1 flex-col justify-between gap-4">
                            <div className="flex items-center gap-4 text-[12.5px] text-muted">
                                <span className="inline-flex items-center gap-1.5">
                                    <Users className="h-3.5 w-3.5" strokeWidth={1.75} />
                                    {role.users_count} {role.users_count === 1 ? 'user' : 'users'}
                                </span>
                                <span className="inline-flex items-center gap-1.5">
                                    <ShieldCheck className="h-3.5 w-3.5" strokeWidth={1.75} />
                                    {role.permissions_count} permissions
                                </span>
                            </div>

                            {role.is_locked ? (
                                <p className="rounded-lg bg-canvas px-3 py-2 text-[11.5px] text-muted">
                                    Locked role: its permission set is defined by the application.
                                </p>
                            ) : (
                                <Button href={`/access-control/roles/${role.id}/edit`} variant="secondary" size="sm" className="w-fit">
                                    Edit permissions
                                </Button>
                            )}
                        </CardBody>
                    </Card>
                ))}
            </div>

            <CreateRoleForm />

            <Card className="mt-4">
                <CardHeader
                    icon={KeyRound}
                    title="Permission catalogue"
                    description="The exact permission names checked by the backend."
                />
                <CardBody className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {groups.map(([group, permissions]) => (
                        <div key={group} className="rounded-lg border border-line bg-canvas/40 px-3.5 py-3">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-[12.5px] font-semibold text-ink">{group}</p>
                                <span className={cn('text-[10.5px] font-medium uppercase tracking-wide text-muted')}>{permissions.length}</span>
                            </div>
                            <ul className="mt-2 space-y-1">
                                {permissions.map((permission) => (
                                    <li key={permission} className="text-[11.5px] leading-relaxed text-muted">
                                        {permission}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </CardBody>
            </Card>
        </AppLayout>
    );
}
