import { Head, Link, router, usePage } from '@inertiajs/react';
import { LogOut, Mail, Phone, UserCircle2 } from 'lucide-react';
import AdminLayout from '../../../Layouts/AdminLayout';
import Button from '../../../Components/UI/Button';

function Avatar({ user, size = 'h-24 w-24' }) {
    if (user?.avatar_url) {
        return <img className={`${size} rounded-3xl border border-white/80 object-cover shadow-soft dark:border-white/10`} src={user.avatar_url} alt={user.name} />;
    }

    return (
        <div className={`${size} grid place-items-center rounded-3xl border border-white/80 bg-gradient-to-br from-champagne to-gold text-2xl font-black text-gold-deep shadow-soft dark:border-white/10`}>
            {user?.name?.charAt(0)?.toUpperCase() ?? 'U'}
        </div>
    );
}

export default function Index({ title, user }) {
    const { auth } = usePage().props;

    const logout = () => {
        router.post('/logout', {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6 lg:grid-cols-[0.95fr_1.05fr]">
                <section className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                        <Avatar user={user} />
                        <div className="min-w-0">
                            <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-ink-soft">Profil Login</p>
                            <h1 className="mt-1 text-3xl font-extrabold text-ink dark:text-white">{user.name}</h1>
                            <p className="mt-2 text-sm leading-6 text-ink-soft dark:text-white/60">{user.email}</p>
                        </div>
                    </div>

                    <div className="mt-6 grid gap-3">
                        <div className="rounded-2xl bg-silver-soft px-4 py-3 dark:bg-white/6">
                            <div className="flex items-center gap-3 text-sm font-bold text-ink-soft dark:text-white/72">
                                <Mail size={16} />
                                Email
                            </div>
                            <p className="mt-1 font-extrabold text-ink dark:text-white">{user.email}</p>
                        </div>
                        <div className="rounded-2xl bg-silver-soft px-4 py-3 dark:bg-white/6">
                            <div className="flex items-center gap-3 text-sm font-bold text-ink-soft dark:text-white/72">
                                <Phone size={16} />
                                Telepon
                            </div>
                            <p className="mt-1 font-extrabold text-ink dark:text-white">{user.phone ?? '-'}</p>
                        </div>
                        <div className="rounded-2xl bg-silver-soft px-4 py-3 dark:bg-white/6">
                            <div className="flex items-center gap-3 text-sm font-bold text-ink-soft dark:text-white/72">
                                <UserCircle2 size={16} />
                                Role
                            </div>
                            <p className="mt-1 font-extrabold text-ink dark:text-white">{user.roles?.join(', ') || '-'}</p>
                        </div>
                    </div>

                    <div className="mt-6 flex flex-wrap gap-3">
                        <Link href="/admin/dashboard">
                            <Button variant="outline" type="button">
                                Kembali ke Dashboard
                            </Button>
                        </Link>
                        <Button type="button" onClick={logout}>
                            <LogOut size={17} />
                            Logout
                        </Button>
                    </div>
                </section>

                <section className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <h2 className="text-2xl font-extrabold text-ink dark:text-white">Akun Login</h2>
                    <p className="mt-2 text-sm leading-7 text-ink-soft dark:text-white/60">
                        Informasi ini dipakai untuk header, sidebar, dan identitas login di seluruh dashboard internal.
                    </p>

                    <div className="mt-6 grid gap-4">
                        <div className="rounded-3xl border border-silver-deep/60 bg-silver-soft p-5 dark:border-white/10 dark:bg-white/6">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">Nama Pengguna</p>
                            <p className="mt-2 text-xl font-extrabold text-ink dark:text-white">{auth?.user?.name ?? user.name}</p>
                        </div>
                        <div className="rounded-3xl border border-silver-deep/60 bg-silver-soft p-5 dark:border-white/10 dark:bg-white/6">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">Avatar</p>
                            <div className="mt-4 flex items-center gap-4">
                                <Avatar user={user} size="h-16 w-16" />
                                <p className="text-sm leading-6 text-ink-soft dark:text-white/60">
                                    Avatar ini akan muncul di header dan sidebar. Jika file avatar belum diisi, sistem tetap menampilkan foto default dari data user.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Profil'}>{page}</AdminLayout>;
