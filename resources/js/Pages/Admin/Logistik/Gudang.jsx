import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, LoaderCircle, Lock, Save, Search, Trash2, Unlock, X } from 'lucide-react';
import { useState } from 'react';
import { Button, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function Gudang({ title, baseUrl, rows, filters = {}, options }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState(null);
    const form = useForm({
        nama_gudang: '',
        cabang_id: '',
        perumahan_id: '',
        penanggung_jawab: '',
        phone: '',
        alamat: '',
        catatan: '',
        status: 'aktif',
    });

    const reset = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const edit = (row) => {
        setEditing(row);
        form.setData({
            nama_gudang: row.nama_gudang ?? '',
            cabang_id: row.cabang_id ?? '',
            perumahan_id: row.perumahan_id ?? '',
            penanggung_jawab: row.penanggung_jawab ?? '',
            phone: row.phone ?? '',
            alamat: row.alamat ?? '',
            catatan: row.catatan ?? '',
            status: row.status ?? 'aktif',
        });
    };

    const submit = (event) => {
        event.preventDefault();
        const opts = { preserveScroll: true, onSuccess: reset };
        editing ? form.put(`${baseUrl}/${editing.id}`, opts) : form.post(baseUrl, opts);
    };

    const lockRow = (row) => {
        if (!window.confirm(`Lock data gudang ${row.nama_gudang}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock gudang ${row.nama_gudang}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <Form collapsible title={editing ? 'Edit Gudang' : 'Tambah Gudang'} description="Gudang menjadi lokasi stok material." onSubmit={submit} actions={<>{editing && <Button type="button" variant="outline" onClick={reset}><X size={17} /> Batal</Button>}<Button type="submit" disabled={form.processing}>{form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />} Simpan</Button></>}>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Input label="Nama Gudang" value={form.data.nama_gudang} error={form.errors.nama_gudang} onChange={(event) => form.setData('nama_gudang', event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Cabang</span><Dropdown value={form.data.cabang_id} label="Opsional" options={options.cabangs} onChange={(value) => form.setData('cabang_id', value)} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown value={form.data.perumahan_id} label="Opsional" options={options.perumahans} onChange={(value) => form.setData('perumahan_id', value)} /></div>
                        <Input label="Penanggung Jawab" value={form.data.penanggung_jawab} onChange={(event) => form.setData('penanggung_jawab', event.target.value)} />
                        <Input label="No. Telepon" value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Status</span><Dropdown value={form.data.status} options={options.status} onChange={(value) => form.setData('status', value)} /></div>
                    </div>
                    <Textarea label="Alamat Gudang" value={form.data.alamat} onChange={(event) => form.setData('alamat', event.target.value)} />
                    <Textarea label="Catatan" value={form.data.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                </Form>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true }); }}>
                        <Input className="md:max-w-md" label="Search" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft"><tr>{['Kode', 'Gudang', 'Cabang', 'Perumahan', 'PIC', 'Lock', 'Status', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr></thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => <tr key={row.id}><td className="px-5 py-4 font-bold">{row.kode_gudang}</td><td className="px-5 py-4">{row.nama_gudang}</td><td className="px-5 py-4">{row.cabang}</td><td className="px-5 py-4">{row.perumahan}</td><td className="px-5 py-4">{row.penanggung_jawab ?? '-'}</td><td className="px-5 py-4 font-bold">{row.record_status_label}</td><td className="px-5 py-4 font-bold">{row.status}</td><td className="px-5 py-4"><div className="flex gap-2">{row.record_status === 'locked' ? <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}><Unlock size={15} /> Unlock</Button> : <><Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}><Lock size={15} /> Lock</Button><Button type="button" size="sm" variant="outline" onClick={() => edit(row)}><Edit3 size={15} /> Edit</Button><Button type="button" size="sm" variant="outline" onClick={() => router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true })}><Trash2 size={15} /> Hapus</Button></> }</div></td></tr>)}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Gudang.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Management Gudang'}>{page}</AdminLayout>;
