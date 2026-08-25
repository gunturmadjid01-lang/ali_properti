import { Head, router } from '@inertiajs/react';
import { Activity, BarChart3, CalendarRange, Search, Target, Users } from 'lucide-react';
import { useState } from 'react';
import { Button, Input } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

function Card({ children, className = '' }) {
    return <section className={`rounded-2xl border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/7 ${className}`}>{children}</section>;
}

export default function Index({
    title,
    description,
    baseUrl,
    filters = {},
    summary = {},
    stageRows = [],
    marketingRows = [],
    dailyRows = [],
    timeline = [],
    forecast = {},
}) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');

    const filter = (event) => {
        event.preventDefault();
        router.get(baseUrl, { date_from: dateFrom, date_to: dateTo }, { preserveState: true, replace: true });
    };

    const stats = [
        ['Aktivitas Alur Penjualan', summary.activities ?? 0, Activity],
        ['Pelanggan Bergerak', summary.customers ?? 0, Users],
        ['Closing', summary.closing ?? 0, Target],
        ['Rasio Closing', `${summary.closing_rate ?? 0}%`, BarChart3],
    ];

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <Card className="p-6">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-gold-deep">Marketing Analytics</p>
                    <h1 className="mt-2 font-display text-3xl font-extrabold">{title}</h1>
                    <p className="mt-2 max-w-4xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </Card>

                <Card className="p-5">
                    <form className="grid gap-4 md:grid-cols-[220px_220px_auto]" onSubmit={filter}>
                        <Input label="Dari Tanggal" type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
                        <Input label="Sampai Tanggal" type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
                        <div className="flex items-end"><Button><Search size={16} /> Tampilkan</Button></div>
                    </form>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {stats.map(([label, value, Icon]) => (
                        <Card className="p-5" key={label}>
                            <div className="flex items-center justify-between">
                                <span className="grid h-11 w-11 place-items-center rounded-xl bg-ink text-white dark:bg-white dark:text-ink"><Icon size={19} /></span>
                                <strong className="text-3xl">{value}</strong>
                            </div>
                            <p className="mt-3 text-sm font-bold text-ink-soft">{label}</p>
                        </Card>
                    ))}
                </div>

                <Card className="p-5">
                    <h2 className="text-lg font-extrabold">Perkiraan Penjualan Aktif</h2>
                    <p className="mt-1 text-sm text-ink-soft">Perkiraan dihitung dari harga unit atau anggaran lead dan bobot tahap saat ini.</p>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {[
                            ['Lead aktif', forecast.leads ?? 0],
                            ['Nilai potensi', `Rp ${(forecast.potential_value ?? 0).toLocaleString('id-ID')}`],
                            ['Nilai tertimbang', `Rp ${(forecast.weighted_value ?? 0).toLocaleString('id-ID')}`],
                            ['Lead lebih 7 hari', forecast.aging_over_7_days ?? 0],
                        ].map(([label, value]) => <div className="rounded-xl bg-silver-soft p-4 dark:bg-white/5" key={label}><div className="text-2xl font-extrabold">{value}</div><div className="mt-1 text-xs font-bold text-ink-soft">{label}</div></div>)}
                    </div>
                </Card>

                <Card className="overflow-hidden">
                    <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                        <h2 className="text-lg font-extrabold">Funnel dan Konversi Tahapan</h2>
                        <p className="mt-1 text-xs font-semibold text-ink-soft">Total adalah pelanggan unik yang masuk tahap tersebut selama periode.</p>
                    </div>
                    <div className="overflow-x-auto">
                        <div className="flex min-w-max gap-3 p-5">
                            {stageRows.map((row, index) => (
                                <div className="relative w-48 rounded-xl border border-silver-deep/60 bg-silver-soft/80 p-4 dark:border-white/10 dark:bg-white/5" key={row.value}>
                                    <p className="text-xs font-bold uppercase tracking-wider text-ink-soft">{row.label}</p>
                                    <strong className="mt-2 block text-3xl">{row.total}</strong>
                                    {index > 0 && <span className="mt-3 inline-flex rounded-full bg-ink px-2.5 py-1 text-xs font-extrabold text-white dark:bg-white dark:text-ink">{row.conversion}% dari tahap sebelumnya</span>}
                                </div>
                            ))}
                        </div>
                    </div>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                    <Card className="overflow-hidden">
                        <h2 className="border-b border-silver-deep/60 px-5 py-4 text-lg font-extrabold dark:border-white/10">Performa Per Marketing</h2>
                        <div className="overflow-x-auto"><table className="min-w-full text-sm">
                            <thead className="bg-silver-soft text-left text-xs uppercase text-ink-soft dark:bg-white/5"><tr>{['Marketing', 'Aktivitas', 'Pelanggan', 'Survei', 'SPR', 'Closing'].map((label) => <th className="px-4 py-3" key={label}>{label}</th>)}</tr></thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">{marketingRows.map((row) => <tr key={row.user}>
                                <td className="px-4 py-4 font-extrabold">{row.user}</td><td className="px-4 py-4">{row.activities}</td><td className="px-4 py-4">{row.customers}</td><td className="px-4 py-4">{row.survey}</td><td className="px-4 py-4">{row.spr}</td><td className="px-4 py-4 font-extrabold">{row.closing}</td>
                            </tr>)}</tbody>
                        </table></div>
                    </Card>

                    <Card className="overflow-hidden">
                        <h2 className="flex items-center gap-2 border-b border-silver-deep/60 px-5 py-4 text-lg font-extrabold dark:border-white/10"><CalendarRange size={18} /> Aktivitas Harian</h2>
                        <div className="max-h-96 divide-y divide-silver-deep/50 overflow-y-auto dark:divide-white/10">
                            {dailyRows.map((row) => <div className="grid grid-cols-[1fr_auto_auto] gap-4 px-5 py-4" key={row.date}><b>{row.date}</b><span>{row.customers} customer</span><span className="font-extrabold">{row.closing} closing</span></div>)}
                        </div>
                    </Card>
                </div>

                <Card className="overflow-hidden">
                    <h2 className="border-b border-silver-deep/60 px-5 py-4 text-lg font-extrabold dark:border-white/10">Riwayat Perpindahan Alur Penjualan</h2>
                    <div className="max-h-[620px] overflow-auto"><table className="min-w-full text-sm">
                        <thead className="sticky top-0 bg-silver-soft text-left text-xs uppercase text-ink-soft dark:bg-graphite"><tr>{['Tanggal', 'Pelanggan', 'Dari', 'Ke', 'Marketing', 'Catatan'].map((label) => <th className="px-4 py-3" key={label}>{label}</th>)}</tr></thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">{timeline.map((row) => <tr key={row.id}>
                            <td className="px-4 py-4">{row.date}</td><td className="px-4 py-4 font-bold">{row.customer}<br /><small className="text-ink-soft">{row.code}</small></td><td className="px-4 py-4">{row.from}</td><td className="px-4 py-4 font-extrabold">{row.to}</td><td className="px-4 py-4">{row.user}</td><td className="px-4 py-4">{row.note}</td>
                        </tr>)}</tbody>
                    </table></div>
                </Card>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Laporan Alur Penjualan'}>{page}</AdminLayout>;
