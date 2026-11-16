import { cn } from '@/Utils/cn';

export function initialsFromName(name = '') {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
}

const sizes = {
    sm: 'h-8 w-8 text-[11px]',
    md: 'h-9 w-9 text-xs',
    lg: 'h-12 w-12 text-sm',
};

export default function Avatar({ name, size = 'md', className }) {
    return (
        <span
            className={cn(
                'grid shrink-0 place-items-center rounded-full bg-brand-700 font-semibold uppercase tracking-wide text-white',
                sizes[size],
                className,
            )}
            aria-hidden="true"
        >
            {initialsFromName(name)}
        </span>
    );
}
