import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    AlertTriangle,
    BellRing,
    CalendarClock,
    ChevronDown,
    CircleDollarSign,
    Clock3,
    Eye,
    Filter,
    Plus,
    RotateCcw,
    Search,
    Settings2,
    Users,
} from "lucide-react";
import { useState } from "react";
import { Button, CurrencyInput, Dropdown, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
const methodOptions = [
    { value: "", label: "Semua Metode" },
    { value: "cash_bertahap", label: "Cash Bertahap" },
    { value: "kpr_developer", label: "KPR Developer" },
];
const urgencyOptions = [
    { value: "", label: "Semua Prioritas" },
    { value: "overdue", label: "Sudah Terlambat" },
    { value: "urgent", label: "Segera Jatuh Tempo" },
    { value: "warning", label: "Dalam Peringatan" },
    { value: "upcoming", label: "Jadwal Berikutnya" },
];
const statusOptions = [
    { value: "", label: "Semua Status Bayar" },
    { value: "belum_dibayar", label: "Belum Dibayar" },
    { value: "sebagian", label: "Dibayar Sebagian" },
];
const urgencyStyle = {
    overdue:
        "border-red-300 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200",
    urgent: "border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/10 dark:text-orange-200",
    warning:
        "border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200",
    safe: "border-slate-200 bg-white text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-white/75",
};

function Pager({ links = [] }) {
    return (
        <div className="flex flex-wrap gap-2">
            {links.map((link, index) => (
                <Button
                    key={`${link.label}-${index}`}
                    size="sm"
                    variant={link.active ? "primary" : "outline"}
                    disabled={!link.url}
                    onClick={() =>
                        link.url &&
                        router.get(
                            link.url,
                            {},
                            { preserveScroll: true, preserveState: true },
                        )
                    }
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

export default function DueMonitor({
    title,
    rows,
    filters = {},
    summary = {},
    setting = {},
    options = {},
    canManageSetting,
    canCreateReceipt,
}) {
    const [advanced, setAdvanced] = useState(
        Boolean(
            filters.due_from ||
            filters.due_to ||
            filters.amount_min ||
            filters.amount_max ||
            filters.marketing_id,
        ),
    );
    const [showSetting, setShowSetting] = useState(false);
    const [query, setQuery] = useState({
        search: filters.search ?? "",
        payment_method: filters.payment_method ?? "",
        perumahan_id: String(filters.perumahan_id ?? ""),
        marketing_id: String(filters.marketing_id ?? ""),
        urgency: filters.urgency ?? "",
        payment_status: filters.payment_status ?? "",
        due_from: filters.due_from ?? "",
        due_to: filters.due_to ?? "",
        amount_min: filters.amount_min ?? "",
        amount_max: filters.amount_max ?? "",
        has_remaining: "1",
    });
    const settingForm = useForm({
        warning_days: setting.warning_days ?? 14,
        urgent_days: setting.urgent_days ?? 3,
    });
    const set = (key, value) =>
        setQuery((current) => ({ ...current, [key]: value }));
    const apply = (event) => {
        event.preventDefault();
        router.get("/admin/keuangan/monitoring-jatuh-tempo", query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
    const reset = () => router.get("/admin/keuangan/monitoring-jatuh-tempo");
    const saveSetting = (event) => {
        event.preventDefault();
        settingForm.put("/admin/keuangan/monitoring-jatuh-tempo/pengaturan", {
            preserveScroll: true,
            onSuccess: () => setShowSetting(false),
        });
    };
    const cards = [
        [
            "Tagihan Terpantau",
            summary.count ?? 0,
            CalendarClock,
            "text-blue-600",
        ],
        [
            "Sisa Harus Dibayar",
            money(summary.remaining),
            CircleDollarSign,
            "text-emerald-600",
        ],
        [
            "Sudah Terlambat",
            summary.overdue_count ?? 0,
            AlertTriangle,
            "text-red-600",
        ],
        [
            "Nominal Terlambat",
            money(summary.overdue_amount),
            BellRing,
            "text-orange-600",
        ],
        [
            "Jatuh Tempo 7 Hari",
            summary.due_7_days ?? 0,
            Clock3,
            "text-amber-600",
        ],
    ];

    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 p-6 text-white shadow-soft md:p-8">
                    <div className="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl" />
                    <div className="relative flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <p className="text-xs font-black uppercase tracking-[0.2em] text-blue-300">
                                Keuangan & Piutang Customer
                            </p>
                            <h1 className="mt-2 text-3xl font-black">
                                {title}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm leading-6 text-white/65">
                                Prioritas penagihan Cash Bertahap dan KPR
                                Developer berdasarkan invoice resmi, sisa
                                piutang, serta tanggal jatuh tempo.
                            </p>
                        </div>
                        {canManageSetting && (
                            <Button
                                variant="outline"
                                className="border-white/20 bg-white/10 text-white hover:bg-white/20"
                                onClick={() => setShowSetting(!showSetting)}
                            >
                                <Settings2 size={16} /> Pengaturan Peringatan
                            </Button>
                        )}
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    {cards.map(([label, value, Icon, tone]) => (
                        <article
                            className="rounded-xl border border-white/80 bg-white/85 p-4 shadow-soft dark:border-white/10 dark:bg-white/7"
                            key={label}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-bold text-ink-soft">
                                        {label}
                                    </p>
                                    <strong className="mt-2 block break-words text-xl">
                                        {value}
                                    </strong>
                                </div>
                                <Icon className={tone} size={21} />
                            </div>
                        </article>
                    ))}
                </section>

                {showSetting && canManageSetting && (
                    <section className="rounded-xl border border-blue-200 bg-blue-50/70 p-5 dark:border-blue-400/20 dark:bg-blue-400/8">
                        <div>
                            <h2 className="font-black">
                                Pengaturan Peringatan Jatuh Tempo
                            </h2>
                            <p className="mt-1 text-sm text-ink-soft">
                                Rekomendasi: peringatan 14 hari dan prioritas
                                mendesak 3 hari sebelum jatuh tempo.
                            </p>
                        </div>
                        <form
                            className="mt-4 grid gap-4 md:grid-cols-3 md:items-end"
                            onSubmit={saveSetting}
                        >
                            <Input
                                label="Peringatan Mulai (hari)"
                                type="number"
                                min="1"
                                max="90"
                                value={settingForm.data.warning_days}
                                error={settingForm.errors.warning_days}
                                onChange={(e) =>
                                    settingForm.setData(
                                        "warning_days",
                                        e.target.value,
                                    )
                                }
                            />
                            <Input
                                label="Mendesak Mulai (hari)"
                                type="number"
                                min="0"
                                max="30"
                                value={settingForm.data.urgent_days}
                                error={settingForm.errors.urgent_days}
                                onChange={(e) =>
                                    settingForm.setData(
                                        "urgent_days",
                                        e.target.value,
                                    )
                                }
                            />
                            <Button disabled={settingForm.processing}>
                                Simpan Pengaturan
                            </Button>
                        </form>
                    </section>
                )}

                <section className="rounded-xl border border-white/80 bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-white/7">
                    <form className="grid gap-4" onSubmit={apply}>
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(240px,1fr)_220px_220px_200px_auto]">
                            <Input
                                icon={<Search size={16} />}
                                label="Cari Tagihan"
                                placeholder="Invoice, transaksi, customer, telepon..."
                                value={query.search}
                                onChange={(e) => set("search", e.target.value)}
                            />
                            <Dropdown
                                label="Metode Pembayaran"
                                value={query.payment_method}
                                options={methodOptions}
                                onChange={(value) =>
                                    set("payment_method", value)
                                }
                            />
                            <Dropdown
                                label="Prioritas"
                                value={query.urgency}
                                options={urgencyOptions}
                                onChange={(value) => set("urgency", value)}
                            />
                            <Dropdown
                                label="Status Pembayaran"
                                value={query.payment_status}
                                options={statusOptions}
                                onChange={(value) =>
                                    set("payment_status", value)
                                }
                            />
                            <Button>
                                <Filter size={16} /> Terapkan
                            </Button>
                        </div>
                        <button
                            className="flex items-center gap-2 text-sm font-black text-blue-700 dark:text-blue-300"
                            type="button"
                            onClick={() => setAdvanced(!advanced)}
                        >
                            <ChevronDown
                                className={`transition ${advanced ? "rotate-180" : ""}`}
                                size={16}
                            />{" "}
                            Filter lanjutan
                        </button>
                        {advanced && (
                            <div className="grid gap-3 border-t border-silver-deep/50 pt-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                                <Dropdown
                                    label="Perumahan"
                                    value={query.perumahan_id}
                                    options={[
                                        { value: "", label: "Semua Perumahan" },
                                        ...(options.perumahans ?? []),
                                    ]}
                                    onChange={(value) =>
                                        set("perumahan_id", value)
                                    }
                                />
                                <Dropdown
                                    label="Marketing / PIC"
                                    value={query.marketing_id}
                                    options={[
                                        { value: "", label: "Semua Marketing" },
                                        ...(options.marketings ?? []),
                                    ]}
                                    onChange={(value) =>
                                        set("marketing_id", value)
                                    }
                                />
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
                                <CurrencyInput
                                    label="Sisa Minimum"
                                    value={query.amount_min}
                                    onChange={(value) =>
                                        set("amount_min", value)
                                    }
                                />
                                <CurrencyInput
                                    label="Sisa Maksimum"
                                    value={query.amount_max}
                                    onChange={(value) =>
                                        set("amount_max", value)
                                    }
                                />
                            </div>
                        )}
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={reset}
                            >
                                <RotateCcw size={15} /> Reset Semua Filter
                            </Button>
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-xl border border-white/80 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/7">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-silver-deep/50 p-5 dark:border-white/10">
                        <div>
                            <h2 className="text-lg font-black">
                                Daftar Prioritas Pembayaran
                            </h2>
                            <p className="text-sm text-ink-soft">
                                Diurutkan dari tanggal jatuh tempo terdekat.
                            </p>
                        </div>
                        <span className="rounded-full bg-silver-soft px-3 py-1 text-xs font-black dark:bg-white/10">
                            Peringatan {setting.warning_days} hari · Mendesak{" "}
                            {setting.urgent_days} hari
                        </span>
                    </div>
                    <div className="divide-y divide-silver-deep/50 dark:divide-white/10">
                        {rows.data.map((row) => (
                            <article
                                className="grid gap-4 p-5 xl:grid-cols-[minmax(230px,1.2fr)_minmax(220px,1fr)_160px_190px_auto] xl:items-center"
                                key={row.id}
                            >
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <b>{row.invoice_no}</b>
                                        <span
                                            className={`rounded-full border px-2 py-0.5 text-[11px] font-black ${urgencyStyle[row.urgency] ?? urgencyStyle.safe}`}
                                        >
                                            {row.days < 0
                                                ? `${Math.abs(row.days)} hari terlambat`
                                                : row.days === 0
                                                  ? "Jatuh tempo hari ini"
                                                  : `${row.days} hari lagi`}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-sm text-ink-soft">
                                        {row.reference} · {row.method_label}
                                    </p>
                                    <p className="text-sm font-bold">
                                        {row.type}
                                    </p>
                                </div>
                                <div>
                                    <p className="font-black">{row.customer}</p>
                                    <p className="text-sm text-ink-soft">
                                        {row.phone || "-"} · {row.housing} /{" "}
                                        {row.unit}
                                    </p>
                                    <p className="mt-1 text-xs font-bold text-ink-soft">
                                        <Users
                                            className="mr-1 inline"
                                            size={13}
                                        />
                                        {row.marketing || "Belum ada marketing"}
                                    </p>
                                </div>
                                <div>
                                    <small className="font-bold uppercase text-ink-soft">
                                        Jatuh Tempo
                                    </small>
                                    <p className="mt-1 font-black">
                                        {row.due_date}
                                    </p>
                                </div>
                                <div>
                                    <small className="font-bold uppercase text-ink-soft">
                                        Sisa Pembayaran
                                    </small>
                                    <p className="mt-1 text-lg font-black">
                                        {money(row.remaining)}
                                    </p>
                                    <p className="text-xs text-ink-soft">
                                        dari {money(row.bill)}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        as={Link}
                                        href={row.invoice_url}
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Eye size={14} /> Invoice
                                    </Button>
                                    {canCreateReceipt && (
                                        <Button
                                            as={Link}
                                            href={row.receipt_url}
                                            size="sm"
                                        >
                                            <Plus size={14} /> Input Bayar
                                        </Button>
                                    )}
                                </div>
                            </article>
                        ))}
                        {!rows.data.length && (
                            <div className="p-12 text-center">
                                <CalendarClock
                                    className="mx-auto text-ink-soft"
                                    size={38}
                                />
                                <h3 className="mt-3 font-black">
                                    Tidak ada tagihan sesuai filter
                                </h3>
                                <p className="mt-1 text-sm text-ink-soft">
                                    Ubah rentang atau reset filter untuk melihat
                                    data lain.
                                </p>
                            </div>
                        )}
                    </div>
                </section>
                <Pager links={rows.links} />
            </div>
        </>
    );
}

DueMonitor.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Monitoring Jatuh Tempo"}>
        {page}
    </AdminLayout>
);
