import { Head, router, useForm } from '@inertiajs/react';
import { Lock, Save, Search, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';
import { Button, CurrencyInput, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

export default function HargaMaterial({ title, baseUrl, rows, filters = {}, options }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const form = useForm({ barang_material_id: '', tanggal_berlaku: new Date().toISOString().slice(0, 10), harga_satuan: '', supplier: '', keterangan: '', status: 'aktif' });
    const submit = (event) => { event.preventDefault(); form.post(baseUrl, { preserveScroll: true, onSuccess: () => form.reset('barang_material_id', 'harga_satuan', 'supplier', 'keterangan') }); };
    const lockRow = (row) => {
        if (!window.confirm(`Lock harga material ${row.material}?`)) return;
        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };
    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock harga material ${row.material}?`)) return;
        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <Form collapsible title="Tambah Harga Dasar Material" description="Harga aktif akan menjadi harga default untuk HPP dan pembelian material." onSubmit={submit} actions={<Button type="submit" disabled={form.processing}><Save size={17} /> Simpan Harga</Button>}>
                    <div className="grid gap-4 md:grid-cols-4">
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Material</span><Dropdown value={form.data.barang_material_id} label="Pilih Material" options={options.materials} onChange={(value, selected) => form.setData({ ...form.data, barang_material_id: value, harga_satuan: selected?.harga_hpp ?? form.data.harga_satuan })} />{form.errors.barang_material_id && <span className="text-xs font-bold text-red-600">{form.errors.barang_material_id}</span>}</div>
                        <Input label="Tanggal Berlaku" type="date" value={form.data.tanggal_berlaku} error={form.errors.tanggal_berlaku} onChange={(event) => form.setData('tanggal_berlaku', event.target.value)} />
                        <CurrencyInput label="Harga Satuan" value={form.data.harga_satuan} error={form.errors.harga_satuan} onChange={(value) => form.setData('harga_satuan', value)} />
                        <Input label="Supplier" value={form.data.supplier} onChange={(event) => form.setData('supplier', event.target.value)} />
                    </div>
                    <Textarea label="Keterangan" value={form.data.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} />
                </Form>
                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true });
                        }}
                    >
                        <Input className="md:max-w-md" label="Search" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft">
                                <tr>
                                    {['Material', 'Tanggal', 'Harga', 'Supplier', 'Lock', 'Status', 'Aksi'].map((column) => (
                                        <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4">{row.material}</td>
                                        <td className="px-5 py-4">{row.tanggal_berlaku}</td>
                                        <td className="px-5 py-4 font-bold">{money(row.harga_satuan)}</td>
                                        <td className="px-5 py-4">{row.supplier ?? '-'}</td>
                                        <td className="px-5 py-4 font-bold">{row.record_status_label}</td>
                                        <td className="px-5 py-4">{row.status}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                {row.record_status === 'locked' ? (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}>
                                                        <Unlock size={15} /> Unlock
                                                    </Button>
                                                ) : (
                                                    <>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}>
                                                            <Lock size={15} /> Lock
                                                        </Button>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true })}>
                                                            <Trash2 size={15} /> Hapus
                                                        </Button>
                                                    </>
                                                )}
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

HargaMaterial.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Harga Material'}>{page}</AdminLayout>;
