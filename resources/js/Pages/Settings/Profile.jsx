import Badge from '@/Components/Badge';
import Button from '@/Components/Button';
import { Card, CardBody, CardFooter, CardHeader } from '@/Components/Card';
import SettingsLayout from '@/Layouts/SettingsLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, MailCheck, Trash2, UserRound } from 'lucide-react';
import { useState } from 'react';

const inputClasses =
    'block w-full rounded-lg border-line bg-surface text-sm text-ink shadow-sm placeholder:text-muted/70 focus:border-brand-500 focus:ring-brand-500';

function FieldError({ message }) {
    if (!message) {
        return null;
    }

    return <p className="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{message}</p>;
}

export default function Profile({ mustVerifyEmail, status }) {
    const { auth } = usePage().props;
    const user = auth.user;

    const [confirmingDeletion, setConfirmingDeletion] = useState(false);

    const profileForm = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
    });

    const deleteForm = useForm({ password: '' });

    const submitProfile = (event) => {
        event.preventDefault();
        profileForm.patch('/settings/profile', { preserveScroll: true });
    };

    const submitDeletion = (event) => {
        event.preventDefault();
        deleteForm.delete('/settings/profile', {
            preserveScroll: true,
            onFinish: () => deleteForm.reset(),
        });
    };

    return (
        <SettingsLayout title="Profile" description="Update the name and email address attached to your account.">
            <Head title="Profile settings" />

            <Card>
                <CardHeader
                    icon={UserRound}
                    title="Profile information"
                    description="This identity is recorded against every import, approval and audit entry you perform."
                />

                <form onSubmit={submitProfile}>
                    <CardBody className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label htmlFor="name" className="mb-1.5 block text-[13px] font-medium text-ink">
                                    Full name
                                </label>
                                <input
                                    id="name"
                                    name="name"
                                    type="text"
                                    autoComplete="name"
                                    required
                                    value={profileForm.data.name}
                                    onChange={(event) => profileForm.setData('name', event.target.value)}
                                    className={inputClasses}
                                />
                                <FieldError message={profileForm.errors.name} />
                            </div>

                            <div>
                                <label htmlFor="email" className="mb-1.5 block text-[13px] font-medium text-ink">
                                    Email address
                                </label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autoComplete="username"
                                    required
                                    value={profileForm.data.email}
                                    onChange={(event) => profileForm.setData('email', event.target.value)}
                                    className={inputClasses}
                                />
                                <FieldError message={profileForm.errors.email} />
                            </div>
                        </div>

                        {mustVerifyEmail && !user?.email_verified_at && (
                            <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 text-[12.5px] text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                                <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" strokeWidth={1.75} />
                                <p>
                                    Your email address is not verified.{' '}
                                    <Link href="/email/verification-notification" method="post" as="button" className="font-semibold underline">
                                        Resend the verification link
                                    </Link>
                                    .
                                </p>
                            </div>
                        )}

                        {status === 'verification-link-sent' && (
                            <p className="flex items-center gap-2 text-[12.5px] text-emerald-700 dark:text-emerald-400">
                                <MailCheck className="h-4 w-4" strokeWidth={1.75} />
                                A new verification link has been sent.
                            </p>
                        )}
                    </CardBody>

                    <CardFooter className="flex items-center justify-end gap-3">
                        {profileForm.recentlySuccessful && (
                            <span className="text-[12px] font-medium text-emerald-600 dark:text-emerald-400">Saved</span>
                        )}
                        <Button type="submit" disabled={profileForm.processing}>
                            Save changes
                        </Button>
                    </CardFooter>
                </form>
            </Card>

            <Card className="border-rose-200/70 dark:border-rose-500/25">
                <CardHeader
                    icon={Trash2}
                    title="Delete account"
                    description="Removing your account revokes access immediately. Records you created stay in the database for audit."
                    action={
                        <Badge size="sm" tone="danger">
                            Danger
                        </Badge>
                    }
                />

                {!confirmingDeletion ? (
                    <CardBody>
                        <Button variant="danger" size="sm" onClick={() => setConfirmingDeletion(true)}>
                            Delete my account
                        </Button>
                    </CardBody>
                ) : (
                    <form onSubmit={submitDeletion}>
                        <CardBody className="space-y-3">
                            <p className="text-[13px] leading-relaxed text-muted">
                                Confirm with your password. This action cannot be undone from the interface.
                            </p>

                            <div className="max-w-sm">
                                <label htmlFor="delete-password" className="mb-1.5 block text-[13px] font-medium text-ink">
                                    Password
                                </label>
                                <input
                                    id="delete-password"
                                    name="password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={deleteForm.data.password}
                                    onChange={(event) => deleteForm.setData('password', event.target.value)}
                                    className={inputClasses}
                                />
                                <FieldError message={deleteForm.errors.password} />
                            </div>
                        </CardBody>

                        <CardFooter className="flex items-center justify-end gap-2">
                            <Button type="button" variant="secondary" size="sm" onClick={() => setConfirmingDeletion(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" variant="danger" size="sm" disabled={deleteForm.processing}>
                                Delete account
                            </Button>
                        </CardFooter>
                    </form>
                )}
            </Card>
        </SettingsLayout>
    );
}
