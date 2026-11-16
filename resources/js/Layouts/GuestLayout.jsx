import AppLogoMark from '@/Components/branding/AppLogoMark';
import ContourBackdrop from '@/Components/branding/ContourBackdrop';
import ThemeToggle from '@/Components/layout/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

/**
 * Authentication shell: one centred card on a quiet topographic background.
 * Every guest screen (sign in, password reset, verification) renders inside it.
 */
export default function GuestLayout({ children, title, description }) {
    const { application = {} } = usePage().props;
    const shortName = application.short_name ?? 'EEC-BHDMS';
    const year = new Date().getFullYear();

    return (
        <div className="relative flex min-h-screen flex-col bg-canvas">
            <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
                <ContourBackdrop className="text-brand-700/[0.07] dark:text-brand-300/[0.08]" />
                <div className="absolute left-1/2 top-0 h-[26rem] w-[52rem] -translate-x-1/2 -translate-y-1/3 rounded-full bg-brand-400/10 blur-3xl dark:bg-brand-500/10" />
            </div>

            <header className="relative z-10 flex h-16 shrink-0 items-center justify-between gap-4 px-5 sm:px-8">
                <Link href={route('welcome')} className="focus-ring flex items-center gap-2.5 rounded-lg">
                    <AppLogoMark className="h-8 w-8" />
                    <span className="text-[13.5px] font-semibold tracking-tight text-ink">{shortName}</span>
                </Link>

                <div className="flex items-center gap-1.5">
                    <Link
                        href={route('welcome')}
                        className="focus-ring hidden rounded-lg px-2.5 py-1.5 text-[12px] font-medium text-muted transition-colors hover:text-ink sm:block"
                    >
                        System overview
                    </Link>
                    <ThemeToggle />
                </div>
            </header>

            <main className="relative z-10 flex flex-1 items-center justify-center px-5 py-8 sm:px-8">
                <div className="w-full max-w-[400px] animate-fade-in">
                    <div className="rounded-2xl border border-line bg-surface p-6 shadow-card sm:p-8">
                        {title && (
                            <div className="mb-6">
                                <h1 className="text-[22px] font-semibold tracking-tight text-ink">{title}</h1>
                                {description && <p className="mt-1.5 text-[12.5px] leading-5 text-muted">{description}</p>}
                            </div>
                        )}

                        {children}
                    </div>

                    <p className="mt-5 flex items-center justify-center gap-1.5 text-[11px] text-muted">
                        <ShieldCheck className="h-3.5 w-3.5 text-brand-600 dark:text-brand-400" strokeWidth={1.75} />
                        Authorised EEC personnel only
                    </p>
                </div>
            </main>

            <footer className="relative z-10 px-5 py-5 sm:px-8">
                <div className="mx-auto flex w-full max-w-[400px] flex-col items-center gap-1 text-[10.5px] text-muted sm:flex-row sm:justify-between">
                    <span>
                        © {year} {shortName}
                    </span>
                    <span className="tabular">{application.version ? `v${application.version}` : '\u00A0'}</span>
                </div>
            </footer>
        </div>
    );
}
