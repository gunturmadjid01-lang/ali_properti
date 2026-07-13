import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Eye, Lock, Pencil, Plus, RefreshCw, Search, Send, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, Input, Modal } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function IconButton({ title, icon: Icon, onClick, disabled = false, className = '' }) {
    return (
        <button
            type="button"
            title={title}
            disabled={disabled}
            onClick={onClick}
            className={`grid h-9 w-9 place-items-center rounded-lg border border-silver-deep/70 bg-white/80 text-ink-soft transition hover:bg-silver-soft hover:text-ink disabled:pointer-events-none disabled:opacity-45 dark:border-white/10 dark:bg-white/8 dark:text-white/70 dark:hover:bg-white/12 ${className}`}
        >
            <Icon size={16} />
        </button>
    );
}

const decimal = (value) => Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 3 });

export default function Index({ title, baseUrl, createUrl, rows = { data: [], links: [] }, filters = {}, permissions = {}, canCreate = false }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [detail, setDetail] = useState(null);
    const canUpdate = permissions.canUpdate ?? false;
    const canDelete = permissions.canDelete ?? false;
    const canApproveGudang = permissions.canApproveGudang ?? false;
    const canApproveOwner = permissions.canApproveOwner ?? false;
    const canLock = permissions.canLock ?? false;
    const canUnlock = permissions.canUnlock ?? false;
    const canIssue = permissions.canIssue ?? false;

    const submitFilter = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const resetFilter = () => {
        setSearch('');
        router.get(baseUrl, {}, { preserveScroll: true, preserveState: true, replace: true });
    };

    const lockRow = (row) => window.confirm(`Lock permintaan ${row.kode_request}?`) && router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    const unlockRow = (row) => window.confirm(`Buka lock permintaan ${row.kode_request}?`) && router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    const approveGudang = (row) => window.confirm(`Approve gudang untuk ${row.kode_request}?`) && router.post(`${baseUrl}/${row.id}/approve`, {}, { preserveScroll: true });
    const approveOwner = (row) => window.confirm(`Approve owner untuk ${row.kode_request}?`) && router.post(`${baseUrl}/${row.id}/approve-owner`, {}, { preserveScroll: true });
    const issueRow = (row) => window.confirm(`Kirim barang untuk ${row.kode_request}?`) && router.post(`${baseUrl}/${row.id}/issue`, {}, { preserveScroll: true });

    const workflowButtons = (row) => {
        return (
            <>
                {row.record_status === 'locked' ? (
                    <>
                        {canApproveGudang && row.can_approve_gudang && <IconButton title="Approve Gudang" icon={CheckCircle2} onClick={() => approveGudang(row)} />}
                        {canApproveOwner && row.can_approve_owner && <IconButton title="Approve Owner" icon={CheckCircle2} onClick={() => approveOwner(row)} />}
                        {canIssue && row.can_issue && <IconButton title="Kirim Barang" icon={Send} onClick={() => issueRow(row)} />}
                        {row.can_unlock && canUnlock && <IconButton title="Unlock" icon={Unlock} onClick={() => unlockRow(row)} />}
                    </>
                ) : (
                    <>
                        {canLock && row.can_lock && <IconButton title="Lock" icon={Lock} onClick={() => lockRow(row)} />}
                    </>
                )}
            </>
        );
    };

    return (
        <>
            <Head title={title} />
            <section className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6">
                <div className="border-b border-silver-deep/50 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4">
                    <form className="grid gap-2 xl:grid-cols-[1fr_auto]" onSubmit={submitFilter}>
                        <Input label="Kata Kunci" value={search} onChange={(event) => setSearch(event.target.value)} inputClassName="h-9 min-h-9 text-xs" />
                        <div className="flex items-end justify-end gap-2">
                            <Button type="submit" size="sm" variant="outline" title="Cari"><Search size={16} /></Button>
                            <Button type="button" size="sm" variant="outline" onClick={resetFilter} title="Refresh"><RefreshCw size={16} /></Button>
                            {canCreate && <Button as="a" href={createUrl} size="sm"><Plus size={16} /> Tambah</Button>}
                        </div>
                    </form>
                    <div className="mt-3 text-right text-xs font-black text-ink dark:text-white">Total data yang ditemukan: {rows.total ?? rows.data.length}</div>
                </div>

                <div className="max-h-[64vh] overflow-auto">
                    <table className="w-full min-w-[1120px] divide-y divide-silver-deep/60 text-xs">
                        <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                            <tr>
                                {['Kode', 'Tanggal', 'Gudang', 'Unit', 'Barang', 'Approval Gudang', 'Approval Owner', 'Audit', 'Status', 'Aksi'].map((column) => (
                                    <th key={column} className="px-3 py-3 font-extrabold">{column}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {rows.data.map((row) => (
                                <tr key={row.id} className="cursor-pointer hover:bg-silver-soft/70 dark:hover:bg-white/8" onDoubleClick={() => setDetail(row)}>
                                    <td className="px-3 py-2 font-black text-ink dark:text-white">{row.kode_request}</td>
                                    <td className="px-3 py-2">{row.tanggal}</td>
                                    <td className="px-3 py-2">{row.gudang}</td>
                                    <td className="px-3 py-2">{row.unit}</td>
                                    <td className="px-3 py-2">{row.items_text}</td>
                                    <td className="px-3 py-2 font-bold">{row.approved_at_gudang ? `${row.approved_by_gudang} · ${row.approved_at_gudang}` : 'Menunggu'}</td>
                                    <td className="px-3 py-2 font-bold">{row.approved_at_owner ? `${row.approved_by_owner} · ${row.approved_at_owner}` : 'Menunggu'}</td>
                                    <td className="px-3 py-2 text-xs font-bold text-ink-soft dark:text-white/65">
                                        Dibuat: {row.created_by_name}<br />
                                        Diubah: {row.updated_by_name}<br />
                                        {row.issued_at && <>Dikeluarkan: {row.issued_by_name}</>}
                                    </td>
                                    <td className="px-3 py-2 font-bold">{row.record_status_label}</td>
                                    <td className="px-3 py-2">
                                        <div className="flex gap-1.5">
                                            <IconButton title="Detail" icon={Eye} onClick={() => setDetail(row)} />
                                            {canUpdate && row.can_edit && <IconButton title="Edit" icon={Pencil} onClick={() => router.visit(`${baseUrl}/${row.id}/edit`)} />}
                                            {canDelete && row.can_delete && <IconButton title="Hapus" icon={Trash2} onClick={() => window.confirm(`Hapus ${row.kode_request}?`) && router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true })} className="text-red-600 dark:text-red-300" />}
                                            {workflowButtons(row)}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {rows.data.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="px-4 py-10 text-center font-semibold text-ink-soft dark:text-white/55">Belum ada permintaan barang.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={rows.links} />
            </section>

            <Modal open={Boolean(detail)} onClose={() => setDetail(null)} title={detail ? `Detail ${detail.kode_request}` : 'Detail Permintaan Barang'} size="xl">
                {detail && (
                    <div className="grid gap-4">
                        <div className="grid gap-3 rounded-lg border border-silver-deep/60 bg-silver-soft/60 p-4 dark:border-white/10 dark:bg-white/5 md:grid-cols-5">
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Tanggal</p><p className="mt-1 font-black">{detail.tanggal}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Gudang</p><p className="mt-1 font-black">{detail.gudang}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Unit</p><p className="mt-1 font-black">{detail.unit}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Status</p><p className="mt-1 font-black">{detail.record_status_label}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Barang</p><p className="mt-1 font-black">{detail.items?.length ?? 0}</p></div>
                        </div>

                        <div className="overflow-auto rounded-lg border border-silver-deep/60 dark:border-white/10">
                            <table className="w-full min-w-[860px] divide-y divide-silver-deep/60 text-xs">
                                <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/70">
                                    <tr>{['No', 'Kode Item', 'Material', 'Qty', 'Satuan', 'Catatan'].map((column) => <th className="px-3 py-3 font-extrabold" key={column}>{column}</th>)}</tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {detail.items.map((item, index) => (
                                        <tr key={`${item.barang_material_id}-${index}`}>
                                            <td className="px-3 py-2 font-bold">{index + 1}</td>
                                            <td className="px-3 py-2">{item.kode_barang ?? '-'}</td>
                                            <td className="px-3 py-2 font-bold">{item.nama_barang ?? '-'}</td>
                                            <td className="px-3 py-2 text-right font-black">{decimal(item.qty)}</td>
                                            <td className="px-3 py-2">{item.satuan ?? '-'}</td>
                                            <td className="px-3 py-2">{item.catatan ?? '-'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="grid gap-2 md:ml-auto md:w-96">
                            <TotalRow label="Total Item" value={detail.items?.length ?? 0} />
                        </div>
                    </div>
                )}
            </Modal>
        </>
    );
}

function TotalRow({ label, value }) {
    return (
        <div className="grid grid-cols-[1fr_160px] items-center gap-3 text-sm font-bold">
            <span className="text-right text-ink-soft dark:text-white/60">{label}</span>
            <span className="rounded-md border border-silver-deep/60 bg-white/80 px-3 py-2 text-right dark:border-white/10 dark:bg-white/8">{Number.isFinite(Number(value)) ? decimal(value) : value}</span>
        </div>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Permintaan Barang'}>{page}</AdminLayout>;
