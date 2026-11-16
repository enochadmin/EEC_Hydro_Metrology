import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merge conditional class names and resolve Tailwind conflicts so the last
 * utility wins. Used by every shared component.
 */
export function cn(...inputs) {
    return twMerge(clsx(inputs));
}
