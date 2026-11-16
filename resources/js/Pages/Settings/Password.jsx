import Button from '@/Components/Button';
import { Card, CardBody, CardFooter, CardHeader } from '@/Components/Card';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { Head, useForm } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { useRef } from 'react';

const inputClasses =
    'block w-full rounded-lg border-line bg-surface text-sm text-ink shadow-sm placeholder:text-muted/70 focus:border-brand-500 focus:ring-brand-500';

function FieldError({ message }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{message}</p>;
}

export default function Password() {
    const currentPasswordRef = useRef(null);

    const { data, setData, put, errors, processing, reset, recentlySuccessful } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();

        put('/settings/password', {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: () => currentPasswordRef.current?.focus(),
        });
    };

    return (
        <SettingsLayout title="Password" description="Choose a strong password that is unique to this system.">
            <Head title="Password settings" />

            <Card className="max-w-2xl">
                <CardHeader icon={KeyRound} title="Update password" description="You stay signed in on this device after changing it." />

                <form onSubmit={submit}>
                    <CardBody className="space-y-4">
                        <div>
                            <label htmlFor="current_password" className="mb-1.5 block text-[13px] font-medium text-ink">
                                Current password
                            </label>
                            <input
                                id="current_password"
                                ref={currentPasswordRef}
                                type="password"
                                autoComplete="current-password"
                                value={data.current_password}
                                onChange={(event) => setData('current_password', event.target.value)}
                                className={inputClasses}
                            />
                            <FieldError message={errors.current_password} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label htmlFor="password" className="mb-1.5 block text-[13px] font-medium text-ink">
                                    New password
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    className={inputClasses}
                                />
                                <FieldError message={errors.password} />
                            </div>

                            <div>
                                <label htmlFor="password_confirmation" className="mb-1.5 block text-[13px] font-medium text-ink">
                                    Confirm new password
                                </label>
                                <input
                                    id="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    value={data.password_confirmation}
                                    onChange={(event) => setData('password_confirmation', event.target.value)}
                                    className={inputClasses}
                                />
                                <FieldError message={errors.password_confirmation} />
                            </div>
                        </div>

                        <p className="flex items-start gap-2 rounded-lg bg-canvas px-3.5 py-3 text-[12px] leading-relaxed text-muted">
                            <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-brand-700 dark:text-brand-300" strokeWidth={1.75} />
                            Use at least eight characters. Staff accounts that handle basin records should also be protected by role-based access
                            control, which is configured in Phase 2.
                        </p>
                    </CardBody>

                    <CardFooter className="flex items-center justify-end gap-3">
                        {recentlySuccessful && <span className="text-[12px] font-medium text-emerald-600 dark:text-emerald-400">Saved</span>}
                        <Button type="submit" disabled={processing}>
                            Update password
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </SettingsLayout>
    );
}
