import AppLogo from '@/Components/branding/AppLogo';
import { useAuthorization } from '@/Hooks/useAuthorization';
import { cn } from '@/Utils/cn';
import { isActivePath, navigationGroups, plannedIcon as PlannedIcon } from '@/Utils/navigation';
import { Link, usePage } from '@inertiajs/react';
import { PanelLeftClose, PanelLeftOpen, X } from 'lucide-react';

export default function SidebarContent({ collapsed = false, onToggleCollapsed, onNavigate, onCloseMobile, showCollapseToggle = true }) {
    const { url } = usePage();
    const { can } = useAuthorization();

    return (
        <div className="flex h-full flex-col">
            <div className={cn('flex h-16 shrink-0 items-center border-b border-line', collapsed ? 'justify-center px-2' : 'px-4')}>
                <AppLogo collapsed={collapsed} />

                {onCloseMobile && (
                    <button
                        type="button"
                        onClick={onCloseMobile}
                        aria-label="Close navigation"
                        className="ml-auto grid h-8 w-8 place-items-center rounded-lg text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring"
                    >
                        <X className="h-4 w-4" />
                    </button>
                )}
            </div>

            <nav aria-label="Main navigation" className="eec-scrollbar flex-1 overflow-y-auto px-3 py-4">
                <div className="space-y-5">
                    {navigationGroups.map((group) => {
                        /**
                         * A group only appears when the signed-in user holds the
                         * permission for at least one of its items.
                         */
                        const items = group.items.filter((item) => can(item.permission));

                        if (items.length === 0) {
                            return null;
                        }

                        return (
                            <div key={group.label}>
                                {collapsed ? (
                                    <div className="mx-2 mb-2 h-px bg-line" />
                                ) : (
                                    <p className="mb-1.5 px-2.5 text-[10px] font-semibold uppercase tracking-[0.16em] text-muted/80">
                                        {group.label}
                                    </p>
                                )}

                                <ul className="space-y-0.5">
                                    {items.map((item) => {
                                        const Icon = item.icon;
                                        const active = item.available && isActivePath(url, item.href);

                                        return (
                                            <li key={item.key}>
                                                {item.available ? (
                                                    <Link
                                                        href={item.href}
                                                        onClick={onNavigate}
                                                        title={collapsed ? item.label : undefined}
                                                        aria-current={active ? 'page' : undefined}
                                                        className={cn(
                                                            'group relative flex items-center gap-3 rounded-lg px-2.5 py-2 text-[13px] font-medium transition-colors focus-ring',
                                                            active
                                                                ? 'bg-brand-50 text-brand-800 dark:bg-brand-500/10 dark:text-brand-100'
                                                                : 'text-muted hover:bg-canvas hover:text-ink',
                                                            collapsed && 'justify-center px-0',
                                                        )}
                                                    >
                                                        {active && !collapsed && (
                                                            <span className="absolute inset-y-1.5 left-0 w-[3px] rounded-full bg-brand-600" />
                                                        )}
                                                        <Icon
                                                            className={cn(
                                                                'h-[18px] w-[18px] shrink-0',
                                                                active ? 'text-brand-700 dark:text-brand-300' : 'text-muted/80 group-hover:text-ink',
                                                            )}
                                                            strokeWidth={1.75}
                                                        />
                                                        {!collapsed && <span className="truncate">{item.label}</span>}
                                                    </Link>
                                                ) : (
                                                    <div
                                                        title={`Available in Phase ${item.phase}`}
                                                        aria-disabled="true"
                                                        className={cn(
                                                            'flex cursor-not-allowed items-center gap-3 rounded-lg px-2.5 py-2 text-[13px] font-medium text-muted',
                                                            collapsed && 'justify-center px-0',
                                                        )}
                                                    >
                                                        <Icon className="h-[18px] w-[18px] shrink-0 text-muted/60" strokeWidth={1.75} />
                                                        {!collapsed && (
                                                            <>
                                                                <span className="truncate">{item.label}</span>
                                                                <PlannedIcon className="ml-auto h-3.5 w-3.5 shrink-0 text-muted/70" />
                                                            </>
                                                        )}
                                                    </div>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ul>
                            </div>
                        );
                    })}
                </div>
            </nav>

            <div className="shrink-0 border-t border-line p-3">

                {showCollapseToggle && (
                    <button
                        type="button"
                        onClick={onToggleCollapsed}
                        title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        className={cn(
                            'mt-2 flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-[12px] font-medium text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring',
                            collapsed && 'justify-center px-0',
                        )}
                    >
                        {collapsed ? (
                            <PanelLeftOpen className="h-4 w-4" strokeWidth={1.75} />
                        ) : (
                            <>
                                <PanelLeftClose className="h-4 w-4" strokeWidth={1.75} />
                                <span>Collapse</span>
                            </>
                        )}
                    </button>
                )}
            </div>
        </div>
    );
}