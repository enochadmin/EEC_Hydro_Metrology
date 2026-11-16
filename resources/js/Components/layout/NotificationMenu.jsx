import Badge from '@/Components/Badge';
import EmptyState from '@/Components/EmptyState';
import Popover from '@/Components/Popover';
import { Bell } from 'lucide-react';

/**
 * Notification entry point. The alerting backend arrives with quality
 * control, so the panel states its current empty status explicitly.
 */
export default function NotificationMenu({ count = 0 }) {
    return (
        <Popover
            label="Notifications"
            width="w-[340px]"
            trigger={({ open, toggle, panelId }) => (
                <button
                    type="button"
                    onClick={toggle}
                    aria-expanded={open}
                    aria-controls={panelId}
                    aria-label={count > 0 ? `Notifications, ${count} unread` : 'Notifications'}
                    title="Notifications"
                    className="relative grid h-9 w-9 place-items-center rounded-lg text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring"
                >
                    <Bell className="h-[18px] w-[18px]" strokeWidth={1.75} />
                    {count > 0 && (
                        <span className="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-surface" />
                    )}
                </button>
            )}
        >
            {() => (
                <div>
                    <div className="flex items-center justify-between border-b border-line px-4 py-3">
                        <p className="text-sm font-semibold text-ink">Notifications</p>
                        <Badge size="sm" tone="neutral">
                            {count} new
                        </Badge>
                    </div>

                    <EmptyState
                        icon={Bell}
                        title="No notifications yet"
                        description="Import results, quality-control failures and station outages will surface here."
                        className="py-8"
                    />

                    <div className="border-t border-line px-4 py-2.5">
                        <p className="text-[11px] leading-relaxed text-muted">
                            Alerting is enabled with the quality-control module in Phase 9.
                        </p>
                    </div>
                </div>
            )}
        </Popover>
    );
}
