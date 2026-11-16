import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, formatDateTime } from '@/Utils/format';
import { Head, Link, useForm } from '@inertiajs/react';
import { Activity, KeyRound, ShieldCheck, UserRound } from 'lucide-react';

const field = 'w-full rounded-lg border border-line bg-surface px-2.5 py-1.5 text-[12px] text-ink focus-ring';

export default function Show({ account, activity = [], summary, canManage = {} }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(`/users/${account.id}/password`, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Administration' }, { label: 'Users', href: '/users' }, { label: account.name }]}>
            <Head title={account.name} />

            <PageHeader
                title={account.name}
                description={account.email}
                actions={
                    <div className="flex items-center gap-2">
                        <Badge size="sm" tone={account.is_active ? 'success' : 'neutral'}>
                            {account.is_active ? 'Active' : 'Deactivated'}
                        </Badge>
                        {canManage.edit && <Button href={`/users/${account.id}/edit`} variant="secondary" size="sm">Edit account</Button>}
                    </div>
                }
            />

            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader icon={UserRound} title="Account" />
                    <CardBody>
                        <dl className="grid gap-x-6 sm:grid-cols-2">
                            {[
                                ['Name', account.name],
                                ['Email', account.email],
                                ['Roles', account.roles.length ? account.roles.join(', ') : 'No role assigned'],
                                ['Created', formatDate(account.created_at)],
                                ['Last sign-in', account.last_login_at ? formatDateTime(account.last_login_at) : 'Never'],
                                ['Deactivated', account.deactivated_at ? formatDateTime(account.deactivated_at) : '—'],
                            ].map(([label, value]) => (
                                <div key={label} className="border-b border-line/60 py-2 last:border-0">
                                    <dt className="text-[10.5px] font-medium uppercase tracking-[0.1em] text-muted">{label}</dt>
                                    <dd className="mt-0.5 text-[12.5px] text-ink">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader icon={KeyRound} title="Reset password" />
                    <CardBody>
                        {canManage.reset_password ? (
                            <form onSubmit={submit} className="space-y-3">
                                <div>
                                    <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted" htmlFor="password">
                                        New password
                                    </label>
                                    <input
                                        id="password"
                                        type="password"
                                        autoComplete="new-password"
                                        className={field}
                                        value={data.password}
                                        onChange={(event) => setData('password', event.target.value)}
                                    />
                                    {errors.password && <p className="mt-1 text-[11px] text-rose-600">{errors.password}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted" htmlFor="password_confirmation">
                                        Confirm password
                                    </label>
                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        className={field}
                                        value={data.password_confirmation}
                                        onChange={(event) => setData('password_confirmation', event.target.value)}
                                    />
                                </div>
                                <Button type="submit" size="sm" disabled={processing}>Set password</Button>
                                <p className="text-[10.5px] leading-relaxed text-muted">
                                    The password is hashed on save and never written to the activity log.
                                </p>
                            </form>
                        ) : (
                            <p className="text-[12px] text-muted">You do not hold the edit users permission.</p>
                        )}
                    </CardBody>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader
                    icon={Activity}
                    title="Account activity"
                    description={`${summary.events} recorded events · last ${summary.last_event_at ? formatDateTime(summary.last_event_at) : '—'}`}
                />

                {activity.length === 0 ? (
                    <CardBody>
                        <p className="py-6 text-center text-[12px] text-muted">No activity has been recorded for this account.</p>
                    </CardBody>
                ) : (
                    <div className="eec-scrollbar max-h-[460px] overflow-auto">
                        <table className="w-full text-left">
                            <thead className="sticky top-0 bg-surface">
                                <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                                    <th className="px-5 py-2.5">When</th>
                                    <th className="px-5 py-2.5">Event</th>
                                    <th className="px-5 py-2.5">Description</th>
                                    <th className="px-5 py-2.5">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                {activity.map((entry) => (
                                    <tr key={entry.id} className="border-b border-line/70 last:border-0">
                                        <td className="whitespace-nowrap px-5 py-2 text-[11.5px] text-muted">{formatDateTime(entry.created_at)}</td>
                                        <td className="px-5 py-2">
                                            <Badge size="sm" tone="neutral">{entry.event}</Badge>
                                        </td>
                                        <td className="px-5 py-2 text-[12px] text-ink">{entry.description ?? '—'}</td>
                                        <td className="px-5 py-2 text-[11.5px] text-muted">{entry.ip_address ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="border-t border-line px-5 py-3">
                    <Link href="/activity-logs" className="inline-flex items-center gap-1.5 text-[11.5px] font-medium text-brand-700 hover:underline dark:text-brand-300">
                        <ShieldCheck className="h-3 w-3" strokeWidth={2} />
                        Open the full activity log
                    </Link>
                </div>
            </Card>
        </AppLayout>
    );
}
