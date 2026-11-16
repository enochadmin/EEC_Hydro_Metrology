import AppLogoMark from '@/Components/branding/AppLogoMark';
import ContourBackdrop from '@/Components/branding/ContourBackdrop';
import ThemeToggle from '@/Components/layout/ThemeToggle';
import WelcomeMap from '@/Components/welcome/WelcomeMap';
import { cn } from '@/Utils/cn';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BarChart3, Database, FileSpreadsheet, Map, ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const stats = [
    { value: '12', label: 'Major basins', detail: 'One national registry' },
    { value: '3', label: 'Registry levels', detail: 'Basin, sub-basin, watershed' },
    { value: '4', label: 'Core parameters', detail: 'Level, discharge, rainfall, quality' },
    { value: '1', label: 'Audited pipeline', detail: 'Upload to approved import' },
];

const modules = [
    {
        title: 'Basin registry',
        description: 'Basins, sub-basins and watersheds with codes, areas, boundaries and source documents.',
        icon: Map,
    },
    {
        title: 'Gauge network',
        description: 'Monitoring stations with coordinates, parameters and coverage across the country.',
        icon: Database,
    },
    {
        title: 'Excel transformation',
        description: 'Upload, map, transform and validate field workbooks before anything is committed.',
        icon: FileSpreadsheet,
    },
    {
        title: 'Reports & analysis',
        description: 'Hydrographs, basin summaries and exportable station reports from approved data.',
        icon: BarChart3,
    },
];

const pipeline = ['Upload workbook', 'Map columns', 'Transform', 'Validate', 'Import'];

const pipelineNotes = ['Original file stored', 'Columns matched to schema', 'Units and dates aligned', 'Errors checked', 'Approved record'];

function Reveal({ children, className, delay = 0 }) {
    const ref = useRef(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const node = ref.current;

        if (!node) {
            return;
        }

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setVisible(true);

            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.12, rootMargin: '0px 0px -48px 0px' },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    return (
        <div ref={ref} style={{ animationDelay: `${delay}ms` }} className={cn(className, visible ? 'welcome-reveal' : 'opacity-0')}>
            {children}
        </div>
    );
}

