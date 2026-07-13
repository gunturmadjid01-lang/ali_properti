import { Head, router } from '@inertiajs/react';
import { ArrowDownAZ, ArrowUpAZ, CheckCircle2, Eye, Lock, Pencil, Plus, RefreshCw, Search, ShoppingCart, Trash2, Unlock, XCircle } from 'lucide-react';
import { useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, Dropdown, Input, Modal } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value ?? 0));
const decimal = (value) => Number(value ?? 0).toLocaleString('id-ID');

function IconButton({ title, icon: Icon, onClick, disabled = false }) {
    return (
        <button
            type="button"
            title={title}
            disabled={disabled}
            onClick={onClick}
            className="grid h-9 w-9 place-items-center rounded-lg border border-silver-deep/70 bg-white/80 text-ink-soft transition hover:bg-silver-soft hover:text-ink disabled:pointer-events-none disabled:opacity-45 dark:border-white/10 dark:bg-white/8 dark:text-white/70 dark:hover:bg-white/12"
        >
            <Icon size={16} />
        </button>
    );
}

export default function Index({ title, baseUrl, createUrl, rows = { data: [], links: [] }, filters = {}, options = {}, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [gudangId, setGudangId] = useState(filters.gudang_id ?? '');
    const [days, setDays] = useState(filters.days ?? '');
    const [sort, setSort] = useState(filters.sort ?? 'tanggal');
    const [direction, setDirection] = useState(filters.direction ?? 'desc');
    const [detail, setDetail] = useState(null);

    const filter = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search, gudang_id: gudangId, days, sort, direction }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const reset = () => {
        setSearch('');
        setGudangId('');
        setDays('');
        setSort('tanggal');
        setDirection('desc');
        router.get(baseUrl, {}, { preserveScroll: true, preserveState: true, replace: true });
    };

    const post = (url, data = {}) => router.post(url, data, { preserveScroll: true });
    const lockRow = (row) => window.confirm(`Lock pembelian ${row.kode_pembelian}?`) && post(`${baseUrl}/${row.id}/lock`);
    const unlockRow = (row) => window.confirm(`Buka lock pembelian ${row.kode_pembelian}?`) && post(`${baseUrl}/${row.id}/unlock`);
    const approveRow = (row) => window.confirm(`Approve pembelian ${row.kode_pembelian}?`) && post(`${baseUrl}/${row.id}/approve`);

    const workflowButtons = (row) => {
        if (row.record_status === 'locked') {
            return row.can_unlock && permissions.canUnlock ? <IconButton title="Unlock" icon={Unlock} onClick={() => unlockRow(row)} /> : null;
        }

        return (
            <>
                {permissions.canApprove && row.can_approve && <IconButton title="Approve" icon={CheckCircle2} onClick={() => approveRow(row)} />}
                {permissions.canLock && row.can_lock && <IconButton title="Lock" icon={Lock} onClick={() => lockRow(row)} />}
            </>
        );
    };

    return (
        <>
            <Head title={title} />
            <section className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6">
                <div className="border-b border-silver-deep/50 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4">
                    <form className="grid gap-2 xl:grid-cols-[1fr_240px_210px_210px_auto]" onSubmit={filter}>
                        <Input label="Kata Kunci" value={search} onChange={(event) => setSearch(event.target.value)} inputClassName="h-9 min-h-9 text-xs" />
                        <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                            Dept. / Gudang
                            <Dropdown value={gudangId} label="Semua Gudang" options={[{ value: '', label: 'Semua Gudang' }, ...(options.gudangs ?? [])]} onChange={setGudangId} buttonClassName="min-h-9 text-xs" />
                        </label>
                        <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                            Tampilkan Data
                            <Dropdown
                                value={days}
                                label="Semua"
                                options={[
                                    { value: '', label: 'Semua data' },
                                    { value: '7', label: '7 hari terakhir' },
                                    { value: '30', label: '30 hari terakhir' },
                                    { value: '90', label: '90 hari terakhir' },
                                ]}
                                onChange={setDays}
                                searchable={false}
                                buttonClassName="min-h-9 text-xs"
                            />
                        </label>
                        <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                            Urut Berdasar
                            <div className="flex gap-1">
                                <Dropdown value={sort} options={[{ value: 'tanggal', label: 'Tanggal' }, { value: 'kode_pembelian', label: 'No Transaksi' }, { value: 'total_nominal', label: 'Total' }]} onChange={setSort} searchable={false} buttonClassName="min-h-9 text-xs" />
                                <button type="button" title="Urut naik" onClick={() => setDirection('asc')} className={`grid h-9 w-10 place-items-center rounded-lg border border-silver-deep/70 ${direction === 'asc' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/20 dark:text-emerald-200' : 'bg-white/80 text-ink-soft dark:bg-white/8 dark:text-white/65'}`}><ArrowUpAZ size={16} /></button>
                                <button type="button" title="Urut turun" onClick={() => setDirection('desc')} className={`grid h-9 w-10 place-items-center rounded-lg border border-silver-deep/70 ${direction === 'desc' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/20 dark:text-emerald-200' : 'bg-white/80 text-ink-soft dark:bg-white/8 dark:text-white/65'}`}><ArrowDownAZ size={16} /></button>
                            </div>
                        </label>
                        <div className="flex items-end justify-end gap-2">
                            <Button type="submit" size="sm" variant="outline" title="Cari"><Search size={16} /></Button>
                            <Button type="button" size="sm" variant="outline" onClick={reset} title="Refresh"><RefreshCw size={16} /></Button>
                            {permissions.canCreate && <Button as="a" href={createUrl} size="sm"><Plus size={16} /> Tambah</Button>}
                        </div>
                    </form>
                    <div className="mt-3 text-right text-xs font-black text-ink dark:text-white">Total data yang ditemukan: {rows.total ?? rows.data.length}</div>
                </div>

                <div className="max-h-[64vh] overflow-auto">
                    <table className="w-full min-w-[1120px] divide-y divide-silver-deep/60 text-xs">
                        <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                            <tr>
                                {['No Transaksi', 'Tanggal', 'Kd Supp.', 'Nama', 'Mata Uang', 'Keterangan', 'Total', 'User Buat', 'User Ubah', 'Aksi'].map((column) => (
                                    <th className="px-3 py-3 font-extrabold" key={column}>{column}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {rows.data.map((row) => (
                                <tr key={row.id} onDoubleClick={() => setDetail(row)} className="cursor-pointer hover:bg-silver-soft/70 dark:hover:bg-white/8">
                                    <td className="px-3 py-2 font-black text-ink dark:text-white">{row.kode_pembelian}</td>
                                    <td className="px-3 py-2">{row.tanggal}</td>
                                    <td className="px-3 py-2">{row.supplier_code}</td>
                                    <td className="px-3 py-2 font-bold">{row.supplier}</td>
                                    <td className="px-3 py-2">IDR</td>
                                    <td className="px-3 py-2 font-bold">{row.status_label}</td>
                                    <td className="px-3 py-2 text-right font-black">{money(row.total_nominal)}</td>
                                    <td className="px-3 py-2">{row.created_by_name}</td>
                                    <td className="px-3 py-2">{row.updated_by_name}</td>
                                    <td className="px-3 py-2">
                                        <div className="flex gap-1.5">
                                            <IconButton title="Detail" icon={Eye} onClick={() => setDetail(row)} />
                                            {permissions.canUpdate && row.can_edit && <IconButton title="Edit" icon={Pencil} onClick={() => router.visit(`${baseUrl}/${row.id}/edit`)} />}
                                            {workflowButtons(row)}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {rows.data.length === 0 && (
                                <tr>
                                    <td colSpan={10} className="px-4 py-10 text-center font-semibold text-ink-soft dark:text-white/55">Belum ada transaksi pembelian.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={rows.links} />
            </section>

            <Modal open={Boolean(detail)} onClose={() => setDetail(null)} title={detail ? `Detail ${detail.kode_pembelian}` : 'Detail Pembelian'} size="xl">
                {detail && (
                    <div className="grid gap-4">
                        <div className="grid gap-3 rounded-lg border border-silver-deep/60 bg-silver-soft/60 p-4 dark:border-white/10 dark:bg-white/5 md:grid-cols-5">
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Tanggal</p><p className="mt-1 font-black">{detail.tanggal}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Supplier</p><p className="mt-1 font-black">{detail.supplier}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Masuk ke</p><p className="mt-1 font-black">{detail.gudang}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Tanggal Barang Masuk</p><p className="mt-1 font-black">{detail.tanggal_barang_masuk}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Metode</p><p className="mt-1 font-black">{detail.metode_pembayaran === 'hutang' ? 'Hutang Supplier' : 'Tunai / Cash'}</p></div>
                        </div>
                        <div className="overflow-auto rounded-lg border border-silver-deep/60 dark:border-white/10">
                            <table className="w-full min-w-[940px] divide-y divide-silver-deep/60 text-xs">
                                <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/70">
                                    <tr>{['No', 'Kode Item', 'Keterangan', 'Jumlah', 'Satuan', 'Harga', 'Potongan', 'Total'].map((column) => <th className="px-3 py-3 font-extrabold" key={column}>{column}</th>)}</tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {detail.items.map((item, index) => (
                                        <tr key={item.id}>
                                            <td className="px-3 py-2 font-bold">{index + 1}</td>
                                            <td className="px-3 py-2">{item.kode_barang}</td>
                                            <td className="px-3 py-2 font-bold">{item.barang}</td>
                                            <td className="px-3 py-2 text-right">{decimal(item.qty)}</td>
                                            <td className="px-3 py-2">{item.satuan}</td>
                                            <td className="px-3 py-2 text-right">{money(item.harga_satuan)}</td>
                                            <td className="px-3 py-2 text-right">{money(item.diskon ?? 0)}</td>
                                            <td className="px-3 py-2 text-right font-black">{money(item.subtotal)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="grid gap-2 md:ml-auto md:w-96">
                            <TotalRow label="Sub Total" value={detail.subtotal_nominal ?? detail.total_nominal} />
                            <TotalRow label="Potongan Transaksi" value={detail.diskon_transaksi ?? 0} />
                            <TotalRow label="Total Akhir" value={detail.total_nominal} strong />
                        </div>
                    </div>
                )}
            </Modal>

        </>
    );
}

function TotalRow({ label, value, strong = false }) {
    return (
        <div className={`grid grid-cols-[1fr_160px] items-center gap-3 text-sm ${strong ? 'font-black' : 'font-bold'}`}>
            <span className="text-right text-ink-soft dark:text-white/60">{label}</span>
            <span className="rounded-md border border-silver-deep/60 bg-white/80 px-3 py-2 text-right dark:border-white/10 dark:bg-white/8">{money(value)}</span>
        </div>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Daftar Pembelian Material'}>{page}</AdminLayout>;
