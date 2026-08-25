import { Head, Link, router } from "@inertiajs/react";
import { Banknote, Edit3, Eye, LockKeyhole, Plus, Printer, Trash2, UnlockKeyhole } from "lucide-react";
import { Button } from "../../../Components/UI";
import Pagination from "../../../Components/Pagination";
import AdminLayout from "../../../Layouts/AdminLayout";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
export default function Index({ title, rows, createUrl, canCreate, filters = {}, companies = [], summary = {} }) {
    const action = (url, confirmText) => { if (!confirmText || window.confirm(confirmText)) router.post(url); };
    return <><Head title={title}/><section className="rounded-2xl border bg-white/90 p-5 dark:bg-white/5">
        <div className="flex items-center justify-between"><div><h1 className="text-2xl font-black">{title}</h1><p className="text-sm text-ink-soft">Kontrak unit lama maupun dari SPR, dengan pembukuan perusahaan penerima yang terpisah.</p></div>{canCreate && <Button as={Link} href={createUrl}><Plus size={16}/> Tambah</Button>}</div>
        <form className="mt-5 grid gap-2 md:grid-cols-5"><input name="search" defaultValue={filters.search} placeholder="Cari kontrak/customer" className="rounded-lg border p-2"/><select name="company_id" defaultValue={filters.company_id ?? ""} className="rounded-lg border p-2"><option value="">Semua perusahaan</option>{companies.map((company) => <option key={company.id} value={company.id}>{company.nama_cabang}</option>)}</select><select name="status" defaultValue={filters.status} className="rounded-lg border p-2"><option value="">Semua status</option><option value="draft">Draft</option><option value="active">Aktif</option><option value="completed">Selesai</option><option value="cancelled">Dibatalkan</option></select><input type="date" name="from" defaultValue={filters.from ?? ""} className="rounded-lg border p-2"/><Button type="submit">Terapkan</Button></form>
        <div className="mt-4 grid gap-2 md:grid-cols-5">{[["Kontrak", summary.contracts], ["Nilai", money(summary.value)], ["Dibayar", money(summary.paid)], ["Piutang", money(summary.outstanding)], ["Biaya Aktual", money(summary.actual_cost)]].map(([label, value]) => <div key={label} className="rounded-xl bg-slate-50 p-3 dark:bg-white/5"><p className="text-xs font-bold text-ink-soft">{label}</p><p className="font-black">{value}</p></div>)}</div>
        <div className="mt-5 overflow-auto"><table className="w-full min-w-[1250px] text-sm"><thead><tr className="border-b text-left">{["Kontrak","Customer / Unit","Perusahaan","Pekerjaan","Nilai","Tagihan / Bayar","Status","Approval","Aksi"].map((item) => <th className="p-3" key={item}>{item}</th>)}</tr></thead><tbody>{rows.data.map((row) => <tr className="border-b align-top" key={row.id}>
            <td className="p-3"><strong>{row.contract_no}</strong><br/><span className="text-xs">{row.date}</span></td>
            <td className="p-3"><strong>{row.customer}</strong><br/><span className="text-xs">{row.housing} — {row.unit}</span></td>
            <td className="p-3 font-bold">{row.company}</td><td className="p-3">{row.items_count} item</td><td className="p-3 text-right font-black">{money(row.value)}</td>
            <td className="p-3 text-right">{money(row.billed)}<br/><span className="text-emerald-600">Bayar {money(row.paid)}</span></td>
            <td className="p-3">{row.business_status}<br/><span className="text-xs">{row.record_status}</span></td><td className="p-3">{row.approval_stage ?? "Belum diajukan"}</td>
            <td className="p-3"><div className="flex flex-wrap gap-1">
                <Button as={Link} href={row.show_url} size="sm" variant="outline"><Eye size={14}/></Button>
                {row.can_edit && <Button as={Link} href={row.edit_url} size="sm" variant="outline"><Edit3 size={14}/></Button>}
                {row.can_lock && <Button type="button" size="sm" onClick={() => action(row.lock_url, "Finalisasi kontrak dan buat jadwal piutang?")}><LockKeyhole size={14}/></Button>}
                {row.can_unlock && <Button type="button" size="sm" variant="outline" onClick={() => action(row.unlock_url, "Unlock akan membalik invoice dan jurnal yang belum dibayar. Lanjutkan?")}><UnlockKeyhole size={14}/></Button>}
                {row.record_status === "locked" && <Button as="a" href={row.print_url} target="_blank" size="sm" variant="outline"><Printer size={14}/></Button>}
                {row.business_status === "active" && <Button as={Link} href={row.receipt_url} size="sm" variant="outline"><Banknote size={14}/></Button>}
                {row.can_review && <><Button type="button" size="sm" onClick={() => action(row.approve_url)}>Approve</Button><Button type="button" size="sm" variant="outline" onClick={() => { const note = window.prompt("Alasan penolakan"); if (note) router.post(row.reject_url, { note }); }}>Reject</Button></>}
                {row.can_delete && <Button type="button" size="sm" variant="outline" onClick={() => { if (window.confirm("Hapus draft kontrak?")) router.delete(`/admin/keuangan/penambahan-mutu/${row.id}`); }}><Trash2 size={14}/></Button>}
            </div></td>
        </tr>)}</tbody></table></div><Pagination links={rows.links}/></section></>;
}
Index.layout = (page) => <AdminLayout title={page.props.title}>{page}</AdminLayout>;
