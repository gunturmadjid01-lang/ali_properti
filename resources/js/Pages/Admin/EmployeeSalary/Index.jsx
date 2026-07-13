import { Head, Link, router } from "@inertiajs/react";
import { Edit3, PlusCircle, Search, ToggleLeft, ToggleRight } from "lucide-react";
import { useState } from "react";
import { Button, Dropdown, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const rupiah = (value) => new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(Number(value || 0));

function StatusBadge({ status }) {
    const colors = { "Sedang berlaku": "bg-emerald-100 text-emerald-800", Terjadwal: "bg-blue-100 text-blue-800", Riwayat: "bg-slate-100 text-slate-700", Nonaktif: "bg-red-100 text-red-700" };
    return <span className={`rounded-full px-2.5 py-1 text-[11px] font-extrabold ${colors[status] ?? colors.Riwayat}`}>{status}</span>;
}

export default function Index({ title, baseUrl, createUrl, filters, rows }) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [status, setStatus] = useState(filters.status ?? "all");
    const applyFilters = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search, status }, { preserveState: true, replace: true });
    };
    const toggle = (row) => router.patch(`${baseUrl}/${row.id}/status`, { is_active: !row.is_active }, { preserveScroll: true });

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-2xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div><p className="text-xs font-bold uppercase tracking-[0.15em] text-gold-deep">Kepegawaian</p><h1 className="mt-1 text-2xl font-black">{title}</h1><p className="mt-1 max-w-2xl text-sm text-ink-soft dark:text-white/60">Kelola gaji pokok, tunjangan, dan periode kenaikan pegawai tanpa menghapus riwayat.</p></div>
                        <Button as={Link} href={createUrl}><PlusCircle size={18} /> Tambah Periode Gaji</Button>
                    </div>
                </section>
                <section className="overflow-hidden rounded-2xl border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="grid gap-3 border-b border-silver-deep/60 p-5 md:grid-cols-[1fr_220px_auto] md:items-end" onSubmit={applyFilters}>
                        <Input label="Cari pegawai" value={search} placeholder="Nama, NIP, atau jabatan" onChange={(event) => setSearch(event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Status periode</span><Dropdown value={status} options={[{ value: "all", label: "Semua status" }, { value: "active", label: "Aktif" }, { value: "inactive", label: "Nonaktif" }]} onChange={setStatus} /></div>
                        <Button type="submit"><Search size={17} /> Terapkan</Button>
                    </form>
                    <div className="overflow-x-auto"><table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                        <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft dark:bg-white/5"><tr><th className="px-4 py-3">Pegawai</th><th className="px-4 py-3">Gaji & Tunjangan</th><th className="px-4 py-3">Periode</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {rows.data.map((row) => <tr key={row.id} className="hover:bg-silver/50 dark:hover:bg-white/5">
                                <td className="px-4 py-4"><p className="font-extrabold">{row.employee_name}</p><p className="mt-1 text-xs text-ink-soft">{[row.employee_number, row.job_title, row.branch].filter(Boolean).join(" · ") || "-"}</p></td>
                                <td className="px-4 py-4"><p className="font-extrabold text-emerald-700">{rupiah(row.total_salary)}</p><p className="mt-1 text-xs text-ink-soft">Pokok {rupiah(row.basic_salary)} + tunjangan {rupiah(row.fixed_allowance)}</p></td>
                                <td className="px-4 py-4 text-xs font-bold">{row.effective_from} s.d. {row.effective_until ?? "seterusnya"}</td><td className="px-4 py-4"><StatusBadge status={row.period_status} /></td>
                                <td className="px-4 py-4"><div className="flex justify-end gap-2"><Button as={Link} href={row.edit_url} size="sm" variant="outline"><Edit3 size={15} /></Button><Button size="sm" variant="outline" type="button" onClick={() => toggle(row)}>{row.is_active ? <ToggleRight className="text-emerald-600" size={18} /> : <ToggleLeft className="text-red-500" size={18} />}</Button></div></td>
                            </tr>)}
                            {rows.data.length === 0 && <tr><td colSpan="5" className="px-5 py-10 text-center font-bold text-ink-soft">Belum ada pengaturan gaji.</td></tr>}
                        </tbody>
                    </table></div>
                    {rows.links?.length > 3 && <div className="flex flex-wrap justify-end gap-2 border-t p-4">{rows.links.map((link, index) => <Button as={Link} key={index} href={link.url ?? "#"} size="sm" variant={link.active ? "dark" : "outline"} className={!link.url ? "pointer-events-none opacity-40" : ""} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>}
                </section>
            </div>
        </>
    );
}
Index.layout = (page) => <AdminLayout title={page?.props?.title ?? "Pengaturan Gaji"}>{page}</AdminLayout>;
