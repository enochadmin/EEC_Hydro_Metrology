import NotificationMenu from '@/Components/layout/NotificationMenu';
import ThemeToggle from '@/Components/layout/ThemeToggle';
import UserMenu from '@/Components/layout/UserMenu';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, Home, Menu } from 'lucide-react';

export default function AppTopbar({ breadcrumbs = [], onOpenMobileNav }) {
    const { auth } = usePage().props;
    const user = auth?.user;

    return (
        <header className="sticky top-0 z-30 border-b border-line bg-surface/85 backdrop-blur supports-[backdrop-filter]:bg-surface/70">
            <div className="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                <button
                    type="button"
                    onClick={onOpenMobileNav}
                    aria-label="Open navigation"
                    className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring lg:hidden"
                >
                    <Menu className="h-5 w-5" strokeWidth={1.75} />
                </button>

                <nav aria-label="Breadcrumb" className="hidden min-w-0 items-center gap-1.5 text-[13px] sm:flex">
                    <Link href="/dashboard" aria-label="Dashboard home" className="text-muted transition-colors hover:text-ink">
                        <Home className="h-4 w-4" strokeWidth={1.75} />
                    </Link>

                    {breadcrumbs.map((crumb, index) => {
                        const isLast = index === breadcrumbs.length - 1;

                        return (
                            <span key={`${crumb.label}-${index}`} className="flex min-w-0 items-center gap-1.5">
                                <ChevronRight className="h-3.5 w-3.5 shrink-0 text-muted/60" strokeWidth={2} />
                                {isLast || !crumb.href ? (
                                    <span className="truncate font-medium text-ink">{crumb.label}</span>
                                ) : (
                                    <Link href={crumb.href} className="truncate text-muted transition-colors hover:text-ink">
                                        {crumb.label}
                                    </Link>
                                )}
                            </span>
                        );
                    })}
                </nav>

                <div className="ml-auto flex items-center gap-1 sm:gap-2">

                    <ThemeToggle />
                    <NotificationMenu count={0} />

                    <span className="mx-1 hidden h-6 w-px bg-line sm:block" />

                    <UserMenu user={user} />
                </div>
            </div>
        </header>
    );
}
