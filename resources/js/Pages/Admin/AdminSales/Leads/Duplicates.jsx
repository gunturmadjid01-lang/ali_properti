import { Head, Link, router, useForm } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";

function Decision({ row }) {
    const form = useForm({
        decision: "existing",
        reason: "",
        telepon: row.phone || "",
        email: row.email || "",
        nik: row.payload?.nik || "",
    });
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/admin/admin-sales/leads/intake/${row.id}/resolve`);
            }}
            className="grid min-w-72 gap-2"
        >
            <select
                className="rounded-lg border p-2"
                value={form.data.decision}
                onChange={(event) =>
                    form.setData("decision", event.target.value)
                }
            >
                <option value="existing">Gunakan data yang sudah ada</option>
                <option value="distinct">Data berbeda</option>
                <option value="discard">Buang/spam</option>
            </select>
            {form.data.decision === "distinct" && (
                <div className="grid gap-2">
                    <input
                        className="rounded-lg border p-2"
                        placeholder="Telepon pembeda"
                        value={form.data.telepon}
                        onChange={(event) =>
                            form.setData("telepon", event.target.value)
                        }
                    />
                    <input
                        className="rounded-lg border p-2"
                        placeholder="Email pembeda"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData("email", event.target.value)
                        }
                    />
                    <input
                        className="rounded-lg border p-2"
                        placeholder="NIK pembeda"
                        value={form.data.nik}
                        onChange={(event) =>
                            form.setData("nik", event.target.value)
                        }
                    />
                </div>
            )}
            <textarea
                className="rounded-lg border p-2"
                placeholder="Alasan wajib"
                value={form.data.reason}
                onChange={(event) => form.setData("reason", event.target.value)}
            />
            <small className="text-red-600">
                {form.errors.reason || form.errors.message}
            </small>
            <button
                disabled={form.processing}
                className="rounded-lg bg-ink p-2 font-bold text-white"
            >
                Simpan keputusan
            </button>
        </form>
    );
}

export default function Duplicates({
    title,
    rows,
    directOverrides = [],
    filters = {},
}) {
    const changeStatus = (status) =>
        router.get(
            "/admin/admin-sales/leads/duplicates",
            { ...filters, status },
            { preserveState: true },
        );
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="flex flex-wrap items-center justify-between gap-3 rounded-3xl border bg-white p-6">
                    <div>
                        <h1 className="text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-ink-soft">
                            Satu pusat pemeriksaan untuk intake dan Lead
                            langsung yang terindikasi serupa.
                        </p>
                    </div>
                    <Link
                        href="/admin/admin-sales/leads/import"
                        className="rounded-xl bg-ink px-4 py-3 font-bold text-white"
                    >
                        Import Baru
                    </Link>
                </header>
                <section className="overflow-x-auto rounded-2xl border bg-white">
                    <div className="border-b p-4">
                        <h2 className="text-xl font-black">
                            Keputusan data berbeda dari input langsung
                        </h2>
                        <p className="text-sm text-ink-soft">
                            Relasi kandidat, PIC, pemeriksa, waktu, dan alasan
                            tersimpan sebagai audit.
                        </p>
                    </div>
                    <table className="min-w-full text-sm">
                        <thead className="bg-silver-soft text-left">
                            <tr>
                                <th className="p-4">Lead baru</th>
                                <th className="p-4">Kandidat lama</th>
                                <th className="p-4">PIC / Pemeriksa</th>
                                <th className="p-4">Alasan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {directOverrides.map((item) => (
                                <tr key={item.id}>
                                    <td className="p-4">
                                        <Link
                                            className="font-bold text-gold-deep"
                                            href={`/admin/marketing/leads/${item.id}`}
                                        >
                                            {item.lead_no} · {item.name}
                                        </Link>
                                        <p>{item.phone || "-"}</p>
                                    </td>
                                    <td className="p-4">
                                        <Link
                                            className="font-bold"
                                            href={`/admin/marketing/leads/${item.possible_duplicate?.id}`}
                                        >
                                            {item.possible_duplicate?.lead_no} ·{" "}
                                            {item.possible_duplicate?.name}
                                        </Link>
                                        <p>
                                            {item.possible_duplicate?.stage ||
                                                "-"}
                                        </p>
                                    </td>
                                    <td className="p-4">
                                        <p>
                                            {item.marketing?.name ||
                                                "Belum dibagi"}
                                        </p>
                                        <small>
                                            {item.duplicate_checker?.name ||
                                                "Sistem"}
                                        </small>
                                    </td>
                                    <td className="p-4">
                                        {item.duplicate_override_reason || "-"}
                                    </td>
                                </tr>
                            ))}
                            {!directOverrides.length && (
                                <tr>
                                    <td
                                        colSpan="4"
                                        className="p-8 text-center text-ink-soft"
                                    >
                                        Belum ada override duplikat dari input
                                        langsung.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
                <select
                    className="max-w-xs rounded-xl border bg-white p-3"
                    value={filters.status || "duplicate"}
                    onChange={(event) => changeStatus(event.target.value)}
                >
                    {[
                        "duplicate",
                        "invalid",
                        "imported",
                        "resolved_distinct",
                        "resolved_existing",
                    ].map((item) => (
                        <option key={item}>{item}</option>
                    ))}
                </select>
                <section className="overflow-x-auto rounded-2xl border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-silver-soft text-left">
                            <tr>
                                <th className="p-4">Data masuk</th>
                                <th className="p-4">Data serupa</th>
                                <th className="p-4">Catatan</th>
                                <th className="p-4">Keputusan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.data.map((item) => (
                                <tr key={item.id}>
                                    <td className="p-4 align-top">
                                        <b>{item.name || "-"}</b>
                                        <p>{item.phone || "-"}</p>
                                        <p>{item.email || "-"}</p>
                                    </td>
                                    <td className="p-4 align-top">
                                        {item.duplicate_lead ? (
                                            <>
                                                <Link
                                                    className="font-bold text-gold-deep"
                                                    href={`/admin/marketing/leads/${item.duplicate_lead.id}`}
                                                >
                                                    {
                                                        item.duplicate_lead
                                                            .lead_no
                                                    }{" "}
                                                    · {item.duplicate_lead.name}
                                                </Link>
                                                <p>
                                                    {item.duplicate_lead
                                                        .marketing?.name ||
                                                        "Belum ditugaskan"}
                                                </p>
                                                <p>
                                                    Tahap:{" "}
                                                    {item.duplicate_lead.stage}
                                                </p>
                                            </>
                                        ) : item.duplicate_customer ? (
                                            <>
                                                <Link
                                                    className="font-bold text-gold-deep"
                                                    href={`/admin/marketing/calon-konsumen/${item.duplicate_customer.id}`}
                                                >
                                                    {
                                                        item.duplicate_customer
                                                            .kode_costumer
                                                    }{" "}
                                                    ·{" "}
                                                    {
                                                        item.duplicate_customer
                                                            .nama
                                                    }
                                                </Link>
                                                <p>
                                                    Customer sudah terkonversi
                                                </p>
                                            </>
                                        ) : (
                                            "Tidak ditemukan"
                                        )}
                                    </td>
                                    <td className="p-4 align-top">
                                        {item.validation_note || "-"}
                                    </td>
                                    <td className="p-4 align-top">
                                        {["duplicate", "invalid"].includes(
                                            item.status,
                                        ) ? (
                                            <Decision row={item} />
                                        ) : (
                                            item.status
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {!rows.data.length && (
                                <tr>
                                    <td
                                        colSpan="4"
                                        className="p-10 text-center text-ink-soft"
                                    >
                                        Tidak ada data pada status ini.
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
Duplicates.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Duplikat Lead"}>
        {page}
    </AdminLayout>
);
