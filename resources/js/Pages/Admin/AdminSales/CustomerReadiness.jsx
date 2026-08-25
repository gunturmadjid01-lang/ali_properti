import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../Layouts/AdminLayout";

const gaps = [
    ["", "Semua Customer"],
    ["profile", "Profil Belum Lengkap"],
    ["unit", "Tanpa Unit Pilihan"],
    ["payment", "Tanpa Metode Pembayaran"],
    ["documents", "Dokumen Belum Lengkap"],
];

export default function CustomerReadiness({
    title,
    rows,
    filters = {},
    summary = {},
}) {
    const [search, setSearch] = useState(filters.search || "");
    const openFilter = (gap = filters.gap || "") =>
        router.get(
            "/admin/admin-sales/kelengkapan-customer",
            { search, gap },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="rounded-3xl border bg-white p-6">
                    <p className="text-xs font-black uppercase tracking-widest text-gold-deep">
                        Kontrol administrasi penjualan
                    </p>
                    <h1 className="mt-2 text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-sm text-ink-soft">
                        Satu antrean untuk menemukan kekurangan profil, minat
                        unit, metode pembayaran, dan dokumen sebelum Customer
                        melanjutkan transaksi.
                    </p>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        ["Customer Aktif", summary.total, ""],
                        ["Tanpa Unit", summary.without_unit, "unit"],
                        [
                            "Tanpa Metode Pembayaran",
                            summary.without_payment,
                            "payment",
                        ],
                        [
                            "Dokumen Belum Lengkap",
                            summary.documents_incomplete,
                            "documents",
                        ],
                    ].map(([label, count, gap]) => (
                        <button
                            key={label}
                            onClick={() => openFilter(gap)}
                            className="rounded-2xl border bg-white p-5 text-left transition hover:border-gold"
                        >
                            <p className="text-sm text-ink-soft">{label}</p>
                            <p className="mt-2 text-3xl font-black">
                                {count || 0}
                            </p>
                        </button>
                    ))}
                </section>

                <section className="rounded-2xl border bg-white p-5">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            openFilter();
                        }}
                        className="flex flex-wrap gap-3"
                    >
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari nama, kode, telepon, atau NIK..."
                            className="min-w-64 flex-1 rounded-xl border px-4 py-3"
                        />
                        <button className="rounded-xl bg-ink px-5 py-3 font-bold text-white">
                            Cari
                        </button>
                    </form>
                    <nav className="mt-4 flex flex-wrap gap-2">
                        {gaps.map(([value, label]) => (
                            <Link
                                key={value}
                                href={`/admin/admin-sales/kelengkapan-customer?gap=${value}&search=${encodeURIComponent(search)}`}
                                className={`rounded-full border px-4 py-2 text-sm font-bold ${filters.gap === value ? "bg-ink text-white" : ""}`}
                            >
                                {label}
                            </Link>
                        ))}
                    </nav>
                </section>

                <section className="grid gap-4">
                    {rows.data.map((row) => (
                        <article
                            key={row.id}
                            className="rounded-2xl border bg-white p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-xs font-black uppercase tracking-wide text-gold-deep">
                                        {row.code}
                                    </p>
                                    <h2 className="mt-1 text-xl font-black">
                                        {row.name}
                                    </h2>
                                    <p className="text-sm text-ink-soft">
                                        {row.phone || "Tanpa telepon"} ·{" "}
                                        {row.marketing || "Belum ada Marketing"}{" "}
                                        ·{" "}
                                        {row.housing ||
                                            "Perumahan belum dipilih"}
                                    </p>
                                </div>
                                <div className="min-w-44">
                                    <div className="flex justify-between text-sm font-bold">
                                        <span>Kelengkapan</span>
                                        <span>{row.completion}%</span>
                                    </div>
                                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-silver-soft">
                                        <div
                                            className={`h-full ${row.completion >= 80 ? "bg-green-500" : row.completion >= 50 ? "bg-amber-500" : "bg-red-500"}`}
                                            style={{
                                                width: `${row.completion}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            </div>
                            <div className="mt-5 grid gap-4 lg:grid-cols-2">
                                <div>
                                    <h3 className="font-black">
                                        Kekurangan Administrasi
                                    </h3>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {row.missing.map((item) => (
                                            <span
                                                key={item}
                                                className="rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-700"
                                            >
                                                {item}
                                            </span>
                                        ))}
                                        {!row.missing.length && (
                                            <span className="text-sm font-bold text-green-700">
                                                Administrasi utama lengkap
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div>
                                    <h3 className="font-black">
                                        Masalah Dokumen Wajib
                                    </h3>
                                    <div className="mt-2 grid gap-1">
                                        {row.document_problems
                                            .slice(0, 5)
                                            .map((item, index) => (
                                                <p
                                                    key={`${item.name}-${index}`}
                                                    className="text-sm"
                                                >
                                                    <b>{item.name}</b> ·{" "}
                                                    {item.status}
                                                    {item.note
                                                        ? ` · ${item.note}`
                                                        : ""}
                                                </p>
                                            ))}
                                        {!row.document_problems.length && (
                                            <p className="text-sm text-ink-soft">
                                                Tidak ada masalah dokumen rinci
                                                yang tercatat.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div className="mt-5 flex flex-wrap gap-3 border-t pt-4">
                                <Link
                                    href={row.customer_url}
                                    className="rounded-xl bg-ink px-4 py-2 font-bold text-white"
                                >
                                    Buka Detail Customer
                                </Link>
                                <Link
                                    href={row.checklist_url}
                                    className="rounded-xl border px-4 py-2 font-bold"
                                >
                                    Buka Checklist Dokumen
                                </Link>
                                <span className="self-center text-xs text-ink-soft">
                                    Aktivitas terakhir:{" "}
                                    {row.last_activity_at || "belum tercatat"}
                                </span>
                            </div>
                        </article>
                    ))}
                    {!rows.data.length && (
                        <div className="rounded-2xl border bg-white p-10 text-center text-ink-soft">
                            Tidak ada Customer pada filter ini.
                        </div>
                    )}
                </section>

                {rows.links?.length > 3 && (
                    <nav className="flex flex-wrap justify-center gap-2">
                        {rows.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`rounded-lg border px-3 py-2 text-sm ${link.active ? "bg-ink text-white" : "bg-white"}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    key={index}
                                    className="rounded-lg border px-3 py-2 text-sm opacity-40"
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ),
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

CustomerReadiness.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Kelengkapan Customer"}>
        {page}
    </AdminLayout>
);
