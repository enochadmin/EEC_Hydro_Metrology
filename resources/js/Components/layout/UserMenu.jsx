import Avatar from '@/Components/Avatar';
import Popover from '@/Components/Popover';
import { cn } from '@/Utils/cn';
import { Link } from '@inertiajs/react';
import { ChevronDown, KeyRound, LogOut, Palette, UserRound } from 'lucide-react';

function MenuLink({ href, icon: Icon, children, onClick }) {
    return (
        <Link
            href={href}
            onClick={onClick}
            className="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring"
        >
            <Icon className="h-4 w-4" strokeWidth={1.75} />
            {children}
        </Link>
    );
}

export default function UserMenu({ user }) {
    const primaryRole = user?.roles?.[0] ?? (user ? 'Signed in' : 'Guest');

    return (
        <Popover
            label="Account"
            width="w-64"
            trigger={({ open, toggle, panelId }) => (
                <button
                    type="button"
                    onClick={toggle}
                    aria-expanded={open}
                    aria-controls={panelId}
                    className={cn(
                        'flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 transition-colors hover:bg-canvas focus-ring',
                        open && 'bg-canvas',
                    )}
                >
                    <Avatar name={user?.name} size="sm" />
                    <span className="hidden min-w-0 text-left sm:block">
                        <span className="block max-w-[140px] truncate text-[13px] font-medium leading-tight text-ink">{user?.name}</span>
                        <span className="block max-w-[140px] truncate text-[11px] capitalize leading-tight text-muted">{primaryRole}</span>
                    </span>
                    <ChevronDown className="hidden h-4 w-4 text-muted sm:block" strokeWidth={1.75} />
                </button>
            )}
        >
            {({ close }) => (
                <div>
                    <div className="border-b border-line px-4 py-3">
                        <p className="truncate text-sm font-medium text-ink">{user?.name}</p>
                        <p className="truncate text-xs text-muted">{user?.email}</p>
                    </div>

                    <div className="space-y-0.5 p-1.5">
                        <MenuLink href="/settings/profile" icon={UserRound} onClick={close}>
                            Profile
                        </MenuLink>
                        <MenuLink href="/settings/password" icon={KeyRound} onClick={close}>
                            Password
                        </MenuLink>
                        <MenuLink href="/settings/appearance" icon={Palette} onClick={close}>
                            Appearance
                        </MenuLink>
                    </div>

                    <div className="border-t border-line p-1.5">
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            onClick={close}
                            className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium text-rose-600 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                        >
                            <LogOut className="h-4 w-4" strokeWidth={1.75} />
                            Sign out
                        </Link>
                    </div>
                </div>
            )}
        </Popover>
    );
}
