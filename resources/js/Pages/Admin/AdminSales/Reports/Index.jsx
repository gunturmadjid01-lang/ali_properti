import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Index({ title, summary = [], byAdmin = [], filters = {}, adminSales = [] }) {
    const [form, setForm] = useState(filters);
    const query = new URLSearchParams(Object.entries(form).filter(([, value]) => value)).toString();
    return <><Head title={title} /><div className="grid gap-5">
        <header className="rounded-3xl border bg-white p-6"><h1 className="text-3xl font-black">{title}</h1><p className="mt-2 text-ink-soft">Ringkasan operasional yang dapat ditelusuri ke data lead dan tugas sumber.</p></header>
        <form onSubmit={(e) => { e.preventDefault(); router.get("/admin/admin-sales/laporan", form, { preserveState: true }); }} className="grid gap-3 rounded-2xl border bg-white p-4 md:grid-cols-4">
            <input type="date" className="rounded-xl border p-3" value={form.from || ""} onChange={(e) => setForm({ ...form, from: e.target.value })} />
            <input type="date" className="rounded-xl border p-3" value={form.to || ""} onChange={(e) => setForm({ ...form, to: e.target.value })} />
            <select className="rounded-xl border p-3" value={form.admin_sales_id || ""} onChange={(e) => setForm({ ...form, admin_sales_id: e.target.value })}><option value="">Semua Admin Sales</option>{adminSales.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select>
            <button className="rounded-xl bg-ink p-3 font-bold text-white">Terapkan Filter</button>
        </form>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{summary.map((x) => <div key={x.label} className="rounded-2xl border bg-white p-5"><p className="text-sm text-ink-soft">{x.label}</p><p className="mt-2 text-3xl font-black">{x.value}</p></div>)}</div>
        <div className="flex flex-wrap gap-2"><a href={`/admin/admin-sales/leads/export?${query}`} className="rounded-xl border bg-white px-4 py-3 font-bold">Export Detail Lead CSV</a><a href="/admin/admin-sales/leads" className="rounded-xl border bg-white px-4 py-3 font-bold">Buka Data Lead</a><a href="/admin/admin-sales/tugas" className="rounded-xl border bg-white px-4 py-3 font-bold">Buka Data Tugas</a></div>
        <section className="overflow-x-auto rounded-2xl border bg-white"><table className="min-w-full text-sm"><thead className="bg-silver-soft text-left"><tr><th className="p-4">Admin Sales</th><th className="p-4">Total Tugas</th><th className="p-4">Selesai</th><th className="p-4">Penyelesaian</th></tr></thead><tbody className="divide-y">{byAdmin.map((x) => <tr key={x.id}><td className="p-4 font-bold">{x.name}</td><td className="p-4">{x.total_tasks}</td><td className="p-4">{x.completed_tasks}</td><td className="p-4">{x.total_tasks ? Math.round((x.completed_tasks / x.total_tasks) * 100) : 0}%</td></tr>)}</tbody></table></section>
    </div></>;
}
Index.layout = (page) => <AdminLayout title={page?.props?.title ?? "Laporan Admin Sales"}>{page}</AdminLayout>;