export default function Welcome() {
    const { application = {} } = usePage().props;
    const shortName = application.short_name ?? 'EEC-BHDMS';
    const fullName = application.full_name ?? 'EEC Basin & Hydrological Data Management System';
    const year = new Date().getFullYear();

    return (
        <div className="min-h-screen bg-canvas">
            <Head title="Welcome" />

            <header className="sticky top-0 z-30 border-b border-line/80 bg-canvas/85 backdrop-blur">
                <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-5 sm:px-6">
                    <Link href={route('welcome')} className="focus-ring flex items-center gap-2.5 rounded-lg">
                        <AppLogoMark className="h-8 w-8" />
                        <span className="min-w-0">
                            <span className="block text-[13.5px] font-semibold leading-tight tracking-tight text-ink">{shortName}</span>
                            <span className="block text-[9.5px] font-medium uppercase tracking-[0.16em] text-muted">Basin &amp; Hydrology</span>
                        </span>
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex">
                        {[
                            ['Overview', '#overview'],
                            ['Platform', '#platform'],
                            ['Pipeline', '#pipeline'],
                        ].map(([label, href]) => (
                            <a
                                key={href}
                                href={href}
                                className="focus-ring rounded-lg px-3 py-1.5 text-[12.5px] font-medium text-muted transition-colors hover:text-ink"
                            >
                                {label}
                            </a>
                        ))}
                    </nav>

                    <div className="flex items-center gap-1.5">
                        <ThemeToggle />
                        <Link
                            href={route('login')}
                            className="focus-ring inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-700 px-3.5 text-[12.5px] font-semibold text-white shadow-sm transition-colors hover:bg-brand-800"
                        >
                            Sign in
                            <ArrowRight className="h-3.5 w-3.5" strokeWidth={2} />
                        </Link>
                    </div>
                </div>
            </header>

            <main>
                <section className="relative overflow-hidden">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-x-0 top-0 h-[520px] bg-[radial-gradient(60%_60%_at_50%_0%,rgb(42_141_166_/_0.14),transparent_70%)] dark:bg-[radial-gradient(60%_60%_at_50%_0%,rgb(69_171_194_/_0.14),transparent_70%)]"
                    />

                    <div className="relative mx-auto w-full max-w-6xl px-5 pb-8 pt-14 sm:px-6 sm:pt-20">
                        <div className="welcome-reveal max-w-3xl">
                            <p className="flex items-center gap-2.5 text-[10px] font-semibold uppercase tracking-[0.22em] text-brand-700 dark:text-brand-300">
                                <span className="h-px w-8 bg-brand-500/60" />
                                Ethiopian basin &amp; hydrological reference
                            </p>

                            <h1 className="mt-5 text-balance text-[34px] font-semibold leading-[1.08] tracking-tight text-ink sm:text-[46px]">
                                Every basin, gauge and reading in one audited record.
                            </h1>

                            <p className="mt-5 max-w-2xl text-[14px] leading-7 text-muted">
                                {fullName} brings basin boundaries, monitoring stations, hydrological observations and reporting into a single
                                controlled workspace for authorised EEC personnel.
                            </p>

                            <div className="mt-7 flex flex-wrap items-center gap-2.5">
                                <Link
                                    href={route('login')}
                                    className="focus-ring inline-flex h-11 items-center gap-2 rounded-lg bg-brand-700 px-5 text-[13px] font-semibold text-white shadow-sm transition-colors hover:bg-brand-800"
                                >
                                    Sign in to the workspace
                                    <ArrowRight className="h-4 w-4" strokeWidth={2} />
                                </Link>
                                <a
                                    href="#overview"
                                    className="focus-ring inline-flex h-11 items-center gap-2 rounded-lg border border-line bg-surface px-4 text-[13px] font-medium text-ink transition-colors hover:border-brand-300 hover:text-brand-700 dark:hover:border-brand-500/40 dark:hover:text-brand-300"
                                >
                                    <Map className="h-4 w-4 text-brand-600 dark:text-brand-400" strokeWidth={1.75} />
                                    Explore the basin map
                                </a>
                            </div>

                            <p className="mt-5 flex items-center gap-1.5 text-[11px] text-muted">
                                <ShieldCheck className="h-3.5 w-3.5 text-brand-600 dark:text-brand-400" strokeWidth={1.75} />
                                Access is limited to authorised personnel.
                            </p>
                        </div>

                        <Reveal className="mt-10 sm:mt-12" delay={120}>
                            <div id="overview" className="scroll-mt-24">
                                <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                                    <div>
                                        <h2 className="text-[15px] font-semibold text-ink">National basin overview</h2>
                                        <p className="mt-0.5 text-[11.5px] text-muted">Twelve major basins · schematic preview of the GIS registry</p>
                                    </div>
                                    <span className="inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-2.5 py-1 text-[10px] font-medium text-muted">
                                        <span className="relative flex h-1.5 w-1.5">
                                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-brand-500 opacity-60" />
                                            <span className="relative inline-flex h-1.5 w-1.5 rounded-full bg-brand-600" />
                                        </span>
                                        Interactive
                                    </span>
                                </div>

                                <WelcomeMap />
                            </div>
                        </Reveal>
                    </div>
                </section>

                <section className="mx-auto w-full max-w-6xl px-5 sm:px-6">
                    <Reveal className="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-line bg-line md:grid-cols-4">
                        {stats.map((stat) => (
                            <div key={stat.label} className="bg-surface px-5 py-4">
                                <p className="tabular text-[22px] font-semibold leading-none text-ink">{stat.value}</p>
                                <p className="mt-1.5 text-[11.5px] font-semibold text-ink">{stat.label}</p>
                                <p className="mt-0.5 text-[10.5px] leading-4 text-muted">{stat.detail}</p>
                            </div>
                        ))}
                    </Reveal>
                </section>

                <section id="platform" className="mx-auto w-full max-w-6xl scroll-mt-24 px-5 py-16 sm:px-6 sm:py-20">
                    <Reveal className="max-w-2xl">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-700 dark:text-brand-300">Platform</p>
                        <h2 className="mt-2.5 text-[26px] font-semibold tracking-tight text-ink sm:text-[30px]">Built for hydrological operations</h2>
                        <p className="mt-3 text-[13.5px] leading-6 text-muted">
                            From the first gauge reading to the approved report, each step is recorded, validated and traceable.
                        </p>
                    </Reveal>

                    <div className="mt-9 grid gap-4 sm:grid-cols-2">
                        {modules.map((module, index) => {
                            const Icon = module.icon;

                            return (
                                <Reveal key={module.title} delay={index * 70}>
                                    <article className="group h-full rounded-xl border border-line bg-surface p-6 shadow-card transition-all duration-300 ease-out-soft hover:-translate-y-1 hover:border-brand-300 hover:shadow-lift dark:hover:border-brand-500/40">
                                        <span className="grid h-10 w-10 place-items-center rounded-lg bg-brand-50 text-brand-700 transition-colors group-hover:bg-brand-700 group-hover:text-white dark:bg-brand-500/10 dark:text-brand-300">
                                            <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                                        </span>
                                        <h3 className="mt-4 text-[15px] font-semibold text-ink">{module.title}</h3>
                                        <p className="mt-1.5 text-[12.5px] leading-6 text-muted">{module.description}</p>
                                    </article>
                                </Reveal>
                            );
                        })}
                    </div>
                </section>

                <section id="pipeline" className="border-y border-line bg-surface/60">
                    <div className="mx-auto w-full max-w-6xl scroll-mt-24 px-5 py-16 sm:px-6 sm:py-20">
                        <Reveal className="max-w-2xl">
                            <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-700 dark:text-brand-300">Pipeline</p>
                            <h2 className="mt-2.5 text-[26px] font-semibold tracking-tight text-ink sm:text-[30px]">
                                Nothing enters the record unverified
                            </h2>
                            <p className="mt-3 text-[13.5px] leading-6 text-muted">
                                Field workbooks pass through a controlled sequence. Every transformation is kept against the original upload.
                            </p>
                        </Reveal>

                        <Reveal className="relative mt-10" delay={100}>
                            <div aria-hidden="true" className="absolute left-3 top-4 hidden h-px w-[calc(100%-1.5rem)] lg:block">
                                <svg className="h-1 w-full text-brand-400/50" preserveAspectRatio="none" viewBox="0 0 100 2">
                                    <line
                                        x1="0"
                                        y1="1"
                                        x2="100"
                                        y2="1"
                                        stroke="currentColor"
                                        strokeWidth="1.5"
                                        strokeDasharray="2 2"
                                        className="welcome-pipeline-line"
                                    />
                                </svg>
                            </div>

                            <ol className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:gap-3">
                                {pipeline.map((step, index) => (
                                    <li key={step} className="relative rounded-lg border border-line bg-surface p-4 shadow-card">
                                        <span className="tabular inline-grid h-7 w-7 place-items-center rounded-full bg-brand-700 text-[11px] font-semibold text-white">
                                            {index + 1}
                                        </span>
                                        <p className="mt-2.5 text-[12.5px] font-semibold text-ink">{step}</p>
                                        <p className="mt-0.5 text-[10.5px] leading-4 text-muted">{pipelineNotes[index]}</p>
                                    </li>
                                ))}
                            </ol>
                        </Reveal>
                    </div>
                </section>

                <section className="mx-auto w-full max-w-6xl px-5 py-16 sm:px-6 sm:py-20">
                    <Reveal className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-950 via-brand-900 to-[#0a2530] px-6 py-10 sm:px-10">
                        <ContourBackdrop className="text-brand-300/15" />
                        <div className="relative z-10 flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
                            <div className="max-w-xl">
                                <h2 className="text-[24px] font-semibold tracking-tight text-white sm:text-[28px]">
                                    Open the hydrological workspace
                                </h2>
                                <p className="mt-2 text-[13px] leading-6 text-brand-100/70">
                                    Basin registry, gauge network, workbook importer and reports — in one controlled environment.
                                </p>
                            </div>
                            <Link
                                href={route('login')}
                                className="focus-ring inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-white px-5 text-[13px] font-semibold text-brand-900 shadow-sm transition-colors hover:bg-brand-50"
                            >
                                Sign in
                                <ArrowRight className="h-4 w-4" strokeWidth={2} />
                            </Link>
                        </div>
                    </Reveal>
                </section>
            </main>

            <footer className="border-t border-line">
                <div className="mx-auto flex w-full max-w-6xl flex-col gap-2 px-5 py-6 text-[11px] text-muted sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <span>
                        © {year} {fullName}
                    </span>
                    <span className="tabular">
                        {application.phase ?? 'Phase 1 — Foundation'}
                        {application.version ? ` · v${application.version}` : ''}
                    </span>
                </div>
            </footer>
        </div>
    );
}
