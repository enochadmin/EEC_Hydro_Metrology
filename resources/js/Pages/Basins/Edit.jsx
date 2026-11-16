import Badge from '@/Components/Badge';
import { Card, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import BasinForm from '@/Pages/Basins/Partials/BasinForm';
import { boundaryStatusMeta } from '@/Utils/basins';
import { Head } from '@inertiajs/react';
import { Waves } from 'lucide-react';

export default function Edit({ basin, dataSources = [], statuses = [] }) {
    const boundary = boundaryStatusMeta(basin.boundary_status);

    return (
        <AppLayout
            breadcrumbs={[
                { label: 'Basin registry' },
                { label: 'Basins', href: '/basins' },
                { label: basin.code, href: `/basins/${basin.id}` },
                { label: 'Edit' },
            ]}
        >
            <Head title={`Edit ${basin.code}`} />

            <PageHeader
                eyebrow="Basin registry"
                title={`Edit ${basin.name}`}
                description="Changes are written to the audit trail. Codes stay reserved even when a basin is archived."
                actions={
                    <div className="flex flex-wrap items-center gap-1.5">
                        <Badge tone={boundary.tone} size="sm">{boundary.short}</Badge>
                        <Badge tone="neutral" size="sm">{basin.sub_basins_count} sub-basins</Badge>
                        <Badge tone="neutral" size="sm">{basin.stations_count} stations</Badge>
                    </div>
                }
            />

            <Card className="mt-6 max-w-4xl overflow-hidden">
                <CardHeader
                    icon={Waves}
                    title={`${basin.code} — ${basin.name}`}
                    description="Update registry metadata. Boundary polygons are managed from the basin detail page."
                />
                <BasinForm
                    action={`/basins/${basin.id}`}
                    method="patch"
                    initial={basin}
                    dataSources={dataSources}
                    statuses={statuses}
                    submitLabel="Save changes"
                    cancelHref={`/basins/${basin.id}`}
                />
            </Card>
        </AppLayout>
    );
}
