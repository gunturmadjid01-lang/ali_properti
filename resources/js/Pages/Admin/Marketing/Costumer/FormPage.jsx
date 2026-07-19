import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-react';
import { Button, Form } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';
import FieldRenderer from '../../Management/Components/FieldRenderer';

const groups = [
    ['profile', 'Profil Pelanggan', 'Identitas utama dan kontak pelanggan.'],
    ['pekerjaan', 'Pekerjaan Pelanggan', 'Pekerjaan, penghasilan, dan perusahaan pelanggan.'],
    ['pasangan', 'Pasangan Pelanggan', 'Data pasangan jika pelanggan sudah menikah.'],
    ['cicilan', 'Cicilan Berjalan', 'Daftar kewajiban cicilan konsumen maupun pasangan. Seluruh data pada bagian ini opsional.'],
];

export default function FormPage({ title, description, baseUrl, actionUrl, method = 'post', fields = [], options = {}, row = null }) {
    const defaults = Object.fromEntries(fields.map((field) => [field.name, row?.[field.name] ?? (field.type === 'checkbox' ? false : field.type === 'installments' ? [] : '')]));
    const form = useForm(defaults);
    const submit = (event) => {
        event.preventDefault();
        form[method](actionUrl, { preserveScroll: true, onSuccess: () => router.visit(baseUrl) });
    };

    return <>
        <Head title={title} />
        <div className="grid gap-6">
            <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Marketing / Calon Konsumen</p>
                <h1 className="mt-2 text-3xl font-extrabold">{title}</h1>
                <p className="mt-2 text-sm text-ink-soft dark:text-white/60">{description}</p>
            </section>
            <Form title="Data Pelanggan" description="Form ini berada pada halaman khusus dan tidak lagi dibuka melalui modal." onSubmit={submit} actions={<>
                <Button type="button" variant="outline" onClick={() => router.visit(baseUrl)}><ArrowLeft size={17} /> Kembali</Button>
                <Button type="submit" disabled={form.processing}><Save size={17} /> {form.processing ? 'Menyimpan...' : 'Simpan'}</Button>
            </>}>
                {Object.values(form.errors).length > 0 && <div className="rounded-lg border border-red-300 bg-red-50 p-4 text-sm font-bold text-red-700">{Object.values(form.errors).map((error) => <p key={error}>{error}</p>)}</div>}
                {groups.map(([key, label, help]) => <section className="rounded-lg border border-silver-deep/70 p-5 dark:border-white/10" key={key}>
                    <h2 className="font-extrabold">{label}</h2><p className="mb-4 mt-1 text-xs text-ink-soft">{help}</p>
                    <div className="grid gap-4 md:grid-cols-2">{fields.filter((field) => field.group === key).map((field) => <div className={field.full ? 'md:col-span-2' : ''} key={field.name}>{field.type === 'installments' ? <InstallmentFields value={form.data[field.name]} errors={form.errors} onChange={(value) => form.setData(field.name, value)} /> : <FieldRenderer field={field} value={form.data[field.name]} error={form.errors[field.name]} options={options} onChange={form.setData} />}</div>)}</div>
                </section>)}
            </Form>
        </div>
    </>;
}

FormPage.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Calon Konsumen'}>{page}</AdminLayout>;

const emptyInstallment = () => ({ pemilik: 'konsumen', jenis: '', kreditur: '', angsuran_bulanan: '', sisa_pokok: '', tanggal_selesai: '' });

function InstallmentFields({ value = [], errors = {}, onChange }) {
    const rows = Array.isArray(value) ? value : [];
    const update = (index, key, nextValue) => onChange(rows.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: nextValue } : row));
    const inputClass = 'w-full rounded-lg border border-silver-deep bg-white px-3 py-2.5 text-sm font-semibold outline-none focus:border-ink dark:border-white/15 dark:bg-white/5';

    return <div className="grid gap-4">
        {rows.map((row, index) => <div className="rounded-lg border border-silver-deep/70 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5" key={index}>
            <div className="mb-3 flex items-center justify-between"><h3 className="font-extrabold">Cicilan {index + 1}</h3><button className="inline-flex items-center gap-1 text-sm font-bold text-red-600" type="button" onClick={() => onChange(rows.filter((_, rowIndex) => rowIndex !== index))}><Trash2 size={15} /> Hapus</button></div>
            <div className="grid gap-3 md:grid-cols-3">
                <label className="grid gap-1 text-sm font-bold">Pemilik<select className={inputClass} value={row.pemilik ?? 'konsumen'} onChange={(event) => update(index, 'pemilik', event.target.value)}><option value="konsumen">Konsumen</option><option value="pasangan">Pasangan</option></select></label>
                <label className="grid gap-1 text-sm font-bold">Jenis Cicilan<input className={inputClass} value={row.jenis ?? ''} placeholder="KPR, kendaraan, kartu kredit..." onChange={(event) => update(index, 'jenis', event.target.value)} /></label>
                <label className="grid gap-1 text-sm font-bold">Kreditur<input className={inputClass} value={row.kreditur ?? ''} placeholder="Nama bank/leasing" onChange={(event) => update(index, 'kreditur', event.target.value)} /></label>
                <label className="grid gap-1 text-sm font-bold">Angsuran Bulanan (Rp)<input className={inputClass} min="0" type="number" value={row.angsuran_bulanan ?? ''} onChange={(event) => update(index, 'angsuran_bulanan', event.target.value)} /></label>
                <label className="grid gap-1 text-sm font-bold">Sisa Pokok (Rp)<input className={inputClass} min="0" type="number" value={row.sisa_pokok ?? ''} onChange={(event) => update(index, 'sisa_pokok', event.target.value)} /></label>
                <label className="grid gap-1 text-sm font-bold">Tanggal Selesai<input className={inputClass} type="date" value={row.tanggal_selesai ?? ''} onChange={(event) => update(index, 'tanggal_selesai', event.target.value)} /></label>
            </div>
            {Object.entries(errors).filter(([key]) => key.startsWith(`daftar_cicilan.${index}.`)).map(([key, message]) => <p className="mt-2 text-xs font-bold text-red-600" key={key}>{message}</p>)}
        </div>)}
        {rows.length === 0 && <p className="rounded-lg border border-dashed border-silver-deep p-5 text-center text-sm font-semibold text-ink-soft">Belum ada cicilan berjalan. Bagian ini boleh dikosongkan.</p>}
        <div><Button type="button" variant="outline" onClick={() => onChange([...rows, emptyInstallment()])}><Plus size={16} /> Tambah Cicilan</Button></div>
    </div>;
}
