import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import StationForm from '@/Pages/Stations/Partials/StationForm';
import { Head } from '@inertiajs/react';

export default function Create({ categories = [], basins = [], subBasins = [], parameters = [], dataSources = [], defaultBasinId = null }) {
    return (
        <AppLayout breadcrumbs={[{ label: 'Monitoring stations', href: '/stations' }, { label: 'Register station' }]}>
            <Head title="Register monitoring station" />
            <PageHeader title="Register station" description="Add a monitoring location to the basin network." />
            <div className="mt-5">
                <StationForm
                    action="/stations"
                    station={{ basin_id: defaultBasinId }}
                    categories={categories}
                    basins={basins}
                    subBasins={subBasins}
                    parameters={parameters}
                    dataSources={dataSources}
                    cancelHref="/stations"
                    submitLabel="Register station"
                />
            </div>
        </AppLayout>
    );
}
