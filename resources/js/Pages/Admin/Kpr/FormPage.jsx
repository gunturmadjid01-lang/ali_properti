import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Landmark, Save } from "lucide-react";
import { Button, CurrencyInput, Dropdown, Input, Textarea } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

export default function FormPage({ title, baseUrl, actionUrl, row, banks, statusOptions }) {
    const form = useForm(row);
    const submit = (event) => { event.preventDefault(); form.put(actionUrl); };
    return <><Head title={title} /><div className="mx-auto grid max-w-5xl gap-5 pb-8">
        <section className="rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#27323b] px-6 py-5 text-white shadow-lg"><div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><p className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.16em] text-champagne"><Landmark size={16} /> Pengajuan KPR</p><h1 className="mt-2 text-2xl font-black">{title}</h1><p className="mt-2 text-sm text-white/65">Perbarui bank tujuan, nilai pengajuan, dan status proses KPR.</p></div><Button as={Link} href={baseUrl} variant="outline" className="border-white/20 bg-white/10 text-white"><ArrowLeft size={17} /> Kembali</Button></div></section>
        <form className="grid gap-4" onSubmit={submit}><section className="rounded-2xl border border-silver-deep/60 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/[0.04]"><div className="grid gap-5 md:grid-cols-2">
            <div className="grid gap-2 md:col-span-2"><span className="text-sm font-extrabold">Bank Kredit *</span><Dropdown value={form.data.bank_kredit_id} options={banks} label="Pilih Bank Kredit" onChange={(value) => form.setData("bank_kredit_id", value)} />{form.errors.bank_kredit_id && <span className="text-xs font-bold text-red-600">{form.errors.bank_kredit_id}</span>}</div>
            <Input label="Tanggal Pengajuan" type="date" value={form.data.tanggal_pengajuan} error={form.errors.tanggal_pengajuan} onChange={(event) => form.setData("tanggal_pengajuan", event.target.value)} /><CurrencyInput label="Nilai Pengajuan" required value={form.data.nilai_pengajuan} error={form.errors.nilai_pengajuan} onChange={(value) => form.setData("nilai_pengajuan", value)} />
            <div className="grid gap-2 md:col-span-2"><span className="text-sm font-extrabold">Status KPR *</span><Dropdown value={form.data.status} options={statusOptions} onChange={(value) => form.setData("status", value)} />{form.errors.status && <span className="text-xs font-bold text-red-600">{form.errors.status}</span>}</div><div className="md:col-span-2"><Textarea label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData("catatan", event.target.value)} /></div>
        </div></section><div className="flex justify-end gap-3 rounded-xl border bg-white p-4 dark:border-white/10 dark:bg-graphite"><Button as={Link} href={baseUrl} variant="outline">Batal</Button><Button disabled={form.processing}><Save size={17} /> Simpan Perubahan</Button></div></form>
    </div></>;
}
FormPage.layout = (page) => <AdminLayout title={page?.props?.title ?? "Form KPR"}>{page}</AdminLayout>;
