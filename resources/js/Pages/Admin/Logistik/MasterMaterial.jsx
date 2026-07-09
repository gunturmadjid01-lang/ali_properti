import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, LoaderCircle, Lock, Save, Search, Trash2, Unlock, X } from 'lucide-react';
import { useState } from 'react';
import { Button, CurrencyInput, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

export default function MasterMaterial({ title, baseUrl, rows, filters = {}, options }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState(null);
    const kategoriMaterialOptions = options?.kategoriMaterial ?? [{ value: '', label: 'Tanpa Kategori' }];
    const form = useForm({ nama_barang: '', kategori_material: '', satuan: '', harga_hpp: '', stok_minimum: '0', catatan: '', status: 'aktif' });
    const reset = () => { setEditing(null); form.reset(); form.clearErrors(); };
    const edit = (row) => { setEditing(row); form.setData({ nama_barang: row.nama_barang ?? '', kategori_material: row.kategori_material ?? '', satuan: row.satuan ?? '', harga_hpp: row.harga_hpp ?? '', stok_minimum: row.stok_minimum ?? '0', catatan: row.catatan ?? '', status: row.status ?? 'aktif' }); };
    const submit = (event) => { event.preventDefault(); const opts = { preserveScroll: true, onSuccess: reset }; editing ? form.put(`${baseUrl}/${editing.id}`, opts) : form.post(baseUrl, opts); };
    const lockRow = (row) => {
        if (!window.confirm(`Lock data material ${row.nama_barang}?`)) return;
        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };
    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock material ${row.nama_barang}?`)) return;
        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <Form collapsible title={editing ? 'Edit Item Material' : 'Tambah Item Material'} description="Harga aktif dipakai sebagai default transaksi baru. Detail transaksi lama menyimpan harga satuannya sendiri, jadi tidak berubah ketika harga item diperbarui." onSubmit={submit} actions={<>{editing && <Button type="button" variant="outline" onClick={reset}><X size={17} /> Batal</Button>}<Button type="submit" disabled={form.processing}>{form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />} Simpan</Button></>}>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Input label="Nama Material" value={form.data.nama_barang} error={form.errors.nama_barang} onChange={(event) => form.setData('nama_barang', event.target.value)} />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Kategori Material</span>
                            <Dropdown value={form.data.kategori_material ?? ''} options={kategoriMaterialOptions} onChange={(value) => form.setData('kategori_material', value)} />
                            {form.errors.kategori_material && <span className="text-xs font-bold text-red-600">{form.errors.kategori_material}</span>}
                        </div>
                        <Input label="Satuan Default" value={form.data.satuan} error={form.errors.satuan} onChange={(event) => form.setData('satuan', event.target.value)} />
                        <CurrencyInput label="Harga Dasar/HPP" value={form.data.harga_hpp} error={form.errors.harga_hpp} onChange={(value) => form.setData('harga_hpp', value)} />
                        <Input label="Stok Minimum" type="number" value={form.data.stok_minimum} onChange={(event) => form.setData('stok_minimum', event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Status</span><Dropdown value={form.data.status} options={options.status} onChange={(value) => form.setData('status', value)} /></div>
                    </div>
                    <Textarea label="Catatan" value={form.data.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                </Form>
                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true }); }}><Input className="md:max-w-md" label="Search" value={search} onChange={(event) => setSearch(event.target.value)} /><Button type="submit"><Search size={17} /> Cari</Button></form>
                    <div className="overflow-x-auto"><table className="min-w-full divide-y divide-silver-deep/60 text-sm"><thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft"><tr>{['Kode', 'Material', 'Kategori', 'Satuan', 'Harga Dasar', 'Stok Minimum', 'Lock', 'Status', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr></thead><tbody className="divide-y divide-silver-deep/50">{rows.data.map((row) => <tr key={row.id}><td className="px-5 py-4 font-bold">{row.kode_barang}</td><td className="px-5 py-4">{row.nama_barang}</td><td className="px-5 py-4">{row.kategori_material ?? '-'}</td><td className="px-5 py-4">{row.satuan}</td><td className="px-5 py-4 font-bold">{money(row.harga_hpp)}</td><td className="px-5 py-4">{row.stok_minimum}</td><td className="px-5 py-4 font-bold">{row.record_status_label}</td><td className="px-5 py-4 font-bold">{row.status}</td><td className="px-5 py-4"><div className="flex gap-2">{row.record_status === 'locked' ? <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}><Unlock size={15} /> Unlock</Button> : <><Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}><Lock size={15} /> Lock</Button><Button type="button" size="sm" variant="outline" onClick={() => edit(row)}><Edit3 size={15} /> Edit</Button><Button type="button" size="sm" variant="outline" onClick={() => router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true })}><Trash2 size={15} /> Hapus</Button></> }</div></td></tr>)}</tbody></table></div>
                </section>
            </div>
        </>
    );
}

MasterMaterial.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Kelola Item Material'}>{page}</AdminLayout>;
