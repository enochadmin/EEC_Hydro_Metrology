import { cn } from '@/Utils/cn';

const TONES = {
    neutral: 'text-ink',
    success: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    danger: 'text-rose-600 dark:text-rose-400',
};

/**
 * Compact metric strip for coverage and quality figures. Used instead of a chart
 * where the useful information is a single number.
 */
export default function CoverageTiles({ items = [], className }) {
    return (
        <dl className={cn('grid gap-px overflow-hidden rounded-lg border border-line bg-line sm:grid-cols-2 lg:grid-cols-4', className)}>
            {items.map((item) => (
                <div key={item.label} className="bg-surface px-4 py-3">
                    <dt className="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted">{item.label}</dt>
                    <dd className={cn('mt-1 text-[17px] font-semibold leading-none tabular', TONES[item.tone] ?? TONES.neutral)}>
                        {item.value}
                    </dd>
                    {item.hint && <p className="mt-1.5 text-[10.5px] leading-snug text-muted">{item.hint}</p>}
                </div>
            ))}
        </dl>
    );
}
