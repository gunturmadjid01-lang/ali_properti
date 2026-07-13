import { Head, router, useForm } from '@inertiajs/react';
import { Download, Edit3, Eye, FileText, LayoutDashboard, PlusCircle, Search, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, CurrencyInput, Dropdown, Input, Modal, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const titleCase = (v) => String(v ?? '-').replaceAll('_', ' ').replace(/\b\w/g, (x) => x.toUpperCase());
const formatValue = (v) => v === null || v === '' ? '-' : typeof v === 'boolean' ? (v ? 'Aktif' : 'Tidak Aktif') : titleCase(v);

export default function Index({ title, module, section, sectionTitle, baseUrl, menu = [], fields = [], rows = { data: [], links: [] }, summary = [], options = {}, filters = {}, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const defaults = useMemo(() => Object.fromEntries(fields.map((field) => [field.name, field.type === 'boolean' ? true : ''])), [fields]);
    const form = useForm(defaults);
    const visibleColumns = useMemo(() => fields.slice(0, 7).map((f) => f.name), [fields]);

    const showForm = (row = null) => { setEditing(row); form.setData(row ? Object.fromEntries(fields.map((f) => [f.name, row[f.name] ?? ''])) : defaults); form.clearErrors(); setOpen(true); };
    const submit = (event) => { event.preventDefault(); const url = `${baseUrl}/${section}/records${editing ? `/${editing.id}` : ''}`; const done = { preserveScroll: true, onSuccess: () => { setOpen(false); setEditing(null); form.reset(); } }; editing ? form.put(url, done) : form.post(url, done); };
    const remove = (row) => window.confirm('Arsipkan data ini? Histori transaksi tetap disimpan.') && router.delete(`${baseUrl}/${section}/records/${row.id}`, { preserveScroll: true });
    const sortBy = (key) => router.get(`${baseUrl}/${section}`, { search, sort: key, direction: filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc' }, { preserveState: true, preserveScroll: true });

    const fieldControl = (field) => {
        const common = { label: field.label, required: field.required, error: form.errors[field.name] };
        if (field.type === 'select') {
            const source = field.options ?? options[field.optionsKey] ?? {};
            const opts = Array.isArray(source) ? source : Object.entries(source).map(([value, label]) => ({ value: String(value), label }));
            return <div className="grid gap-2"><span className="text-sm font-extrabold">{field.label}{field.required && <span className="text-red-500"> *</span>}</span><Dropdown value={String(form.data[field.name] ?? '')} options={opts} label={`Pilih ${field.label}`} onChange={(value) => form.setData(field.name, value)} />{form.errors[field.name] && <span className="text-xs font-bold text-red-600">{form.errors[field.name]}</span>}</div>;
        }
        if (field.type === 'boolean') return <label className="flex items-center gap-3 rounded-lg border border-silver-deep/60 p-4 font-bold"><input type="checkbox" checked={Boolean(form.data[field.name])} onChange={(e) => form.setData(field.name, e.target.checked)} /> {field.label}</label>;
        if (/notes|description|chronology|purpose|reason|damage/.test(field.name)) return <Textarea {...common} value={form.data[field.name] ?? ''} onChange={(e) => form.setData(field.name, e.target.value)} />;
        if (/cost|price|biaya/.test(field.name)) return <CurrencyInput {...common} value={form.data[field.name] ?? ''} onChange={(value) => form.setData(field.name, value)} />;
        return <Input {...common} type={field.type} value={form.data[field.name] ?? ''} onChange={(e) => form.setData(field.name, e.target.value)} />;
    };

    const isDashboard = section === 'dashboard';
    const isReport = section === 'reports';

    return <>
        <Head title={`${sectionTitle} - ${title}`} />
        <div className="grid gap-6">
            <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">{module === 'heavy' ? 'Manajemen Aset Alat Berat' : 'Manajemen Aset Bergerak Perusahaan'}</p>
                <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                <p className="mt-2 text-ink-soft">Modul mandiri dengan histori transaksi, audit pengguna, dan soft delete.</p>
            </section>

            <nav className="flex gap-2 overflow-x-auto rounded-lg border border-white/80 bg-white/78 p-3 shadow-soft dark:border-white/10 dark:bg-white/8">
                {menu.map((item) => <Button key={item.key} size="sm" variant={item.key === section ? 'primary' : 'ghost'} className="shrink-0" onClick={() => router.get(`${baseUrl}/${item.key}`)}>{item.key === 'dashboard' ? <LayoutDashboard size={15} /> : <FileText size={15} />}{item.label}</Button>)}
            </nav>

            {isDashboard && <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{summary.map((card) => <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8" key={card.label}><p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">{card.label}</p><p className="mt-3 text-3xl font-extrabold">{card.value}</p></div>)}</section>}

            {isReport && <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8"><h3 className="text-xl font-extrabold">Pusat Laporan {title}</h3><p className="mt-2 text-ink-soft">Buka salah satu menu data atau transaksi, atur pencarian, lalu gunakan tombol PDF atau Excel. Export mempertahankan histori aktif dan tidak mengubah data.</p><div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{menu.filter((x) => !['dashboard','reports'].includes(x.key)).map((item) => <Button key={item.key} variant="outline" onClick={() => router.get(`${baseUrl}/${item.key}`)}><FileText size={16} /> {item.label}</Button>)}</div></section>}

            {!isDashboard && !isReport && <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                <div className="flex flex-col gap-4 border-b border-silver-deep/60 p-5 dark:border-white/10 md:flex-row md:items-end md:justify-between"><form className="flex flex-1 gap-2" onSubmit={(e) => { e.preventDefault(); router.get(`${baseUrl}/${section}`, { search }, { preserveState: true }); }}><Input className="max-w-lg flex-1" label="Pencarian" value={search} placeholder={`Cari ${sectionTitle.toLowerCase()}...`} onChange={(e) => setSearch(e.target.value)} /><Button className="self-end"><Search size={16} /> Cari</Button></form><div className="flex flex-wrap gap-2">{permissions.export && <><Button variant="outline" onClick={() => window.open(`${baseUrl}/${section}/export/pdf`, '_blank')}><Download size={16} /> PDF</Button><Button variant="outline" onClick={() => window.open(`${baseUrl}/${section}/export/excel`, '_blank')}><Download size={16} /> Excel</Button></>}{permissions.create && <Button onClick={() => showForm()}><PlusCircle size={16} /> Tambah Data</Button>}</div></div>
                <div className="overflow-x-auto"><table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10"><thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft"><tr><th className="px-5 py-4">No</th>{visibleColumns.map((key) => <th className="px-5 py-4" key={key}><button type="button" className="whitespace-nowrap hover:text-gold-deep" onClick={() => sortBy(key)}>{fields.find((f) => f.name === key)?.label ?? key}{filters.sort === key ? (filters.direction === 'asc' ? ' ↑' : ' ↓') : ''}</button></th>)}<th className="px-5 py-4">Aksi</th></tr></thead><tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">{(rows.data ?? []).map((row, index) => <tr key={row.id}><td className="px-5 py-4 font-bold">{index + 1}</td>{visibleColumns.map((key) => <td className="max-w-xs truncate px-5 py-4" key={key}>{formatValue(row[key])}</td>)}<td className="px-5 py-4"><div className="flex gap-2">{module === 'heavy' && section === 'equipment' && <Button size="sm" variant="outline" onClick={() => router.get(`${baseUrl}/equipment/${row.id}`)}><Eye size={14} /></Button>}{section === 'stock-opname' && row.status === 'draft' && permissions.verify && <Button size="sm" onClick={() => router.post(`${baseUrl}/stock-opname/${row.id}/verify`)}>Verifikasi</Button>}{permissions.update && <Button size="sm" variant="outline" onClick={() => showForm(row)}><Edit3 size={14} /></Button>}{permissions.delete && <Button size="sm" variant="outline" className="text-red-600" onClick={() => remove(row)}><Trash2 size={14} /></Button>}</div></td></tr>)}{!(rows.data ?? []).length && <tr><td colSpan={visibleColumns.length + 2} className="px-5 py-12 text-center font-bold text-ink-soft">Belum ada data.</td></tr>}</tbody></table></div><Pagination links={rows.links ?? []} />
            </section>}
        </div>

        <Modal open={open} onClose={() => setOpen(false)} title={`${editing ? 'Edit' : 'Tambah'} ${sectionTitle}`} footer={null}>
            <form className="grid gap-5" onSubmit={submit}><div className="grid gap-4 md:grid-cols-2">{fields.map((field) => <div className={/notes|description|chronology|purpose|reason|damage/.test(field.name) ? 'md:col-span-2' : ''} key={field.name}>{fieldControl(field)}</div>)}</div><div className="flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setOpen(false)}><X size={16} /> Batal</Button><Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan Data'}</Button></div></form>
        </Modal>
    </>;
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Manajemen Aset'}>{page}</AdminLayout>;
