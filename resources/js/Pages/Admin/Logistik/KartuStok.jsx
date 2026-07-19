import { Head, router } from '@inertiajs/react';
import { Printer, RefreshCw, Search } from 'lucide-react';
import { useState } from 'react';
import { Dropdown } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const number = (value) => Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 6 });

function ToolButton({ icon: Icon, children, onClick, disabled = false }) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className="inline-flex h-9 min-w-24 items-center justify-center gap-2 rounded-lg border border-silver-deep/70 bg-white/80 px-3 text-xs font-extrabold text-ink-soft transition hover:bg-silver-soft hover:text-ink disabled:cursor-not-allowed disabled:opacity-45 dark:border-white/10 dark:bg-white/8 dark:text-white/70 dark:hover:bg-white/12 dark:hover:text-white"
        >
            <Icon size={15} />
            {children}
        </button>
    );
}

export default function KartuStok({ title, dataUrl, filters = {}, options = {}, assignmentWarning = null, materialNotFound = false, selectedMaterial = null, card = {} }) {
    const [kodeItem, setKodeItem] = useState(filters.kode_item ?? selectedMaterial?.kode_barang ?? '');
    const [periode, setPeriode] = useState(filters.periode ?? 'tahunan');
    const [year, setYear] = useState(String(filters.year ?? new Date().getFullYear()));
    const [gudangId, setGudangId] = useState(filters.gudang_id ?? '');
    const [currentCard, setCurrentCard] = useState(card);
    const [currentMaterial, setCurrentMaterial] = useState(selectedMaterial);
    const [currentMaterialNotFound, setCurrentMaterialNotFound] = useState(materialNotFound);
    const [processing, setProcessing] = useState(false);

    const submit = async (event) => {
        event.preventDefault();
        setProcessing(true);

        const params = new URLSearchParams({
            kode_item: kodeItem.trim(),
            gudang_id: gudangId,
            periode,
            year,
        });

        try {
            const response = await fetch(`${dataUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Gagal memuat kartu stok.');
            }

            const payload = await response.json();
            setCurrentCard(payload.card ?? {});
            setCurrentMaterial(payload.selectedMaterial ?? null);
            setCurrentMaterialNotFound(Boolean(payload.materialNotFound));
        } catch (error) {
            window.alert(error.message);
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title={title} />
            <div className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6">
                <div className="flex flex-col gap-3 border-b border-silver-deep/50 px-5 py-4 dark:border-white/10 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-[11px] font-black uppercase tracking-[0.18em] text-ink-soft dark:text-white/45">Gudang</p>
                        <h2 className="mt-1 text-xl font-black text-ink dark:text-white">Kartu Stok</h2>
                    </div>
                </div>

                {assignmentWarning && (
                    <div className="border-b border-amber-300/70 bg-amber-50 px-5 py-3 text-xs font-semibold text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                        {assignmentWarning}
                    </div>
                )}

                <div className="grid min-h-[calc(100vh-210px)] grid-rows-[auto_1fr_auto]">
                    <form className="border-b border-silver-deep/50 bg-silver-soft/45 px-5 py-4 dark:border-white/10 dark:bg-white/4" onSubmit={submit}>
                        <div className="grid gap-3 xl:grid-cols-[160px_40px_1fr_180px_120px_220px_auto] xl:items-end">
                            <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">Kode Item
                            <input
                                value={kodeItem}
                                onChange={(event) => setKodeItem(event.target.value)}
                                className="h-9 rounded-lg border border-silver-deep/70 bg-white/85 px-3 text-xs font-bold text-ink outline-none focus:border-ink/30 dark:border-white/10 dark:bg-white/8 dark:text-white"
                                placeholder="0600"
                            />
                            </label>
                            <button type="submit" className="grid h-9 w-10 place-items-center rounded-lg border border-silver-deep/70 bg-white/80 text-ink-soft transition hover:bg-silver-soft hover:text-ink dark:border-white/10 dark:bg-white/8 dark:text-white/70" title="Proses kode item">
                                <Search size={15} />
                            </button>
                            <div className="flex h-9 items-center rounded-lg border border-silver-deep/70 bg-silver-soft/70 px-3 text-xs font-bold text-ink dark:border-white/10 dark:bg-white/4 dark:text-white">
                                {currentMaterial?.nama_barang ?? 'Material belum diproses'}
                            </div>
                            {currentMaterialNotFound && (
                                <>
                                    <span />
                                    <div className="border border-red-300 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-200">
                                        Material tidak ditemukan.
                                    </div>
                                </>
                            )}

                            <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">Periode
                                <Dropdown
                                    value={periode}
                                    label="Pilih Periode"
                                    options={options.periods ?? []}
                                    searchable={false}
                                    onChange={setPeriode}
                                    buttonClassName="min-h-9 px-3 text-xs"
                                />
                            </label>
                            <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">Tahun
                                <input type="number" step="1" min="2000" max="2100" value={year} onChange={(event) => setYear(event.target.value)} className="h-9 w-full rounded-lg border border-silver-deep/70 bg-white/85 px-3 text-xs font-bold text-ink dark:border-white/10 dark:bg-white/8 dark:text-white" />
                            </label>
                            <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">Gudang
                                <Dropdown
                                    value={gudangId}
                                    label="Pilih Gudang"
                                    options={options.gudangs ?? []}
                                    searchable={false}
                                    onChange={setGudangId}
                                    buttonClassName="min-h-9 px-3 text-xs"
                                />
                            </label>
                            <button type="submit" className="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-silver-deep/70 bg-ink px-4 text-xs font-extrabold text-white dark:border-white/10 dark:bg-white dark:text-graphite">
                                <Search size={15} /> {processing ? 'Memuat...' : 'Proses'}
                            </button>
                        </div>
                    </form>

                    <div className="overflow-auto bg-white dark:bg-transparent">
                        <table className="w-full min-w-[1120px] divide-y divide-silver-deep/60 text-xs">
                            <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                                <tr>
                                    {['No Transaksi', 'Kantor', 'Tanggal', 'Tipe', 'Keterangan', 'Input Asli', 'Masuk Level 1', 'Keluar Level 1', 'Saldo Level 1', 'Supplier/Pelanggan'].map((column) => (
                                        <th key={column} className="px-4 py-3 font-extrabold">{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {(currentCard.rows ?? []).map((row, index) => (
                                    <tr key={`${row.kode_transaksi}-${index}`} className="transition hover:bg-silver-soft/70 dark:hover:bg-white/8">
                                        <td className="px-4 py-3 font-black text-ink dark:text-white">{row.kode_transaksi}</td>
                                        <td className="px-4 py-3">{currentCard.gudang_label ?? '-'}</td>
                                        <td className="px-4 py-3">{row.tanggal}</td>
                                        <td className="px-4 py-3 font-black uppercase">{row.jenis}</td>
                                        <td className="px-4 py-3">{row.keterangan}</td>
                                        <td className="px-4 py-3 font-bold">{row.input ?? '-'}</td>
                                        <td className="px-4 py-3 text-right">{Number(row.masuk || 0) > 0 ? number(row.masuk) : '0,00'}</td>
                                        <td className="px-4 py-3 text-right">{Number(row.keluar || 0) > 0 ? number(row.keluar) : '0,00'}</td>
                                        <td className="px-4 py-3 text-right font-black">{number(row.saldo)}</td>
                                        <td className="px-4 py-3">{row.sumber}</td>
                                    </tr>
                                ))}
                                {(currentCard.rows ?? []).length === 0 && (
                                    <tr>
                                        <td colSpan={9} className="px-4 py-10 text-center font-semibold text-[#64748b] dark:text-white/55">
                                            Masukkan kode item, atur periode, lalu tekan Proses.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-silver-deep/60 bg-silver-soft/45 px-4 py-3 dark:border-white/10 dark:bg-white/4">
                        <div className="flex flex-wrap items-center gap-3">
                            <div className="flex flex-wrap gap-2 text-xs font-extrabold text-ink-soft dark:text-white/60">
                                <span className="rounded-lg border border-silver-deep/60 px-3 py-2 dark:border-white/10">Total Masuk: {number(currentCard.summary?.total_masuk ?? 0)}</span>
                                <span className="rounded-lg border border-silver-deep/60 px-3 py-2 dark:border-white/10">Saldo Awal: {number(currentCard.summary?.saldo_awal ?? 0)}</span>
                                <span className="rounded-lg border border-silver-deep/60 px-3 py-2 dark:border-white/10">Total Keluar: {number(currentCard.summary?.total_keluar ?? 0)}</span>
                                <span className="rounded-lg border border-silver-deep/60 px-3 py-2 dark:border-white/10">Saldo Akhir: {number(currentCard.summary?.saldo_akhir ?? 0)}</span>
                            </div>
                            <ToolButton icon={Printer} disabled={!currentMaterial}>Cetak</ToolButton>
                            <button type="button" className="ml-auto inline-flex h-9 items-center gap-2 rounded-lg border border-silver-deep/70 bg-white/80 px-4 text-xs font-extrabold text-emerald-700 dark:border-white/10 dark:bg-white/8 dark:text-emerald-300" onClick={() => router.visit('/admin/dashboard')}>
                                <RefreshCw size={15} /> Tutup
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

KartuStok.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Kartu Stok'}>{page}</AdminLayout>;
