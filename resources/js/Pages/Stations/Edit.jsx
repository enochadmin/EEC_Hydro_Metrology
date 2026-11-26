import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import StationForm from '@/Pages/Stations/Partials/StationForm';
import { Head } from '@inertiajs/react';

export default function Edit({ station, categories = [], classes = [], basins = [], subBasins = [], parameters = [], dataSources = [] }) {
    return (
        <AppLayout breadcrumbs={[{ label: 'Monitoring stations', href: '/stations' }, { label: station.code, href: '/stations/' + station.id }, { label: 'Edit' }]}>
            <Head title={'Edit ' + station.name} />
            <PageHeader title="Edit station" description={station.code + ' · ' + station.name} />
            <div className="mt-5">
                <StationForm
                    action={'/stations/' + station.id}
                    method="patch"
                    station={station}
                    categories={categories}
                    classes={classes}
                    basins={basins}
                    subBasins={subBasins}
                    parameters={parameters}
                    dataSources={dataSources}
                    cancelHref={'/stations/' + station.id}
                    submitLabel="Save changes"
                />
            </div>
        </AppLayout>
    );
}
