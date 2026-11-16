import SidebarContent from '@/Components/layout/SidebarContent';

/**
 * Off-canvas navigation for tablets and phones.
 *
 * The overlay closes on click and the panel is marked as a modal dialog so
 * screen readers announce the navigation context.
 */
export default function MobileSidebar({ open, onClose }) {
    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigation">
            <div className="absolute inset-0 animate-fade-in bg-slate-950/50 backdrop-blur-sm" onClick={onClose} aria-hidden="true" />

            <div className="absolute inset-y-0 left-0 flex w-[280px] max-w-[85%] animate-slide-in-left flex-col border-r border-line bg-surface shadow-lift">
                <SidebarContent onNavigate={onClose} onCloseMobile={onClose} showCollapseToggle={false} />
            </div>
        </div>
    );
}
