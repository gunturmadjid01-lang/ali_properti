import { Head, router } from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    BarChart3,
    ClipboardList,
    FileSignature,
    Search,
    Target,
    TrendingUp,
    Users,
} from "lucide-react";
import { useState } from "react";
import { Button, Input } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

function StatCard({ label, value, Icon }) {
    return (
        <article className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex items-center justify-between gap-3">
                <span className="grid h-11 w-11 place-items-center rounded-xl bg-silver text-ink-soft dark:bg-white/10 dark:text-white/70">
                    <Icon size={20} />
                </span>
                <strong className="text-3xl font-extrabold text-ink dark:text-white">
                    {value}
                </strong>
            </div>
            <p className="mt-3 text-sm font-bold text-ink-soft dark:text-white/58">
                {label}
            </p>
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

function OperationalChart({ rows = [] }) {
    const series = [
        ["Lead", "lead", "#ea580c"],
        ["Follow up", "follow_up", "#059669"],
        ["Survei", "survey", "#d97706"],
        ["SPR", "spr", "#2563eb"],
    ];
    const max = Math.max(
        1,
        ...rows.flatMap((row) =>
            series.map(([, key]) => Number(row[key] ?? 0)),
        ),
    );
    return (
        <section className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="text-lg font-extrabold">
                        Aktivitas Lead Harian
                    </h3>
                    <p className="text-sm text-ink-soft">
                        Bersumber langsung dari lead, follow-up, survei, dan
                        SPR.
                    </p>
                </div>
                <BarChart3 size={22} />
            </div>
            <div className="mt-6 flex h-56 items-end gap-2 overflow-x-auto border-b border-silver-deep/60 pb-2">
                {rows.map((row) => (
                    <div
                        className="flex h-full min-w-12 flex-1 flex-col justify-end"
                        key={row.date}
                    >
                        <div className="flex h-[185px] items-end justify-center gap-1">
                            {series.map(([label, key, color]) => (
                                <div
                                    className="w-full max-w-5 rounded-t"
                                    key={key}
                                    style={{
                                        height: `${Math.max(row[key] ? 5 : 1, (Number(row[key] ?? 0) / max) * 100)}%`,
                                        backgroundColor: color,
                                    }}
                                    title={`${label}: ${row[key]}`}
                                />
                            ))}
                        </div>
                        <small className="mt-2 truncate text-center text-[10px] font-bold text-ink-soft">
                            {row.date}
                        </small>
                    </div>
                ))}
            </div>
            <div className="mt-4 flex flex-wrap gap-4">
                {series.map(([label, key, color]) => (
                    <span
                        className="flex items-center gap-2 text-xs font-bold text-ink-soft"
                        key={key}
                    >
                        <i
                            className="h-2.5 w-2.5 rounded-full"
                            style={{ backgroundColor: color }}
                        />
                        {label}
                    </span>
                ))}
            </div>
        </section>
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
    operationalDaily = [],
}) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? "");
    const [dateTo, setDateTo] = useState(filters.date_to ?? "");

    const filterRows = (event) => {
        event.preventDefault();
        router.get(
            baseUrl,
            { date_from: dateFrom, date_to: dateTo },
            { preserveState: true, replace: true },
        );
    };

    const stats = [
        ["Total Pelanggan", summary.total_customers ?? 0, Users],
        ["Lead Baru", summary.lead_period ?? 0, Target],
        ["Tindak Lanjut", summary.follow_up_period ?? 0, Activity],
        ["Survei", summary.survey_period ?? 0, ClipboardList],
        ["SPR Dibuat", summary.spr_period ?? 0, FileSignature],
        ["Closing", summary.closing_period ?? 0, TrendingUp],
        ["Transaksi Gagal", summary.failed_period ?? 0, AlertTriangle],
    ];

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-2xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-gold-deep">
                        Marketing Report
                    </p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">
                        {title}
                    </h2>
                    <p className="mt-2 max-w-4xl leading-7 text-ink-soft dark:text-white/60">
                        {description}
                    </p>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    {stats.map(([label, value, Icon]) => (
                        <StatCard
                            key={label}
                            label={label}
                            value={value}
                            Icon={Icon}
                        />
                    ))}
                </section>

                <OperationalChart rows={operationalDaily} />

                <section className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-4 md:grid-cols-[220px_220px_auto]"
                        onSubmit={filterRows}
                    >
                        <Input
                            label="Dari Tanggal"
                            type="date"
                            value={dateFrom}
                            onChange={(event) =>
                                setDateFrom(event.target.value)
                            }
                        />
                        <Input
                            label="Sampai Tanggal"
                            type="date"
                            value={dateTo}
                            onChange={(event) => setDateTo(event.target.value)}
                        />
                        <div className="flex items-end">
                            <Button type="submit">
                                <Search size={16} /> Tampilkan
                            </Button>
                        </div>
                    </form>
                </section>

                <section className="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                    <div className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex items-center gap-2">
                            <BarChart3 size={18} />
                            <h3 className="text-lg font-extrabold">
                                Status Pelanggan Saat Ini
                            </h3>
                        </div>
                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            {currentStatus.map((row) => (
                                <div
                                    className="rounded-xl border border-silver-deep/60 bg-silver-soft/70 p-4 dark:border-white/10 dark:bg-white/6"
                                    key={row.value}
                                >
                                    <p className="text-xs font-extrabold uppercase tracking-[0.14em] text-ink-soft dark:text-white/45">
                                        {row.label}
                                    </p>
                                    <strong className="mt-2 block text-2xl font-extrabold">
                                        {row.total}
                                    </strong>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <h3 className="text-lg font-extrabold">
                            Pergerakan Status Pada Periode
                        </h3>
                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            {periodStats.map((row) => (
                                <div
                                    className="flex items-center justify-between gap-3 rounded-xl border border-silver-deep/60 bg-silver-soft/70 p-4 dark:border-white/10 dark:bg-white/6"
                                    key={row.value}
                                >
                                    <span className="text-sm font-extrabold text-ink-soft dark:text-white/62">
                                        {row.label}
                                    </span>
                                    <strong className="text-xl font-extrabold">
                                        {row.total}
                                    </strong>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-[1fr_0.7fr]">
                    <div className="overflow-hidden rounded-2xl border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                            <h3 className="text-lg font-extrabold">
                                Aktivitas Harian
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft dark:bg-white/5">
                                    <tr>
                                        <th className="px-5 py-4">Tanggal</th>
                                        <th className="px-5 py-4">
                                            Total Pelanggan Bergerak
                                        </th>
                                        <th className="px-5 py-4">
                                            Rincian Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {dailyRows.map((row) => (
                                        <tr key={row.tanggal}>
                                            <td className="px-5 py-4 font-extrabold">
                                                {row.tanggal}
                                            </td>
                                            <td className="px-5 py-4">
                                                {row.total}
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {row.statuses.map(
                                                        (status) => (
                                                            <StatusBadge
                                                                key={
                                                                    status.label
                                                                }
                                                            >
                                                                {status.label}:{" "}
                                                                {status.total}
                                                            </StatusBadge>
                                                        ),
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                    {dailyRows.length === 0 && (
                                        <tr>
                                            <td
                                                className="px-5 py-10 text-center font-bold text-ink-soft"
                                                colSpan={3}
                                            >
                                                Belum ada aktivitas lead pada
                                                periode ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <h3 className="text-lg font-extrabold">
                            Sumber Lead Baru
                        </h3>
                        <div className="mt-4 grid gap-3">
                            {sourceRows.map((row) => (
                                <div
                                    className="flex items-center justify-between rounded-xl bg-silver-soft/80 px-4 py-3 dark:bg-white/6"
                                    key={row.label}
                                >
                                    <span className="font-bold text-ink-soft dark:text-white/65">
                                        {row.label}
                                    </span>
                                    <strong>{row.total}</strong>
                                </div>
                            ))}
                            {sourceRows.length === 0 && (
                                <p className="rounded-xl bg-silver-soft/80 px-4 py-4 text-sm font-bold text-ink-soft dark:bg-white/6 dark:text-white/60">
                                    Belum ada pelanggan baru pada periode ini.
                                </p>
                            )}
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                        <h3 className="text-lg font-extrabold">
                            Timeline Aktivitas Terakhir
                        </h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft dark:bg-white/5">
                                <tr>
                                    {[
                                        "Tanggal",
                                        "Pelanggan",
                                        "Dari",
                                        "Ke",
                                        "Pengguna",
                                        "Catatan",
                                    ].map((label) => (
                                        <th className="px-5 py-4" key={label}>
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {timeline.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">
                                            {row.tanggal}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.customer}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.kode_customer}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.status_from}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.status_to}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.user}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.note}
                                        </td>
                                    </tr>
                                ))}
                                {timeline.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center font-bold text-ink-soft"
                                            colSpan={6}
                                        >
                                            Belum ada timeline lead.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Laporan Lead"}>
        {page}
    </AdminLayout>
);
