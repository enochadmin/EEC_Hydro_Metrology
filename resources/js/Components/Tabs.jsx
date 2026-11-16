import { cn } from '@/Utils/cn';

/**
 * Underline tabs used by the basin detail page.
 *
 * Buttons keep real tab semantics so keyboard and screen-reader users get the
 * same navigation as pointer users.
 */
export default function Tabs({ tabs, value, onChange, className }) {
    return (
        <div className={cn('eec-scrollbar overflow-x-auto border-b border-line', className)}>
            <div role="tablist" className="flex min-w-max gap-1">
                {tabs.map((tab) => {
                    const active = tab.key === value;

                    return (
                        <button
                            key={tab.key}
                            type="button"
                            role="tab"
                            aria-selected={active}
                            onClick={() => onChange(tab.key)}
                            className={cn(
                                'relative flex items-center gap-2 whitespace-nowrap px-3.5 py-2.5 text-[13px] font-medium transition-colors focus-ring',
                                active ? 'text-brand-700 dark:text-brand-200' : 'text-muted hover:text-ink',
                            )}
                        >
                            {tab.label}
                            {typeof tab.count === 'number' && (
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 py-0.5 text-[10.5px] tabular',
                                        active
                                            ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-200'
                                            : 'bg-canvas text-muted',
                                    )}
                                >
                                    {tab.count}
                                </span>
                            )}
                            {active && <span className="absolute inset-x-2 -bottom-px h-[2px] rounded-full bg-brand-600" />}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
