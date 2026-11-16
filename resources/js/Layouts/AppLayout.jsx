import AppSidebar from '@/Components/layout/AppSidebar';
import FlashMessage from '@/Components/FlashMessage';
import AppTopbar from '@/Components/layout/AppTopbar';
import MobileSidebar from '@/Components/layout/MobileSidebar';
import { cn } from '@/Utils/cn';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const SIDEBAR_STORAGE_KEY = 'eec-sidebar';

/**
 * Authenticated application shell: persistent sidebar, sticky topbar and the
 * scrollable content area used by every module.
 */
export default function AppLayout({ children, breadcrumbs = [] }) {
    const { url, props } = usePage();
    const application = props.application ?? {};
    const flash = props.flash ?? {};

    const [collapsed, setCollapsed] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'collapsed';
    });
    const [mobileOpen, setMobileOpen] = useState(false);

    // Close the mobile drawer whenever Inertia navigates.
    useEffect(() => {
        setMobileOpen(false);
    }, [url]);

    useEffect(() => {
        window.localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? 'collapsed' : 'expanded');
    }, [collapsed]);

    // Prevent the page behind the drawer from scrolling.
    useEffect(() => {
        document.body.style.overflow = mobileOpen ? 'hidden' : '';

        return () => {
            document.body.style.overflow = '';
        };
    }, [mobileOpen]);

    return (
        <div className="min-h-screen bg-canvas">
            <AppSidebar collapsed={collapsed} onToggleCollapsed={() => setCollapsed((value) => !value)} />
            <MobileSidebar open={mobileOpen} onClose={() => setMobileOpen(false)} />

            <div
                className={cn(
                    'flex min-h-screen flex-col transition-[padding] duration-200 ease-out-soft',
                    collapsed ? 'lg:pl-sidebar-collapsed' : 'lg:pl-sidebar',
                )}
            >
                <AppTopbar breadcrumbs={breadcrumbs} onOpenMobileNav={() => setMobileOpen(true)} />

                <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    <FlashMessage flash={flash} />
                    {children}
                </main>

                <footer className="border-t border-line">
                    <div className="mx-auto flex w-full max-w-[1440px] flex-col gap-1 px-4 py-4 text-[11px] text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <span>{application.full_name ?? 'EEC Basin & Hydrological Data Management System'}</span>
                        <span className="tabular">
                            {application.phase ?? 'Phase 1 — Foundation'} · v{application.version ?? '0.1.0'}
                        </span>
                    </div>
                </footer>
            </div>
        </div>
    );
}
