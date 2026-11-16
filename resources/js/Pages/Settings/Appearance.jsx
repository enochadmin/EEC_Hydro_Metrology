import { Card, CardBody, CardHeader } from '@/Components/Card';
import { useTheme } from '@/Hooks/useTheme';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { cn } from '@/Utils/cn';
import { Head } from '@inertiajs/react';
import { Check, Monitor, Moon, Palette, Sun } from 'lucide-react';

const options = [
    {
        value: 'light',
        label: 'Light',
        description: 'High contrast for bright offices and printed reports.',
        icon: Sun,
        preview: 'bg-white',
        bar: 'bg-slate-200',
        accent: 'bg-brand-600',
    },
    {
        value: 'dark',
        label: 'Dark',
        description: 'Reduced glare for long monitoring sessions.',
        icon: Moon,
        preview: 'bg-slate-900',
        bar: 'bg-slate-700',
        accent: 'bg-brand-400',
    },
    {
        value: 'system',
        label: 'System',
        description: 'Follow the operating system preference automatically.',
        icon: Monitor,
        preview: 'bg-gradient-to-r from-white to-slate-900',
        bar: 'bg-slate-400/60',
        accent: 'bg-brand-500',
    },
];

export default function Appearance() {
    const { theme, setTheme } = useTheme();

    return (
        <SettingsLayout title="Appearance" description="Pick the palette used across the application on this browser.">
            <Head title="Appearance settings" />

            <Card>
                <CardHeader icon={Palette} title="Theme" description="Your choice is stored in this browser and applied before the first paint." />

                <CardBody>
                    <div className="grid gap-3 sm:grid-cols-3">
                        {options.map((option) => {
                            const Icon = option.icon;
                            const active = theme === option.value;

                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() => setTheme(option.value)}
                                    aria-pressed={active}
                                    className={cn(
                                        'group rounded-xl border p-3 text-left transition-colors focus-ring',
                                        active
                                            ? 'border-brand-500 bg-brand-50/40 dark:bg-brand-500/10'
                                            : 'border-line hover:border-brand-300 hover:bg-canvas',
                                    )}
                                >
                                    <span className={cn('flex h-20 flex-col justify-between rounded-lg border border-line p-2.5', option.preview)}>
                                        <span className={cn('h-1.5 w-8 rounded-full', option.bar)} />
                                        <span className="space-y-1">
                                            <span className={cn('block h-1.5 w-12 rounded-full', option.accent)} />
                                            <span className={cn('block h-1.5 w-9 rounded-full', option.bar)} />
                                        </span>
                                    </span>

                                    <span className="mt-3 flex items-center justify-between gap-2">
                                        <span className="flex items-center gap-1.5 text-[13px] font-medium text-ink">
                                            <Icon className="h-3.5 w-3.5 text-muted" strokeWidth={1.75} />
                                            {option.label}
                                        </span>
                                        {active && <Check className="h-4 w-4 text-brand-700 dark:text-brand-300" strokeWidth={2.25} />}
                                    </span>

                                    <span className="mt-1 block text-[11.5px] leading-relaxed text-muted">{option.description}</span>
                                </button>
                            );
                        })}
                    </div>
                </CardBody>
            </Card>
        </SettingsLayout>
    );
}
