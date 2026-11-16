import { cn } from '@/Utils/cn';

export default function PageHeader({ eyebrow, title, description, actions, className }) {
    return (
        <div className={cn('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between', className)}>
            <div className="min-w-0">
                {eyebrow && (
                    <p className="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-700 dark:text-brand-300">{eyebrow}</p>
                )}
                <h1 className="mt-1 text-2xl font-semibold tracking-tight text-ink sm:text-[28px] sm:leading-9">{title}</h1>
                {description && <p className="mt-1.5 max-w-2xl text-sm leading-relaxed text-muted">{description}</p>}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
