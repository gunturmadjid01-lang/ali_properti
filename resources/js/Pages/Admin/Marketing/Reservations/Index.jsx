import { Head, Link, router } from "@inertiajs/react";
import {
    BarChart3,
    CalendarDays,
    CircleDollarSign,
    Home,
    Plus,
    Eye,
    Pencil,
    Trash2,
    Lock,
    Printer,
    Ban,
    Users,
} from "lucide-react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import {
    FinanceChart,
    FinanceTrendChart,
} from "../../../../Components/Finance/FinanceChart";
import { Button, Dropdown, TableActions } from "../../../../Components/UI";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
const reservationLabels = {
    draft: "Draft Privat",
    active: "Booking Fee Diterima",
    spr_created: "SPR Dibuat",
    sales_process: "Proses Penjualan",
    handover: "Serah Terima",
    occupied: "Sudah Dihuni",
    completed: "Selesai",
    customer_cancelled: "Batal oleh Customer",
    cancelled: "Dibatalkan Internal",
    expired: "Kedaluwarsa",
};
const paymentLabels = {
    received_pending_approval: "Dana Diterima, Menunggu Approval",
    paid: "Lunas",
    refunded: "Dikembalikan",
};
const months = [
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
];

function Stat({ icon: Icon, label, value, note }) {
    return (
        <div className="rounded-2xl border bg-white p-5">
            <div className="flex items-center gap-3 text-ink-soft">
                <Icon size={20} />
                <span>{label}</span>
            </div>
            <p className="mt-3 text-2xl font-black">{value}</p>
            {note && <p className="mt-1 text-xs text-ink-soft">{note}</p>}
        </div>
    );
}

