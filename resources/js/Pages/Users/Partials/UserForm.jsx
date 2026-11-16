import Button from '@/Components/Button';
import { useAuthorization } from '@/Hooks/useAuthorization';
import { cn } from '@/Utils/cn';
import { Link, useForm } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';

const inputClasses =
    'block w-full rounded-lg border-line bg-surface text-sm text-ink shadow-sm placeholder:text-muted/70 focus:border-brand-500 focus:ring-brand-500';

function FieldError({ message }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{message}</p>;
}

export default function UserForm({ action, method = 'post', initial = {}, roles = [], requirePassword = false, submitLabel = 'Save' }) {
    const { isSuperAdmin } = useAuthorization();

    const { data, setData, post, patch, processing, errors } = useForm({
        name: initial.name ?? '',
        email: initial.email ?? '',
        password: '',
        password_confirmation: '',
        roles: initial.roles ?? [],
    });

    const submit = (event) => {
        event.preventDefault();

        const options = { preserveScroll: true };

        if (method === 'patch') {
            patch(action, options);
        } else {
            post(action, options);
        }
    };

    const toggleRole = (role) => {
        setData(
            'roles',
            data.roles.includes(role) ? data.roles.filter((value) => value !== role) : [...data.roles, role],
        );
    };

    return (
        <form onSubmit={submit}>
            <div className="space-y-6 px-5 py-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label htmlFor="name" className="mb-1.5 block text-[13px] font-medium text-ink">
                            Full name
                        </label>
                        <input
                            id="name"
                            type="text"
                            autoComplete="name"
                            required
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            className={inputClasses}
                        />
                        <FieldError message={errors.name} />
                    </div>

                    <div>
                        <label htmlFor="email" className="mb-1.5 block text-[13px] font-medium text-ink">
                            Email address
                        </label>
                        <input
                            id="email"
                            type="email"
                            autoComplete="email"
                            required
                            value={data.email}
                            onChange={(event) => setData('email', event.target.value)}
                            className={inputClasses}
                        />
                        <FieldError message={errors.email} />
                    </div>

                    <div>
                        <label htmlFor="password" className="mb-1.5 block text-[13px] font-medium text-ink">
                            {requirePassword ? 'Password' : 'New password (optional)'}
                        </label>
                        <input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            required={requirePassword}
                            value={data.password}
                            onChange={(event) => setData('password', event.target.value)}
                            className={inputClasses}
                        />
                        <FieldError message={errors.password} />
                    </div>

                    <div>
                        <label htmlFor="password_confirmation" className="mb-1.5 block text-[13px] font-medium text-ink">
                            Confirm password
                        </label>
                        <input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required={requirePassword}
                            value={data.password_confirmation}
                            onChange={(event) => setData('password_confirmation', event.target.value)}
                            className={inputClasses}
                        />
                        <FieldError message={errors.password_confirmation} />
                    </div>
                </div>

                <div>
                    <div className="mb-2 flex items-end justify-between gap-3">
                        <div>
                            <p className="text-[13px] font-medium text-ink">Roles</p>
                            <p className="text-xs text-muted">Permissions are inherited from every assigned role.</p>
                        </div>
                        <span className="text-[11px] text-muted">{data.roles.length} selected</span>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        {roles.map((role) => {
                            const checked = data.roles.includes(role.name);
                            const blocked = role.is_locked && !isSuperAdmin;

                            return (
                                <label
                                    key={role.name}
                                    className={cn(
                                        'flex cursor-pointer items-start gap-3 rounded-lg border px-3.5 py-3 transition-colors',
                                        checked
                                            ? 'border-brand-500 bg-brand-50/50 dark:bg-brand-500/10'
                                            : 'border-line hover:bg-canvas',
                                        blocked && 'cursor-not-allowed opacity-60',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        disabled={blocked}
                                        onChange={() => toggleRole(role.name)}
                                        className="mt-0.5 rounded border-line text-brand-600 focus:ring-brand-500"
                                    />
                                    <span className="min-w-0">
                                        <span className="flex items-center gap-1.5 text-[13px] font-medium text-ink">
                                            {role.name}
                                            {role.is_locked && <LockKeyhole className="h-3 w-3 text-muted" strokeWidth={2} />}
                                        </span>
                                        {role.description && <span className="mt-0.5 block text-[11.5px] leading-relaxed text-muted">{role.description}</span>}
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                    <FieldError message={errors.roles} />
                </div>
            </div>

            <div className="flex items-center justify-end gap-2 border-t border-line px-5 py-3.5">
                <Link
                    href="/users"
                    className="rounded-lg px-3 py-2 text-[13px] font-medium text-muted transition-colors hover:bg-canvas hover:text-ink focus-ring"
                >
                    Cancel
                </Link>
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
