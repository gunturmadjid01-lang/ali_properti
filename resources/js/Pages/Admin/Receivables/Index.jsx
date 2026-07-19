import { Head, Link, router } from "@inertiajs/react";
import { BarChart3, Eye, Plus, Search } from "lucide-react";
import { Button, Input, TableActions } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
const money = (v) => `Rp ${Number(v || 0).toLocaleString("id-ID")}`;
const colors = {
    safe: "bg-white dark:bg-white/5",
    warning: "bg-amber-100/80 dark:bg-amber-950/30",
    urgent: "bg-rose-100/80 dark:bg-rose-950/30",
    overdue: "bg-red-200/90 dark:bg-red-950/50",
    paid: "bg-emerald-100/70 dark:bg-emerald-950/30",
};
export default function Index({
    title,
    rows,
    filters,
    summary,
    statistics,
    canCreateReceipt,
}) {
    const search = (e) => {
        e.preventDefault();
        router.get(
            "/admin/keuangan/piutang",
            {
                search: e.currentTarget.search.value,
                period: statistics.period,
                year: statistics.year,
                month: statistics.month,
            },
            { preserveState: true },
        );
    };
    const setStatistics = (changes) =>
        router.get(
            "/admin/keuangan/piutang",
            {
                ...filters,
                period: statistics.period,
                year: statistics.year,
                month: statistics.month,
                ...changes,
            },
            { preserveState: true, preserveScroll: true },
        );
    const chartMax = Math.max(
        1,
        ...statistics.buckets.flatMap((item) => [
            item.bill,
            item.paid,
            item.remaining,
        ]),
    );
    const years = Array.from(
        { length: 11 },
        (_, index) => new Date().getFullYear() - 5 + index,
    );
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <header className="rounded-xl border bg-white/80 p-6 dark:bg-white/8">
                    <h1 className="text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-ink-soft">
                        Satu sumber piutang resmi seluruh transaksi penjualan
                        yang jadwalnya sudah disetujui.
                    </p>
                    {canCreateReceipt && (
                        <Button
                            as={Link}
                            href="/admin/keuangan/penerimaan-customer/create"
                            className="mt-4"
                        >
                            <Plus size={16} />
                            Input Penerimaan
                        </Button>
                    )}
                </header>
                <section className="grid gap-3 md:grid-cols-4">
                    {[
                        ["Total Tagihan", summary.bill],
                        ["Sudah Dibayar", summary.paid],
                        ["Sisa Piutang", summary.remaining],
                        ["Lewat Jatuh Tempo", summary.overdue],
                    ].map(([l, v]) => (
                        <div
                            className="rounded-xl border bg-white/80 p-5 dark:bg-white/8"
                            key={l}
                        >
                            <p className="text-xs font-black uppercase text-ink-soft">
                                {l}
                            </p>
                            <p className="mt-2 text-xl font-black">
                                {money(v)}
                            </p>
                        </div>
                    ))}
                </section>
                <section className="rounded-xl border bg-white/80 p-5 dark:bg-white/8">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 className="flex items-center gap-2 text-xl font-black">
                                <BarChart3 size={20} /> Statistik Uang Piutang
                            </h2>
                            <p className="mt-1 text-sm text-ink-soft">
                                Perbandingan total tagihan, sudah dibayar, dan
                                sisa piutang berdasarkan jatuh tempo.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <select
                                aria-label="Periode statistik"
                                value={statistics.period}
                                onChange={(event) =>
                                    setStatistics({
                                        period: event.target.value,
                                    })
                                }
                                className="rounded-lg border bg-white px-3 py-2 text-sm dark:bg-slate-900"
                            >
                                <option value="daily">Harian</option>
                                <option value="monthly">Bulanan</option>
                                <option value="yearly">Tahunan</option>
                            </select>
                            {statistics.period === "daily" && (
                                <select
                                    aria-label="Bulan statistik"
                                    value={statistics.month}
                                    onChange={(event) =>
                                        setStatistics({
                                            month: Number(event.target.value),
                                        })
                                    }
                                    className="rounded-lg border bg-white px-3 py-2 text-sm dark:bg-slate-900"
                                >
                                    {[
                                        "Januari",
                                        "Februari",
                                        "Maret",
                                        "April",
                                        "Mei",
                                        "Juni",
                                        "Juli",
                                        "Agustus",
                                        "September",
                                        "Oktober",
                                        "November",
                                        "Desember",
                                    ].map((label, index) => (
                                        <option value={index + 1} key={label}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            )}
                            <select
                                aria-label="Tahun statistik"
                                value={statistics.year}
                                onChange={(event) =>
                                    setStatistics({
                                        year: Number(event.target.value),
                                    })
                                }
                                className="rounded-lg border bg-white px-3 py-2 text-sm dark:bg-slate-900"
                            >
                                {years.map((year) => (
                                    <option key={year} value={year}>
                                        {year}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="mt-4 flex flex-wrap gap-4 text-xs font-bold">
                        <span className="flex items-center gap-2">
                            <i className="h-3 w-3 rounded-sm bg-blue-600" />{" "}
                            Total Tagihan
                        </span>
                        <span className="flex items-center gap-2">
                            <i className="h-3 w-3 rounded-sm bg-emerald-500" />{" "}
                            Sudah Dibayar
                        </span>
                        <span className="flex items-center gap-2">
                            <i className="h-3 w-3 rounded-sm bg-amber-500" />{" "}
                            Sisa Piutang
                        </span>
                    </div>
                    <div className="mt-4 max-h-[34rem] space-y-4 overflow-y-auto pr-2">
                        {statistics.buckets.map((item) => (
                            <div
                                key={item.key}
                                className="grid gap-2 border-b pb-4 lg:grid-cols-[180px_1fr]"
                            >
                                <div>
                                    <p className="text-sm font-black capitalize">
                                        {item.label}
                                    </p>
                                    <p className="text-xs text-ink-soft">
                                        Tagihan {money(item.bill)}
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    {[
                                        ["bill", "bg-blue-600"],
                                        ["paid", "bg-emerald-500"],
                                        ["remaining", "bg-amber-500"],
                                    ].map(([key, color]) => (
                                        <div
                                            className="flex items-center gap-2"
                                            key={key}
                                        >
                                            <div className="h-3 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                                <div
                                                    className={`h-full rounded-full ${color}`}
                                                    style={{
                                                        width: `${(item[key] / chartMax) * 100}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="w-32 text-right text-xs font-bold">
                                                {money(item[key])}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
                <form onSubmit={search} className="flex gap-2">
                    <Input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Cari invoice, transaksi, pelanggan..."
                    />
                    <Button>
                        <Search size={16} />
                        Cari
                    </Button>
                </form>
                <section className="overflow-x-auto rounded-xl border bg-white/80 dark:bg-white/8">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase">
                                <th className="p-4">Invoice / Transaksi</th>
                                <th className="p-4">Pelanggan / Unit</th>
                                <th className="p-4">Tagihan</th>
                                <th className="p-4">Jatuh Tempo</th>
                                <th className="p-4">Nilai</th>
                                <th className="p-4">Dibayar / Sisa</th>
                                <th className="p-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((r) => (
                                <tr
                                    key={r.id}
                                    className={`border-t ${colors[r.urgency]}`}
                                >
                                    <td className="p-4">
                                        <b>{r.invoice_no}</b>
                                        <div>{r.reference}</div>
                                    </td>
                                    <td className="p-4">
                                        <b>{r.customer}</b>
                                        <div>
                                            {r.housing} — {r.unit}
                                        </div>
                                    </td>
                                    <td className="p-4">{r.type}</td>
                                    <td className="p-4">
                                        <b>{r.due_date}</b>
                                        <div>
                                            {r.days < 0
                                                ? `${Math.abs(r.days)} hari terlambat`
                                                : `${r.days} hari lagi`}
                                        </div>
                                    </td>
                                    <td className="p-4 font-bold">
                                        {money(r.bill)}
                                    </td>
                                    <td className="p-4">
                                        {money(r.paid)}
                                        <div className="font-bold">
                                            Sisa {money(r.remaining)}
                                        </div>
                                    </td>
                                    <td className="p-4">
                                        <TableActions>
                                            <Button
                                                as={Link}
                                                href={r.invoice_url}
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Eye size={15} />
                                                Pratinjau
                                            </Button>
                                        </TableActions>
                                    </td>
                                </tr>
                            ))}
                            {!rows.data.length && (
                                <tr>
                                    <td
                                        colSpan="7"
                                        className="p-10 text-center"
                                    >
                                        Belum ada piutang resmi.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}
Index.layout = (p) => (
    <AdminLayout title={p?.props?.title ?? "Piutang Pelanggan"}>
        {p}
    </AdminLayout>
);
