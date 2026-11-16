import AppLogoMark from '@/Components/branding/AppLogoMark';
import { cn } from '@/Utils/cn';
import { Link } from '@inertiajs/react';

export default function AppLogo({ collapsed = false, className }) {
    return (
        <Link
            href="/dashboard"
            className={cn('flex min-w-0 items-center gap-2.5 rounded-lg focus-ring', collapsed && 'justify-center', className)}
        >
            <AppLogoMark className="h-9 w-9 shrink-0" />

            {!collapsed && (
                <span className="min-w-0">
                    <span className="block truncate text-[15px] font-semibold leading-tight tracking-tight text-ink">EEC-BHDMS</span>
                    <span className="block truncate text-[10px] font-medium uppercase tracking-[0.14em] text-muted">Basin &amp; Hydrology</span>
                </span>
            )}
        </Link>
    );
}
