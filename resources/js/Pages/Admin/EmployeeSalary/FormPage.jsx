import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Banknote, CalendarClock, Info, Save, WalletCards } from "lucide-react";
import { useMemo } from "react";
import { Button, CurrencyInput, Dropdown, FieldLabel, Input, Textarea } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

export default function FormPage({ title, baseUrl, actionUrl, method, initialData, employees }) {
    const form = useForm(initialData);
    const employeeOptions = useMemo(() => employees.map((employee) => ({ value: employee.value, label: `${employee.label}${employee.job_title ? ` · ${employee.job_title}` : ""}${employee.branch ? ` · ${employee.branch}` : ""}` })), [employees]);
    const submit = (event) => { event.preventDefault(); method === "put" ? form.put(actionUrl) : form.post(actionUrl); };
    return <><Head title={title} /><div className="mx-auto grid max-w-5xl gap-5 pb-8">
        <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#27323b] px-6 py-5 text-white shadow-lg"><div className="absolute -right-12 -top-20 h-52 w-52 rounded-full bg-gold/15 blur-2xl" /><div className="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><p className="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-champagne"><WalletCards size={15} /> Penggajian Pegawai</p><h1 className="mt-2 text-2xl font-black md:text-3xl">{title}</h1><p className="mt-2 text-sm text-white/65">Atur nominal dan tanggal efektif. Periode sebelumnya akan disesuaikan otomatis.</p></div><Button as={Link} href={baseUrl} variant="outline" className="border-white/20 bg-white/10 text-white hover:bg-white/20"><ArrowLeft size={17} /> Kembali</Button></div></section>
        {Object.keys(form.errors).length > 0 && <div className="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700"><Info size={18} /> Periksa kembali kolom yang ditandai merah.</div>}
        <form className="grid gap-4" onSubmit={submit}><section className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]"><header className="border-b bg-silver-soft/55 px-5 py-4 dark:border-white/10 dark:bg-white/[0.03]"><h2 className="flex items-center gap-2 text-sm font-black"><Banknote size={18} /> Informasi Periode Gaji</h2><p className="mt-1 text-xs text-ink-soft">Kolom bertanda * wajib diisi.</p></header><div className="grid gap-5 p-5 md:grid-cols-2">
            <div className="grid gap-2 md:col-span-2"><FieldLabel required>Pegawai</FieldLabel><Dropdown value={form.data.user_id} options={employeeOptions} label="Pilih pegawai" onChange={(value) => form.setData("user_id", value)} />{form.errors.user_id && <span className="text-xs font-bold text-red-600">{form.errors.user_id}</span>}</div>
            <CurrencyInput label="Gaji Pokok" required value={form.data.basic_salary} error={form.errors.basic_salary} onChange={(value) => form.setData("basic_salary", value)} /><CurrencyInput label="Tunjangan Tetap" value={form.data.fixed_allowance} error={form.errors.fixed_allowance} onChange={(value) => form.setData("fixed_allowance", value)} />
            <Input label="Mulai Berlaku" required type="date" icon={<CalendarClock size={17} />} value={form.data.effective_from} error={form.errors.effective_from} onChange={(event) => form.setData("effective_from", event.target.value)} />
            <label className="flex min-h-12 items-center gap-3 self-end rounded-lg border border-silver-deep/70 px-4 text-sm font-extrabold dark:border-white/10"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData("is_active", event.target.checked)} /> Periode gaji aktif</label>
            <div className="md:col-span-2"><Textarea label="Catatan" value={form.data.notes} error={form.errors.notes} onChange={(event) => form.setData("notes", event.target.value)} /></div>
        </div></section><div className="flex justify-end gap-3 rounded-xl border bg-white/90 p-4 dark:border-white/10 dark:bg-graphite"><Button as={Link} href={baseUrl} variant="outline">Batal</Button><Button type="submit" disabled={form.processing}><Save size={17} /> {form.processing ? "Menyimpan..." : "Simpan Gaji"}</Button></div></form>
    </div></>;
}
FormPage.layout = (page) => <AdminLayout title={page?.props?.title ?? "Form Gaji"}>{page}</AdminLayout>;
