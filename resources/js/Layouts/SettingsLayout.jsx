import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/Utils/cn';
import { Link, usePage } from '@inertiajs/react';
import { KeyRound, Palette, UserRound } from 'lucide-react';

const tabs = [
    { label: 'Profile', href: '/settings/profile', icon: UserRound },
    { label: 'Password', href: '/settings/password', icon: KeyRound },
    { label: 'Appearance', href: '/settings/appearance', icon: Palette },
];

export default function SettingsLayout({ title, description, children }) {
    const { url } = usePage();
    const currentPath = url.split('?')[0];

    return (
        <AppLayout breadcrumbs={[{ label: 'Settings' }, { label: title }]}>
            <PageHeader eyebrow="Account" title="Settings" description={description} />

            <div className="mt-6 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                <nav aria-label="Settings sections" className="lg:sticky lg:top-24 lg:self-start">
                    <ul className="flex gap-1 overflow-x-auto rounded-xl border border-line bg-surface p-1.5 shadow-card lg:flex-col lg:overflow-visible">
                        {tabs.map((tab) => {
                            const Icon = tab.icon;
                            const active = currentPath === tab.href;

                            return (
                                <li key={tab.href} className="shrink-0">
                                    <Link
                                        href={tab.href}
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium transition-colors focus-ring',
                                            active
                                                ? 'bg-brand-50 text-brand-800 dark:bg-brand-500/10 dark:text-brand-200'
                                                : 'text-muted hover:bg-canvas hover:text-ink',
                                        )}
                                    >
                                        <Icon
                                            className={cn('h-4 w-4', active ? 'text-brand-700 dark:text-brand-300' : 'text-muted/80')}
                                            strokeWidth={1.75}
                                        />
                                        {tab.label}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <div className="min-w-0 space-y-5">{children}</div>
            </div>
        </AppLayout>
    );
}
