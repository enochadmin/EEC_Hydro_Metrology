import { Card, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import SubBasinForm from '@/Pages/SubBasins/Partials/SubBasinForm';
import { Head } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';

export default function Create({ basins = [], dataSources = [], statuses = [], defaultBasinId = null }) {
    return (
        <AppLayout breadcrumbs={[{ label: 'Basin registry' }, { label: 'Sub-basins', href: '/sub-basins' }, { label: 'New sub-basin' }]}>
            <Head title="New sub-basin" />

            <PageHeader
                eyebrow="Basin registry"
                title="Register a sub-basin"
                description="Attach a catchment to its parent basin. Area and location may stay empty until an authoritative source confirms them."
            />

            <Card className="mt-6 max-w-4xl overflow-hidden">
                <CardHeader
                    icon={GitBranch}
                    title="Sub-basin details"
                    description="The parent basin controls which stations and observations roll up into the basin view."
                />
                <SubBasinForm
                    action="/sub-basins"
                    basins={basins}
                    dataSources={dataSources}
                    statuses={statuses}
                    initial={{ basin_id: defaultBasinId ?? '', data_status: 'pending', is_active: true }}
                    submitLabel="Create sub-basin"
                />
            </Card>
        </AppLayout>
    );
}
