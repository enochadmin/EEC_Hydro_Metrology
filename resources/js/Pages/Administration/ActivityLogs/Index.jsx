import { Card, CardBody, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/Utils/format';
import { Head, router } from '@inertiajs/react';
import { Activity, Filter } from 'lucide-react';
import { useState } from 'react';

const field = 'w-full rounded-lg border border-line bg-surface px-2.5 py-1.5 text-[12px] text-ink focus-ring';

export default function ActivityLogs({ logs, events = [], users = [], filters = {}, summary = {} }) {
    const [form, setForm] = useState({
        event: filters.event ?? '',
        user: filters.user ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });

    const apply = (event) => {
        event.preventDefault();
        router.get('/activity-logs', form, { preserveState: true, preserveScroll: true });
    };

    const clear = () => {
        const empty = { event: '', user: '', from: '', to: '' };
        setForm(empty);
        router.get('/activity-logs', {}, { preserveState: true });
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Administration' }, { label: 'Activity logs' }]}>
            <Head title="Activity logs" />

            <PageHeader
                title="Activity logs"
                description={`${summary.total ?? 0} entries · ${summary.today ?? 0} today · ${summary.actors ?? 0} users recorded`}
            />

            <Card className="mt-6">
                <CardBody>
                    <form onSubmit={apply} className="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div>
                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted" htmlFor="event">Event</label>
                            <select id="event" className={field} value={form.event} onChange={(event) => setForm({ ...form, event: event.target.value })}>
                                <option value="">All events</option>
                                {events.map((event) => (
                                    <option key={event} value={event}>{event}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted" htmlFor="user">User</label>
                            <select id="user" className={field} value={form.user} onChange={(event) => setForm({ ...form, user: event.target.value })}>
                                <option value="">All users</option>
                                {users.map((user) => (
                                    <option key={user.id} value={user.id}>{user.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted" htmlFor="from">From</label>
                            <input id="from" type="date" className={field} value={form.from} onChange={(event) => setForm({ ...form, from: event.target.value })} />
                        </div>

                        <div>
                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted" htmlFor="to">To</label>
                            <input id="to" type="date" className={field} value={form.to} onChange={(event) => setForm({ ...form, to: event.target.value })} />
                        </div>

                        <div className="flex items-end gap-2">
                            <button type="submit" className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-700 px-3 py-2 text-[12px] font-medium text-white transition-colors hover:bg-brand-800 focus-ring dark:bg-brand-600">
                                <Filter className="h-3.5 w-3.5" strokeWidth={2} />
                                Filter
                            </button>
                            <button type="button" onClick={clear} className="rounded-lg border border-line px-3 py-2 text-[12px] font-medium text-ink hover:bg-canvas focus-ring">
                                Clear
                            </button>
                        </div>
                    </form>
                </CardBody>
            </Card>

            <Card className="mt-4">
                <CardHeader icon={Activity} title="Audit trail" description="Append-only. Credentials are never recorded." />

                {logs.data.length === 0 ? (
                    <CardBody>
                        <p className="py-8 text-center text-[12px] text-muted">No entries match these filters.</p>
                    </CardBody>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b border-line text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">
                                    <th className="px-5 py-2.5">When</th>
                                    <th className="px-5 py-2.5">Event</th>
                                    <th className="px-5 py-2.5">User</th>
                                    <th className="px-5 py-2.5">Description</th>
                                    <th className="px-5 py-2.5">Record</th>
                                    <th className="px-5 py-2.5">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((entry) => (
                                    <tr key={entry.id} className="border-b border-line/70 last:border-0 hover:bg-canvas/50">
                                        <td className="whitespace-nowrap px-5 py-2 text-[11.5px] text-muted">{formatDateTime(entry.created_at)}</td>
                                        <td className="px-5 py-2 text-[11.5px] font-medium text-ink">{entry.event}</td>
                                        <td className="px-5 py-2 text-[12px] text-ink">{entry.user ?? 'System'}</td>
                                        <td className="px-5 py-2 text-[11.5px] text-muted">{entry.description ?? '—'}</td>
                                        <td className="px-5 py-2 text-[11px] text-muted">{entry.subject ?? '—'}</td>
                                        <td className="px-5 py-2 text-[11px] text-muted">{entry.ip_address ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="flex items-center justify-between gap-3 border-t border-line px-5 py-3">
                    <p className="text-[11.5px] text-muted">
                        Page {logs.current_page} of {logs.last_page} · {logs.total} entries
                    </p>
                    <div className="flex items-center gap-2">
                        {logs.prev_page_url && (
                            <a href={logs.prev_page_url} className="rounded-lg border border-line px-2.5 py-1.5 text-[11.5px] font-medium text-ink hover:bg-canvas">
                                Previous
                            </a>
                        )}
                        {logs.next_page_url && (
                            <a href={logs.next_page_url} className="rounded-lg border border-line px-2.5 py-1.5 text-[11.5px] font-medium text-ink hover:bg-canvas">
                                Next
                            </a>
                        )}
                    </div>
                </div>
            </Card>
        </AppLayout>
    );
}
