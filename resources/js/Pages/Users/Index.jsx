import Avatar from '@/Components/Avatar';
import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import Can from '@/Components/Can';
import { Card } from '@/Components/Card';
import EmptyState from '@/Components/EmptyState';
import PageHeader from '@/Components/PageHeader';
import Pagination from '@/Components/Pagination';
import { useAuthorization } from '@/Hooks/useAuthorization';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/Utils/cn';
import { Head, router } from '@inertiajs/react';
import { Search, UserPlus, Users as UsersIcon, X } from 'lucide-react';
import { useState } from 'react';

const selectClasses =
    'h-9 rounded-lg border-line bg-surface py-0 pl-3 pr-8 text-[13px] text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500';

function formatDate(value) {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function Index({ users, filters = {}, roles = [], summary = {} }) {
    const { can } = useAuthorization();
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (next = {}) => {
        router.get(
            '/users',
            { ...filters, ...next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event) => {
        event.preventDefault();
        applyFilters({ search });
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Administration' }, { label: 'Users' }]}>
            <Head title="Users" />

            <PageHeader
                title="Users"
                description="Accounts, roles and access status."
                actions={
                    <Can permission="create users">
                        <Button href="/users/create">
                            <UserPlus className="h-4 w-4" strokeWidth={1.75} />
                            New user
                        </Button>
                    </Can>
                }
            />

            <div className="mt-6 grid gap-3 sm:grid-cols-3">
                {[
                    { label: 'Total accounts', value: summary.total ?? 0, tone: 'text-ink' },
                    { label: 'Active', value: summary.active ?? 0, tone: 'text-emerald-600 dark:text-emerald-400' },
                    { label: 'Deactivated', value: summary.inactive ?? 0, tone: 'text-rose-600 dark:text-rose-400' },
                ].map((item) => (
                    <Card key={item.label} className="px-5 py-4">
                        <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">{item.label}</p>
                        <p className={cn('mt-2 text-2xl font-semibold tabular', item.tone)}>{item.value}</p>
                    </Card>
                ))}
            </div>

            <Card className="mt-4 overflow-hidden">
                <div className="flex flex-col gap-3 border-b border-line px-5 py-3.5 lg:flex-row lg:items-center lg:justify-between">
                    <form onSubmit={submitSearch} className="relative w-full lg:max-w-sm">
                        <Search
                            className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"
                            strokeWidth={1.75}
                        />
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search name or email"
                            aria-label="Search users"
                            className="h-9 w-full rounded-lg border-line bg-surface pl-9 pr-3 text-[13px] text-ink shadow-sm placeholder:text-muted/70 focus:border-brand-500 focus:ring-brand-500"
                        />
                    </form>

                    <div className="flex flex-wrap items-center gap-2">
                        <label className="sr-only" htmlFor="role-filter">
                            Filter by role
                        </label>
                        <select
                            id="role-filter"
                            value={filters.role ?? ''}
                            onChange={(event) => applyFilters({ role: event.target.value })}
                            className={selectClasses}
                        >
                            <option value="">All roles</option>
                            {roles.map((role) => (
                                <option key={role} value={role}>
                                    {role}
                                </option>
                            ))}
                        </select>

                        <label className="sr-only" htmlFor="status-filter">
                            Filter by status
                        </label>
                        <select
                            id="status-filter"
                            value={filters.status ?? ''}
                            onChange={(event) => applyFilters({ status: event.target.value })}
                            className={selectClasses}
                        >
                            <option value="">All statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Deactivated</option>
                        </select>

                        {(filters.search || filters.role || filters.status) && (
                            <Button variant="ghost" size="sm" onClick={() => router.get('/users')}>
                                <X className="h-3.5 w-3.5" strokeWidth={2} />
                                Clear
                            </Button>
                        )}
                    </div>
                </div>

                {users.data.length === 0 ? (
                    <EmptyState
                        icon={UsersIcon}
                        title="No accounts match this filter"
                        description="Adjust the search or clear the filters to see every user."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-canvas/60 text-[10.5px] uppercase tracking-[0.12em] text-muted">
                                <tr>
                                    <th scope="col" className="px-5 py-2.5 font-semibold">
                                        User
                                    </th>
                                    <th scope="col" className="px-5 py-2.5 font-semibold">
                                        Roles
                                    </th>
                                    <th scope="col" className="px-5 py-2.5 font-semibold">
                                        Status
                                    </th>
                                    <th scope="col" className="px-5 py-2.5 font-semibold">
                                        Last sign-in
                                    </th>
                                    <th scope="col" className="px-5 py-2.5 text-right font-semibold">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {users.data.map((user) => (
                                    <tr key={user.id} className="transition-colors hover:bg-canvas/50">
                                        <td className="px-5 py-3">
                                            <div className="flex items-center gap-3">
                                                <Avatar name={user.name} size="sm" />
                                                <div className="min-w-0">
                                                    <p className="truncate text-[13px] font-medium text-ink">{user.name}</p>
                                                    <p className="truncate text-xs text-muted">{user.email}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex flex-wrap items-center gap-1">
                                                {user.roles.slice(0, 2).map((role) => (
                                                    <Badge key={role} tone={role === 'Super Admin' ? 'brand' : 'neutral'} size="sm">
                                                        {role}
                                                    </Badge>
                                                ))}
                                                {user.roles.length > 2 && (
                                                    <span className="text-[11px] text-muted">+{user.roles.length - 2}</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-5 py-3">
                                            <span className="inline-flex items-center gap-1.5 text-[12.5px] text-muted">
                                                <span
                                                    className={cn(
                                                        'h-1.5 w-1.5 rounded-full',
                                                        user.is_active ? 'bg-emerald-500' : 'bg-rose-500',
                                                    )}
                                                />
                                                {user.is_active ? 'Active' : 'Deactivated'}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3 text-[12.5px] text-muted">{formatDate(user.last_login_at)}</td>
                                        <td className="px-5 py-3 text-right">
                                            {can('edit users') && (
                                                <Button href={`/users/${user.id}/edit`} variant="secondary" size="sm">
                                                    Edit
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination meta={users} />
            </Card>
        </AppLayout>
    );
}
