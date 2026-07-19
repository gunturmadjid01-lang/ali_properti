import { Head, Link, router } from "@inertiajs/react";
import {
    BadgeDollarSign,
    Check,
    Eye,
    FileImage,
    Filter,
    Lock,
    Pencil,
    Plus,
    Printer,
    RotateCcw,
    Search,
    ShieldAlert,
    Undo2,
    Unlock,
    WalletCards,
    X,
} from "lucide-react";
import { useState } from "react";
import { Button, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { FinanceChart } from "../../../Components/Finance/FinanceChart";
const money = (v) => `Rp ${Number(v || 0).toLocaleString("id-ID")}`;
const labels = {
    additional_charge: "Tagihan Tambahan",
    customer_advance: "Talangan Customer",
};
const status = {
    draft: ["Draf", "bg-slate-500/10 text-slate-700 dark:text-slate-300"],
    pending_approval: [
        "Menunggu Persetujuan",
        "bg-blue-500/10 text-blue-700 dark:text-blue-300",
    ],
    posted: [
        "Terposting",
        "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300",
    ],
    rejected: ["Ditolak", "bg-red-500/10 text-red-700 dark:text-red-300"],
    reversed: [
        "Direversal",
        "bg-orange-500/10 text-orange-700 dark:text-orange-300",
    ],
};
const Select = ({ label, children, ...props }) => (
    <label className="grid gap-1.5 text-sm font-bold">
        <span>{label}</span>
        <select
            className="h-11 rounded-lg border border-slate-300 bg-white px-3 dark:border-white/15 dark:bg-slate-950"
            {...props}
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
    permissions = {},
}) {
    const [advanced, setAdvanced] = useState(
        Boolean(
            filters.perumahan_id ||
            filters.due_from ||
            filters.due_to ||
            filters.amount_min ||
            filters.amount_max,
        ),
    );
    const [query, setQuery] = useState({
        search: filters.search ?? "",
        type: filters.type ?? "",
        status: filters.status ?? "",
        category: filters.category ?? "",
        perumahan_id: String(filters.perumahan_id ?? ""),
        due_from: filters.due_from ?? "",
        due_to: filters.due_to ?? "",
        amount_min: filters.amount_min ?? "",
        amount_max: filters.amount_max ?? "",
    });
    const set = (k, v) => setQuery((q) => ({ ...q, [k]: v }));
    const apply = (e) => {
        e.preventDefault();
        router.get("/admin/keuangan/tagihan-talangan-customer", query, {
            preserveState: true,
            replace: true,
        });
    };
    const post = (url, data = {}) =>
        router.post(url, data, { preserveScroll: true });
    const cards = [
        ["Jumlah Dokumen", summary.count, WalletCards, "text-blue-600"],
        [
            "Total Kewajiban",
            money(summary.total),
            BadgeDollarSign,
            "text-amber-600",
        ],
        [
            "Total Talangan",
            money(summary.advance),
            ShieldAlert,
            "text-amber-600",
        ],
        ["Menunggu Proses", money(summary.pending), Lock, "text-slate-600"],
    ];
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="relative overflow-hidden rounded-2xl border border-slate-950 bg-gradient-to-br from-[#20262d] via-[#29313a] to-[#101419] p-7 text-white">
                    <div className="absolute -right-16 -top-24 h-72 w-72 rounded-full bg-amber-400/15 blur-3xl" />
                    <div className="relative flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <p className="text-xs font-black uppercase tracking-[.2em] text-amber-300">
                                Keuangan / Piutang Pelanggan
                            </p>
                            <h1 className="mt-2 text-3xl font-black">
                                {title}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm text-white/65">
                                Biaya di luar kontrak dan dana customer yang
                                terlebih dahulu ditanggung developer, lengkap
                                dengan invoice, jurnal, approval, dan reversal.
                            </p>
                        </div>
                        {permissions.create && (
                            <Button
                                as={Link}
                                href="/admin/keuangan/tagihan-talangan-customer/create"
                                className="border-amber-300 bg-amber-400 text-slate-950 hover:bg-amber-300"
                            >
                                <Plus size={16} /> Tambah Dokumen
                            </Button>
                        )}
                    </div>
                </header>
                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(([l, v, I, c]) => (
                        <article
                            className="rounded-xl border border-white/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-white/7"
                            key={l}
                        >
                            <div className="flex justify-between">
                                <div>
                                    <p className="text-xs font-bold text-ink-soft">
                                        {l}
                                    </p>
                                    <strong className="mt-2 block text-xl">
                                        {v || 0}
                                    </strong>
                                </div>
                                <I className={c} size={22} />
                            </div>
                        </article>
                    ))}
                </section>
                <FinanceChart
                    title="Komposisi Tagihan dan Talangan"
                    subtitle="Nilai mengikuti filter dan perumahan aktif."
                    items={[
                        {
                            label: "Total Kewajiban",
                            value: summary.total,
                            tone: "bg-blue-500",
                        },
                        {
                            label: "Talangan Customer",
                            value: summary.advance,
                            tone: "bg-amber-500",
                        },
                        {
                            label: "Menunggu Proses",
                            value: summary.pending,
                            tone: "bg-red-500",
                        },
                    ]}
                />
                <section className="rounded-xl border border-white/80 bg-white/85 p-5 dark:border-white/10 dark:bg-white/7">
                    <form className="grid gap-4" onSubmit={apply}>
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[1fr_210px_210px_210px_auto] xl:items-end">
                            <Input
                                label="Cari Dokumen"
                                icon={<Search size={16} />}
                                placeholder="Nomor, transaksi, customer, uraian..."
                                value={query.search}
                                onChange={(e) => set("search", e.target.value)}
                            />
                            <Select
                                label="Jenis"
                                value={query.type}
                                onChange={(e) => set("type", e.target.value)}
                            >
                                <option value="">Semua jenis</option>
                                <option value="additional_charge">
                                    Tagihan Tambahan
                                </option>
                                <option value="customer_advance">
                                    Talangan Customer
                                </option>
                            </Select>
                            <Select
                                label="Status"
                                value={query.status}
                                onChange={(e) => set("status", e.target.value)}
                            >
                                <option value="">Semua status</option>
                                {Object.entries(status).map(([v, m]) => (
                                    <option key={v} value={v}>
                                        {m[0]}
                                    </option>
                                ))}
                            </Select>
                            <Select
                                label="Kategori"
                                value={query.category}
                                onChange={(e) =>
                                    set("category", e.target.value)
                                }
                            >
                                <option value="">Semua kategori</option>
                                {(options.categories ?? []).map((v) => (
                                    <option key={v} value={v}>
                                        {v.replaceAll("_", " ")}
                                    </option>
                                ))}
                            </Select>
                            <Button>
                                <Search size={16} /> Terapkan
                            </Button>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => setAdvanced(!advanced)}
                            >
                                <Filter size={15} /> Filter Lanjutan
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() =>
                                    router.get(
                                        "/admin/keuangan/tagihan-talangan-customer",
                                    )
                                }
                            >
                                <RotateCcw size={15} /> Reset
                            </Button>
                        </div>
                        {advanced && (
                            <div className="grid gap-3 border-t pt-4 md:grid-cols-2 xl:grid-cols-3">
                                <Select
                                    label="Perumahan"
                                    value={query.perumahan_id}
                                    onChange={(e) =>
                                        set("perumahan_id", e.target.value)
                                    }
                                >
                                    <option value="">Semua perumahan</option>
                                    {(options.perumahans ?? []).map((i) => (
                                        <option key={i.value} value={i.value}>
                                            {i.label}
                                        </option>
                                    ))}
                                </Select>
                                <Input
                                    label="Jatuh Tempo Mulai"
                                    type="date"
                                    value={query.due_from}
                                    onChange={(e) =>
                                        set("due_from", e.target.value)
                                    }
                                />
                                <Input
                                    label="Jatuh Tempo Sampai"
                                    type="date"
                                    value={query.due_to}
                                    onChange={(e) =>
                                        set("due_to", e.target.value)
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
                <section className="grid gap-3">
                    {(rows.data ?? []).map((row) => {
                        const meta = status[row.status] ?? status.draft;
                        return (
                            <article
                                key={row.id}
                                className="rounded-xl border border-white/80 bg-white/90 p-5 shadow-soft dark:border-white/10 dark:bg-white/7"
                            >
                                <div className="grid gap-5 xl:grid-cols-[1.2fr_1fr_.8fr_auto] xl:items-center">
                                    <div>
                                        <div className="flex flex-wrap gap-2">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-black ${meta[1]}`}
                                            >
                                                {meta[0]}
                                            </span>
                                            <span className="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-black text-amber-700 dark:text-amber-300">
                                                {labels[row.type]}
                                            </span>
                                        </div>
                                        <h2 className="mt-3 text-lg font-black">
                                            {row.charge_no}
                                        </h2>
                                        <p className="font-bold">
                                            {row.customer}
                                        </p>
                                        <p className="text-sm text-ink-soft">
                                            {row.transaction} · {row.housing} /{" "}
                                            {row.unit}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs font-black uppercase text-ink-soft">
                                            {row.category?.replaceAll("_", " ")}
                                        </p>
                                        <p className="mt-1 font-bold">
                                            {row.description}
                                        </p>
                                        <p className="mt-2 text-xs text-ink-soft">
                                            Transaksi {row.charge_date} · Jatuh
                                            tempo {row.due_date}
                                        </p>
                                        {row.type === "customer_advance" && (
                                            <p className="mt-2 text-xs">
                                                Dibayar ke{" "}
                                                <b>{row.paid_to || "-"}</b>
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <strong className="text-2xl">
                                            {money(row.amount)}
                                        </strong>
                                        <p className="mt-1 text-xs text-ink-soft">
                                            Invoice:{" "}
                                            {row.invoice_no ||
                                                "belum terbentuk"}
                                        </p>
                                        <p className="text-xs text-ink-soft">
                                            Jurnal:{" "}
                                            {row.journal_no ||
                                                "belum diposting"}
                                        </p>
                                        {row.approval_status === "pending" && (
                                            <p className="mt-2 text-xs font-bold text-blue-600">
                                                Approval {row.approval_step}/
                                                {row.approval_total}
                                            </p>
                                        )}
                                        {row.reversal_status && (
                                            <p className="mt-2 text-xs font-bold text-orange-600">
                                                Reversal: {row.reversal_status}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap gap-2 xl:max-w-[270px] xl:justify-end">
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
                                        {row.can_edit && (
                                            <Button
                                                as={Link}
                                                href={row.edit_url}
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Pencil size={14} /> Edit
                                            </Button>
                                        )}
                                        {row.can_lock && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    post(
                                                        `/admin/keuangan/tagihan-talangan-customer/${row.id}/lock`,
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
                                                        `/admin/keuangan/tagihan-talangan-customer/${row.id}/unlock`,
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
                                                            `/admin/keuangan/tagihan-talangan-customer/${row.id}/review/approve`,
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
                                                            prompt(
                                                                "Alasan penolakan",
                                                            );
                                                        if (note)
                                                            post(
                                                                `/admin/keuangan/tagihan-talangan-customer/${row.id}/review/reject`,
                                                                { note },
                                                            );
                                                    }}
                                                >
                                                    <X size={14} /> Tolak
                                                </Button>
                                            </>
                                        )}
                                        {row.can_reverse && (
                                            <Button
                                                size="sm"
                                                variant="danger"
                                                onClick={() => {
                                                    const reason = prompt(
                                                        "Alasan reversal wajib diisi",
                                                    );
                                                    if (reason)
                                                        post(
                                                            `/admin/keuangan/tagihan-talangan-customer/${row.id}/reversal`,
                                                            { reason },
                                                        );
                                                }}
                                            >
                                                <Undo2 size={14} /> Reversal
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </article>
                        );
                    })}
                    {!rows.data?.length && (
                        <div className="rounded-xl border border-dashed p-12 text-center">
                            <BadgeDollarSign
                                className="mx-auto text-ink-soft"
                                size={40}
                            />
                            <h2 className="mt-3 text-lg font-black">
                                Belum ada tagihan tambahan atau talangan
                            </h2>
                            <p className="text-sm text-ink-soft">
                                Dokumen yang dibuat akan tampil di sini setelah
                                filter diterapkan.
                            </p>
                        </div>
                    )}
                </section>
                {(rows.links ?? []).length > 3 && (
                    <nav className="flex flex-wrap justify-center gap-2">
                        {rows.links.map((link, i) => (
                            <Button
                                key={i}
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
    <AdminLayout title={page?.props?.title ?? "Tagihan & Talangan Customer"}>
        {page}
    </AdminLayout>
);
