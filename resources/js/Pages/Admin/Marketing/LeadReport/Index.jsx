import { Head, router } from '@inertiajs/react';
import { BarChart3, CalendarDays, ClipboardList, Search, TrendingUp, Users } from 'lucide-react';
import { useState } from 'react';
import { Button, Input } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

function StatCard({ label, value, Icon }) {
    return (
        <article className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex items-center justify-between gap-3">
                <span className="grid h-11 w-11 place-items-center rounded-xl bg-silver text-ink-soft dark:bg-white/10 dark:text-white/70">
                    <Icon size={20} />
                </span>
                <strong className="text-3xl font-extrabold text-ink dark:text-white">{value}</strong>
            </div>
            <p className="mt-3 text-sm font-bold text-ink-soft dark:text-white/58">{label}</p>
        </article>
    );
}

function StatusBadge({ children }) {
    return (
        <span className="rounded-full bg-silver px-3 py-1 text-xs font-extrabold text-ink-soft dark:bg-white/10 dark:text-white/70">
            {children}
        </span>
    );
}

export default function Index({
    title,
    description,
    baseUrl,
    filters = {},
    summary = {},
    currentStatus = [],
    periodStats = [],
    dailyRows = [],
    sourceRows = [],
    timeline = [],
}) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const filterRows = (event) => {
        event.preventDefault();
        router.get(baseUrl, { date_from: dateFrom, date_to: dateTo }, { preserveState: true, replace: true });
    };

    const stats = [
        ['Total Customer', summary.total_customers ?? 0, Users],
        ['Aktivitas Periode', summary.activities ?? 0, ClipboardList],
        ['Booking Fee Periode', summary.booking_period ?? 0, CalendarDays],
        ['Closing Periode', summary.closing_period ?? 0, TrendingUp],
    ];

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-2xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-gold-deep">Marketing Report</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-4xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {stats.map(([label, value, Icon]) => (
                        <StatCard key={label} label={label} value={value} Icon={Icon} />
                    ))}
                </section>

                <section className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="grid gap-4 md:grid-cols-[220px_220px_auto]" onSubmit={filterRows}>
                        <Input label="Dari Tanggal" type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
                        <Input label="Sampai Tanggal" type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
                        <div className="flex items-end">
                            <Button type="submit"><Search size={16} /> Tampilkan</Button>
                        </div>
                    </form>
                </section>

                <section className="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                    <div className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex items-center gap-2">
                            <BarChart3 size={18} />
                            <h3 className="text-lg font-extrabold">Status Customer Saat Ini</h3>
                        </div>
                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            {currentStatus.map((row) => (
                                <div className="rounded-xl border border-silver-deep/60 bg-silver-soft/70 p-4 dark:border-white/10 dark:bg-white/6" key={row.value}>
                                    <p className="text-xs font-extrabold uppercase tracking-[0.14em] text-ink-soft dark:text-white/45">{row.label}</p>
                                    <strong className="mt-2 block text-2xl font-extrabold">{row.total}</strong>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <h3 className="text-lg font-extrabold">Pergerakan Status Pada Periode</h3>
                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            {periodStats.map((row) => (
                                <div className="flex items-center justify-between gap-3 rounded-xl border border-silver-deep/60 bg-silver-soft/70 p-4 dark:border-white/10 dark:bg-white/6" key={row.value}>
                                    <span className="text-sm font-extrabold text-ink-soft dark:text-white/62">{row.label}</span>
                                    <strong className="text-xl font-extrabold">{row.total}</strong>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-[1fr_0.7fr]">
                    <div className="overflow-hidden rounded-2xl border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                            <h3 className="text-lg font-extrabold">Aktivitas Harian</h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft dark:bg-white/5">
                                    <tr>
                                        <th className="px-5 py-4">Tanggal</th>
                                        <th className="px-5 py-4">Total Customer Bergerak</th>
                                        <th className="px-5 py-4">Rincian Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {dailyRows.map((row) => (
                                        <tr key={row.tanggal}>
                                            <td className="px-5 py-4 font-extrabold">{row.tanggal}</td>
                                            <td className="px-5 py-4">{row.total}</td>
                                            <td className="px-5 py-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {row.statuses.map((status) => <StatusBadge key={status.label}>{status.label}: {status.total}</StatusBadge>)}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {dailyRows.length === 0 && (
                                        <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={3}>Belum ada aktivitas lead pada periode ini.</td></tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <h3 className="text-lg font-extrabold">Sumber Lead Baru</h3>
                        <div className="mt-4 grid gap-3">
                            {sourceRows.map((row) => (
                                <div className="flex items-center justify-between rounded-xl bg-silver-soft/80 px-4 py-3 dark:bg-white/6" key={row.label}>
                                    <span className="font-bold text-ink-soft dark:text-white/65">{row.label}</span>
                                    <strong>{row.total}</strong>
                                </div>
                            ))}
                            {sourceRows.length === 0 && <p className="rounded-xl bg-silver-soft/80 px-4 py-4 text-sm font-bold text-ink-soft dark:bg-white/6 dark:text-white/60">Belum ada customer baru pada periode ini.</p>}
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                        <h3 className="text-lg font-extrabold">Timeline Aktivitas Terakhir</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft dark:bg-white/5">
                                <tr>{['Tanggal', 'Customer', 'Dari', 'Ke', 'User', 'Catatan'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {timeline.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">{row.tanggal}</td>
                                        <td className="px-5 py-4 font-bold">{row.customer}<br /><span className="text-xs text-ink-soft">{row.kode_customer}</span></td>
                                        <td className="px-5 py-4">{row.status_from}</td>
                                        <td className="px-5 py-4">{row.status_to}</td>
                                        <td className="px-5 py-4">{row.user}</td>
                                        <td className="px-5 py-4">{row.note}</td>
                                    </tr>
                                ))}
                                {timeline.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={6}>Belum ada timeline lead.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Laporan Lead'}>{page}</AdminLayout>;
