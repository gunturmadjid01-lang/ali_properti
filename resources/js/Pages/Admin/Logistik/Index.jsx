import { Head, router } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine, Search, WalletCards } from 'lucide-react';
import { useState } from 'react';
import { Button, Input } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';
import { SectionCard, StatGrid, WarehousePage } from './components/WarehouseShell';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

export default function Index({ title, baseUrl, rows = { data: [], links: [] }, filters = {}, summary = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');

    return (
        <>
            <Head title={title} />
            <WarehousePage
                eyebrow="Mutasi Gudang"
                title="Riwayat Pergerakan Material"
                description="Jejak otomatis dari approval permintaan material, penerimaan pembelian, dan pengembalian material dari lokasi."
            >
                <StatGrid
                    items={[
                        { icon: ArrowDownToLine, label: 'Nilai Barang Masuk', value: money(summary.total_masuk) },
                        { icon: ArrowUpFromLine, label: 'Nilai Barang Keluar', value: money(summary.total_keluar) },
                        { icon: WalletCards, label: 'Realisasi Kawasan', value: money(summary.total_realisasi_perumahan) },
                        { icon: WalletCards, label: 'Realisasi Unit', value: money(summary.total_realisasi_rumah) },
                    ]}
                />
                <SectionCard
                    title="Daftar mutasi"
                    description="Pilih transaksi untuk menelusuri sumber pergerakan material."
                    actions={(
                        <form className="flex flex-col gap-3 md:flex-row md:items-end" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search }, { preserveState: true, replace: true }); }}>
                            <Input className="md:w-[420px]" label="Cari kode, jenis, atau keterangan mutasi" value={search} onChange={(event) => setSearch(event.target.value)} />
                            <Button type="submit"><Search size={16} /> Cari</Button>
                        </form>
                    )}
                >
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-xs">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft"><tr>{['Kode', 'Tanggal', 'Mutasi', 'Gudang', 'Tujuan / Asal', 'Material', 'Sumber', 'Nilai'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr></thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">{row.kode_transaksi}</td>
                                        <td className="px-5 py-4">{row.tanggal}</td>
                                        <td className="px-5 py-4"><span className={`rounded-full px-3 py-1 text-xs font-extrabold ${row.jenis === 'masuk' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>{row.jenis === 'masuk' ? 'Masuk Gudang' : 'Keluar Gudang'}</span></td>
                                        <td className="px-5 py-4">{row.gudang}</td>
                                        <td className="px-5 py-4">{row.perumahan ?? '-'}<br /><span className="text-xs text-ink-soft">{row.detail_rumah} / {row.tahapan}</span></td>
                                        <td className="max-w-sm px-5 py-4">{row.items_text || '-'}</td>
                                        <td className="px-5 py-4">{row.sumber}<br /><span className="text-xs text-ink-soft">{row.keterangan || '-'}</span></td>
                                        <td className="px-5 py-4 font-extrabold">{money(row.total_nominal)}</td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={8}>Belum ada mutasi gudang otomatis.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </SectionCard>
            </WarehousePage>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Riwayat Mutasi Gudang'}>{page}</AdminLayout>;
