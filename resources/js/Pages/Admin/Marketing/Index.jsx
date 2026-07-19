import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, LayoutGrid, PhoneCall, Users } from 'lucide-react';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function Hero({ title, description, points = [], roles = [] }) {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-gold-deep">Marketing Workspace</p>
                    <h2 className="mt-2 text-3xl font-extrabold text-ink dark:text-white">{title}</h2>
                    <p className="mt-3 max-w-3xl text-sm leading-7 text-ink-soft dark:text-white/62">{description}</p>
                </div>
                <div className="rounded-2xl border border-silver-deep/60 bg-silver-soft px-4 py-3 text-sm font-bold text-ink-soft dark:border-white/10 dark:bg-white/6 dark:text-white/70">
                    <div className="flex items-center gap-2">
                        <PhoneCall size={16} />
                        {roles.length ? roles.join(', ') : 'Tidak ada role'}
                    </div>
                </div>
            </div>

            <div className="mt-6 grid gap-3 md:grid-cols-2">
                {points.map((point) => (
                    <div className="flex items-center gap-3 rounded-2xl border border-silver-deep/60 bg-silver-soft/70 px-4 py-3 text-sm font-bold text-ink/78 dark:border-white/10 dark:bg-white/6 dark:text-white/72" key={point}>
                        <CheckCircle2 className="text-gold-deep" size={18} />
                        {point}
                    </div>
                ))}
            </div>
        </section>
    );
}

function StatCard({ label, value, Icon }) {
    return (
        <article className="rounded-3xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex items-center justify-between">
                <span className="grid h-11 w-11 place-items-center rounded-2xl bg-silver text-ink-soft dark:bg-white/10 dark:text-white/70">
                    <Icon size={20} />
                </span>
            </div>
            <strong className="mt-4 block text-2xl font-extrabold text-ink dark:text-white">{value}</strong>
            <p className="mt-1 text-sm font-bold text-ink-soft dark:text-white/58">{label}</p>
        </article>
    );
}

function MenuCard({ menu }) {
    return (
        <Link className="group block rounded-3xl border border-silver-deep/60 bg-white/80 p-5 shadow-soft transition hover:-translate-y-0.5 hover:border-gold/70 hover:shadow-[0_16px_40px_rgba(31,37,43,0.12)] dark:border-white/10 dark:bg-white/6" href={menu.href ?? '#'}>
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h3 className="text-lg font-extrabold text-ink dark:text-white">{menu.label}</h3>
                    <p className="mt-2 text-sm leading-6 text-ink-soft dark:text-white/60">{menu.description}</p>
                </div>
                <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-champagne to-gold text-gold-deep">
                    <ArrowRight className="transition group-hover:translate-x-0.5" size={18} />
                </div>
            </div>
        </Link>
    );
}

function DataTable({ title, rows = [], columns = [] }) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                <h3 className="text-base font-extrabold text-ink dark:text-white">{title}</h3>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                    <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                        <tr>
                            {columns.map((column) => (
                                <th className="px-4 py-3 font-extrabold" key={column.key}>{column.label}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                        {rows.map((row) => (
                            <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                {columns.map((column) => (
                                    <td className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72" key={column.key}>
                                        {row[column.key] ?? '-'}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function Index({ title, description, points = [], menus = [], featured = [], summary = {}, roles = [], customers = [], progressRows = [] }) {
    const stats = [
        ['Total Pelanggan', summary.total_customers ?? 0, Users],
        ['Prospek Tinggi', summary.high_prospects ?? 0, PhoneCall],
        ['Dokumen Pelanggan', summary.documents ?? 0, LayoutGrid],
        ['Kemajuan 30 Hari', summary.recent_progress ?? 0, ArrowRight],
    ];

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <Hero title={title} description={description} points={points} roles={roles} />

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {stats.map(([label, value, Icon]) => (
                        <StatCard key={label} label={label} value={typeof value === 'number' ? value : value} Icon={Icon} />
                    ))}
                </section>

                <section className="grid gap-6 xl:grid-cols-[1fr_0.92fr]">
                    <div className="grid gap-4">
                        {featured.length > 0 && (
                            <div className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                                <h3 className="text-lg font-extrabold text-ink dark:text-white">Fokus Marketing</h3>
                                <div className="mt-4 grid gap-3">
                                    {featured.map((item) => (
                                        <div className="rounded-2xl bg-silver-soft px-4 py-3 dark:bg-white/6" key={item.label}>
                                            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">{item.label}</p>
                                            <p className="mt-1 text-sm font-bold text-ink dark:text-white">{item.value}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <DataTable
                            title="Calon Konsumen Terbaru"
                            rows={customers}
                            columns={[
                                { key: 'nama', label: 'Nama' },
                                { key: 'no_identitas', label: 'No Identitas' },
                                { key: 'telepon', label: 'Telepon' },
                                { key: 'pekerjaan', label: 'Pekerjaan' },
                                { key: 'penghasilan', label: 'Penghasilan' },
                            ]}
                        />

                        <DataTable
                            title="Kemajuan Pembangunan Terbaru"
                            rows={progressRows}
                            columns={[
                                { key: 'tanggal', label: 'Tanggal' },
                                { key: 'proyek', label: 'Proyek' },
                                { key: 'unit', label: 'Unit' },
                                { key: 'persentase', label: 'Persentase' },
                                { key: 'user', label: 'Input Oleh' },
                            ]}
                        />
                    </div>

                    <div className="grid gap-4">
                        <section className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <h3 className="text-lg font-extrabold text-ink dark:text-white">Menu Kerja</h3>
                            <p className="mt-2 text-sm leading-7 text-ink-soft dark:text-white/60">
                                Menu di bawah disusun untuk alur kerja marketing yang paling sering dipakai.
                            </p>
                            <div className="mt-5 grid gap-4">
                                {menus.map((menu) => (
                                    <MenuCard key={menu.label} menu={menu} />
                                ))}
                            </div>
                        </section>

                        <section className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <h3 className="text-lg font-extrabold text-ink dark:text-white">Aksi Cepat</h3>
                            <div className="mt-4 grid gap-3">
                                <Link className="rounded-2xl border border-silver-deep/60 bg-silver-soft px-4 py-3 text-sm font-bold text-ink transition hover:bg-silver dark:border-white/10 dark:bg-white/6 dark:text-white" href="/admin/marketing/calon-konsumen">
                                    Buka data calon konsumen
                                </Link>
                                <Link className="rounded-2xl border border-silver-deep/60 bg-silver-soft px-4 py-3 text-sm font-bold text-ink transition hover:bg-silver dark:border-white/10 dark:bg-white/6 dark:text-white" href="/admin/marketing/laporan">
                                    Lihat laporan marketing
                                </Link>
                            </div>
                        </section>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Marketing'}>{page}</AdminLayout>;

export default Index;
