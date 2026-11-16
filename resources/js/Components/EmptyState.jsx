import { cn } from '@/Utils/cn';

export default function EmptyState({ icon: Icon, title, description, action, className }) {
    return (
        <div className={cn('flex flex-col items-center justify-center px-6 py-10 text-center', className)}>
            {Icon && (
                <span className="grid h-11 w-11 place-items-center rounded-full border border-line bg-canvas text-muted">
                    <Icon className="h-5 w-5" strokeWidth={1.75} />
                </span>
            )}
            <p className="mt-3 text-sm font-medium text-ink">{title}</p>
            {description && <p className="mt-1 max-w-sm text-xs leading-relaxed text-muted">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
