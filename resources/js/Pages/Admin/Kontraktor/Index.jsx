import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, LoaderCircle, Lock, PlusCircle, Save, Search, Trash2, Unlock, X } from 'lucide-react';
import { useState } from 'react';
import { Button, Dropdown, Form, Input, TableActions, Textarea } from '../../../Components/UI';
import AuditCell from '../../../Components/UI/AuditCell';
import AdminLayout from '../../../Layouts/AdminLayout';

function FormErrorSummary({ errors }) {
    const messages = Object.values(errors ?? {}).filter(Boolean);

    if (messages.length === 0) return null;

    return (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            <p>Data belum bisa disimpan. Periksa bagian berikut:</p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {messages.map((message, index) => <li key={`${message}-${index}`}>{message}</li>)}
            </ul>
        </div>
    );
}

export default function Index({ title, description, baseUrl, rows, filters = {}, options }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState(null);
    const form = useForm({
        nama_kontraktor: '',
        jenis_badan: '',
        bidang_pekerjaan: '',
        penanggung_jawab: '',
        phone: '',
        email: '',
        alamat: '',
        catatan: '',
        status: 'aktif',
    });

    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const editRow = (row) => {
        setEditing(row);
        form.setData({
            nama_kontraktor: row.nama_kontraktor ?? '',
            jenis_badan: row.jenis_badan ?? '',
            bidang_pekerjaan: row.bidang_pekerjaan ?? '',
            penanggung_jawab: row.penanggung_jawab ?? '',
            phone: row.phone ?? '',
            email: row.email ?? '',
            alamat: row.alamat ?? '',
            catatan: row.catatan ?? '',
            status: row.status ?? 'aktif',
        });
    };

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true, onSuccess: resetForm };
        editing ? form.put(`${baseUrl}/${editing.id}`, requestOptions) : form.post(baseUrl, requestOptions);
    };

    const destroyRow = (row) => {
        if (!window.confirm(`Hapus kontraktor ${row.nama_kontraktor}? SPK kontraktor ini juga akan ikut terhapus.`)) return;
        router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    const lockRow = (row) => {
        if (!window.confirm(`Lock data kontraktor ${row.nama_kontraktor}?`)) return;
        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock kontraktor ${row.nama_kontraktor}?`)) return;
        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Manajemen Proyek</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <Form
                    collapsible
                    title={editing ? 'Ubah Kontraktor' : 'Tambah Kontraktor'}
                    description="Daftar kontraktor yang bisa dipilih ketika membuat SPK pembangunan rumah, jalan, atau pembukaan lahan."
                    onSubmit={submit}
                    actions={(
                        <>
                            {editing && <Button type="button" variant="outline" onClick={resetForm}><X size={17} /> Batal Ubah</Button>}
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />}
                                {editing ? 'Simpan Perubahan' : 'Tambah Kontraktor'}
                            </Button>
                        </>
                    )}
                >
                    <FormErrorSummary errors={form.errors} />
                    <div className="grid gap-4 md:grid-cols-3">
                        <Input label="Nama Kontraktor" value={form.data.nama_kontraktor} error={form.errors.nama_kontraktor} onChange={(event) => form.setData('nama_kontraktor', event.target.value)} />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Jenis Badan</span>
                            <Dropdown value={form.data.jenis_badan} options={options.jenisBadan} onChange={(value) => form.setData('jenis_badan', value)} />
                        </div>
                        <Input label="Bidang Pekerjaan" placeholder="Rumah, jalan, pembukaan lahan..." value={form.data.bidang_pekerjaan} error={form.errors.bidang_pekerjaan} onChange={(event) => form.setData('bidang_pekerjaan', event.target.value)} />
                        <Input label="Penanggung Jawab" value={form.data.penanggung_jawab} error={form.errors.penanggung_jawab} onChange={(event) => form.setData('penanggung_jawab', event.target.value)} />
                        <Input label="No. Telepon" value={form.data.phone} error={form.errors.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                        <Input label="Email" type="email" value={form.data.email} error={form.errors.email} onChange={(event) => form.setData('email', event.target.value)} />
                    </div>
                    <Textarea label="Alamat" value={form.data.alamat} error={form.errors.alamat} onChange={(event) => form.setData('alamat', event.target.value)} />
                    <div className="grid gap-4 md:grid-cols-[1fr_220px]">
                        <Textarea label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                        <div className="grid content-start gap-2">
                            <span className="text-sm font-extrabold">Status</span>
                            <Dropdown value={form.data.status} options={options.status} onChange={(value) => form.setData('status', value)} />
                        </div>
                    </div>
                </Form>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true }); }}>
                        <Input className="md:max-w-md" label="Pencarian" value={search} placeholder="Cari kode, nama, bidang, PIC..." onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>{['Kode', 'Nama', 'Bidang', 'PIC', 'Kontak', 'Kunci', 'Status', 'Audit', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-extrabold">{row.kode_kontraktor}</td>
                                        <td className="px-5 py-4 font-semibold">{row.nama_kontraktor}</td>
                                        <td className="px-5 py-4">{row.bidang_pekerjaan ?? '-'}</td>
                                        <td className="px-5 py-4">{row.penanggung_jawab ?? '-'}</td>
                                        <td className="px-5 py-4">{row.phone ?? row.email ?? '-'}</td>
                                        <td className="px-5 py-4 font-bold">{row.record_status_label}</td>
                                        <td className="px-5 py-4 font-bold">{row.status}</td>
                                        <td className="px-5 py-4"><AuditCell createdBy={row.created_by} updatedBy={row.updated_by} /></td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                {row.record_status === 'locked' ? (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}><Unlock size={15} /> Unlock</Button>
                                                ) : (
                                                    <>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}><Lock size={15} /> Kunci</Button>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={15} /> Ubah</Button>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => destroyRow(row)}><Trash2 size={15} /> Hapus</Button>
                                                    </>
                                                )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={9}>Belum ada kontraktor.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Manajemen Kontraktor'}>{page}</AdminLayout>;
