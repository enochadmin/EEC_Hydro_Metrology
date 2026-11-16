import SidebarContent from '@/Components/layout/SidebarContent';
import { cn } from '@/Utils/cn';

/**
 * Desktop navigation rail. Width is mirrored by the `lg:pl-*` offset applied
 * in AppLayout so content never sits underneath it.
 */
export default function AppSidebar({ collapsed, onToggleCollapsed }) {
    return (
        <aside
            className={cn(
                'fixed inset-y-0 left-0 z-40 hidden border-r border-line bg-surface transition-[width] duration-200 ease-out-soft lg:flex lg:flex-col',
                collapsed ? 'w-sidebar-collapsed' : 'w-sidebar',
            )}
        >
            <SidebarContent collapsed={collapsed} onToggleCollapsed={onToggleCollapsed} />
        </aside>
    );
}
