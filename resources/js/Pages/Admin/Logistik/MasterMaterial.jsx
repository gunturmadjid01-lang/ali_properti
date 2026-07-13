import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, LoaderCircle, Lock, Save, Search, Trash2, Unlock, X } from 'lucide-react';
import { useState } from 'react';
import { Button, CurrencyInput, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';
import { SectionCard, WarehousePage } from './components/WarehouseShell';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

function IconAction({ title, icon: Icon, onClick, tone = '' }) {
    return (
        <button
            type="button"
            title={title}
            aria-label={title}
            onClick={onClick}
            className={`inline-grid h-8 w-8 place-items-center rounded-lg border border-silver-deep/70 bg-white/70 text-ink-soft transition hover:bg-silver-soft hover:text-ink dark:border-white/10 dark:bg-white/8 dark:text-white/65 dark:hover:bg-white/12 dark:hover:text-white ${tone}`}
        >
            <Icon size={15} />
        </button>
    );
}

export default function MasterMaterial({ title, baseUrl, rows, filters = {}, options }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [gudangId, setGudangId] = useState(filters.gudang_id ?? '');
    const [editing, setEditing] = useState(null);
    const jenisMaterialOptions = options?.jenisMaterial ?? [{ value: '', label: 'Tanpa Jenis' }];
    const gudangOptions = [{ value: '', label: 'Semua Gudang' }, ...(options?.gudangs ?? [])];
    const form = useForm({ nama_barang: '', jenis_material: '', merk_material: '', satuan: '', harga_hpp: '', stok_minimum: '0', catatan: '', status: 'aktif' });
    const reset = () => { setEditing(null); form.reset(); form.clearErrors(); };
    const edit = (row) => { setEditing(row); form.setData({ nama_barang: row.nama_barang ?? '', jenis_material: row.jenis_material ?? '', merk_material: row.merk_material ?? '', satuan: row.satuan ?? '', harga_hpp: row.harga_hpp ?? '', stok_minimum: row.stok_minimum ?? '0', catatan: row.catatan ?? '', status: row.status ?? 'aktif' }); };
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
            <WarehousePage
                eyebrow="Master Gudang"
                title="Kelola Item Material"
                description="Data material ini menjadi sumber utama untuk HPP, stok gudang, saldo awal, dan seluruh transaksi material."
            >
                <Form collapsible title={editing ? 'Edit Item Material' : 'Tambah Item Material'} description="Harga aktif dipakai sebagai default transaksi baru. Detail transaksi lama menyimpan harga satuannya sendiri, jadi tidak berubah ketika harga item diperbarui." onSubmit={submit} actions={<>{editing && <Button type="button" variant="outline" onClick={reset}><X size={17} /> Batal</Button>}<Button type="submit" disabled={form.processing}>{form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />} Simpan</Button></>}>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Input label="Nama Material" value={form.data.nama_barang} error={form.errors.nama_barang} onChange={(event) => form.setData('nama_barang', event.target.value)} />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Jenis</span>
                            <Dropdown value={form.data.jenis_material ?? ''} options={jenisMaterialOptions} onChange={(value) => form.setData('jenis_material', value)} />
                            {form.errors.jenis_material && <span className="text-xs font-bold text-red-600">{form.errors.jenis_material}</span>}
                        </div>
                        <Input label="Merk" value={form.data.merk_material} error={form.errors.merk_material} onChange={(event) => form.setData('merk_material', event.target.value)} />
                        <Input label="Satuan Default" value={form.data.satuan} error={form.errors.satuan} onChange={(event) => form.setData('satuan', event.target.value)} />
                        <CurrencyInput label="Harga Dasar/HPP" value={form.data.harga_hpp} error={form.errors.harga_hpp} onChange={(value) => form.setData('harga_hpp', value)} />
                        <Input label="Stok Minimum" type="number" step="1" min="0" value={form.data.stok_minimum} onChange={(event) => form.setData('stok_minimum', event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Status</span><Dropdown value={form.data.status} options={options.status} onChange={(value) => form.setData('status', value)} /></div>
                    </div>
                    <Textarea label="Catatan" value={form.data.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                </Form>
                <SectionCard
                    title="Daftar material"
                    description="Pilih gudang untuk melihat stok per gudang. Kosongkan pilihan gudang untuk melihat akumulasi stok seluruh gudang."
                    actions={(
                        <form className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search, gudang_id: gudangId }, { preserveScroll: true, preserveState: true, replace: true }); }}>
                            <div className="grid gap-2 md:w-64">
                                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Gudang</span>
                                <Dropdown value={gudangId} label="Semua Gudang" options={gudangOptions} onChange={setGudangId} />
                            </div>
                            <Input className="md:w-80" label="Cari material" value={search} onChange={(event) => setSearch(event.target.value)} />
                            <Button type="submit"><Search size={17} /> Cari</Button>
                        </form>
                    )}
                >
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft"><tr>{['Kode', 'Material', 'Jenis', 'Merk', 'Satuan', 'Stok', 'Harga Dasar', 'Min', 'Status', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr></thead>
                            <tbody className="divide-y divide-silver-deep/50">{rows.data.map((row) => <tr key={row.id}><td className="px-5 py-4 font-bold">{row.kode_barang}</td><td className="px-5 py-4">{row.nama_barang}</td><td className="px-5 py-4">{row.jenis_material ?? '-'}</td><td className="px-5 py-4">{row.merk_material ?? '-'}</td><td className="px-5 py-4">{row.satuan}</td><td className="px-5 py-4 font-black">{Number(row.stok_tersedia ?? 0).toLocaleString('id-ID')}</td><td className="px-5 py-4 font-bold">{money(row.harga_hpp)}</td><td className="px-5 py-4">{row.stok_minimum}</td><td className="px-5 py-4 font-bold">{row.status}</td><td className="px-5 py-4"><div className="flex gap-2">{row.record_status === 'locked' ? <IconAction title="Unlock" icon={Unlock} onClick={() => unlockRow(row)} /> : <><IconAction title="Lock" icon={Lock} onClick={() => lockRow(row)} /><IconAction title="Edit" icon={Edit3} onClick={() => edit(row)} /><IconAction title="Hapus" icon={Trash2} tone="text-red-600 hover:text-red-700 dark:text-red-300" onClick={() => router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true })} /></> }</div></td></tr>)}</tbody>
                        </table>
                    </div>
                </SectionCard>
            </WarehousePage>
        </>
    );
}

MasterMaterial.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Kelola Item Material'}>{page}</AdminLayout>;
