import { cn } from '@/Utils/cn';

export const inputClasses =
    'block w-full rounded-lg border-line bg-surface text-sm text-ink shadow-sm placeholder:text-muted/70 focus:border-brand-500 focus:ring-brand-500';

export const selectClasses =
    'block w-full rounded-lg border-line bg-surface py-2 pl-3 pr-8 text-sm text-ink shadow-sm focus:border-brand-500 focus:ring-brand-500';

export function FieldError({ message }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{message}</p>;
}

export function Field({ label, htmlFor, hint, error, required = false, children, className }) {
    return (
        <div className={cn('min-w-0', className)}>
            <label htmlFor={htmlFor} className="mb-1.5 block text-[13px] font-medium text-ink">
                {label}
                {required && <span className="ml-0.5 text-rose-500">*</span>}
            </label>
            {children}
            {hint && !error && <p className="mt-1.5 text-[11.5px] leading-relaxed text-muted">{hint}</p>}
            <FieldError message={error} />
        </div>
    );
}

export default Field;
