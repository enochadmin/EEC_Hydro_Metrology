import Button from '@/Components/Button';
import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { cn } from '@/Utils/cn';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, Loader2, Lock, Mail } from 'lucide-react';
import { useState } from 'react';

const fieldBase =
    'h-11 w-full rounded-lg border bg-surface pl-10 text-[13.5px] text-ink shadow-sm transition-colors placeholder:text-muted/60 focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-60';

function fieldClasses(hasError) {
    return cn(
        fieldBase,
        hasError
            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-500/20 dark:border-rose-500/50'
            : 'border-line focus:border-brand-500 focus:ring-brand-500/20',
    );
}

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const [showPassword, setShowPassword] = useState(false);

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout title="Sign in" description="Use the credentials issued by your system administrator.">
            <Head title="Sign in" />

            {status && (
                <div
                    role="status"
                    className="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-[12.5px] font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
                >
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label htmlFor="email" className="block text-[12.5px] font-medium text-ink">
                        Email address
                    </label>

                    <div className="relative mt-1.5">
                        <Mail className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" strokeWidth={1.75} />
                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autoFocus
                            autoComplete="username"
                            placeholder="name@organisation.et"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            aria-invalid={Boolean(errors.email)}
                            aria-describedby={errors.email ? 'email-error' : undefined}
                            className={fieldClasses(Boolean(errors.email))}
                        />
                    </div>

                    <InputError id="email-error" message={errors.email} className="mt-1.5" />
                </div>

                <div>
                    <div className="flex items-center justify-between gap-3">
                        <label htmlFor="password" className="block text-[12.5px] font-medium text-ink">
                            Password
                        </label>

                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                tabIndex={4}
                                className="focus-ring rounded text-[12px] font-medium text-brand-700 transition-colors hover:text-brand-800 dark:text-brand-300 dark:hover:text-brand-200"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>

                    <div className="relative mt-1.5">
                        <Lock className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" strokeWidth={1.75} />
                        <input
                            id="password"
                            name="password"
                            type={showPassword ? 'text' : 'password'}
                            required
                            autoComplete="current-password"
                            placeholder="Enter your password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            aria-invalid={Boolean(errors.password)}
                            aria-describedby={errors.password ? 'password-error' : undefined}
                            className={cn(fieldClasses(Boolean(errors.password)), 'pr-11')}
                        />

                        <button
                            type="button"
                            onClick={() => setShowPassword((value) => !value)}
                            aria-label={showPassword ? 'Hide password' : 'Show password'}
                            aria-pressed={showPassword}
                            className="focus-ring absolute inset-y-0 right-0 grid w-11 place-items-center rounded-r-lg text-muted transition-colors hover:text-ink"
                        >
                            {showPassword ? <EyeOff className="h-4 w-4" strokeWidth={1.75} /> : <Eye className="h-4 w-4" strokeWidth={1.75} />}
                        </button>
                    </div>

                    <InputError id="password-error" message={errors.password} className="mt-1.5" />
                </div>

                <label htmlFor="remember" className="flex w-fit cursor-pointer items-center gap-2.5 text-[12.5px] text-muted">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                        className="h-4 w-4 rounded border-muted/50 text-brand-600 shadow-sm focus:ring-brand-500/30 dark:border-white/25 dark:bg-surface dark:ring-offset-surface"
                    />
                    Keep me signed in on this device
                </label>

                <Button
                    type="submit"
                    size="lg"
                    disabled={processing}
                    aria-busy={processing}
                    className="mt-1 w-full justify-center gap-2.5 font-semibold"
                >
                    {processing ? <Loader2 className="h-4 w-4 animate-spin" strokeWidth={2} /> : <Lock className="h-4 w-4" strokeWidth={2} />}
                    <span>{processing ? 'Signing in…' : 'Sign in'}</span>
                    {!processing && <ArrowRight className="h-4 w-4" strokeWidth={2} />}
                </Button>
            </form>
        </GuestLayout>
    );
}
