import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";
export default function Index({ title, rows, filters = {}, canCreate }) {
    const [form, setForm] = useState(filters);
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="flex flex-wrap items-center justify-between rounded-3xl border bg-white p-6">
                    <div>
                        <p className="text-xs font-bold uppercase text-ink-soft">
                            CRM Marketing
                        </p>
                        <h1 className="text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-ink-soft">
                            Lead terpisah dari Customer. Customer hanya dibuat
                            setelah Lead Qualified dan diverifikasi Admin Sales.
                        </p>
                    </div>
                    {canCreate && (
                        <Link
                            href="/admin/marketing/leads/create"
                            className="rounded-xl bg-ink px-4 py-3 font-bold text-white"
                        >
                            Tambah Lead Langsung
                        </Link>
                    )}
                </header>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get("/admin/marketing/leads", form, {
                            preserveState: true,
                        });
                    }}
                    className="grid gap-3 rounded-2xl border bg-white p-4 md:grid-cols-4"
                >
                    <input
                        className="rounded-xl border p-3"
                        placeholder="Nama atau telepon"
                        value={form.search || ""}
                        onChange={(e) =>
                            setForm({ ...form, search: e.target.value })
                        }
                    />
                    <select
                        className="rounded-xl border p-3"
                        value={form.stage || ""}
                        onChange={(e) =>
                            setForm({ ...form, stage: e.target.value })
                        }
                    >
                        <option value="">Semua tahap</option>
                        {[
                            "new",
                            "contacted",
                            "nurturing",
                            "qualified",
                            "postponed",
                            "lost",
                            "converted",
                        ].map((x) => (
                            <option key={x}>{x}</option>
                        ))}
                    </select>
                    <select
                        className="rounded-xl border p-3"
                        value={form.ownership_type || ""}
                        onChange={(e) =>
                            setForm({ ...form, ownership_type: e.target.value })
                        }
                    >
                        <option value="">Semua kepemilikan</option>
                        <option value="marketing">Marketing</option>
                        <option value="company">Perusahaan</option>
                    </select>
                    <button className="rounded-xl bg-ink p-3 font-bold text-white">
                        Filter
                    </button>
                </form>
                <section className="overflow-x-auto rounded-2xl border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-silver-soft text-left">
                            <tr>
                                <th className="p-4">Lead</th>
                                <th className="p-4">Sumber</th>
                                <th className="p-4">Marketing</th>
                                <th className="p-4">Tahap CRM</th>
                                <th className="p-4">Customer</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.data.map((x) => (
                                <tr key={x.id}>
                                    <td className="p-4">
                                        <Link
                                            href={`/admin/marketing/leads/${x.id}`}
                                            className="font-bold text-gold-deep"
                                        >
                                            {x.lead_no} · {x.name}
                                        </Link>
                                        <p>{x.phone || "-"}</p>
                                    </td>
                                    <td className="p-4">
                                        {x.source?.nama_sumber ||
                                            x.source_channel}
                                    </td>
                                    <td className="p-4">
                                        {x.marketing?.name || "Belum dibagi"}
                                    </td>
                                    <td className="p-4 font-bold">{x.stage}</td>
                                    <td className="p-4">
                                        {x.customer
                                            ? `${x.customer.kode_costumer} · ${x.customer.nama}`
                                            : "Belum dikonversi"}
                                    </td>
                                </tr>
                            ))}
                            {!rows.data.length && (
                                <tr>
                                    <td
                                        colSpan="5"
                                        className="p-10 text-center"
                                    >
                                        Belum ada Lead.
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
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Lead"}>{page}</AdminLayout>
);
