import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Index({ title, rows, filters = {} }) {
    const [form, setForm] = useState(filters);
    const apply = (e) => {
        e.preventDefault();
        router.get("/admin/admin-sales/leads", form, { preserveState: true });
    };
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="flex flex-wrap items-center justify-between rounded-3xl border bg-white p-6">
                    <div>
                        <h1 className="text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-ink-soft">
                            Lead dari kanal resmi perusahaan, lengkap dengan
                            verifikasi, assignment, SLA, dan riwayat.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href={`/admin/admin-sales/leads/export?verification_status=${form.verification_status || ""}&assignment_status=${form.assignment_status || ""}`} className="rounded-xl border px-4 py-3 font-bold">Export CSV</a>
                        <Link href="/admin/admin-sales/leads/duplicates" className="rounded-xl border px-4 py-3 font-bold">Duplikat</Link>
                        <Link href="/admin/admin-sales/leads/import" className="rounded-xl border px-4 py-3 font-bold">Import</Link>
                        <Link href="/admin/admin-sales/leads/create" className="rounded-xl bg-ink px-4 py-3 font-bold text-white">Tambah Lead</Link>
                    </div>
                </header>
                <form
                    onSubmit={apply}
                    className="grid gap-3 rounded-2xl border bg-white p-4 md:grid-cols-4"
                >
                    <input
                        className="rounded-xl border p-3"
                        placeholder="Nama, telepon, email"
                        value={form.search || ""}
                        onChange={(e) =>
                            setForm({ ...form, search: e.target.value })
                        }
                    />
                    <select
                        className="rounded-xl border p-3"
                        value={form.verification_status || ""}
                        onChange={(e) =>
                            setForm({
                                ...form,
                                verification_status: e.target.value,
                            })
                        }
                    >
                        <option value="">Semua verifikasi</option>
                        {[
                            "pending",
                            "verified",
                            "duplicate",
                            "spam",
                            "needs_revision",
                        ].map((x) => (
                            <option key={x}>{x}</option>
                        ))}
                    </select>
                    <select
                        className="rounded-xl border p-3"
                        value={form.assignment_status || ""}
                        onChange={(e) =>
                            setForm({
                                ...form,
                                assignment_status: e.target.value,
                            })
                        }
                    >
                        <option value="">Semua assignment</option>
                        {[
                            "unassigned",
                            "offered",
                            "accepted",
                            "rejected",
                            "responded",
                        ].map((x) => (
                            <option key={x}>{x}</option>
                        ))}
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
                                <th className="p-4">Verifikasi</th>
                                <th className="p-4">Assignment/SLA</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.data.map((x) => (
                                <tr key={x.id}>
                                    <td className="p-4">
                                        <Link
                                            className="font-bold text-gold-deep"
                                            href={`/admin/admin-sales/leads/${x.id}`}
                                        >
                                            {x.code} · {x.name}
                                        </Link>
                                        <p>
                                            {x.phone} · {x.email || "-"}
                                        </p>
                                    </td>
                                    <td className="p-4">
                                        {x.source || "-"}
                                        <p className="text-xs">{x.channel}</p>
                                    </td>
                                    <td className="p-4">
                                        {x.marketing || "Belum dibagi"}
                                    </td>
                                    <td className="p-4">
                                        {x.verification_status}
                                    </td>
                                    <td className="p-4">
                                        {x.assignment_status}
                                        <p className="text-xs">
                                            {x.response_due_at ||
                                                "Belum ada SLA"}
                                        </p>
                                    </td>
                                </tr>
                            ))}
                            {!rows.data.length && (
                                <tr>
                                    <td
                                        colSpan="5"
                                        className="p-10 text-center text-ink-soft"
                                    >
                                        Belum ada lead perusahaan.
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
    <AdminLayout title={page?.props?.title ?? "Lead Perusahaan"}>
        {page}
    </AdminLayout>
);
