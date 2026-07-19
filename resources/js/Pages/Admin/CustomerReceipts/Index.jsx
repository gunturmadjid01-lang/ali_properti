import { Head, Link, router } from "@inertiajs/react";
import {
    Banknote,
    CalendarDays,
    Check,
    ChevronDown,
    CircleDollarSign,
    Eye,
    FileCheck2,
    FileImage,
    Filter,
    Lock,
    Plus,
    Printer,
    RotateCcw,
    Search,
    ShieldCheck,
    Unlock,
    WalletCards,
    X,
} from "lucide-react";
import { useState } from "react";
import { Button, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { FinanceChart } from "../../../Components/Finance/FinanceChart";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
const date = (value) =>
    value
        ? new Intl.DateTimeFormat("id-ID", { dateStyle: "long" }).format(
              new Date(`${value}T00:00:00`),
          )
        : "-";
const purposeLabels = {
    booking_fee: "Booking Fee",
    down_payment: "Uang Muka / DP",
    invoice_payment: "Pembayaran Tagihan",
    accelerated_payment: "Pelunasan Dipercepat",
    overpayment: "Pembayaran Lebih",
    other: "Penerimaan Lainnya",
};
const methodLabels = {
    transfer: "Transfer Bank",
    cash: "Tunai",
    giro: "Giro",
    virtual_account: "Virtual Account",
    lainnya: "Lainnya",
};
const statusMeta = {
    posted: [
        "Sudah Diposting",
        "bg-emerald-500/12 text-emerald-700 dark:text-emerald-300",
        ShieldCheck,
    ],
    rejected: ["Ditolak", "bg-red-500/12 text-red-700 dark:text-red-300", X],
    pending_approval: [
        "Menunggu Persetujuan",
        "bg-blue-500/12 text-blue-700 dark:text-blue-300",
        Lock,
    ],
    draft: [
        "Draf",
        "bg-slate-500/12 text-slate-700 dark:text-slate-300",
        FileCheck2,
    ],
};

const SelectField = ({ label, value, onChange, children }) => (
    <label className="grid gap-1.5 text-sm font-bold">
        <span>{label}</span>
        <select
            className="h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 dark:border-white/15 dark:bg-slate-950"
            value={value}
            onChange={onChange}
        >
            {children}
        </select>
    </label>
);

export default function Index({
    title,
    rows,
    filters = {},
    summary = {},
    options = {},
    canCreate,
    reservationApprovals = [],
}) {
    const [advanced, setAdvanced] = useState(
        Boolean(
            filters.perumahan_id ||
            filters.creator_id ||
            filters.date_from ||
            filters.date_to ||
            filters.amount_min ||
            filters.amount_max,
        ),
    );
    const [query, setQuery] = useState({
        search: filters.search ?? "",
        status: filters.status ?? "",
        purpose: filters.purpose ?? "",
        method: filters.method ?? "",
        perumahan_id: String(filters.perumahan_id ?? ""),
        creator_id: String(filters.creator_id ?? ""),
        date_from: filters.date_from ?? "",
        date_to: filters.date_to ?? "",
        amount_min: filters.amount_min ?? "",
        amount_max: filters.amount_max ?? "",
    });
    const set = (key, value) =>
        setQuery((current) => ({ ...current, [key]: value }));
    const apply = (event) => {
        event.preventDefault();
        router.get("/admin/keuangan/penerimaan-customer", query, {
            preserveState: true,
            replace: true,
        });
    };
    const reset = () => router.get("/admin/keuangan/penerimaan-customer");
    const post = (url, data = {}) =>
        router.post(url, data, { preserveScroll: true });
    const cards = [
        ["Jumlah Transaksi", summary.count ?? 0, WalletCards, "text-blue-600"],
        [
            "Total Penerimaan",
            money(summary.total),
            CircleDollarSign,
            "text-orange-600",
        ],
        [
            "Sudah Diposting",
            money(summary.posted),
            ShieldCheck,
            "text-emerald-600",
        ],
        [
            "Menunggu Proses",
            money(summary.pending),
            CalendarDays,
            "text-amber-600",
        ],
    ];

    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 p-6 text-white shadow-soft md:p-8">
                    <div className="absolute -right-16 -top-24 h-72 w-72 rounded-full bg-emerald-400/15 blur-3xl" />
                    <div className="relative flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <p className="text-xs font-black uppercase tracking-[0.2em] text-emerald-300">
                                Keuangan & Piutang Pelanggan
                            </p>
                            <h1 className="mt-2 text-3xl font-black">
                                {title}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm leading-6 text-white/65">
                                Catat uang masuk, alokasikan ke invoice, periksa
                                bukti, jalankan persetujuan, lalu cetak kuitansi
                                resmi.
                            </p>
                        </div>
                        {canCreate && (
                            <Button
                                as={Link}
                                href="/admin/keuangan/penerimaan-customer/create"
                                className="bg-emerald-400 text-slate-950 hover:bg-emerald-300"
                            >
                                <Plus size={17} /> Input Penerimaan
                            </Button>
                        )}
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(([label, value, Icon, tone]) => (
                        <article
                            key={label}
                            className="rounded-xl border border-white/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-white/7"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-bold text-ink-soft">
                                        {label}
                                    </p>
                                    <strong className="mt-2 block text-xl">
                                        {value}
                                    </strong>
                                </div>
                                <Icon size={22} className={tone} />
                            </div>
                        </article>
                    ))}
                </section>
                <FinanceChart
                    title="Komposisi Penerimaan Pelanggan"
                    subtitle="Nilai mengikuti seluruh filter yang sedang diterapkan."
                    items={[
                        {
                            label: "Total Penerimaan",
                            value: summary.total,
                            tone: "bg-blue-500",
                        },
                        { label: "Sudah Diposting", value: summary.posted },
                        {
                            label: "Menunggu Proses",
                            value: summary.pending,
                            tone: "bg-amber-500",
                        },
                    ]}
                />

                <section className="rounded-xl border border-white/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-white/7">
                    <form className="grid gap-4" onSubmit={apply}>
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(260px,1fr)_210px_220px_190px_auto] xl:items-end">
                            <Input
                                label="Cari Penerimaan"
                                icon={<Search size={16} />}
                                placeholder="Nomor, transaksi, pelanggan, pengirim..."
                                value={query.search}
                                onChange={(e) => set("search", e.target.value)}
                            />
                            <SelectField
                                label="Status"
                                value={query.status}
                                onChange={(e) => set("status", e.target.value)}
                            >
                                <option value="">Semua status</option>
                                <option value="draft">Draf</option>
                                <option value="pending_approval">
                                    Menunggu persetujuan
                                </option>
                                <option value="posted">Sudah diposting</option>
                                <option value="rejected">Ditolak</option>
                            </SelectField>
                            <SelectField
                                label="Tujuan Pembayaran"
                                value={query.purpose}
                                onChange={(e) => set("purpose", e.target.value)}
                            >
                                <option value="">Semua tujuan</option>
                                {Object.entries(purposeLabels).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </SelectField>
                            <SelectField
                                label="Metode"
                                value={query.method}
                                onChange={(e) => set("method", e.target.value)}
                            >
                                <option value="">Semua metode</option>
                                {Object.entries(methodLabels).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </SelectField>
                            <Button>
                                <Search size={16} /> Terapkan
                            </Button>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setAdvanced(!advanced)}
                            >
                                <Filter size={15} /> Filter Lanjutan{" "}
                                <ChevronDown
                                    size={15}
                                    className={advanced ? "rotate-180" : ""}
                                />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={reset}
                            >
                                <RotateCcw size={15} /> Reset
                            </Button>
                        </div>
                        {advanced && (
                            <div className="grid gap-3 border-t pt-4 md:grid-cols-2 xl:grid-cols-3">
                                <SelectField
                                    label="Perumahan / Proyek"
                                    value={query.perumahan_id}
                                    onChange={(e) =>
                                        set("perumahan_id", e.target.value)
                                    }
                                >
                                    <option value="">Semua perumahan</option>
                                    {(options.perumahans ?? []).map((item) => (
                                        <option
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </option>
                                    ))}
                                </SelectField>
                                <SelectField
                                    label="Petugas Input"
                                    value={query.creator_id}
                                    onChange={(e) =>
                                        set("creator_id", e.target.value)
                                    }
                                >
                                    <option value="">Semua petugas</option>
                                    {(options.creators ?? []).map((item) => (
                                        <option
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </option>
                                    ))}
                                </SelectField>
                                <Input
                                    label="Tanggal Mulai"
                                    type="date"
                                    value={query.date_from}
                                    onChange={(e) =>
                                        set("date_from", e.target.value)
                                    }
                                />
                                <Input
                                    label="Tanggal Sampai"
                                    type="date"
                                    value={query.date_to}
                                    onChange={(e) =>
                                        set("date_to", e.target.value)
                                    }
                                />
                                <Input
                                    label="Nominal Minimum"
                                    type="number"
                                    min="0"
                                    value={query.amount_min}
                                    onChange={(e) =>
                                        set("amount_min", e.target.value)
                                    }
                                />
                                <Input
                                    label="Nominal Maksimum"
                                    type="number"
                                    min="0"
                                    value={query.amount_max}
                                    onChange={(e) =>
                                        set("amount_max", e.target.value)
                                    }
                                />
                            </div>
                        )}
                    </form>
                </section>

                {reservationApprovals.length > 0 && (
                    <section className="grid gap-3 rounded-xl border border-amber-300/70 bg-amber-50/70 p-5 dark:border-amber-400/20 dark:bg-amber-400/5">
                        <div>
                            <p className="text-xs font-black uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">
                                Menunggu verifikasi Keuangan
                            </p>
                            <h2 className="mt-1 text-xl font-black">
                                Booking Fee Reservasi
                            </h2>
                            <p className="mt-1 text-sm text-ink-soft">
                                Periksa bukti dan pastikan dana sudah masuk ke
                                rekening atau Kas Kecil yang tercantum.
                            </p>
                        </div>
                        {reservationApprovals.map((row) => (
                            <article
                                key={`reservation-${row.id}`}
                                className="grid gap-4 rounded-xl border bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/7 xl:grid-cols-[1.2fr_1fr_.8fr_auto] xl:items-center"
                            >
                                <div>
                                    <span className="rounded-full bg-blue-500/12 px-3 py-1 text-xs font-black text-blue-700 dark:text-blue-300">
                                        Menunggu Persetujuan
                                    </span>
                                    <h3 className="mt-3 text-lg font-black">
                                        {row.invoice_no}
                                    </h3>
                                    <p className="font-bold">
                                        {row.customer || "-"}
                                    </p>
                                    <p className="text-sm text-ink-soft">
                                        {row.housing} / {row.unit}
                                    </p>
                                </div>
                                <div className="text-sm">
                                    <p className="text-ink-soft">Pengirim</p>
                                    <p className="font-black">
                                        {row.sender_name || "-"}
                                    </p>
                                    <p className="mt-2 text-ink-soft">
                                        Tujuan dana
                                    </p>
                                    <p className="font-black">
                                        {row.destination || "-"}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs font-bold uppercase text-ink-soft">
                                        Booking Fee
                                    </p>
                                    <p className="text-2xl font-black">
                                        {money(row.amount)}
                                    </p>
                                    <p className="mt-1 text-xs font-bold text-blue-700">
                                        Tahap {row.approval_step}/
                                        {row.approval_total}
                                    </p>
                                </div>
                                {row.can_review &&
                                    row.requires_finance_verification && (
                                        <Button as={Link} href={row.review_url}>
                                            <FileCheck2 size={16} /> Verifikasi
                                        </Button>
                                    )}
                                {row.can_review &&
                                    !row.requires_finance_verification && (
                                        <div className="grid gap-2">
                                            <Button
                                                onClick={() =>
                                                    post(row.approve_url)
                                                }
                                            >
                                                <Check size={16} /> Setujui
                                            </Button>
                                            <Button
                                                variant="danger"
                                                onClick={() => {
                                                    const rejection_note =
                                                        window.prompt(
                                                            "Alasan penolakan reservasi:",
                                                        );
                                                    if (rejection_note?.trim())
                                                        post(row.reject_url, {
                                                            rejection_note:
                                                                rejection_note.trim(),
                                                        });
                                                }}
                                            >
                                                Tolak
                                            </Button>
                                        </div>
                                    )}
                                {!row.can_review && (
                                    <div className="max-w-48 rounded-lg bg-amber-100 px-3 py-2 text-xs font-bold text-amber-800 dark:bg-amber-400/10 dark:text-amber-200">
                                        Tombol verifikasi tersedia untuk role:{" "}
                                        {(row.reviewer_roles ?? []).join(
                                            ", ",
                                        ) || "belum diatur"}
                                    </div>
                                )}
                            </article>
                        ))}
                    </section>
                )}

                <section className="grid gap-3">
                    {(rows.data ?? []).map((row) => {
                        const [statusLabel, statusClass, StatusIcon] =
                            statusMeta[row.status] ?? statusMeta.draft;
                        return (
                            <article
                                key={row.id}
                                className="rounded-xl border border-white/80 bg-white/90 p-5 shadow-soft transition hover:-translate-y-0.5 hover:shadow-lg dark:border-white/10 dark:bg-white/7"
                            >
                                <div className="grid gap-5 xl:grid-cols-[minmax(280px,1.25fr)_minmax(240px,1fr)_minmax(220px,.8fr)_auto] xl:items-center">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span
                                                className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-black ${statusClass}`}
                                            >
                                                <StatusIcon size={13} />
                                                {statusLabel}
                                            </span>
                                            <span className="text-xs font-bold text-ink-soft">
                                                {purposeLabels[row.purpose] ??
                                                    "Penerimaan"}
                                            </span>
                                        </div>
                                        <h2 className="mt-3 text-lg font-black">
                                            {row.receipt_no}
                                        </h2>
                                        <p className="mt-1 font-bold">
                                            {row.customer ||
                                                "Pelanggan tidak tersedia"}
                                        </p>
                                        <p className="text-sm text-ink-soft">
                                            {row.transaction} · {row.housing} /{" "}
                                            {row.unit}
                                        </p>
                                    </div>
                                    <div className="grid gap-2 text-sm">
                                        <p>
                                            <span className="text-ink-soft">
                                                Tanggal diterima
                                            </span>
                                            <strong className="block">
                                                {date(row.date)}
                                            </strong>
                                        </p>
                                        <p>
                                            <span className="text-ink-soft">
                                                Pengirim
                                            </span>
                                            <strong className="block">
                                                {row.sender_name || "-"}{" "}
                                                {row.sender_bank
                                                    ? `· ${row.sender_bank}`
                                                    : ""}
                                            </strong>
                                        </p>
                                        <p className="text-xs text-ink-soft">
                                            Ref: {row.bank_reference || "-"} ·
                                            Input: {row.creator || "-"}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-bold uppercase tracking-wide text-ink-soft">
                                            Total diterima
                                        </p>
                                        <strong className="mt-1 block text-2xl">
                                            {money(row.amount)}
                                        </strong>
                                        <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                            <span>
                                                Invoice:{" "}
                                                <b>{money(row.allocated)}</b>
                                            </span>
                                            <span>
                                                Deposit:{" "}
                                                <b>{money(row.deposit)}</b>
                                            </span>
                                        </div>
                                        <p className="mt-2 text-xs text-ink-soft">
                                            {methodLabels[row.method] ??
                                                row.method}{" "}
                                            · Jurnal:{" "}
                                            {row.journal_no ||
                                                "belum diposting"}
                                        </p>
                                        {row.approval_status === "pending" && (
                                            <p className="mt-2 text-xs font-bold text-blue-600">
                                                Persetujuan tahap{" "}
                                                {row.approval_step}/
                                                {row.approval_total}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2 xl:max-w-[250px] xl:justify-end">
                                        <Button
                                            as={Link}
                                            href={row.preview_url}
                                            target="_blank"
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Eye size={14} /> Detail
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                window.open(
                                                    `${row.preview_url}?print=1`,
                                                    "_blank",
                                                )
                                            }
                                        >
                                            <Printer size={14} /> Cetak
                                        </Button>
                                        {row.proof_url && (
                                            <Button
                                                as="a"
                                                href={row.proof_url}
                                                target="_blank"
                                                size="sm"
                                                variant="outline"
                                            >
                                                <FileImage size={14} /> Bukti
                                            </Button>
                                        )}
                                        {row.can_lock && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    post(
                                                        `/admin/keuangan/penerimaan-customer/${row.id}/lock`,
                                                    )
                                                }
                                            >
                                                <Lock size={14} /> Finalisasi
                                            </Button>
                                        )}
                                        {row.can_unlock && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    post(
                                                        `/admin/keuangan/penerimaan-customer/${row.id}/unlock`,
                                                    )
                                                }
                                            >
                                                <Unlock size={14} /> Buka
                                            </Button>
                                        )}
                                        {row.can_review && (
                                            <>
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        post(
                                                            `/admin/keuangan/penerimaan-customer/${row.id}/review/approve`,
                                                        )
                                                    }
                                                >
                                                    <Check size={14} /> Setujui
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="danger"
                                                    onClick={() => {
                                                        const note =
                                                            window.prompt(
                                                                "Tuliskan alasan penolakan",
                                                            );
                                                        if (note)
                                                            post(
                                                                `/admin/keuangan/penerimaan-customer/${row.id}/review/reject`,
                                                                { note },
                                                            );
                                                    }}
                                                >
                                                    <X size={14} /> Tolak
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                    {!rows.data?.length && (
                        <div className="rounded-xl border border-dashed p-12 text-center">
                            <Banknote
                                className="mx-auto text-ink-soft"
                                size={38}
                            />
                            <h2 className="mt-3 text-lg font-black">
                                Belum ada penerimaan yang sesuai
                            </h2>
                            <p className="mt-1 text-sm text-ink-soft">
                                Ubah filter atau input penerimaan pelanggan
                                baru.
                            </p>
                        </div>
                    )}
                </section>

                {(rows.links ?? []).length > 3 && (
                    <nav className="flex flex-wrap justify-center gap-2">
                        {rows.links.map((link, index) => (
                            <Button
                                key={index}
                                as={link.url ? Link : "button"}
                                href={link.url || undefined}
                                disabled={!link.url}
                                variant={link.active ? "primary" : "outline"}
                                size="sm"
                            >
                                <span
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            </Button>
                        ))}
                    </nav>
                )}
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Penerimaan Pelanggan"}>
        {page}
    </AdminLayout>
);
