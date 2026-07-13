import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, FileSignature, KeyRound, Save } from "lucide-react";
import { Button, Dropdown, Input, Textarea } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function FormPage({ title, type, baseUrl, actionUrl, method, submissionOptions, initialData }) {
    const form = useForm(initialData);
    const submit = (event) => { event.preventDefault(); form.post(actionUrl, { forceFormData: true }); };
    const Icon = type === "akad" ? FileSignature : KeyRound;
    return <><Head title={title} /><div className="mx-auto grid max-w-5xl gap-5 pb-8">
        <section className="rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#27323b] px-6 py-5 text-white shadow-lg"><div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><p className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.16em] text-champagne"><Icon size={16} /> {type === "akad" ? "Proses Akad KPR" : "Proses Serah Terima"}</p><h1 className="mt-2 text-2xl font-black">{title}</h1><p className="mt-2 text-sm text-white/65">{type === "akad" ? "Catat pelaksanaan akad kredit secara terpisah dari pengajuan KPR." : "Catat penyerahan unit setelah akad selesai."}</p></div><Button as={Link} href={baseUrl} variant="outline" className="border-white/20 bg-white/10 text-white"><ArrowLeft size={17} /> Kembali</Button></div></section>
        <form className="grid gap-4" onSubmit={submit}><section className="rounded-2xl border border-silver-deep/60 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/[0.04]"><div className="grid gap-5 md:grid-cols-2">
            {!method.includes("put") && <div className="grid gap-2 md:col-span-2"><span className="text-sm font-extrabold">Customer / KPR *</span><Dropdown value={form.data.kpr_submission_id} options={submissionOptions} label="Pilih customer siap diproses" onChange={(value) => form.setData("kpr_submission_id", value)} />{form.errors.kpr_submission_id && <span className="text-xs font-bold text-red-600">{form.errors.kpr_submission_id}</span>}</div>}
            <Input label={type === "akad" ? "Tanggal Akad" : "Tanggal Serah Terima"} required type="datetime-local" value={form.data.tanggal_proses} error={form.errors.tanggal_proses} onChange={(event) => form.setData("tanggal_proses", event.target.value)} /><Input label="Lokasi" required value={form.data.lokasi} error={form.errors.lokasi} onChange={(event) => form.setData("lokasi", event.target.value)} />
            <Input label="Nomor Dokumen" value={form.data.nomor_dokumen} error={form.errors.nomor_dokumen} onChange={(event) => form.setData("nomor_dokumen", event.target.value)} /><Input label="Pihak Terkait" value={form.data.pihak_terkait} error={form.errors.pihak_terkait} onChange={(event) => form.setData("pihak_terkait", event.target.value)} />
            <Input className="md:col-span-2" label="Dokumentasi" type="file" multiple onChange={(event) => form.setData("dokumen", Array.from(event.target.files ?? []))} />
            <div className="md:col-span-2"><Textarea label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData("catatan", event.target.value)} /></div>
        </div></section><div className="flex justify-end gap-3 rounded-xl border bg-white p-4 dark:border-white/10 dark:bg-graphite"><Button as={Link} href={baseUrl} variant="outline">Batal</Button><Button disabled={form.processing}><Save size={17} /> Simpan {type === "akad" ? "Akad" : "Serah Terima"}</Button></div></form>
    </div></>;
}
FormPage.layout = (page) => <AdminLayout title={page?.props?.title ?? "Form Proses KPR"}>{page}</AdminLayout>;
