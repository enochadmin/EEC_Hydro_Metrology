import { cn } from '@/Utils/cn';
import { useId } from 'react';

/**
 * EEC-BHDMS product mark: a gauge droplet over a basin contour line.
 */
export default function AppLogoMark({ className }) {
    const gradientId = `eec-mark-${useId().replace(/:/g, '')}`;

    return (
        <svg viewBox="0 0 40 40" className={cn('h-9 w-9', className)} role="img" aria-label="EEC-BHDMS">
            <defs>
                <linearGradient id={gradientId} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stopColor="#2A8DA6" />
                    <stop offset="100%" stopColor="#1F4F60" />
                </linearGradient>
            </defs>

            <rect width="40" height="40" rx="11" fill={`url(#${gradientId})`} />
            <path
                d="M20 8.5c3.7 4.8 7.2 8.7 7.2 12.9a7.2 7.2 0 1 1-14.4 0c0-4.2 3.5-8.1 7.2-12.9Z"
                fill="#ffffff"
                opacity="0.95"
            />
            <path
                d="M10.5 31.5c3.2-1.7 5.9-1.7 9.1 0s5.9 1.7 9.1 0"
                fill="none"
                stroke="#ffffff"
                strokeWidth="1.6"
                strokeLinecap="round"
                opacity="0.55"
            />
        </svg>
    );
}
