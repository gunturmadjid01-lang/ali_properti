import { Head, Link, useForm } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Import({ title, batches = [] }) {
    const { data, setData, post, processing, errors } = useForm({ file: null });
    return <>
        <Head title={title} />
        <div className="grid gap-5">
            <header className="rounded-3xl border bg-white p-6">
                <h1 className="text-3xl font-black">{title}</h1>
                <p className="mt-2 text-ink-soft">Unggah CSV atau Excel (.xlsx). Data duplikat dan tidak valid ditahan untuk diperiksa, bukan langsung masuk ke customer.</p>
            </header>
            <form onSubmit={(e) => { e.preventDefault(); post("/admin/admin-sales/leads/import", { forceFormData: true }); }} className="grid gap-4 rounded-2xl border bg-white p-6">
                <a href="/admin/admin-sales/leads/import/template" className="font-bold text-gold-deep">Unduh template CSV</a>
                <input type="file" accept=".csv,.txt,.xlsx" onChange={(e) => setData("file", e.target.files[0])} className="rounded-xl border p-3" />
                <small className="text-red-600">{errors.file}</small>
                <button disabled={processing || !data.file} className="rounded-xl bg-ink p-3 font-bold text-white">{processing ? "Memproses..." : "Import dan Periksa"}</button>
            </form>
            <section className="overflow-x-auto rounded-2xl border bg-white">
                <table className="min-w-full text-sm"><thead className="bg-silver-soft text-left"><tr><th className="p-4">Batch</th><th className="p-4">File</th><th className="p-4">Hasil</th></tr></thead>
                    <tbody className="divide-y">{batches.map((x) => <tr key={x.id}><td className="p-4"><Link href={`/admin/admin-sales/leads/duplicates?batch_id=${x.id}`} className="font-bold text-gold-deep">{x.batch_no}</Link></td><td className="p-4">{x.original_filename}</td><td className="p-4">{x.imported_rows} masuk · {x.duplicate_rows} duplikat · {x.invalid_rows} tidak valid</td></tr>)}</tbody>
                </table>
            </section>
        </div>
    </>;
}
Import.layout = (page) => <AdminLayout title={page?.props?.title ?? "Import Lead"}>{page}</AdminLayout>;
