import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, Lock, Plus, Search, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, Dropdown, Input, Modal, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const emptyForm = {
    nama_supplier: '',
    pic: '',
    phone: '',
    email: '',
    alamat: '',
    nama_bank: '',
    nomor_rekening: '',
    nama_rekening: '',
    npwp: '',
    catatan: '',
    status: 'aktif',
};

function IconButton({ title, icon: Icon, onClick, disabled = false, className = '' }) {
    return (
        <button
            type="button"
            title={title}
            disabled={disabled}
            onClick={onClick}
            className={`grid h-9 w-9 place-items-center rounded-lg border border-silver-deep/70 bg-white/80 text-ink-soft transition hover:bg-silver-soft hover:text-ink disabled:pointer-events-none disabled:opacity-40 dark:border-white/10 dark:bg-white/8 dark:text-white/70 dark:hover:bg-white/12 dark:hover:text-white ${className}`}
        >
            <Icon size={16} />
        </button>
    );
}

export default function Supplier({ title, baseUrl, rows, filters = {}, options = {}, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [editing, setEditing] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const form = useForm(emptyForm);

    const openCreate = () => {
        setEditing(null);
        form.clearErrors();
        form.setData(emptyForm);
        setModalOpen(true);
    };

    const openEdit = (row) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            nama_supplier: row.nama_supplier ?? '',
            pic: row.pic ?? '',
            phone: row.phone ?? '',
            email: row.email ?? '',
            alamat: row.alamat ?? '',
            nama_bank: row.nama_bank ?? '',
            nomor_rekening: row.nomor_rekening ?? '',
            nama_rekening: row.nama_rekening ?? '',
            npwp: row.npwp ?? '',
            catatan: row.catatan ?? '',
            status: row.status ?? 'aktif',
        });
        setModalOpen(true);
    };

    const submit = (event) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setModalOpen(false);
                setEditing(null);
                form.setData(emptyForm);
            },
        };

        if (editing) {
            form.put(`${baseUrl}/${editing.id}`, options);
            return;
        }

        form.post(baseUrl, options);
    };

    const filter = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search, status }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const destroy = (row) => {
        if (!window.confirm(`Hapus supplier ${row.nama_supplier}?`)) return;
        router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    const postAction = (row, action) => {
        router.post(`${baseUrl}/${row.id}/${action}`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-4">
                <section className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6">
                    <div className="flex flex-col gap-3 border-b border-silver-deep/50 px-5 py-4 dark:border-white/10 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p className="text-[11px] font-black uppercase tracking-[0.18em] text-ink-soft dark:text-white/45">Master Logistik</p>
                            <h1 className="mt-1 text-xl font-black text-ink dark:text-white">Kelola Supplier</h1>
                        </div>
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                            <form className="flex flex-col gap-2 md:flex-row md:items-end" onSubmit={filter}>
                                <Input label="Cari Supplier" value={search} onChange={(event) => setSearch(event.target.value)} inputClassName="md:w-72" />
                                <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                                    Status
                                    <Dropdown value={status} label="Semua Status" options={options.status ?? []} onChange={setStatus} searchable={false} buttonClassName="md:w-44" />
                                </label>
                                <Button type="submit" variant="outline"><Search size={16} /> Cari</Button>
                            </form>
                            {permissions.canCreate && (
                                <Button type="button" onClick={openCreate}><Plus size={16} /> Supplier Baru</Button>
                            )}
                        </div>
                    </div>

                    <div className="max-h-[62vh] overflow-auto">
                        <table className="w-full min-w-[1040px] divide-y divide-silver-deep/60 text-xs">
                            <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                                <tr>
                                    {['Kode', 'Supplier', 'Kontak', 'Rekening', 'NPWP', 'Pembelian', 'Status', 'Lock', 'Aksi'].map((column) => (
                                        <th className="px-4 py-3 font-extrabold" key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr key={row.id} className="hover:bg-silver-soft/70 dark:hover:bg-white/8">
                                        <td className="px-4 py-3 font-black text-ink dark:text-white">{row.kode_supplier}</td>
                                        <td className="px-4 py-3">
                                            <div className="font-black text-ink dark:text-white">{row.nama_supplier}</div>
                                            <div className="mt-1 max-w-[260px] truncate text-[11px] font-semibold text-ink-soft dark:text-white/50">{row.alamat || '-'}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="font-bold">{row.pic || '-'}</div>
                                            <div className="text-[11px] text-ink-soft dark:text-white/50">{row.phone || row.email || '-'}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="font-bold">{row.nama_bank || '-'}</div>
                                            <div className="text-[11px] text-ink-soft dark:text-white/50">{row.nomor_rekening || '-'}</div>
                                        </td>
                                        <td className="px-4 py-3">{row.npwp || '-'}</td>
                                        <td className="px-4 py-3 text-right font-black">{row.purchases_count}</td>
                                        <td className="px-4 py-3">
                                            <span className={`rounded-full px-3 py-1 text-[11px] font-black ${row.status === 'aktif' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-white/60'}`}>
                                                {row.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 font-bold">{row.record_status_label}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex gap-1.5">
                                                {row.record_status === 'locked' ? (
                                                    <IconButton title="Unlock" icon={Unlock} disabled={!permissions.canUnlock} onClick={() => postAction(row, 'unlock')} />
                                                ) : (
                                                    <>
                                                        <IconButton title="Edit" icon={Edit3} disabled={!permissions.canUpdate} onClick={() => openEdit(row)} />
                                                        <IconButton title="Hapus" icon={Trash2} disabled={!permissions.canDelete} onClick={() => destroy(row)} className="hover:text-red-600 dark:hover:text-red-300" />
                                                        <IconButton title="Lock" icon={Lock} disabled={!permissions.canLock} onClick={() => postAction(row, 'lock')} />
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-10 text-center font-semibold text-ink-soft dark:text-white/55">Belum ada supplier yang cocok.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination links={rows.links} />
                </section>
            </div>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editing ? `Edit ${editing.nama_supplier}` : 'Supplier Baru'}
                size="lg"
                footer={(
                    <>
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>Batal</Button>
                        <Button type="submit" form="supplier-form" disabled={form.processing}>Simpan</Button>
                    </>
                )}
            >
                <form id="supplier-form" className="grid gap-4" onSubmit={submit}>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Input label="Nama Supplier" value={form.data.nama_supplier} error={form.errors.nama_supplier} onChange={(event) => form.setData('nama_supplier', event.target.value)} />
                        <Input label="PIC" value={form.data.pic} error={form.errors.pic} onChange={(event) => form.setData('pic', event.target.value)} />
                        <Input label="No. Telepon" value={form.data.phone} error={form.errors.phone} onChange={(event) => form.setData('phone', event.target.value)} />
                        <Input label="Email" type="email" value={form.data.email} error={form.errors.email} onChange={(event) => form.setData('email', event.target.value)} />
                        <Input label="Bank" value={form.data.nama_bank} error={form.errors.nama_bank} onChange={(event) => form.setData('nama_bank', event.target.value)} />
                        <Input label="Nomor Rekening" value={form.data.nomor_rekening} error={form.errors.nomor_rekening} onChange={(event) => form.setData('nomor_rekening', event.target.value)} />
                        <Input label="Nama Rekening" value={form.data.nama_rekening} error={form.errors.nama_rekening} onChange={(event) => form.setData('nama_rekening', event.target.value)} />
                        <Input label="NPWP" value={form.data.npwp} error={form.errors.npwp} onChange={(event) => form.setData('npwp', event.target.value)} />
                    </div>
                    <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                        Status
                        <Dropdown value={form.data.status} label="Pilih Status" options={(options.status ?? []).filter((item) => item.value !== '')} onChange={(value) => form.setData('status', value)} searchable={false} />
                    </label>
                    <Textarea label="Alamat" value={form.data.alamat} error={form.errors.alamat} onChange={(event) => form.setData('alamat', event.target.value)} />
                    <Textarea label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                </form>
            </Modal>
        </>
    );
}

Supplier.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Kelola Supplier'}>{page}</AdminLayout>;
