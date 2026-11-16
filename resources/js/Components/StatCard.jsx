import { Card } from '@/Components/Card';
import { cn } from '@/Utils/cn';

/**
 * Headline metric tile.
 *
 * When `value` is null the tile renders an explicit unavailable state instead
 * of a fabricated number, which keeps the dashboard honest until the owning
 * module ships.
 */
export default function StatCard({ label, value, hint, icon: Icon, phase, className }) {
    const hasValue = value !== null && value !== undefined;

    return (
        <Card className={cn('p-5', className)}>
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-2.5">
                    {Icon && (
                        <span className="grid h-9 w-9 place-items-center rounded-lg bg-canvas text-brand-700 dark:text-brand-300">
                            <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                        </span>
                    )}
                    <p className="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted">{label}</p>
                </div>
                {phase && (
                    <span className="shrink-0 whitespace-nowrap rounded-full border border-line px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted">
                        Phase {phase}
                    </span>
                )}
            </div>

            <p
                className={cn(
                    'mt-4 tabular tracking-tight',
                    hasValue ? 'text-[30px] font-semibold leading-none text-ink' : 'text-[13px] font-medium leading-[30px] text-muted',
                )}
            >
                {hasValue ? value.toLocaleString() : 'Not yet available'}
            </p>

            {hint && <p className="mt-2 text-xs leading-relaxed text-muted">{hint}</p>}
        </Card>
    );
}