export default function Index({
    title,
    rows,
    filters,
    statistics,
    chart,
    years,
    canCreate,
    canManage,
}) {
    const apply = (key, value) =>
        router.get(
            "/admin/marketing/reservasi-perumahan",
            { ...filters, [key]: value || undefined },
            { preserveState: true, replace: true },
        );
    const cancel = (row, type) => {
        const reason = prompt(
            type === "customer"
                ? "Alasan pembatalan customer"
                : "Alasan pembatalan internal",
        );
        if (reason)
            router.post(
                `/admin/marketing/reservasi-perumahan/${row.id}/cancel`,
                { reason, type },
                { preserveScroll: true },
            );
    };
    const actionClass =
        "inline-flex min-h-9 items-center gap-2 rounded-lg border border-transparent px-3 text-left text-sm font-semibold hover:border-slate-200 hover:bg-slate-50";
    const dangerActionClass = `${actionClass} text-red-600 hover:border-red-200 hover:bg-red-50`;
    return (
        <AdminLayout>
            <Head title={title} />
            <div className="grid gap-6">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-ink-soft">
                            Pantau penahanan unit, tagihan Booking Fee,
                            pembayaran, dan kelanjutan proses sampai hunian
                            selesai.
                        </p>
                    </div>
                    {canCreate && (
                        <Link href="/admin/marketing/reservasi-perumahan/create">
                            <Button>
                                <Plus size={18} /> Buat Reservasi
                            </Button>
                        </Link>
                    )}
                </header>
                <section className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <Stat
                        icon={Users}
                        label="Reservasi"
                        value={statistics.total}
                    />
                    <Stat
                        icon={Home}
                        label="Masih Berproses"
                        value={statistics.active}
                    />
                    <Stat
                        icon={CalendarDays}
                        label="Selesai"
                        value={statistics.completed}
                    />
                    <Stat
                        icon={Users}
                        label="Batal/Kedaluwarsa"
                        value={statistics.cancelled}
                    />
                    <Stat
                        icon={CircleDollarSign}
                        label="Nilai Tagihan"
                        value={money(statistics.billed)}
                    />
                    <Stat
                        icon={CircleDollarSign}
                        label="Sudah Dibayar"
                        value={money(statistics.paid)}
                        note={`Sisa ${money(statistics.billed - statistics.paid)}`}
                    />
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="flex items-center gap-2 text-lg font-bold">
                                <BarChart3 size={20} /> Statistik Keuangan
                                Booking Fee
                            </h2>
                            <p className="text-sm text-ink-soft">
                                Tagihan dan pembayaran berdasarkan tanggal
                                reservasi.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <select
                                className="rounded-lg border p-2"
                                value={filters.month || ""}
                                onChange={(e) => apply("month", e.target.value)}
                            >
                                <option value="">Semua bulan</option>
                                {months.map((x, i) => (
                                    <option key={x} value={i + 1}>
                                        {x}
                                    </option>
                                ))}
                            </select>
                            <select
                                className="rounded-lg border p-2"
                                value={filters.year}
                                onChange={(e) => apply("year", e.target.value)}
                            >
                                {[...new Set([filters.year, ...years])].map(
                                    (x) => (
                                        <option key={x} value={x}>
                                            {x}
                                        </option>
                                    ),
                                )}
                            </select>
                        </div>
                    </div>
                    <div className="mt-5 grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                        <FinanceTrendChart
                            title="Tren Nominal Booking Fee"
                            subtitle="Nominal tagihan dan pembayaran per periode."
                            items={chart}
                            series={[
                                {
                                    key: "billed",
                                    label: "Tagihan",
                                    color: "#f59e0b",
                                    area: true,
                                },
                                {
                                    key: "paid",
                                    label: "Dibayar",
                                    color: "#10b981",
                                },
                            ]}
                        />
                        <FinanceChart
                            title="Jumlah Reservasi"
                            subtitle="Batang dipakai untuk membandingkan jumlah transaksi."
                            items={chart.map((row) => ({
                                label: row.label,
                                value: row.total,
                            }))}
                            primaryLabel="Transaksi"
                            valueFormatter={(value) => `${value} transaksi`}
                        />
                    </div>
                </section>
                <section className="rounded-2xl border bg-white p-4">
                    <div className="grid gap-2 md:grid-cols-4">
                        <input
                            className="rounded-lg border p-2"
                            placeholder="Cari nomor/customer..."
                            defaultValue={filters.search || ""}
                            onKeyDown={(e) =>
                                e.key === "Enter" &&
                                apply("search", e.currentTarget.value)
                            }
                        />
                        <select
                            className="rounded-lg border p-2"
                            value={filters.status || ""}
                            onChange={(e) => apply("status", e.target.value)}
                        >
                            <option value="">Semua status reservasi</option>
                            {Object.entries(reservationLabels).map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>
                        <select
                            className="rounded-lg border p-2"
                            value={filters.payment_status || ""}
                            onChange={(e) =>
                                apply("payment_status", e.target.value)
                            }
                        >
                            <option value="">Semua status bayar</option>
                            {Object.entries(paymentLabels).map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>
                        <Button
                            variant="secondary"
                            onClick={() =>
                                router.get(
                                    "/admin/marketing/reservasi-perumahan",
                                )
                            }
                        >
                            Reset Filter
                        </Button>
                    </div>
                </section>
                <section className="overflow-x-auto rounded-2xl border bg-white">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b bg-slate-50 text-left">
                                <th className="p-4">Reservasi/Tagihan</th>
                                <th>Customer</th>
                                <th>Perumahan & Unit</th>
                                <th>Nominal</th>
                                <th>Status Bayar</th>
                                <th>Status Reservasi</th>
                                <th className="p-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((row) => (
                                <tr className="border-b align-top" key={row.id}>
                                    <td className="p-4">
                                        <b>{row.reservation_no}</b>
                                        <p>{row.invoice_no}</p>
                                        <small>
                                            {new Date(
                                                row.reserved_at,
                                            ).toLocaleString("id-ID")}
                                        </small>
                                    </td>
                                    <td>
                                        <b>{row.customer?.nama || "-"}</b>
                                        <p>{row.customer?.kode_costumer}</p>
                                    </td>
                                    <td>
                                        <b>
                                            {row.unit?.perumahan
                                                ?.nama_perusahaan || "-"}
                                        </b>
                                        <p>
                                            {row.unit?.kode_nlok}/
                                            {row.unit?.nomor_rumah} · Tipe{" "}
                                            {row.unit?.tipe_rumah}
                                        </p>
                                    </td>
                                    <td>
                                        <b>{money(row.booking_fee)}</b>
                                        <p className="text-emerald-700">
                                            Terbayar {money(row.paid_amount)}
                                        </p>
                                    </td>
                                    <td>
                                        {paymentLabels[row.payment_status] ||
                                            row.payment_status}
                                        <p className="text-xs">
                                            Diterima{" "}
                                            {new Date(
                                                row.payment_submitted_at,
                                            ).toLocaleDateString("id-ID")}
                                        </p>
                                    </td>
                                    <td>
                                        <b>
                                            {reservationLabels[row.status] ||
                                                row.status}
                                        </b>
                                        <p>
                                            {row.process_stage ||
                                                row.spr?.kode_spr ||
                                                "-"}
                                        </p>
                                    </td>
                                    <td className="p-4">
                                        <TableActions
                                            label={`Aksi reservasi ${row.reservation_number || row.id}`}
                                        >
                                            <Link
                                                className={actionClass}
                                                href={row.show_url}
                                                title="Lihat detail"
                                                aria-label="Lihat detail"
                                            >
                                                <Eye size={17} />
                                                <span>Detail</span>
                                            </Link>
                                            {row.invoice_url && (
                                                <Link
                                                    className={actionClass}
                                                    href={row.invoice_url}
                                                    title="Preview dan cetak invoice"
                                                    aria-label="Preview dan cetak invoice"
                                                >
                                                    <Printer size={17} />
                                                    <span>Cetak Invoice</span>
                                                </Link>
                                            )}
                                            {row.can_edit && (
                                                <Link
                                                    className={actionClass}
                                                    href={row.edit_url}
                                                    title="Edit draft"
                                                    aria-label="Edit draft"
                                                >
                                                    <Pencil size={17} />
                                                    <span>Edit Draft</span>
                                                </Link>
                                            )}
                                            {row.can_lock && (
                                                <button
                                                    className={actionClass}
                                                    title="Lock reservasi"
                                                    aria-label="Lock reservasi"
                                                    onClick={() =>
                                                        confirm(
                                                            "Lock reservasi ini? Setelah lock data tidak dapat diedit atau dihapus.",
                                                        ) &&
                                                        router.post(
                                                            `/admin/marketing/reservasi-perumahan/${row.id}/lock`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Lock size={17} />
                                                    <span>Lock Reservasi</span>
                                                </button>
                                            )}
                                            {row.can_delete && (
                                                <button
                                                    className={
                                                        dangerActionClass
                                                    }
                                                    title="Hapus draft"
                                                    aria-label="Hapus draft"
                                                    onClick={() =>
                                                        confirm(
                                                            "Hapus draft reservasi ini?",
                                                        ) &&
                                                        router.delete(
                                                            `/admin/marketing/reservasi-perumahan/${row.id}`,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2 size={17} />
                                                    <span>Hapus Draft</span>
                                                </button>
                                            )}
                                            {row.can_review && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        className="gap-2"
                                                        onClick={() =>
                                                            router.post(
                                                                `/admin/approval/${row.approval_id}/approve`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Lock size={16} />
                                                        Setujui
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        className="border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"
                                                        onClick={() => {
                                                            const note =
                                                                window.prompt(
                                                                    "Alasan penolakan reservasi",
                                                                );
                                                            if (note)
                                                                router.post(
                                                                    `/admin/approval/${row.approval_id}/reject`,
                                                                    {
                                                                        rejection_note:
                                                                            note,
                                                                    },
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                        }}
                                                    >
                                                        <Ban size={16} />
                                                        Tolak
                                                    </Button>
                                                </>
                                            )}
                                            {canManage && row.can_cancel && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            cancel(
                                                                row,
                                                                "customer",
                                                            )
                                                        }
                                                        title="Batalkan oleh customer"
                                                        aria-label="Batalkan oleh customer"
                                                    >
                                                        <Ban size={17} />
                                                        <span>
                                                            Batal oleh Customer
                                                        </span>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        className="border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"
                                                        onClick={() =>
                                                            cancel(
                                                                row,
                                                                "internal",
                                                            )
                                                        }
                                                        title="Batalkan internal"
                                                        aria-label="Batalkan internal"
                                                    >
                                                        <Trash2 size={17} />
                                                        <span>
                                                            Batalkan Internal
                                                        </span>
                                                    </Button>
                                                </>
                                            )}
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
                                        Tidak ada data reservasi pada filter
                                        ini.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
                {rows.links?.length > 3 && (
                    <nav className="flex flex-wrap gap-2">
                        {rows.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url || "#"}
                                preserveScroll
                                className={`rounded-lg border px-3 py-2 text-sm ${link.active ? "bg-slate-900 text-white" : "bg-white"} ${!link.url ? "pointer-events-none opacity-40" : ""}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                )}
            </div>
        </AdminLayout>
    );
}
