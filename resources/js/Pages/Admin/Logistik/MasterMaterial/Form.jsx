import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-react';
import { Button, CurrencyInput, Dropdown, Input, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(value ?? 0));

export default function FormPage({ title, indexUrl, actionUrl, method, material = null, options = {} }) {
    const form = useForm({
        nama_barang: material?.nama_barang ?? '', material_type_id: String(material?.material_type_id ?? ''), material_brand_id: String(material?.material_brand_id ?? ''),
        base_unit_id: String(material?.base_unit_id ?? ''), harga_hpp: material?.harga_hpp ?? '', stok_minimum: material?.stok_minimum ?? 0,
        catatan: material?.catatan ?? '', status: material?.status ?? 'aktif', conversions: material?.conversions ?? [], submit_action: 'save',
    });
    const units = options.units ?? [];
    const unitById = (id) => units.find((unit) => String(unit.value) === String(id));
    const addConversion = () => form.setData('conversions', [...form.data.conversions, { unit_id: '', factor: '' }]);
    const updateConversion = (index, key, value) => form.setData('conversions', form.data.conversions.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row));
    const removeConversion = (index) => form.setData('conversions', form.data.conversions.filter((_, rowIndex) => rowIndex !== index));
    const submit = (action) => {
        form.setData('submit_action', action);
        form.transform((data) => ({ ...data, submit_action: action }));
        form[method](actionUrl, { onFinish: () => form.transform((data) => data) });
    };
    let currentPrice = Number(form.data.harga_hpp || 0);
    let parentUnitId = form.data.base_unit_id;

    return <>
        <Head title={title} />
        <div className="grid gap-6">
            <section className="flex flex-col gap-4 rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8 md:flex-row md:items-center md:justify-between"><div><p className="text-xs font-black uppercase tracking-wider text-ink-soft">Master Material</p><h1 className="mt-1 text-2xl font-black">{title}</h1><p className="mt-1 text-sm text-ink-soft">Level 1 menjadi satuan saldo utama pada stok, kartu stok, pembelian, dan opname.</p></div><Button as={Link} href={indexUrl} variant="outline"><ArrowLeft size={16} /> Kembali</Button></section>
            <form className="grid gap-6" onSubmit={(event) => { event.preventDefault(); submit('save'); }}>
                <section className="rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"><h2 className="font-black">Informasi Material</h2><div className="mt-4 grid gap-4 md:grid-cols-3"><Input label="Nama Material" value={form.data.nama_barang} error={form.errors.nama_barang} onChange={(event) => form.setData('nama_barang', event.target.value)} /><div className="grid gap-2"><span className="text-sm font-extrabold">Jenis</span><Dropdown value={form.data.material_type_id} options={options.types ?? []} onChange={(value) => form.setData('material_type_id', value)} />{form.errors.material_type_id && <span className="text-xs text-red-600">{form.errors.material_type_id}</span>}</div><div className="grid gap-2"><span className="text-sm font-extrabold">Merk</span><Dropdown value={form.data.material_brand_id} options={[{ value: '', label: 'Tanpa Merk' }, ...(options.brands ?? [])]} onChange={(value) => form.setData('material_brand_id', value)} /></div><CurrencyInput label="Harga Pokok Level 1" value={form.data.harga_hpp} error={form.errors.harga_hpp} onChange={(value) => form.setData('harga_hpp', value)} /><Input label="Stok Minimum Level 1" type="number" min="0" step="0.000001" value={form.data.stok_minimum} onChange={(event) => form.setData('stok_minimum', event.target.value)} /><div className="grid gap-2"><span className="text-sm font-extrabold">Status</span><Dropdown value={form.data.status} options={options.status ?? []} onChange={(value) => form.setData('status', value)} /></div></div><div className="mt-4"><Textarea label="Catatan" value={form.data.catatan} onChange={(event) => form.setData('catatan', event.target.value)} /></div></section>
                <section className="rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"><div className="flex items-center justify-between"><div><h2 className="font-black">Satuan dan Konversi Harga</h2><p className="mt-1 text-sm text-ink-soft">Susun dari kemasan terbesar/level 1 menuju satuan yang lebih kecil.</p></div><Button type="button" variant="outline" onClick={addConversion}><Plus size={16} /> Tambah Level</Button></div><div className="mt-4 rounded-lg border border-gold/30 bg-champagne/40 p-4"><p className="text-xs font-black uppercase">Level 1 — Satuan Stok Utama</p><div className="mt-3 grid gap-4 md:grid-cols-2"><div className="grid gap-2"><span className="text-sm font-extrabold">Satuan Level 1</span><Dropdown value={form.data.base_unit_id} options={units} onChange={(value) => form.setData('base_unit_id', value)} /></div><div><p className="text-sm font-extrabold">Harga per {unitById(form.data.base_unit_id)?.symbol ?? 'satuan'}</p><p className="mt-2 text-lg font-black">{money(form.data.harga_hpp)}</p></div></div></div><div className="mt-4 grid gap-3">{form.data.conversions.map((row, index) => { const factor = Number(row.factor || 0); const childPrice = factor > 0 ? currentPrice / factor : 0; const parent = unitById(parentUnitId); const child = unitById(row.unit_id); const card = <div className="grid gap-3 rounded-lg border border-silver-deep/70 p-4 md:grid-cols-[120px_1fr_1fr_1fr_auto] md:items-end" key={index}><div className="font-black">Level {index + 2}</div><div className="grid gap-2"><span className="text-sm font-extrabold">Satuan</span><Dropdown value={row.unit_id} options={units.filter((unit) => String(unit.value) !== String(form.data.base_unit_id))} onChange={(value) => updateConversion(index, 'unit_id', value)} /></div><Input label={`Isi per ${parent?.symbol ?? 'level sebelumnya'}`} type="number" min="0.000001" step="0.000001" value={row.factor} onChange={(event) => updateConversion(index, 'factor', event.target.value)} /><div><p className="text-sm font-extrabold">Konversi otomatis</p><p className="mt-2 font-black">1 {parent?.symbol ?? '?'} = {factor || 0} {child?.symbol ?? '?'}</p><p className="text-xs text-ink-soft">Harga: {money(childPrice)} / {child?.symbol ?? '?'}</p></div><Button type="button" variant="outline" className="text-red-600" onClick={() => removeConversion(index)}><Trash2 size={15} /></Button></div>; if (factor > 0) currentPrice = childPrice; if (row.unit_id) parentUnitId = row.unit_id; return card; })}</div>{form.errors.conversions && <p className="mt-3 text-sm font-bold text-red-600">{form.errors.conversions}</p>}</section>
                <section className="flex flex-col justify-end gap-3 rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8 sm:flex-row">{!material && <Button type="button" variant="outline" disabled={form.processing} onClick={() => submit('add_another')}><Plus size={16} /> Tambah Item Baru</Button>}<Button type="submit" disabled={form.processing}><Save size={16} /> Simpan</Button></section>
            </form>
        </div>
    </>;
}

FormPage.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Material'}>{page}</AdminLayout>;
