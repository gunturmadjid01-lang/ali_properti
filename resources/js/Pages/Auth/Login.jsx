import { Head, useForm, Link } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, Lock, Mail } from 'lucide-react';
import { useState } from 'react';
import Button from '../../Components/UI/Button';
import Input from '../../Components/UI/Input';
import AuthLayout from '../../Layouts/AuthLayout';

function Login({ title = 'Login Area Internal' }) {
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const submit = (event) => {
        event.preventDefault();

        form.post('/login', {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={title} />

            <form className="grid gap-5" onSubmit={submit}>
                <Input
                    label="Email"
                    type="email"
                    autoComplete="email"
                    placeholder="nama@email.com"
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    icon={<Mail size={16} />}
                />

                <label className="grid gap-2 text-sm font-extrabold text-ink/75">
                    <span>Password</span>
                    <div className="relative">
                        <Lock
                            className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-soft"
                            size={16}
                        />
                        <input
                            className="min-h-11 w-full rounded-lg border border-silver-deep/70 bg-white/85 px-10 py-2.5 pr-12 font-semibold text-ink outline-none ring-4 ring-transparent transition placeholder:text-ink-soft/60 focus:border-ink-soft focus:ring-ink-soft/15 dark:border-white/10 dark:bg-white/8 dark:text-white dark:placeholder:text-white/35"
                            type={showPassword ? 'text' : 'password'}
                            autoComplete="current-password"
                            placeholder="Masukkan password"
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                        <button
                            className="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1.5 text-ink-soft transition hover:bg-silver hover:text-ink"
                            type="button"
                            onClick={() => setShowPassword((current) => !current)}
                            aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                        >
                            {showPassword ? <EyeOff size={17} /> : <Eye size={17} />}
                        </button>
                    </div>
                    {form.errors.password && (
                        <span className="text-xs font-bold text-red-600">
                            {form.errors.password}
                        </span>
                    )}
                </label>

                <label className="flex items-center gap-3 rounded-xl border border-silver-deep/60 bg-silver-soft/70 px-4 py-3 text-sm font-bold text-ink-soft">
                    <input
                        className="h-4 w-4 rounded border-silver-deep/70 text-ink focus:ring-ink-soft/20"
                        type="checkbox"
                        checked={form.data.remember}
                        onChange={(event) => form.setData('remember', event.target.checked)}
                    />
                    Ingat saya di perangkat ini
                </label>

                <Button className="mt-1 w-full" type="submit" disabled={form.processing}>
                    Masuk ke Dasbor
                    <ArrowRight size={18} />
                </Button>

                <div className="grid gap-3 text-center text-sm text-ink-soft">
                    <p>
                        Akun contoh awal: <strong className="text-ink">admin@ptali.com</strong>, <strong className="text-ink">keuangan@ptali.com</strong>, <strong className="text-ink">marketing@ptali.com</strong>
                    </p>
                    <p>
                        Password contoh: <strong className="text-ink">password</strong>
                    </p>
                    <Link
                        className="font-bold text-ink underline decoration-ink/25 underline-offset-4 transition hover:text-graphite"
                        href="/"
                    >
                        Kembali ke website
                    </Link>
                </div>
            </form>
        </>
    );
}

Login.layout = (page) => <AuthLayout title="Login">{page}</AuthLayout>;

export default Login;
