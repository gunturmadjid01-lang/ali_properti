import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, Lock, MinusCircle, PlusCircle, Save, Search, Trash2, Unlock, X } from 'lucide-react';
import { useState } from 'react';
import { Button, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function itemTemplate() {
    return { barang_material_id: '', qty: '', satuan: '', catatan: '' };
}

export default function Index({ title, baseUrl, rows = { data: [] }, filters = {}, options = {}, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState(null);
    const canCreate = permissions.canCreate ?? false;
    const canUpdate = permissions.canUpdate ?? false;
    const canDelete = permissions.canDelete ?? false;
    const form = useForm({
        tanggal: new Date().toISOString().slice(0, 10),
        gudang_id: '',
        keterangan: '',
        items: [itemTemplate()],
    });

    const setItem = (index, key, value) => {
        form.setData('items', form.data.items.map((item, itemIndex) => {
            if (itemIndex !== index) return item;
            const next = { ...item, [key]: value };
            if (key === 'barang_material_id') {
                next.satuan = options.barangMaterials?.find((option) => option.value === String(value))?.satuan ?? '';
            }
            return next;
        }));
    };

    const resetForm = () => {
        setEditing(null);
        form.clearErrors();
        form.setData({
            tanggal: new Date().toISOString().slice(0, 10),
            gudang_id: '',
            keterangan: '',
            items: [itemTemplate()],
        });
    };

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true, onSuccess: resetForm };
        if (editing) {
            form.put(`${baseUrl}/${editing.id}`, requestOptions);
            return;
        }
        form.post(baseUrl, requestOptions);
    };

    const editRow = (row) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            tanggal: row.tanggal ?? '',
            gudang_id: row.gudang_id ?? '',
            keterangan: row.keterangan ?? '',
            items: row.items?.length ? row.items : [itemTemplate()],
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                {canCreate && (
                    <Form
                        collapsible
                        title={editing ? `Edit ${editing.kode_request}` : title}
                        description="Gudang mengajukan kebutuhan restock. Permintaan ini tidak menambah HPP proyek dan tidak langsung mengubah stok."
                        onSubmit={submit}
                        actions={(
                            <>
                                {editing && canUpdate && <Button type="button" variant="outline" onClick={resetForm}><X size={16} /> Batal</Button>}
                                <Button type="submit" disabled={form.processing}><Save size={17} /> {editing ? 'Simpan' : 'Kirim Permintaan'}</Button>
                            </>
                        )}
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <Input label="Tanggal" type="date" value={form.data.tanggal} error={form.errors.tanggal} onChange={(event) => form.setData('tanggal', event.target.value)} />
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">Gudang Tujuan</span>
                                <Dropdown value={form.data.gudang_id} label="Pilih Gudang" options={options.gudangs ?? []} onChange={(value) => form.setData('gudang_id', value)} />
                                {form.errors.gudang_id && <p className="text-xs font-bold text-red-500">{form.errors.gudang_id}</p>}
                            </div>
                        </div>

                        <div className="grid gap-3">
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="text-sm font-extrabold">Material yang Dibutuhkan</h3>
                                <Button type="button" variant="outline" size="sm" onClick={() => form.setData('items', [...form.data.items, itemTemplate()])}><PlusCircle size={15} /> Tambah Item</Button>
                            </div>
                            {form.errors.items && <p className="text-xs font-bold text-red-500">{form.errors.items}</p>}
                            {form.data.items.map((item, index) => (
                                <div className="grid gap-3 rounded-lg border border-silver-deep/70 p-3 md:grid-cols-[1.5fr_0.6fr_0.6fr_1fr_auto]" key={index}>
                                    <div className="grid gap-2">
                                        <span className="text-xs font-extrabold text-ink-soft">Material</span>
                                        <Dropdown value={item.barang_material_id} label="Pilih Material" options={options.barangMaterials ?? []} onChange={(value) => setItem(index, 'barang_material_id', value)} />
                                    </div>
                                    <Input label="Qty" type="number" value={item.qty} onChange={(event) => setItem(index, 'qty', event.target.value)} />
                                    <Input label="Satuan" value={item.satuan} onChange={(event) => setItem(index, 'satuan', event.target.value)} />
                                    <Input label="Catatan Item" value={item.catatan} onChange={(event) => setItem(index, 'catatan', event.target.value)} />
                                    <div className="flex items-end">
                                        <Button type="button" variant="ghost" size="sm" className="text-red-600" disabled={form.data.items.length === 1} onClick={() => form.setData('items', form.data.items.filter((_, itemIndex) => itemIndex !== index))}><MinusCircle size={16} /></Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <Textarea label="Keterangan Permintaan" value={form.data.keterangan} error={form.errors.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} />
                    </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true }); }}>
                        <Input className="md:max-w-md" label="Cari Permintaan" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft">
                                <tr>{['Kode', 'Tanggal', 'Gudang', 'Material', 'Pemohon', 'Status', 'Lock', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">{row.kode_request}</td>
                                        <td className="px-5 py-4">{row.tanggal}</td>
                                        <td className="px-5 py-4">{row.gudang}</td>
                                        <td className="min-w-72 px-5 py-4">{row.items_text}</td>
                                        <td className="px-5 py-4">{row.pemohon}</td>
                                        <td className="px-5 py-4 font-bold">{row.status}</td>
                                        <td className="px-5 py-4 font-bold">{row.record_status === 'locked' ? 'Locked' : 'Draft'}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                {canUpdate && row.can_edit && <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={15} /> Edit</Button>}
                                                {canDelete && row.can_delete && <Button type="button" size="sm" variant="outline" className="text-red-600" onClick={() => window.confirm(`Hapus ${row.kode_request}?`) && router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true })}><Trash2 size={15} /> Hapus</Button>}
                                                {row.record_status === 'locked'
                                                    ? <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true })}><Unlock size={15} /> Unlock</Button>
                                                    : <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true })}><Lock size={15} /> Lock</Button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Permintaan Pembelian Barang'}>{page}</AdminLayout>;
