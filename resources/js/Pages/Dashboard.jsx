import Badge from '@/Components/Badge';
import BasinMiniMap from '@/Components/gis/BasinMiniMap';
import { Card, CardBody, CardFooter, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import StatCard from '@/Components/StatCard';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/Utils/cn';
import { Head, Link } from '@inertiajs/react';
import { Activity, ArrowRight, Database, FileSpreadsheet, GitBranch, Layers, Map, RadioTower, Waves } from 'lucide-react';

const statIcons = {
    basins: Waves,
    sub_basins: GitBranch,
    stations: RadioTower,
    records: Database,
};

const stateDot = {
    ok: 'bg-emerald-500',
    warning: 'bg-amber-500',
    error: 'bg-rose-500',
};

const importSteps = ['Upload', 'Map columns', 'Validate', 'Import'];

export default function Dashboard({ stats = [], modules = [], systemStatus = { items: [] }, basemap = null }) {
    const checkedAt = systemStatus.checked_at ? new Date(systemStatus.checked_at) : null;

    return (
        <AppLayout breadcrumbs={[{ label: 'Dashboard' }]}>
            <Head title="Dashboard" />

            <PageHeader
                title="Dashboard"
                description="Basin registry, monitoring stations and hydrological records."
            />

            <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat) => (
                    <StatCard key={stat.key} label={stat.label} value={stat.value} icon={statIcons[stat.key]} />
                ))}
            </div>

            <Card className="mt-4 overflow-hidden">
                <CardHeader
                    icon={Map}
                    title="Basin map"
                    description="Mapped polygons and monitoring stations."
                    action={
                        <Link
                            href="/gis"
                            className="inline-flex items-center gap-1.5 rounded-lg border border-line px-2.5 py-1.5 text-[11.5px] font-medium text-ink transition-colors hover:bg-canvas focus-ring"
                        >
                            Open GIS
                        </Link>
                    }
                />
                <CardBody className="p-0">
                    <BasinMiniMap height={280} basemap={basemap ?? undefined} className="rounded-none border-0" />
                </CardBody>
            </Card>

            <div className="mt-4 grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader
                        icon={FileSpreadsheet}
                        title="Data import"
                        description="Turn a workbook into validated observations."
                        action={<Badge size="sm" tone="success">Available</Badge>}
                    />

                    <CardBody>
                        <div className="flex flex-wrap items-center gap-2">
                            {importSteps.map((step, index) => (
                                <div key={step} className="flex items-center gap-2">
                                    <span className="rounded-lg border border-line bg-canvas px-2.5 py-1.5 text-[11.5px] font-medium text-ink">
                                        {index + 1}. {step}
                                    </span>
                                    {index < importSteps.length - 1 && <ArrowRight className="h-3.5 w-3.5 text-muted" strokeWidth={2} />}
                                </div>
                            ))}
                        </div>
                    </CardBody>

                    <CardFooter>
                        <Link href="/excel" className="inline-flex items-center gap-1 text-[12px] font-medium text-brand-700 hover:underline dark:text-brand-300">
                            Open Excel import <ArrowRight className="h-3.5 w-3.5" strokeWidth={2} />
                        </Link>
                    </CardFooter>
                </Card>

                <Card>
                    <CardHeader icon={Activity} title="System health" />

                    <CardBody className="space-y-3">
                        {systemStatus.items.map((item) => (
                            <div key={item.key} className="flex items-center justify-between gap-3">
                                <span className="flex items-center gap-2 text-[12.5px] text-muted">
                                    <span className={cn('h-1.5 w-1.5 shrink-0 rounded-full', stateDot[item.state] ?? stateDot.warning)} />
                                    {item.label}
                                </span>
                                <span className="max-w-[62%] truncate text-right text-[12.5px] font-medium text-ink" title={item.value}>
                                    {item.value}
                                </span>
                            </div>
                        ))}
                    </CardBody>

                    <CardFooter>
                        <p className="text-[11px] text-muted">{checkedAt ? `Checked ${checkedAt.toLocaleString()}` : 'Awaiting first check'}</p>
                    </CardFooter>
                </Card>
            </div>

            <Card id="module-roadmap" className="mt-4">
                <CardHeader icon={Layers} title="Modules" />

                <CardBody className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    {modules.map((module) => (
                        <div key={module.key} className="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2">
                            <div className="min-w-0">
                                <p className="truncate text-[12.5px] font-medium text-ink">{module.name}</p>
                                <p className="text-[10px] text-muted">Phase {module.phase}</p>
                            </div>
                            <Badge size="sm" tone={module.status === 'available' ? 'success' : 'neutral'}>
                                {module.status === 'available' ? 'Available' : 'Planned'}
                            </Badge>
                        </div>
                    ))}
                </CardBody>
            </Card>
        </AppLayout>
    );
}