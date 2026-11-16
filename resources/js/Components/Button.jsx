import { cn } from '@/Utils/cn';
import { Link } from '@inertiajs/react';

const variants = {
    primary: 'bg-brand-700 text-white shadow-sm hover:bg-brand-800 focus-visible:ring-brand-600/50',
    secondary: 'border border-line bg-surface text-ink shadow-sm hover:bg-canvas focus-visible:ring-brand-500/40',
    subtle: 'bg-brand-50 text-brand-700 hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-200 dark:hover:bg-brand-500/20',
    ghost: 'text-muted hover:bg-canvas hover:text-ink focus-visible:ring-brand-500/40',
    danger: 'bg-rose-600 text-white shadow-sm hover:bg-rose-700 focus-visible:ring-rose-500/50',
    'danger-ghost': 'text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10',
};

const sizes = {
    sm: 'h-9 px-3 text-[13px]',
    md: 'h-10 px-4 text-sm',
    lg: 'h-11 px-5 text-sm',
    icon: 'h-9 w-9',
};

/**
 * Shared button. Renders an Inertia link when `href` is provided, otherwise a
 * native button, so navigation and actions look identical everywhere.
 */
export default function Button({ href, variant = 'primary', size = 'md', className, children, type = 'button', ...props }) {
    const classes = cn(
        'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
        'disabled:pointer-events-none disabled:opacity-60',
        variants[variant],
        sizes[size],
        className,
    );

    if (href) {
        return (
            <Link href={href} className={classes} {...props}>
                {children}
            </Link>
        );
    }

    return (
        <button type={type} className={classes} {...props}>
            {children}
        </button>
    );
}
