import { Head, router } from '@inertiajs/react';
import { Boxes, PackageCheck, RotateCcw, Search, Warehouse } from 'lucide-react';
import { useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, Dropdown, Input } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function Metric({ icon: Icon, label, value }) {
    return (
        <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex items-center gap-3">
                <div className="grid h-10 w-10 place-items-center rounded-lg bg-silver-soft dark:bg-white/10"><Icon size={18} /></div>
                <div><p className="text-xs font-bold uppercase tracking-wider text-ink-soft">{label}</p><p className="text-xl font-extrabold">{value}</p></div>
            </div>
        </div>
    );
}

export default function Index({ title, baseUrl, rows = { data: [], links: [] }, filters = {}, summary = {}, options = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [filterPerumahan, setFilterPerumahan] = useState(filters.perumahan_id ?? '');
    const [filterUnit, setFilterUnit] = useState(filters.detail_rumah_id ?? '');
    const detailRumahs = options.detailRumahs ?? [];

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section>
                    <h2 className="font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 text-ink-soft">Material yang telah keluar dari gudang dan masih berada pada unit atau kawasan proyek.</p>
                </section>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric icon={Boxes} label="Posisi Material" value={summary.jenis_material ?? 0} />
                    <Metric icon={PackageCheck} label="Total Diterima" value={summary.total_diterima ?? 0} />
                    <Metric icon={Warehouse} label="Total Dipakai" value={summary.total_dipakai ?? 0} />
                    <Metric icon={RotateCcw} label="Total Sisa" value={summary.total_sisa ?? 0} />
                </div>
                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="grid gap-3 p-5 lg:grid-cols-[1.2fr_1fr_1fr_auto]" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search, perumahan_id: filterPerumahan, detail_rumah_id: filterUnit }, { preserveState: true, replace: true }); }}>
                        <Input label="Cari material, perumahan, blok, atau unit" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown value={filterPerumahan} label="Semua Perumahan" options={[{ value: '', label: 'Semua Perumahan' }, ...(options.perumahans ?? [])]} onChange={(value) => { setFilterPerumahan(value); setFilterUnit(''); }} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Unit</span><Dropdown value={filterUnit} label="Semua Unit" options={[{ value: '', label: 'Semua Unit' }, ...detailRumahs.filter((row) => !filterPerumahan || row.perumahan_id === String(filterPerumahan))]} onChange={setFilterUnit} /></div>
                        <div className="flex items-end"><Button className="w-full" type="submit"><Search size={16} /> Cari</Button></div>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-xs">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                <tr>{['Lokasi', 'Tahapan', 'Material / HPP', 'Diterima', 'Dipakai', 'Menunggu Kembali', 'Dikembalikan', 'Sisa'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4">{row.perumahan}<br /><span className="text-xs text-ink-soft">{row.unit} / {row.gudang}</span></td>
                                        <td className="px-5 py-4">{row.tahapan}</td>
                                        <td className="px-5 py-4 font-bold">{row.material}<br /><span className="text-xs font-normal text-ink-soft">{row.kelompok_hpp}</span></td>
                                        <td className="px-5 py-4">{row.diterima} {row.satuan}</td>
                                        <td className="px-5 py-4">{row.dipakai} {row.satuan}</td>
                                        <td className="px-5 py-4">{row.menunggu_pengembalian} {row.satuan}</td>
                                        <td className="px-5 py-4">{row.dikembalikan} {row.satuan}</td>
                                        <td className="px-5 py-4 font-extrabold">{row.sisa} {row.satuan}</td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={8}>Belum ada material di lokasi proyek.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Sisa Material Lokasi'}>{page}</AdminLayout>;
