import { Card, CardHeader } from '@/Components/Card';
import PageHeader from '@/Components/PageHeader';
import AppLayout from '@/Layouts/AppLayout';
import UserForm from '@/Pages/Users/Partials/UserForm';
import { Head } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';

export default function Create({ roles = [] }) {
    return (
        <AppLayout breadcrumbs={[{ label: 'Administration' }, { label: 'Users', href: '/users' }, { label: 'New user' }]}>
            <Head title="New user" />

            <PageHeader
                eyebrow="Administration"
                title="New user"
                description="Create an account and assign the roles that define what it can do."
            />

            <Card className="mt-6 max-w-3xl overflow-hidden">
                <CardHeader
                    icon={UserPlus}
                    title="Account details"
                    description="Credentials are hashed with the application's default hasher. Roles can be changed later."
                />
                <UserForm action="/users" roles={roles} requirePassword submitLabel="Create user" />
            </Card>
        </AppLayout>
    );
}
