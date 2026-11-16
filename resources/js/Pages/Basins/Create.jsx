import { Card, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import BasinForm from '@/Pages/Basins/Partials/BasinForm';
import { Head } from '@inertiajs/react';
import { Waves } from 'lucide-react';

export default function Create({ dataSources = [], statuses = [] }) {
    return (
        <AppLayout breadcrumbs={[{ label: 'Basin registry' }, { label: 'Basins', href: '/basins' }, { label: 'New basin' }]}>
            <Head title="New basin" />

            <PageHeader
                eyebrow="Basin registry"
                title="Register a basin"
                description="Add a basin record. Geography can stay pending until a verified source is available; the boundary is imported separately as GeoJSON."
            />

            <Card className="mt-6 max-w-4xl overflow-hidden">
                <CardHeader
                    icon={Waves}
                    title="Basin details"
                    description="Only values taken from official EEC or survey records should be entered here."
                />
                <BasinForm action="/basins" dataSources={dataSources} statuses={statuses} submitLabel="Create basin" />
            </Card>
        </AppLayout>
    );
}
