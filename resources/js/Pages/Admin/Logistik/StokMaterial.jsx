import { Head, router } from '@inertiajs/react';
import { ArrowDownAZ, ArrowUpAZ, FileText, PackagePlus, RefreshCw, Search, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Dropdown } from '../../../Components/UI';

const money = (value) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value ?? 0));
const number = (value) => Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 6 });

function ToolbarButton({ icon: Icon, label, disabled = false, onClick }) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className="inline-flex h-9 items-center gap-1.5 rounded-lg border border-silver-deep/70 bg-white/80 px-3 text-xs font-extrabold text-ink-soft transition hover:bg-silver-soft hover:text-ink disabled:cursor-not-allowed disabled:opacity-45 dark:border-white/10 dark:bg-white/8 dark:text-white/70 dark:hover:bg-white/12 dark:hover:text-white"
        >
            <Icon size={15} />
            {label}
        </button>
    );
}

function DesktopSelect({ value, onChange, options = [], className = '' }) {
    return <Dropdown value={String(value)} options={options} onChange={onChange} className={className} buttonClassName="min-h-9 h-9 px-3 text-xs" />;
}

export default function StokMaterial({ title, dataUrl, cardUrl, masterMaterialUrl, rows = { data: [], links: [] }, filters = {}, options = {}, selectedGudang = null, assignmentWarning = null }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [gudangId, setGudangId] = useState(filters.gudang_id ?? selectedGudang?.id ?? '');
    const [kategori, setKategori] = useState(filters.kategori ?? '');
    const [perPage, setPerPage] = useState(filters.per_page ?? '10');
    const [currentRows, setCurrentRows] = useState(rows);
    const [currentFilters, setCurrentFilters] = useState(filters);
    const [selectedRow, setSelectedRow] = useState(rows.data?.[0] ?? null);
    const [processing, setProcessing] = useState(false);

    const gudangOptions = useMemo(() => options.gudangs ?? [], [options.gudangs]);
    const deptOptions = useMemo(() => [{ value: '', label: 'Semua Gudang' }, ...gudangOptions], [gudangOptions]);
    const kategoriOptions = useMemo(() => [{ value: '', label: 'Semua' }, ...(options.kategoriMaterials ?? [])], [options.kategoriMaterials]);
    const pageOptions = useMemo(() => [
        { value: '10', label: '10 data' },
        { value: '25', label: '25 data' },
        { value: '50', label: '50 data' },
        { value: 'all', label: 'Semua data' },
    ], []);

    const loadRows = async (next = {}) => {
        setProcessing(true);
        const params = new URLSearchParams({
            search,
            gudang_id: gudangId,
            kategori,
            per_page: perPage,
            ...next,
        });

        try {
            const response = await fetch(`${dataUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Gagal memuat stok material.');
            }

            const payload = await response.json();
            const nextRows = payload.rows ?? { data: [] };
            setCurrentRows(nextRows);
            setCurrentFilters(payload.filters ?? {});
            setSelectedRow(nextRows.data?.[0] ?? null);
        } catch (error) {
            window.alert(error.message);
        } finally {
            setProcessing(false);
        }
    };

    const submitItems = (event) => {
        event.preventDefault();
        loadRows({ page: 1 });
    };

    const openStockCard = (row = selectedRow) => {
        if (!row) return;
        router.visit(`${cardUrl}?kode_item=${encodeURIComponent(row.kode_barang)}&gudang_id=${gudangId}`);
    };

    const deleteSelected = () => {
        if (!selectedRow) return;
        if (!window.confirm(`Hapus item ${selectedRow.kode_barang} - ${selectedRow.nama_barang}?`)) return;
        router.delete(`${masterMaterialUrl}/${selectedRow.id}`, { preserveScroll: true });
    };

    const currentPage = currentRows.current_page ?? 1;
    const lastPage = currentRows.last_page ?? 1;

    return (
        <>
            <Head title={title} />
            <div className="grid gap-4">
                <div className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6">
                    <div className="flex flex-col gap-3 border-b border-silver-deep/50 px-5 py-4 dark:border-white/10 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p className="text-[11px] font-black uppercase tracking-[0.18em] text-ink-soft dark:text-white/45">Gudang</p>
                            <h2 className="mt-1 text-xl font-black text-ink dark:text-white">Stok Material</h2>
                        </div>
                        <button type="button" className="inline-flex h-9 items-center gap-2 rounded-lg border border-silver-deep/70 bg-white/80 px-3 text-xs font-extrabold text-ink-soft transition hover:bg-silver-soft hover:text-ink dark:border-white/10 dark:bg-white/8 dark:text-white/70" onClick={() => router.visit(cardUrl)}>
                            <FileText size={15} /> Kartu Stok
                        </button>
                    </div>

                    <form className="border-b border-silver-deep/50 bg-silver-soft/45 px-5 py-4 dark:border-white/10 dark:bg-white/4" onSubmit={submitItems}>
                        <div className="grid gap-3 xl:grid-cols-[1fr_auto]">
                            <div className="grid gap-3 md:grid-cols-[1.2fr_1fr_1fr]">
                                <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                                    Kata Kunci
                                    <input value={search} onChange={(event) => setSearch(event.target.value)} className="h-9 rounded-lg border border-silver-deep/70 bg-white/85 px-3 text-xs font-bold text-ink outline-none focus:border-ink/30 dark:border-white/10 dark:bg-white/8 dark:text-white" />
                                </label>
                                <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                                    Dept. / Gudang
                                    <DesktopSelect value={gudangId} onChange={setGudangId} options={deptOptions} />
                                </label>
                                <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                                    Urut Berdasar
                                    <div className="flex gap-1">
                                        <DesktopSelect value="kode" onChange={() => {}} options={[{ value: 'kode', label: 'Kode Item' }, { value: 'nama', label: 'Nama Item' }]} className="min-w-0 flex-1" />
                                        <button type="button" className="grid h-9 w-10 place-items-center rounded-lg border border-silver-deep/70 bg-white/80 text-emerald-700 dark:border-white/10 dark:bg-white/8 dark:text-emerald-300" title="Urut naik"><ArrowUpAZ size={17} /></button>
                                        <button type="button" className="grid h-9 w-10 place-items-center rounded-lg border border-silver-deep/70 bg-white/80 text-emerald-700 dark:border-white/10 dark:bg-white/8 dark:text-emerald-300" title="Urut turun"><ArrowDownAZ size={17} /></button>
                                    </div>
                                </label>
                            </div>
                            <div className="flex items-end gap-2">
                                <button type="submit" className="inline-flex h-9 items-center gap-2 rounded-lg border border-silver-deep/70 bg-ink px-4 text-xs font-extrabold text-white dark:border-white/10 dark:bg-white dark:text-graphite" title="Cari"><Search size={16} /> Cari</button>
                                <button type="button" className="grid h-9 w-10 place-items-center rounded-lg border border-silver-deep/70 bg-white/80 text-emerald-700 dark:border-white/10 dark:bg-white/8 dark:text-emerald-300" title="Refresh" onClick={() => { setSearch(''); loadRows({ search: '', page: 1 }); }}><RefreshCw size={16} /></button>
                            </div>
                            <div className="md:col-span-2 xl:col-span-2">
                                <div className="flex flex-wrap items-end gap-3 text-xs font-bold text-ink-soft dark:text-white/60">
                                    <label className="grid min-w-[220px] gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                                        Kategori
                                        <DesktopSelect value={kategori} onChange={setKategori} options={kategoriOptions} />
                                    </label>
                                    <label className="grid min-w-[150px] gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                                        Tampilkan
                                        <DesktopSelect
                                            value={perPage}
                                            onChange={(value) => {
                                                setPerPage(value);
                                                loadRows({ per_page: value, page: 1 });
                                            }}
                                            options={pageOptions}
                                        />
                                    </label>
                                    <span className="ml-auto font-extrabold text-ink dark:text-white">Total: {currentFilters.total_found ?? currentRows.total ?? currentRows.data.length}</span>
                                </div>
                            </div>
                        </div>
                    </form>

                    {assignmentWarning && (
                        <div className="border-b border-amber-300/70 bg-amber-50 px-5 py-3 text-xs font-semibold text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                            {assignmentWarning}
                        </div>
                    )}

                    <div className="max-h-[58vh] overflow-auto bg-white dark:bg-transparent">
                        <table className="w-full min-w-[980px] divide-y divide-silver-deep/60 text-xs">
                            <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                                <tr>
                                    {['Kode Item', 'Nama Item', 'Stok', 'Satuan', 'Jenis / Merk', 'Harga Pokok', 'Total Harga Pokok', 'Stok Min.', 'Dept.'].map((column) => (
                                        <th key={column} className="px-4 py-3 font-extrabold">{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {currentRows.data.map((row) => {
                                    const selected = selectedRow?.id === row.id;
                                    return (
                                        <tr
                                            key={row.id}
                                            onClick={() => setSelectedRow(row)}
                                            onDoubleClick={() => openStockCard(row)}
                                            className={`cursor-pointer transition ${selected ? 'bg-emerald-50/80 dark:bg-emerald-400/16' : 'hover:bg-silver-soft/70 dark:hover:bg-white/8'}`}
                                        >
                                            <td className="px-4 py-3 font-black text-ink dark:text-white">{row.kode_barang}</td>
                                            <td className="max-w-[360px] truncate px-4 py-3 font-semibold">{row.nama_barang}</td>
                                            <td className="px-4 py-3 text-right font-black">{number(row.qty)}</td>
                                            <td className="px-4 py-3 font-bold">{row.satuan}</td>
                                            <td className="px-4 py-3">
                                                <div>{row.jenis_material || '-'}</div>
                                                <div className="text-[11px] font-semibold text-ink-soft dark:text-white/50">{row.merk_material || '-'}</div>
                                            </td>
                                            <td className="px-4 py-3 text-right font-bold">{money(row.harga_hpp)}</td>
                                            <td className="px-4 py-3 text-right font-black">{money(Number(row.qty ?? 0) * Number(row.harga_hpp ?? 0))}</td>
                                            <td className="px-4 py-3 text-right">{number(row.stok_minimum)}</td>
                                            <td className="px-4 py-3">{row.gudang}</td>
                                        </tr>
                                    );
                                })}
                                {currentRows.data.length === 0 && (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-10 text-center font-semibold text-ink-soft dark:text-white/55">Belum ada material yang cocok.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-silver-deep/60 bg-silver-soft/45 px-4 py-3 dark:border-white/10 dark:bg-white/4">
                        <div className="flex flex-wrap items-center gap-2">
                            <button type="button" disabled={processing || currentPage <= 1} onClick={() => loadRows({ page: Math.max(1, currentPage - 1) })} className="h-9 w-9 rounded-lg border border-silver-deep/70 bg-white/80 disabled:opacity-50 dark:border-white/10 dark:bg-white/8">&lt;</button>
                            <span className="min-w-24 text-center text-xs font-extrabold">Hal {currentPage} / {lastPage}</span>
                            <button type="button" disabled={processing || currentPage >= lastPage} onClick={() => loadRows({ page: Math.min(lastPage, currentPage + 1) })} className="h-9 w-9 rounded-lg border border-silver-deep/70 bg-white/80 disabled:opacity-50 dark:border-white/10 dark:bg-white/8">&gt;</button>

                            <span className="mx-2 h-7 border-l border-silver-deep/70 dark:border-white/10" />
                            <ToolbarButton icon={PackagePlus} label="Item Baru" onClick={() => router.visit(masterMaterialUrl)} />
                            <ToolbarButton icon={Trash2} label="Hapus Item" disabled={!selectedRow} onClick={deleteSelected} />
                            <ToolbarButton icon={FileText} label="Kartu Stok" disabled={!selectedRow} onClick={() => openStockCard()} />
                            {processing && <span className="text-xs font-extrabold text-ink-soft dark:text-white/55">Memuat...</span>}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

StokMaterial.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Stok Material'}>{page}</AdminLayout>;
