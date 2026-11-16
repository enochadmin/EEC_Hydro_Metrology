import Popover from '@/Components/Popover';
import { useTheme } from '@/Hooks/useTheme';
import { cn } from '@/Utils/cn';
import { Check, Monitor, Moon, Sun } from 'lucide-react';

const options = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

export default function ThemeToggle() {
    const { theme, setTheme, isDark } = useTheme();
    const TriggerIcon = isDark ? Moon : Sun;

    return (
        <Popover
            label="Theme"
            width="w-44"
            trigger={({ open, toggle, panelId }) => (
                <button
                    type="button"
                    onClick={toggle}
                    aria-expanded={open}
                    aria-controls={panelId}
                    aria-label="Change theme"
                    title="Change theme"
                    className="grid h-9 w-9 place-items-center rounded-lg text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring"
                >
                    <TriggerIcon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                </button>
            )}
        >
            {({ close }) => (
                <div className="p-1.5">
                    {options.map((option) => {
                        const Icon = option.icon;
                        const active = theme === option.value;

                        return (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => {
                                    setTheme(option.value);
                                    close();
                                }}
                                className={cn(
                                    'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium transition-colors',
                                    active
                                        ? 'bg-brand-50 text-brand-800 dark:bg-brand-500/10 dark:text-brand-200'
                                        : 'text-muted hover:bg-canvas hover:text-ink',
                                )}
                            >
                                <Icon className="h-4 w-4" strokeWidth={1.75} />
                                {option.label}
                                {active && <Check className="ml-auto h-3.5 w-3.5" strokeWidth={2.25} />}
                            </button>
                        );
                    })}
                </div>
            )}
        </Popover>
    );
}
