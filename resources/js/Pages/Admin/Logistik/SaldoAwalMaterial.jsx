import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, LoaderCircle, Lock, Save, Search, Trash2, Unlock, X } from 'lucide-react';
import { useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, CurrencyInput, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

export default function SaldoAwalMaterial({ title, baseUrl, rows, filters = {}, options }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [gudangFilter, setGudangFilter] = useState(filters.gudang_id ?? '');
    const [editing, setEditing] = useState(null);
    const filterGudangs = [{ value: '', label: 'Semua Gudang' }, ...(options.gudangs ?? [])];
    const form = useForm({
        gudang_id: '',
        barang_material_id: '',
        tanggal_saldo: new Date().toISOString().slice(0, 10),
        qty: '',
        harga_satuan: '',
        catatan: '',
    });

    const reset = () => {
        setEditing(null);
        form.reset();
        form.setData('tanggal_saldo', new Date().toISOString().slice(0, 10));
        form.clearErrors();
    };

    const edit = (row) => {
        setEditing(row);
        form.setData({
            gudang_id: row.gudang_id ?? '',
            barang_material_id: row.barang_material_id ?? '',
            tanggal_saldo: row.tanggal_saldo ?? new Date().toISOString().slice(0, 10),
            qty: row.qty ?? '',
            harga_satuan: row.harga_satuan ?? '',
            catatan: row.catatan ?? '',
        });
    };

    const submit = (event) => {
        event.preventDefault();
        const opts = { preserveScroll: true, onSuccess: reset };
        editing ? form.put(`${baseUrl}/${editing.id}`, opts) : form.post(baseUrl, opts);
    };

    const lockRow = (row) => {
        if (!window.confirm(`Lock saldo awal ${row.nama_barang} di ${row.gudang}?`)) return;
        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock saldo awal ${row.nama_barang} di ${row.gudang}?`)) return;
        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <Form
                    collapsible
                    title={editing ? 'Edit Saldo Awal Material' : 'Tambah Saldo Awal Material'}
                    description="Saldo awal mengisi stok awal per gudang. Harga satuan di sini menjadi snapshot nilai awal dan tidak mengubah transaksi lama."
                    onSubmit={submit}
                    actions={(
                        <>
                            {editing && <Button type="button" variant="outline" onClick={reset}><X size={17} /> Batal</Button>}
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />}
                                Simpan
                            </Button>
                        </>
                    )}
                >
                    <div className="grid gap-4 md:grid-cols-4">
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Gudang</span>
                            <Dropdown value={form.data.gudang_id} label="Pilih Gudang" options={options.gudangs} onChange={(value) => form.setData('gudang_id', value)} />
                            {form.errors.gudang_id && <span className="text-xs font-bold text-red-600">{form.errors.gudang_id}</span>}
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Material</span>
                            <Dropdown
                                value={form.data.barang_material_id}
                                label="Pilih Material"
                                options={options.materials}
                                onChange={(value, selected) => form.setData({ ...form.data, barang_material_id: value, harga_satuan: selected?.harga_hpp ?? form.data.harga_satuan })}
                            />
                            {form.errors.barang_material_id && <span className="text-xs font-bold text-red-600">{form.errors.barang_material_id}</span>}
                        </div>
                        <Input label="Tanggal Saldo" type="date" value={form.data.tanggal_saldo} error={form.errors.tanggal_saldo} onChange={(event) => form.setData('tanggal_saldo', event.target.value)} />
                        <Input label="Qty Awal" type="number" step="0.01" value={form.data.qty} error={form.errors.qty} onChange={(event) => form.setData('qty', event.target.value)} />
                        <CurrencyInput label="Harga Satuan Awal" value={form.data.harga_satuan} error={form.errors.harga_satuan} onChange={(value) => form.setData('harga_satuan', value)} />
                        <div className="grid gap-1 rounded-lg border border-silver-deep/70 bg-silver-soft px-4 py-3 dark:border-white/10 dark:bg-white/8">
                            <span className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft dark:text-white/55">Total Nilai</span>
                            <strong className="text-lg">{money(Number(form.data.qty || 0) * Number(form.data.harga_satuan || 0))}</strong>
                        </div>
                    </div>
                    <Textarea label="Catatan" value={form.data.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                </Form>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 md:grid-cols-[1fr_260px_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(baseUrl, { search, gudang_id: gudangFilter }, { preserveScroll: true, preserveState: true, replace: true });
                        }}
                    >
                        <Input label="Search" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Gudang</span>
                            <Dropdown value={gudangFilter} options={filterGudangs} onChange={(value) => setGudangFilter(value)} />
                        </div>
                        <div className="flex items-end"><Button type="submit"><Search size={17} /> Cari</Button></div>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft">
                                <tr>{['Gudang', 'Kode', 'Material', 'Tanggal', 'Qty', 'Harga Awal', 'Total', 'Lock', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4">{row.gudang}</td>
                                        <td className="px-5 py-4 font-bold">{row.kode_barang}</td>
                                        <td className="px-5 py-4">{row.nama_barang}</td>
                                        <td className="px-5 py-4">{row.tanggal_saldo}</td>
                                        <td className="px-5 py-4 font-extrabold">{row.qty} {row.satuan}</td>
                                        <td className="px-5 py-4 font-bold">{money(row.harga_satuan)}</td>
                                        <td className="px-5 py-4 font-bold">{money(row.total_nilai)}</td>
                                        <td className="px-5 py-4 font-bold">{row.record_status_label}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                {row.record_status === 'locked' ? (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}><Unlock size={15} /> Unlock</Button>
                                                ) : (
                                                    <>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}><Lock size={15} /> Lock</Button>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => edit(row)}><Edit3 size={15} /> Edit</Button>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true })}><Trash2 size={15} /> Hapus</Button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>
        </>
    );
}

SaldoAwalMaterial.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Saldo Awal Material'}>{page}</AdminLayout>;
