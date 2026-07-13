import { Head, router } from '@inertiajs/react';
import { ArrowDownAZ, ArrowUpAZ, Eye, Plus, RefreshCw, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import Pagination from '../../../../Components/Pagination';
import { Button, Dropdown, Input, Modal } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

const decimal = (value) => Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 3 });

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

export default function Index({ title, baseUrl, createUrl, rows = { data: [], links: [] }, filters = {}, options = {}, canCreate = false }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [gudangId, setGudangId] = useState(filters.gudang_id ?? '');
    const [sort, setSort] = useState(filters.sort ?? 'tanggal');
    const [direction, setDirection] = useState(filters.direction ?? 'desc');
    const [detail, setDetail] = useState(null);

    const submitFilter = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search, gudang_id: gudangId, sort, direction }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const resetFilter = () => {
        setSearch('');
        setGudangId('');
        setSort('tanggal');
        setDirection('desc');
        router.get(baseUrl, {}, { preserveScroll: true, preserveState: true, replace: true });
    };

    const filteredRows = useMemo(() => rows.data ?? [], [rows.data]);

    return (
        <>
            <Head title={title} />
            <section className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6">
                <div className="border-b border-silver-deep/50 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4">
                    <form className="grid gap-2 xl:grid-cols-[1fr_240px_240px_220px_auto]" onSubmit={submitFilter}>
                        <Input label="Kata Kunci" value={search} onChange={(event) => setSearch(event.target.value)} inputClassName="h-9 min-h-9 text-xs" />
                        <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                            Gudang
                            <Dropdown value={gudangId} label="Semua Gudang" options={[{ value: '', label: 'Semua Gudang' }, ...(options.gudangs ?? [])]} onChange={setGudangId} buttonClassName="min-h-9 text-xs" />
                        </label>
                        <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                            Urut Berdasar
                            <Dropdown value={sort} label="Tanggal" options={options.sortOptions ?? []} onChange={setSort} searchable={false} buttonClassName="min-h-9 text-xs" />
                        </label>
                        <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                            Arah
                            <div className="flex gap-1">
                                <button type="button" title="Urut naik" onClick={() => setDirection('asc')} className={`grid h-9 w-10 place-items-center rounded-lg border border-silver-deep/70 ${direction === 'asc' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/20 dark:text-emerald-200' : 'bg-white/80 text-ink-soft dark:bg-white/8 dark:text-white/65'}`}><ArrowUpAZ size={16} /></button>
                                <button type="button" title="Urut turun" onClick={() => setDirection('desc')} className={`grid h-9 w-10 place-items-center rounded-lg border border-silver-deep/70 ${direction === 'desc' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/20 dark:text-emerald-200' : 'bg-white/80 text-ink-soft dark:bg-white/8 dark:text-white/65'}`}><ArrowDownAZ size={16} /></button>
                            </div>
                        </label>
                        <div className="flex items-end justify-end gap-2">
                            <Button type="submit" size="sm" variant="outline" title="Cari"><Search size={16} /></Button>
                            <Button type="button" size="sm" variant="outline" onClick={resetFilter} title="Refresh"><RefreshCw size={16} /></Button>
                            {canCreate && <Button as="a" href={createUrl} size="sm"><Plus size={16} /> Tambah</Button>}
                        </div>
                    </form>
                    <div className="mt-3 text-right text-xs font-black text-ink dark:text-white">Total data yang ditemukan: {rows.total ?? filteredRows.length}</div>
                </div>

                <div className="max-h-[64vh] overflow-auto">
                    <table className="w-full min-w-[1120px] divide-y divide-silver-deep/60 text-xs">
                        <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                            <tr>
                                {['No Opname', 'Tanggal', 'Gudang', 'Keterangan', 'Item', 'Selisih', 'Status', 'Aksi'].map((column) => (
                                    <th className="px-3 py-3 font-extrabold" key={column}>{column}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {filteredRows.map((row) => (
                                <tr key={row.id} className="cursor-pointer hover:bg-silver-soft/70 dark:hover:bg-white/8" onDoubleClick={() => setDetail(row)}>
                                    <td className="px-3 py-2 font-black text-ink dark:text-white">{row.kode_opname}</td>
                                    <td className="px-3 py-2">{row.tanggal}</td>
                                    <td className="px-3 py-2 font-bold">{row.gudang}</td>
                                    <td className="px-3 py-2">{row.keterangan}</td>
                                    <td className="px-3 py-2 font-bold">{row.total_item}</td>
                                    <td className="px-3 py-2 text-right font-black">{decimal(row.total_selisih)}</td>
                                    <td className="px-3 py-2 font-bold text-emerald-600 dark:text-emerald-300">{row.status_label}</td>
                                    <td className="px-3 py-2">
                                        <div className="flex gap-1.5">
                                            <IconButton title="Detail" icon={Eye} onClick={() => setDetail(row)} />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {filteredRows.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-4 py-10 text-center font-semibold text-ink-soft dark:text-white/55">Belum ada stock opname.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={rows.links} />
            </section>

            <Modal open={Boolean(detail)} onClose={() => setDetail(null)} title={detail ? `Detail ${detail.kode_opname}` : 'Detail Stock Opname'} size="xl">
                {detail && (
                    <div className="grid gap-4">
                        <div className="grid gap-3 rounded-lg border border-silver-deep/60 bg-silver-soft/60 p-4 dark:border-white/10 dark:bg-white/5 md:grid-cols-4">
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Tanggal</p><p className="mt-1 font-black">{detail.tanggal}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Gudang</p><p className="mt-1 font-black">{detail.gudang}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Item</p><p className="mt-1 font-black">{detail.total_item}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Selisih</p><p className="mt-1 font-black">{decimal(detail.total_selisih)}</p></div>
                        </div>
                        <div className="overflow-auto rounded-lg border border-silver-deep/60 dark:border-white/10">
                            <table className="w-full min-w-[980px] divide-y divide-silver-deep/60 text-xs">
                                <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/70">
                                    <tr>{['No', 'Kode Item', 'Material', 'Jenis / Merk', 'Satuan', 'Buku', 'Fisik', 'Masuk', 'Keluar', 'Selisih'].map((column) => <th className="px-3 py-3 font-extrabold" key={column}>{column}</th>)}</tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {detail.items.map((item, index) => (
                                        <tr key={`${item.kode_barang}-${index}`}>
                                            <td className="px-3 py-2 font-bold">{index + 1}</td>
                                            <td className="px-3 py-2">{item.kode_barang}</td>
                                            <td className="px-3 py-2 font-bold">{item.nama_barang}</td>
                                            <td className="px-3 py-2">{item.jenis_merk}</td>
                                            <td className="px-3 py-2">{item.satuan}</td>
                                            <td className="px-3 py-2 text-right">{decimal(item.stok_sistem)}</td>
                                            <td className="px-3 py-2 text-right">{decimal(item.fisik)}</td>
                                            <td className="px-3 py-2 text-right font-black text-emerald-600 dark:text-emerald-300">{decimal(item.masuk ?? 0)}</td>
                                            <td className="px-3 py-2 text-right font-black text-rose-600 dark:text-rose-300">{decimal(item.keluar ?? 0)}</td>
                                            <td className="px-3 py-2 text-right font-black">{decimal(item.selisih)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="grid gap-2 md:ml-auto md:w-96">
                            <TotalRow label="Total Item" value={detail.total_item} />
                            <TotalRow label="Total Selisih" value={detail.total_selisih} strong />
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
            <span className="rounded-md border border-silver-deep/60 bg-white/80 px-3 py-2 text-right dark:border-white/10 dark:bg-white/8">{decimal(value)}</span>
        </div>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Stock Opname Material'}>{page}</AdminLayout>;
