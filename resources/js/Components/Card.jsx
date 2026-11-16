import { cn } from '@/Utils/cn';

export function Card({ className, children, ...props }) {
    return (
        <div className={cn('rounded-xl border border-line bg-surface shadow-card', className)} {...props}>
            {children}
        </div>
    );
}

export function CardHeader({ title, description, action, icon: Icon, className }) {
    return (
        <div className={cn('flex items-start justify-between gap-4 border-b border-line px-5 py-4', className)}>
            <div className="flex min-w-0 items-start gap-3">
                {Icon && (
                    <span className="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                        <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                    </span>
                )}
                <div className="min-w-0">
                    <h2 className="text-sm font-semibold tracking-tight text-ink">{title}</h2>
                    {description && <p className="mt-0.5 text-xs leading-relaxed text-muted">{description}</p>}
                </div>
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

export function CardBody({ className, children }) {
    return <div className={cn('px-5 py-4', className)}>{children}</div>;
}

export function CardFooter({ className, children }) {
    return <div className={cn('border-t border-line px-5 py-3', className)}>{children}</div>;
}
