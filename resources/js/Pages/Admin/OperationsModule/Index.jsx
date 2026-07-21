import { Head, router } from "@inertiajs/react";
import {
    Banknote,
    CheckCircle2,
    Download,
    Edit3,
    Eye,
    FileText,
    Home,
    LayoutDashboard,
    PlusCircle,
    Printer,
    Search,
    Send,
    ShoppingCart,
    Trash2,
    TrendingUp,
    XCircle,
} from "lucide-react";
import { useMemo, useState } from "react";
import Pagination from "../../../Components/Pagination";
import { FinanceTrendChart } from "../../../Components/Finance/FinanceChart";
import { Button, Dropdown, Input, TableActions } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const titleCase = (value) =>
    String(value ?? "-")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
const formatValue = (value) =>
    value === null || value === ""
        ? "-"
        : typeof value === "boolean"
          ? value
              ? "Aktif"
              : "Tidak Aktif"
          : titleCase(value);
const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
const compactMoney = (value) =>
    new Intl.NumberFormat("id-ID", {
        notation: "compact",
        maximumFractionDigits: 1,
    }).format(Number(value || 0));
const salesMoneyColumns = new Set([
    "sale_price_snapshot",
    "contract_value",
    "financing_amount",
    "estimated_installment",
    "amount",
    "paid_amount",
    "outstanding",
    "minimum_booking_fee",
    "minimum_dp",
    "maximum_financing",
    "administration_fee",
    "contract_fee",
    "minimum_income",
    "approved_limit",
    "disbursed_amount",
    "nilai_pengajuan",
]);
const salesMoneyTokens = [
    "amount",
    "price",
    "fee",
    "income",
    "financing",
    "installment",
    "contract_value",
    "booking",
    "down_payment",
    "minimum_dp",
    "outstanding",
    "paid",
];
const isSalesMoneyColumn = (module, column) => {
    if (module !== "sales") return false;

    const name = String(column?.name ?? "").toLowerCase();
    const label = String(column?.label ?? "").toLowerCase();

    return (
        salesMoneyColumns.has(name) ||
        salesMoneyTokens.some(
            (token) => name.includes(token) || label.includes(token),
        ) ||
        /harga|nominal|biaya|pembiayaan|penghasilan|angsuran|dibayar|tagihan|saldo|uang muka/.test(
            label,
        )
    );
};
const tableValue = (module, column, row) => {
    const value = row[column.name];
    if (value === null || value === undefined || value === "") return "-";
    if (module === "sales" && column.name === "value")
        return row.calculation_type === "fixed"
            ? money(value)
            : `${Number(value || 0).toLocaleString("id-ID")}%`;
    if (isSalesMoneyColumn(module, column)) return money(value);
    return formatValue(value);
};

function MetricCard({ label, value, hint, icon: Icon, tone }) {
    const tones = {
        blue: "bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300",
        green: "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300",
        amber: "bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300",
        violet: "bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300",
    };
    return (
        <div className="rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                        {label}
                    </p>
                    <p className="mt-2 text-2xl font-black">{value}</p>
                    <p className="mt-1 text-xs text-ink-soft">{hint}</p>
                </div>
                <span className={`rounded-xl p-3 ${tones[tone]}`}>
                    <Icon size={20} />
                </span>
            </div>
        </div>
    );
}

