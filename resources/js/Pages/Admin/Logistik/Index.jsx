import { Head, router } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine, Search, WalletCards } from 'lucide-react';
import { useState } from 'react';
import { Button, Input } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

function Metric({ icon: Icon, label, value }) {
    return <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"><div className="flex items-center gap-3"><div className="grid h-10 w-10 place-items-center rounded-lg bg-silver-soft dark:bg-white/10"><Icon size={18} /></div><div><p className="text-xs font-bold uppercase tracking-wider text-ink-soft">{label}</p><p className="text-xl font-extrabold">{money(value)}</p></div></div></div>;
}

export default function Index({ title, baseUrl, rows = { data: [], links: [] }, filters = {}, summary = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section>
                    <h2 className="font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl text-ink-soft">Halaman ini hanya menampilkan jejak mutasi otomatis dari approval permintaan material, penerimaan pembelian, dan pengembalian material dari lokasi.</p>
                </section>
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <Metric icon={ArrowDownToLine} label="Nilai Barang Masuk" value={summary.total_masuk} />
                    <Metric icon={ArrowUpFromLine} label="Nilai Barang Keluar" value={summary.total_keluar} />
                    <Metric icon={WalletCards} label="Realisasi Kawasan" value={summary.total_realisasi_perumahan} />
                    <Metric icon={WalletCards} label="Realisasi Unit" value={summary.total_realisasi_rumah} />
                </div>
                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 sm:flex-row sm:items-end" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search }, { preserveState: true, replace: true }); }}>
                        <Input className="flex-1" label="Cari kode, jenis, atau keterangan mutasi" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={16} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
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
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Riwayat Mutasi Gudang'}>{page}</AdminLayout>;
