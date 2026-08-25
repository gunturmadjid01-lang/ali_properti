import { Head, Link, router } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";
export default function Index({ title, rows, filters = {}, canCreate }) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="flex flex-wrap items-center justify-between rounded-3xl border bg-white p-6">
                    <div>
                        <h1 className="text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-ink-soft">
                            Antrean administrasi dengan PIC, prioritas, tenggat,
                            dan riwayat penyelesaian.
                        </p>
                    </div>
                    {canCreate && (
                        <Link
                            href="/admin/admin-sales/tugas/create"
                            className="rounded-xl bg-ink px-4 py-3 font-bold text-white"
                        >
                            Tambah Tugas
                        </Link>
                    )}
                </header>
                <select
                    className="w-full rounded-xl border p-3 md:w-64"
                    value={filters.status || ""}
                    onChange={(e) =>
                        router.get("/admin/admin-sales/tugas", {
                            status: e.target.value,
                        })
                    }
                >
                    <option value="">Semua status</option>
                    <option value="open">Terbuka</option>
                    <option value="in_progress">Dikerjakan</option>
                    <option value="waiting">Menunggu</option>
                    <option value="completed">Selesai</option>
                    <option value="overdue">Terlambat</option>
                </select>
                <section className="overflow-x-auto rounded-2xl border bg-white">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="bg-silver-soft text-left">
                                <th className="p-4">Nomor/Tugas</th>
                                <th className="p-4">Customer</th>
                                <th className="p-4">PIC</th>
                                <th className="p-4">Tenggat</th>
                                <th className="p-4">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.data.map((x) => (
                                <tr key={x.id}>
                                    <td className="p-4">
                                        <Link
                                            className="font-bold text-gold-deep"
                                            href={`/admin/admin-sales/tugas/${x.id}`}
                                        >
                                            {x.work_no}
                                        </Link>
                                        <p>{x.title}</p>
                                    </td>
                                    <td className="p-4">{x.customer || "-"}</td>
                                    <td className="p-4">{x.assignee || "-"}</td>
                                    <td
                                        className={`p-4 ${x.overdue ? "font-bold text-red-600" : ""}`}
                                    >
                                        {x.due_at || "-"}
                                    </td>
                                    <td className="p-4">{x.status}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Tugas Admin Sales"}>
        {page}
    </AdminLayout>
);
