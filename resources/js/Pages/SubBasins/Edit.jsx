import Badge from '@/Components/Badge';
import { Card, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import SubBasinForm from '@/Pages/SubBasins/Partials/SubBasinForm';
import { Head } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';

export default function Edit({ subBasin, basins = [], dataSources = [], statuses = [] }) {
    return (
        <AppLayout
            breadcrumbs={[
                { label: 'Basin registry' },
                { label: 'Sub-basins', href: '/sub-basins' },
                { label: subBasin.code, href: `/sub-basins/${subBasin.id}` },
                { label: 'Edit' },
            ]}
        >
            <Head title={`Edit ${subBasin.code}`} />

            <PageHeader
                eyebrow="Basin registry"
                title={`Edit ${subBasin.name}`}
                description="Reassign the parent basin or update the recorded area, location and provenance."
                actions={<Badge tone="neutral" size="sm">{subBasin.stations_count} stations</Badge>}
            />

            <Card className="mt-6 max-w-4xl overflow-hidden">
                <CardHeader
                    icon={GitBranch}
                    title={`${subBasin.code} — ${subBasin.name}`}
                    description="Boundary polygons are imported from the sub-basin detail page."
                />
                <SubBasinForm
                    action={`/sub-basins/${subBasin.id}`}
                    method="patch"
                    initial={subBasin}
                    basins={basins}
                    dataSources={dataSources}
                    statuses={statuses}
                    submitLabel="Save changes"
                    cancelHref={`/sub-basins/${subBasin.id}`}
                />
            </Card>
        </AppLayout>
    );
}
