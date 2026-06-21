import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, LayoutGrid, ShieldCheck } from 'lucide-react';
import AdminLayout from '../../../Layouts/AdminLayout';

function AreaCard({ title, description, points = [] }) {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex items-center gap-3">
                <div className="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-champagne to-gold text-gold-deep">
                    <LayoutGrid size={22} />
                </div>
                <div>
                    <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-ink-soft">
                        Area Kerja
                    </p>
                    <h2 className="text-2xl font-extrabold text-ink dark:text-white">
                        {title}
                    </h2>
                </div>
            </div>

            <p className="mt-4 max-w-3xl text-sm leading-7 text-ink-soft dark:text-white/62">
                {description}
            </p>

            <div className="mt-6 grid gap-3 md:grid-cols-2">
                {points.map((point) => (
                    <div
                        className="flex items-center gap-3 rounded-2xl border border-silver-deep/60 bg-silver-soft/70 px-4 py-3 text-sm font-bold text-ink/78 dark:border-white/10 dark:bg-white/6 dark:text-white/72"
                        key={point}
                    >
                        <CheckCircle2 className="text-gold-deep" size={18} />
                        {point}
                    </div>
                ))}
            </div>
        </section>
    );
}

function MenuCard({ menu }) {
    return (
        <Link className="group block rounded-3xl border border-silver-deep/60 bg-white/80 p-5 shadow-soft transition hover:-translate-y-0.5 hover:border-gold/70 hover:shadow-[0_16px_40px_rgba(31,37,43,0.12)] dark:border-white/10 dark:bg-white/6" href={menu.href ?? '#'}>
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h3 className="text-lg font-extrabold text-ink dark:text-white">
                        {menu.label}
                    </h3>
                    <p className="mt-2 text-sm leading-6 text-ink-soft dark:text-white/60">
                        {menu.description}
                    </p>
                </div>
                <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-champagne to-gold text-gold-deep">
                    <ArrowRight className="transition group-hover:translate-x-0.5" size={18} />
                </div>
            </div>
        </Link>
    );
}

function Index({ title, description, points = [], menus = [], roles = [] }) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <AreaCard title={title} description={description} points={points} />

                <section className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h3 className="text-lg font-extrabold text-ink dark:text-white">
                                Menu Kerja
                            </h3>
                            <p className="mt-2 max-w-3xl text-sm leading-7 text-ink-soft dark:text-white/60">
                                Menu di bawah ini disesuaikan dengan role user yang login.
                            </p>
                        </div>
                        <div className="flex items-center gap-2 rounded-2xl border border-silver-deep/60 bg-silver-soft px-4 py-3 text-sm font-bold text-ink-soft dark:border-white/10 dark:bg-white/6 dark:text-white/70">
                            <ShieldCheck size={17} />
                            {roles.length ? roles.join(', ') : 'Tidak ada role'}
                        </div>
                    </div>
                    <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {menus.map((menu) => (
                            <MenuCard key={menu.label} menu={menu} />
                        ))}
                        {menus.length === 0 && (
                            <div className="rounded-3xl border border-dashed border-silver-deep/60 p-6 text-sm font-bold text-ink-soft dark:border-white/10 dark:text-white/55">
                                Belum ada menu yang dikaitkan untuk area ini.
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Admin'}>{page}</AdminLayout>;

export default Index;
