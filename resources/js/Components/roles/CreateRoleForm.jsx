import { Card, CardBody, CardHeader } from '@/Components/Card';
import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

const field = 'w-full rounded-lg border border-line bg-surface px-2.5 py-1.5 text-[12px] text-ink focus-ring';

/**
 * Roles are created with a name and a description; permissions are granted on
 * the role's own page afterwards, where the full catalogue is grouped.
 */
export default function CreateRoleForm() {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset, recentlySuccessful } = useForm({
        name: '',
        description: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post('/access-control/roles', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <Card className="mt-4">
            <CardHeader
                icon={Plus}
                title="Create a role"
                description="Grant its permissions afterwards; a new role starts with none."
                action={
                    <button
                        type="button"
                        onClick={() => setOpen(!open)}
                        className="rounded-lg border border-line px-2.5 py-1.5 text-[11.5px] font-medium text-ink transition-colors hover:bg-canvas focus-ring"
                    >
                        {open ? 'Cancel' : 'New role'}
                    </button>
                }
            />

            {open && (
                <CardBody>
                    <form onSubmit={submit} className="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted" htmlFor="role-name">
                                Role name
                            </label>
                            <input id="role-name" className={field} value={data.name} onChange={(event) => setData('name', event.target.value)} placeholder="Field supervisor" />
                            {errors.name && <p className="mt-1 text-[11px] text-rose-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-muted" htmlFor="role-description">
                                Description
                            </label>
                            <input id="role-description" className={field} value={data.description} onChange={(event) => setData('description', event.target.value)} />
                            {errors.description && <p className="mt-1 text-[11px] text-rose-600">{errors.description}</p>}
                        </div>

                        <div className="flex items-end">
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-lg bg-brand-700 px-3 py-2 text-[12px] font-medium text-white transition-colors hover:bg-brand-800 focus-ring disabled:opacity-60 dark:bg-brand-600"
                            >
                                Create role
                            </button>
                        </div>
                    </form>

                    {errors.permissions && <p className="mt-2 text-[11px] text-rose-600">{errors.permissions}</p>}
                    {recentlySuccessful && <p className="mt-2 text-[11px] text-emerald-600">Role created.</p>}
                </CardBody>
            )}
        </Card>
    );
}