function BarList({
    title,
    subtitle,
    rows,
    valueKey = "value",
    valueFormatter = (value) => value,
    color = "bg-blue-500",
}) {
    const maximum = Math.max(
        1,
        ...rows.map((row) => Number(row[valueKey] || 0)),
    );
    return (
        <section className="rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
            <h3 className="font-black">{title}</h3>
            <p className="mt-1 text-xs text-ink-soft">{subtitle}</p>
            <div className="mt-5 space-y-4">
                {rows.map((row) => (
                    <div key={row.key}>
                        <div className="mb-1.5 flex items-center justify-between gap-4 text-sm">
                            <span className="truncate font-bold">
                                {row.label}
                            </span>
                            <span className="shrink-0 font-black">
                                {valueFormatter(row[valueKey])}
                            </span>
                        </div>
                        <div className="h-2.5 overflow-hidden rounded-full bg-silver-soft dark:bg-white/10">
                            <div
                                className={`h-full rounded-full ${color}`}
                                style={{
                                    width: `${Math.max(3, (Number(row[valueKey] || 0) / maximum) * 100)}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
                {!rows.length && (
                    <p className="py-8 text-center text-sm text-ink-soft">
                        Belum ada data pada filter ini.
                    </p>
                )}
            </div>
        </section>
    );
}

function SalesAnalytics({ analytics }) {
    const summary = analytics?.summary ?? {};
    const trend = analytics?.trend ?? [];
    const maxTrend = Math.max(1, ...trend.map((row) => Number(row.count || 0)));
    return (
        <div className="grid gap-5">
            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Total Transaksi"
                    value={summary.total ?? 0}
                    hint="Sesuai filter yang sedang aktif"
                    icon={ShoppingCart}
                    tone="blue"
                />
                <MetricCard
                    label="Penjualan Selesai"
                    value={summary.completed ?? 0}
                    hint="Sudah menyelesaikan seluruh proses"
                    icon={TrendingUp}
                    tone="green"
                />
                <MetricCard
                    label="Nilai Penjualan"
                    value={money(summary.sales_value)}
                    hint="Omzet dari penjualan selesai"
                    icon={Banknote}
                    tone="amber"
                />
                <MetricCard
                    label="Pipeline Menunggu"
                    value={money(summary.pipeline_value)}
                    hint={`${summary.pipeline ?? 0} transaksi masih diproses`}
                    icon={Home}
                    tone="violet"
                />
            </section>
            <FinanceTrendChart
                title="Penjualan Selesai vs Pipeline"
                subtitle="Omzet hanya menghitung transaksi selesai; pipeline menunjukkan potensi transaksi yang masih diproses."
                items={trend}
                series={[
                    {
                        key: "value",
                        label: "Selesai",
                        color: "#2563eb",
                        area: true,
                    },
                    {
                        key: "pipeline_value",
                        label: "Menunggu / Proses",
                        color: "#f59e0b",
                        area: false,
                    },
                ]}
            />
            <section className="grid gap-5 xl:grid-cols-3">
                <div className="rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8 xl:col-span-2">
                    <h3 className="font-black">Jumlah Unit Selesai</h3>
                    <p className="mt-1 text-xs text-ink-soft">
                        Batang menunjukkan unit selesai; angka bawahnya
                        memperlihatkan pipeline.
                    </p>
                    <div className="mt-6 flex h-56 items-end gap-3 overflow-x-auto border-b border-silver-deep/60 px-2 pb-0">
                        {trend.map((row) => (
                            <div
                                className="flex h-full min-w-16 flex-1 flex-col justify-end"
                                key={row.key}
                            >
                                <div className="mb-2 text-center text-[10px] font-bold text-ink-soft">
                                    {row.count} selesai
                                </div>
                                <div
                                    className="group relative mx-auto w-full max-w-16 rounded-t-lg bg-gradient-to-t from-blue-700 to-blue-400 transition hover:from-gold-deep hover:to-amber-400"
                                    style={{
                                        height: `${Math.max(8, (Number(row.count || 0) / maxTrend) * 165)}px`,
                                    }}
                                    title={`${row.label}: ${money(row.value)} · ${row.count} transaksi`}
                                />
                                <div className="py-2 text-center text-[10px] font-bold">
                                    {row.label}
                                    <span className="block text-ink-soft">
                                        {row.count} selesai
                                        <span className="block text-amber-600">
                                            {row.pipeline_count ?? 0} menunggu
                                        </span>
                                    </span>
                                </div>
                            </div>
                        ))}
                        {!trend.length && (
                            <p className="m-auto text-sm text-ink-soft">
                                Belum ada tren pada filter ini.
                            </p>
                        )}
                    </div>
                </div>
                <BarList
                    title="Metode Pembayaran"
                    subtitle="Komposisi seluruh transaksi, termasuk yang masih diproses."
                    rows={analytics?.methods ?? []}
                    valueFormatter={(value) => `${value} trx`}
                    color="bg-violet-500"
                />
                <BarList
                    title="Penjualan per Perumahan"
                    subtitle="Nilai penjualan selesai per proyek."
                    rows={analytics?.housing ?? []}
                    valueFormatter={money}
                    color="bg-amber-500"
                />
                <BarList
                    title="Pipeline per Perumahan"
                    subtitle="Potensi nilai transaksi yang masih menunggu proses."
                    rows={analytics?.housing ?? []}
                    valueKey="pipeline_value"
                    valueFormatter={money}
                    color="bg-violet-500"
                />
                <BarList
                    title="Status Transaksi"
                    subtitle="Distribusi kondisi transaksi saat ini."
                    rows={analytics?.statuses ?? []}
                    valueFormatter={(value) => `${value} trx`}
                    color="bg-emerald-500"
                />
            </section>
        </div>
    );
}

export default function Index({
    title,
    module,
    section,
    sectionTitle,
    baseUrl,
    menu = [],
    fields = [],
    columns = null,
    rows = { data: [], links: [] },
    summary = [],
    dashboardData = null,
    analytics = null,
    filters = {},
    filterOptions = {},
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [advanced, setAdvanced] = useState({
        status: filters.status ?? "",
        payment_method: filters.payment_method ?? "",
        perumahan_id: filters.perumahan_id ?? "",
        date_from: filters.date_from ?? "",
        date_to: filters.date_to ?? "",
    });
    const visibleColumns = useMemo(
        () =>
            columns ??
            fields.slice(0, 7).map((field) => ({
                name: field.name,
                label: field.label,
                sortable: true,
            })),
        [columns, fields],
    );

    const remove = (row) =>
        window.confirm(
            "Arsipkan data ini? Histori transaksi tetap disimpan.",
        ) &&
        router.delete(`${baseUrl}/${section}/records/${row.id}`, {
            preserveScroll: true,
        });
    const transactionSections =
        module === "inventory"
            ? [
                  "loans",
                  "returns",
                  "transfers",
                  "damages",
                  "losses",
                  "stock-opname",
              ]
            : ["replacements", "usage", "maintenance", "damages", "fuel"];
    const isTransaction = transactionSections.includes(section);
    const archiveUrl = (row, action) =>
        `/admin/arsip-transaksi/${module}/${section}/${row.id}/${action}`;
    const decide = (row, action) => {
        const notes = window.prompt(
            action === "approve"
                ? "Catatan approval (opsional)"
                : "Alasan penolakan",
            "",
        );
        if (notes === null) return;
        router.post(
            archiveUrl(row, "decision"),
            { action, notes },
            { preserveScroll: true },
        );
    };
    const sortBy = (key) =>
        router.get(
            `${baseUrl}/${section}`,
            {
                search,
                sort: key,
                direction:
                    filters.sort === key && filters.direction === "asc"
                        ? "desc"
                        : "asc",
            },
            { preserveState: true, preserveScroll: true },
        );
    const isDashboard = section === "dashboard";
    const isReport = section === "reports";

    return (
        <>
            <Head title={`${sectionTitle} - ${title}`} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                        {module === "heavy"
                            ? "Manajemen Aset Alat Berat"
                            : module === "sales"
                              ? "Penjualan dan Pembiayaan Terintegrasi"
                              : "Manajemen Aset Bergerak Perusahaan"}
                    </p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">
                        {module === "sales" ? sectionTitle : title}
                    </h2>
                    <p className="mt-2 text-ink-soft">
                        Modul aset terhubung dengan stok, lokasi, pemakai,
                        proyek, unit rumah, dan histori transaksi.
                    </p>
                </section>

                {module !== "sales" && (
                    <nav className="flex gap-2 overflow-x-auto rounded-lg border border-white/80 bg-white/78 p-3 shadow-soft dark:border-white/10 dark:bg-white/8">
                        {menu.map((item) => (
                            <Button
                                key={item.key}
                                size="sm"
                                variant={
                                    item.key === section ? "primary" : "ghost"
                                }
                                className="shrink-0"
                                onClick={() =>
                                    router.get(`${baseUrl}/${item.key}`)
                                }
                            >
                                {item.key === "dashboard" ? (
                                    <LayoutDashboard size={15} />
                                ) : (
                                    <FileText size={15} />
                                )}
                                {item.label}
                            </Button>
                        ))}
                    </nav>
                )}

                {isDashboard && (
                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {summary.map((card) => (
                            <div
                                className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"
                                key={card.label}
                            >
                                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                                    {card.label}
                                </p>
                                <p className="mt-3 text-3xl font-extrabold">
                                    {card.value}
                                </p>
                            </div>
                        ))}
                    </section>
                )}

                {isDashboard && module === "inventory" && dashboardData && (
                    <section className="grid gap-6 xl:grid-cols-3">
                        <div className="overflow-hidden rounded-xl border border-white/80 bg-white/80 shadow-soft xl:col-span-2">
                            <div className="flex items-center justify-between border-b border-silver-deep/60 px-5 py-4">
                                <div>
                                    <h3 className="font-black">
                                        Pengambilan Aktif
                                    </h3>
                                    <p className="text-xs text-ink-soft">
                                        Barang yang masih berada pada pemakai
                                        atau proyek.
                                    </p>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        router.get(`${baseUrl}/loans`)
                                    }
                                >
                                    Lihat Semua
                                </Button>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-silver-soft/70 text-left text-xs uppercase text-ink-soft">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Transaksi
                                            </th>
                                            <th className="px-4 py-3">
                                                Pengambil
                                            </th>
                                            <th className="px-4 py-3">
                                                Barang Tersisa
                                            </th>
                                            <th className="px-4 py-3">
                                                Tujuan
                                            </th>
                                            <th className="px-4 py-3">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {dashboardData.activeIssues.map(
                                            (issue) => (
                                                <tr
                                                    className="border-t border-silver-deep/50"
                                                    key={issue.id}
                                                >
                                                    <td className="px-4 py-3">
                                                        <button
                                                            className="font-black text-blue-700 hover:underline"
                                                            type="button"
                                                            onClick={() =>
                                                                router.get(
                                                                    `${baseUrl}/loans/records/${issue.id}`,
                                                                )
                                                            }
                                                        >
                                                            {
                                                                issue.transaction_no
                                                            }
                                                        </button>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {issue.taken_by_name ||
                                                            issue.borrower}
                                                    </td>
                                                    <td className="max-w-xs px-4 py-3">
                                                        {issue.item_summary}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {issue.project_name ||
                                                            issue.location_name ||
                                                            "-"}
                                                        {issue.house_number
                                                            ? ` · Unit ${issue.house_number}`
                                                            : ""}
                                                    </td>
                                                    <td
                                                        className={`px-4 py-3 font-bold ${issue.is_overdue ? "text-red-600" : ""}`}
                                                    >
                                                        {issue.is_overdue
                                                            ? "Terlambat"
                                                            : formatValue(
                                                                  issue.status,
                                                              )}
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                        {!dashboardData.activeIssues.length && (
                                            <tr>
                                                <td
                                                    className="px-4 py-8 text-center text-ink-soft"
                                                    colSpan="5"
                                                >
                                                    Tidak ada pengambilan aktif.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div className="overflow-hidden rounded-xl border border-white/80 bg-white/80 shadow-soft">
                            <div className="border-b border-silver-deep/60 px-5 py-4">
                                <h3 className="font-black">Stok per Lokasi</h3>
                                <p className="text-xs text-ink-soft">
                                    Gabungan stok jumlah dan Unit Aset.
                                </p>
                            </div>
                            <div className="divide-y divide-silver-deep/50">
                                {dashboardData.locationBalances.map(
                                    (location) => (
                                        <div
                                            className="flex items-center justify-between px-5 py-3"
                                            key={location.location_name}
                                        >
                                            <div>
                                                <p className="font-bold">
                                                    {location.location_name}
                                                </p>
                                                <p className="text-xs text-ink-soft">
                                                    Tersedia{" "}
                                                    {location.available_stock}
                                                </p>
                                            </div>
                                            <p className="text-xl font-black">
                                                {location.total_stock}
                                            </p>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                        <div className="overflow-hidden rounded-xl border border-white/80 bg-white/80 shadow-soft xl:col-span-3">
                            <div className="border-b border-silver-deep/60 px-5 py-4">
                                <h3 className="font-black">
                                    Peringatan Stok Minimum
                                </h3>
                            </div>
                            <div className="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                                {dashboardData.lowStock.map((item) => (
                                    <button
                                        className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-left"
                                        key={item.id}
                                        type="button"
                                        onClick={() =>
                                            router.get(
                                                `${baseUrl}/items/records/${item.id}`,
                                            )
                                        }
                                    >
                                        <p className="font-black">
                                            {item.name}
                                        </p>
                                        <p className="mt-1 text-sm text-amber-800">
                                            Tersedia {item.available_stock}{" "}
                                            {item.unit} · Minimum{" "}
                                            {item.minimum_stock}
                                        </p>
                                    </button>
                                ))}
                                {!dashboardData.lowStock.length && (
                                    <p className="p-3 text-sm text-ink-soft">
                                        Semua stok berada di atas batas minimum.
                                    </p>
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {isDashboard && module === "heavy" && dashboardData && (
                    <section className="grid gap-6 xl:grid-cols-2">
                        <div className="overflow-hidden rounded-xl border border-white/80 bg-white/80 shadow-soft">
                            <div className="border-b border-silver-deep/60 px-5 py-4">
                                <h3 className="font-black">
                                    Alat Sedang Digunakan
                                </h3>
                            </div>
                            <div className="divide-y divide-silver-deep/50">
                                {dashboardData.activeUsages.map((usage) => (
                                    <button
                                        className="flex w-full items-center justify-between px-5 py-4 text-left hover:bg-silver-soft/50"
                                        key={usage.transaction_no}
                                        type="button"
                                        onClick={() =>
                                            router.get(
                                                `${baseUrl}/equipment/${usage.id}`,
                                            )
                                        }
                                    >
                                        <div>
                                            <p className="font-black">
                                                {usage.equipment_name}
                                            </p>
                                            <p className="text-sm text-ink-soft">
                                                {usage.operator_name} ·{" "}
                                                {usage.project_name || "-"}
                                                {usage.house_number
                                                    ? ` · Unit ${usage.house_number}`
                                                    : ""}
                                            </p>
                                        </div>
                                        <span className="text-xs font-bold">
                                            HM {usage.hour_meter_start}
                                        </span>
                                    </button>
                                ))}
                                {!dashboardData.activeUsages.length && (
                                    <p className="p-8 text-center text-sm text-ink-soft">
                                        Tidak ada alat yang sedang digunakan.
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="overflow-hidden rounded-xl border border-white/80 bg-white/80 shadow-soft">
                            <div className="border-b border-silver-deep/60 px-5 py-4">
                                <h3 className="font-black">
                                    Maintenance Aktif
                                </h3>
                            </div>
                            <div className="divide-y divide-silver-deep/50">
                                {dashboardData.upcomingMaintenance.map(
                                    (maintenance, index) => (
                                        <button
                                            className="flex w-full items-center justify-between px-5 py-4 text-left hover:bg-silver-soft/50"
                                            key={`${maintenance.id}-${index}`}
                                            type="button"
                                            onClick={() =>
                                                router.get(
                                                    `${baseUrl}/equipment/${maintenance.id}`,
                                                )
                                            }
                                        >
                                            <div>
                                                <p className="font-black">
                                                    {maintenance.equipment_name}
                                                </p>
                                                <p className="text-sm text-ink-soft">
                                                    {
                                                        maintenance.maintenance_type
                                                    }
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-bold">
                                                    {maintenance.next_schedule ||
                                                        "-"}
                                                </p>
                                                <p className="text-xs text-ink-soft">
                                                    {formatValue(
                                                        maintenance.status,
                                                    )}
                                                </p>
                                            </div>
                                        </button>
                                    ),
                                )}
                                {!dashboardData.upcomingMaintenance.length && (
                                    <p className="p-8 text-center text-sm text-ink-soft">
                                        Tidak ada maintenance aktif.
                                    </p>
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {isReport && (
                    <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <h3 className="text-xl font-extrabold">
                            Pusat Laporan {title}
                        </h3>
                        <p className="mt-2 text-ink-soft">
                            Buka salah satu menu data atau transaksi, atur
                            pencarian, lalu gunakan tombol PDF atau Excel.
                        </p>
                        <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {menu
                                .filter(
                                    (item) =>
                                        !["dashboard", "reports"].includes(
                                            item.key,
                                        ),
                                )
                                .map((item) => (
                                    <Button
                                        key={item.key}
                                        variant="outline"
                                        onClick={() =>
                                            router.get(`${baseUrl}/${item.key}`)
                                        }
                                    >
                                        <FileText size={16} /> {item.label}
                                    </Button>
                                ))}
                        </div>
                    </section>
                )}

                {module === "sales" &&
                    section === "transactions" &&
                    analytics && <SalesAnalytics analytics={analytics} />}

                {!isDashboard && !isReport && (
                    <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex flex-col gap-4 border-b border-silver-deep/60 p-5 dark:border-white/10 md:flex-row md:items-end md:justify-between">
                            <form
                                className="grid flex-1 gap-2 md:grid-cols-3 xl:grid-cols-6"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    router.get(
                                        `${baseUrl}/${section}`,
                                        { search, ...advanced },
                                        { preserveState: true },
                                    );
                                }}
                            >
                                <Input
                                    className="xl:col-span-2"
                                    label="Pencarian"
                                    value={search}
                                    placeholder={`Cari ${sectionTitle.toLowerCase()}...`}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                                {module === "sales" &&
                                    section === "transactions" && (
                                        <>
                                            <Dropdown
                                                label="Status"
                                                value={advanced.status}
                                                options={[
                                                    {
                                                        value: "",
                                                        label: "Semua status",
                                                    },
                                                    ...(filterOptions.statuses ??
                                                        []),
                                                ]}
                                                onChange={(value) =>
                                                    setAdvanced({
                                                        ...advanced,
                                                        status: value,
                                                    })
                                                }
                                            />
                                            <Dropdown
                                                label="Metode"
                                                value={advanced.payment_method}
                                                options={[
                                                    {
                                                        value: "",
                                                        label: "Semua metode",
                                                    },
                                                    ...(filterOptions.paymentMethods ??
                                                        []),
                                                ]}
                                                onChange={(value) =>
                                                    setAdvanced({
                                                        ...advanced,
                                                        payment_method: value,
                                                    })
                                                }
                                            />
                                            <Dropdown
                                                label="Perumahan"
                                                value={advanced.perumahan_id}
                                                options={[
                                                    {
                                                        value: "",
                                                        label: "Semua perumahan",
                                                    },
                                                    ...(filterOptions.housing ??
                                                        []),
                                                ]}
                                                onChange={(value) =>
                                                    setAdvanced({
                                                        ...advanced,
                                                        perumahan_id: value,
                                                    })
                                                }
                                            />
                                            <div className="grid grid-cols-2 gap-2 xl:col-span-2">
                                                <Input
                                                    label="Dari"
                                                    type="date"
                                                    value={advanced.date_from}
                                                    onChange={(e) =>
                                                        setAdvanced({
                                                            ...advanced,
                                                            date_from:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                                <Input
                                                    label="Sampai"
                                                    type="date"
                                                    value={advanced.date_to}
                                                    onChange={(e) =>
                                                        setAdvanced({
                                                            ...advanced,
                                                            date_to:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </div>
                                        </>
                                    )}
                                <div className="flex items-end gap-2">
                                    <Button>
                                        <Search size={16} /> Terapkan
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setSearch("");
                                            setAdvanced({
                                                status: "",
                                                payment_method: "",
                                                perumahan_id: "",
                                                date_from: "",
                                                date_to: "",
                                            });
                                            router.get(`${baseUrl}/${section}`);
                                        }}
                                    >
                                        Atur Ulang
                                    </Button>
                                </div>
                            </form>
                            <div className="flex flex-wrap gap-2">
                                {permissions.export && (
                                    <>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                window.open(
                                                    `${baseUrl}/${section}/export/pdf`,
                                                    "_blank",
                                                )
                                            }
                                        >
                                            <Download size={16} /> PDF
                                        </Button>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                window.open(
                                                    `${baseUrl}/${section}/export/excel`,
                                                    "_blank",
                                                )
                                            }
                                        >
                                            <Download size={16} /> Excel
                                        </Button>
                                    </>
                                )}
                                {permissions.create && (
                                    <Button
                                        onClick={() =>
                                            router.get(
                                                `${baseUrl}/${section}/create`,
                                            )
                                        }
                                    >
                                        <PlusCircle size={16} /> Tambah Data
                                    </Button>
                                )}
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                                <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                    <tr>
                                        <th className="px-5 py-4">No</th>
                                        {visibleColumns.map((column) => (
                                            <th
                                                className="px-5 py-4"
                                                key={column.name}
                                            >
                                                {column.sortable === false ? (
                                                    <span className="whitespace-nowrap">
                                                        {column.label}
                                                    </span>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        className="whitespace-nowrap hover:text-gold-deep"
                                                        onClick={() =>
                                                            sortBy(column.name)
                                                        }
                                                    >
                                                        {column.label}
                                                        {filters.sort ===
                                                        column.name
                                                            ? filters.direction ===
                                                              "asc"
                                                                ? " ↑"
                                                                : " ↓"
                                                            : ""}
                                                    </button>
                                                )}
                                            </th>
                                        ))}
                                        <th className="px-5 py-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {(rows.data ?? []).map((row, index) => (
                                        <tr key={row.id}>
                                            <td className="px-5 py-4 font-bold">
                                                {index + 1}
                                            </td>
                                            {visibleColumns.map((column) => (
                                                <td
                                                    className={`max-w-sm px-5 py-4 ${isSalesMoneyColumn(module, column) ? "whitespace-nowrap text-right font-extrabold tabular-nums" : ""}`}
                                                    key={column.name}
                                                >
                                                    {tableValue(
                                                        module,
                                                        column,
                                                        row,
                                                    )}
                                                </td>
                                            ))}
                                            <td className="px-5 py-4">
                                                <TableActions>
                                                    {module === "inventory" &&
                                                        [
                                                            "items",
                                                            "units",
                                                            "loans",
                                                        ].includes(section) && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                title="Lihat detail dan riwayat"
                                                                onClick={() =>
                                                                    router.get(
                                                                        `${baseUrl}/${section}/records/${row.id}`,
                                                                    )
                                                                }
                                                            >
                                                                <Eye
                                                                    size={14}
                                                                />
                                                            </Button>
                                                        )}
                                                    {module === "heavy" &&
                                                        section ===
                                                            "equipment" && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                title="Lihat detail dan riwayat"
                                                                onClick={() =>
                                                                    router.get(
                                                                        `${baseUrl}/equipment/${row.id}`,
                                                                    )
                                                                }
                                                            >
                                                                <Eye
                                                                    size={14}
                                                                />
                                                            </Button>
                                                        )}
                                                    {module === "sales" &&
                                                        [
                                                            "transactions",
                                                            "schemes",
                                                            "contracts",
                                                            "developer-products",
                                                            "developer-applications",
                                                            "bank-applications",
                                                            "bank-application-detail",
                                                            "bank-document-validation",
                                                            "bank-slik",
                                                            "bank-appraisal",
                                                            "bank-decision",
                                                            "bank-sp3k",
                                                            "bank-contract-preparation",
                                                            "bank-contract-schedule",
                                                            "bank-contract-execution",
                                                            "bank-disbursement",
                                                            "bank-change",
                                                            "bank-rejections",
                                                            "bank-reports",
                                                        ].includes(section) && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                title="Lihat detail dan tahapan"
                                                                onClick={() =>
                                                                    router.get(
                                                                        `${baseUrl}/${section}/records/${row.id}`,
                                                                    )
                                                                }
                                                            >
                                                                <Eye
                                                                    size={14}
                                                                />
                                                            </Button>
                                                        )}
                                                    {module === "sales" &&
                                                        [
                                                            "transactions",
                                                            "schemes",
                                                            "contracts",
                                                            "developer-products",
                                                            "developer-applications",
                                                            "bank-applications",
                                                            "bank-application-detail",
                                                            "bank-document-validation",
                                                            "bank-slik",
                                                            "bank-appraisal",
                                                            "bank-decision",
                                                            "bank-sp3k",
                                                            "bank-contract-preparation",
                                                            "bank-contract-schedule",
                                                            "bank-contract-execution",
                                                            "bank-disbursement",
                                                            "bank-change",
                                                            "bank-rejections",
                                                            "bank-reports",
                                                        ].includes(section) && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                title="Pratinjau ringkasan ERP"
                                                                onClick={() =>
                                                                    window.open(
                                                                        `${baseUrl}/${section}/records/${row.id}/preview`,
                                                                        "_blank",
                                                                    )
                                                                }
                                                            >
                                                                <Printer
                                                                    size={14}
                                                                />
                                                            </Button>
                                                        )}
                                                    {module === "sales" &&
                                                        [
                                                            "schemes",
                                                            "developer-products",
                                                            "contracts",
                                                            "developer-applications",
                                                        ].includes(section) &&
                                                        row.record_status ===
                                                            "draft" &&
                                                        permissions.submit && (
                                                            <Button
                                                                size="sm"
                                                                title="Finalisasi dan ajukan approval"
                                                                onClick={() =>
                                                                    router.post(
                                                                        `${baseUrl}/${section}/records/${row.id}/lock`,
                                                                    )
                                                                }
                                                            >
                                                                <Send
                                                                    size={14}
                                                                />
                                                                Finalisasi
                                                            </Button>
                                                        )}
                                                    {module === "sales" &&
                                                        [
                                                            "contracts",
                                                            "developer-applications",
                                                        ].includes(section) &&
                                                        row.can_review && (
                                                            <>
                                                                <Button
                                                                    size="sm"
                                                                    title="Setujui tahap aktif"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            `${baseUrl}/${section}/records/${row.id}/review/approve`,
                                                                        )
                                                                    }
                                                                >
                                                                    <CheckCircle2
                                                                        size={
                                                                            14
                                                                        }
                                                                    />
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="text-red-600"
                                                                    title="Tolak"
                                                                    onClick={() => {
                                                                        const note =
                                                                            prompt(
                                                                                "Alasan penolakan",
                                                                            );
                                                                        if (
                                                                            note
                                                                        )
                                                                            router.post(
                                                                                `${baseUrl}/${section}/records/${row.id}/review/reject`,
                                                                                {
                                                                                    note,
                                                                                },
                                                                            );
                                                                    }}
                                                                >
                                                                    <XCircle
                                                                        size={
                                                                            14
                                                                        }
                                                                    />
                                                                </Button>
                                                            </>
                                                        )}
                                                    {section ===
                                                        "stock-opname" &&
                                                        row.status ===
                                                            "draft" &&
                                                        permissions.verify && (
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    router.post(
                                                                        `${baseUrl}/stock-opname/${row.id}/verify`,
                                                                    )
                                                                }
                                                            >
                                                                Verifikasi
                                                            </Button>
                                                        )}
                                                    {isTransaction && (
                                                        <span className="rounded-full bg-silver-soft px-2 py-1 text-[10px] font-extrabold uppercase">
                                                            {row.archive_status ||
                                                                "draft"}
                                                            {row.approval_step
                                                                ? ` · ${row.approval_step}/${row.approval_total_steps}`
                                                                : ""}
                                                        </span>
                                                    )}
                                                    {isTransaction &&
                                                        [
                                                            "draft",
                                                            "rejected",
                                                        ].includes(
                                                            row.archive_status ||
                                                                "draft",
                                                        ) && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                title="Ajukan approval arsip"
                                                                onClick={() =>
                                                                    router.post(
                                                                        archiveUrl(
                                                                            row,
                                                                            "submit",
                                                                        ),
                                                                        {},
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Send
                                                                    size={14}
                                                                />
                                                            </Button>
                                                        )}
                                                    {isTransaction &&
                                                        row.archive_status ===
                                                            "submitted" &&
                                                        row.can_review && (
                                                            <>
                                                                <Button
                                                                    size="sm"
                                                                    title="Setujui"
                                                                    onClick={() =>
                                                                        decide(
                                                                            row,
                                                                            "approve",
                                                                        )
                                                                    }
                                                                >
                                                                    <CheckCircle2
                                                                        size={
                                                                            14
                                                                        }
                                                                    />
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="text-red-600"
                                                                    title="Tolak"
                                                                    onClick={() =>
                                                                        decide(
                                                                            row,
                                                                            "reject",
                                                                        )
                                                                    }
                                                                >
                                                                    <XCircle
                                                                        size={
                                                                            14
                                                                        }
                                                                    />
                                                                </Button>
                                                            </>
                                                        )}
                                                    {isTransaction &&
                                                        permissions.print && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                title={
                                                                    row.archive_status ===
                                                                    "approved"
                                                                        ? "Cetak arsip resmi"
                                                                        : "Pratinjau draft"
                                                                }
                                                                onClick={() =>
                                                                    window.open(
                                                                        archiveUrl(
                                                                            row,
                                                                            "print",
                                                                        ),
                                                                        "_blank",
                                                                    )
                                                                }
                                                            >
                                                                <Printer
                                                                    size={14}
                                                                />
                                                            </Button>
                                                        )}
                                                    {permissions.update && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.get(
                                                                    `${baseUrl}/${section}/records/${row.id}/edit`,
                                                                )
                                                            }
                                                        >
                                                            <Edit3 size={14} />
                                                        </Button>
                                                    )}
                                                    {permissions.delete && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="text-red-600"
                                                            onClick={() =>
                                                                remove(row)
                                                            }
                                                        >
                                                            <Trash2 size={14} />
                                                        </Button>
                                                    )}
                                                </TableActions>
                                            </td>
                                        </tr>
                                    ))}
                                    {!(rows.data ?? []).length && (
                                        <tr>
                                            <td
                                                colSpan={
                                                    visibleColumns.length + 2
                                                }
                                                className="px-5 py-12 text-center font-bold text-ink-soft"
                                            >
                                                Belum ada data.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <Pagination links={rows.links ?? []} />
                    </section>
                )}
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Manajemen Aset"}>
        {page}
    </AdminLayout>
);
