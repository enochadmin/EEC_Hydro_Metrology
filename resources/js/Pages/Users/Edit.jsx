import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardFooter, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import UserForm from '@/Pages/Users/Partials/UserForm';
import { Head, router } from '@inertiajs/react';
import { CalendarClock, ShieldAlert, UserRound } from 'lucide-react';
import { useState } from 'react';

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Edit({ user, roles = [], canDeactivate = false, isSelf = false }) {
    const [processing, setProcessing] = useState(false);

    const toggleStatus = () => {
        router.patch(
            `/users/${user.id}/status`,
            { is_active: !user.is_active },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Administration' }, { label: 'Users', href: '/users' }, { label: user.name }]}>
            <Head title={`Edit ${user.name}`} />

            <PageHeader
                eyebrow="Administration"
                title={user.name}
                description={user.email}
                actions={
                    <Badge tone={user.is_active ? 'success' : 'danger'}>{user.is_active ? 'Active' : 'Deactivated'}</Badge>
                }
            />

            <div className="mt-6 grid max-w-5xl gap-4 lg:grid-cols-[minmax(0,1fr)_300px]">
                <Card className="overflow-hidden">
                    <CardHeader
                        icon={UserRound}
                        title="Account details"
                        description="Leave the password fields empty to keep the current password."
                    />
                    <UserForm
                        action={`/users/${user.id}`}
                        method="patch"
                        initial={user}
                        roles={roles}
                        submitLabel="Save changes"
                    />
                </Card>

                <div className="space-y-4">
                    <Card>
                        <CardHeader icon={ShieldAlert} title="Access status" description="Deactivation revokes access immediately." />
                        <CardBody className="space-y-3">
                            <p className="text-[12.5px] leading-relaxed text-muted">
                                {user.is_active
                                    ? 'The account can sign in and use every permission granted by its roles.'
                                    : 'The account cannot sign in. Existing sessions are ended on their next request.'}
                            </p>

                            {isSelf ? (
                                <p className="rounded-lg bg-canvas px-3 py-2 text-[11.5px] text-muted">
                                    You cannot change the status of your own account.
                                </p>
                            ) : (
                                <Button
                                    variant={user.is_active ? 'danger' : 'secondary'}
                                    size="sm"
                                    onClick={toggleStatus}
                                    disabled={!canDeactivate || processing}
                                >
                                    {user.is_active ? 'Deactivate account' : 'Reactivate account'}
                                </Button>
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader icon={CalendarClock} title="Account history" />
                        <CardBody className="space-y-2.5 text-[12.5px]">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted">Created</span>
                                <span className="text-right font-medium text-ink">{formatDate(user.created_at)}</span>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-muted">Last sign-in</span>
                                <span className="text-right font-medium text-ink">{formatDate(user.last_login_at)}</span>
                            </div>
                            {user.deactivated_at && (
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted">Deactivated</span>
                                    <span className="text-right font-medium text-ink">{formatDate(user.deactivated_at)}</span>
                                </div>
                            )}
                        </CardBody>
                        <CardFooter>
                            <p className="text-[11px] leading-relaxed text-muted">
                                Account changes are validated on the server and re-checked against your permissions.
                            </p>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
