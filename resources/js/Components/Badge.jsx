import { cn } from '@/Utils/cn';

const tones = {
    neutral: 'border-line bg-canvas text-muted',
    brand: 'border-brand-200 bg-brand-50 text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200',
    success: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300',
    warning: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300',
    danger: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300',
};

const sizes = {
    sm: 'px-2 py-0.5 text-[10.5px]',
    md: 'px-2.5 py-1 text-[11px]',
};

export default function Badge({ tone = 'neutral', size = 'md', className, children, ...props }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full border font-medium uppercase tracking-wide',
                tones[tone],
                sizes[size],
                className,
            )}
            {...props}
        >
            {children}
        </span>
    );
}
