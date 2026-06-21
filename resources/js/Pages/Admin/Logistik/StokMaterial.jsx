import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { Button, Dropdown, Input } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function StokMaterial({ title, baseUrl, rows, filters = {}, options }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [gudangId, setGudangId] = useState(filters.gudang_id ?? '');
    return (
        <>
            <Head title={title} />
            <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                <form className="grid gap-3 p-5 md:grid-cols-[1fr_260px_auto]" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search, gudang_id: gudangId }, { preserveScroll: true, preserveState: true, replace: true }); }}>
                    <Input label="Search" value={search} onChange={(event) => setSearch(event.target.value)} />
                    <div className="grid gap-2"><span className="text-sm font-extrabold">Gudang</span><Dropdown value={gudangId} options={options.gudangs} onChange={(value) => setGudangId(value)} /></div>
                    <div className="flex items-end"><Button type="submit"><Search size={17} /> Cari</Button></div>
                </form>
                <div className="overflow-x-auto"><table className="min-w-full divide-y divide-silver-deep/60 text-sm"><thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft"><tr>{['Gudang', 'Kode', 'Material', 'Qty', 'Satuan', 'Stok Minimum', 'Status'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr></thead><tbody className="divide-y divide-silver-deep/50">{rows.data.map((row) => <tr key={row.id}><td className="px-5 py-4">{row.gudang}</td><td className="px-5 py-4 font-bold">{row.kode_barang}</td><td className="px-5 py-4">{row.nama_barang}</td><td className="px-5 py-4 font-extrabold">{row.qty}</td><td className="px-5 py-4">{row.satuan}</td><td className="px-5 py-4">{row.stok_minimum}</td><td className={`px-5 py-4 font-bold ${row.status_stok === 'Minimum' ? 'text-red-600' : 'text-emerald-600'}`}>{row.status_stok}</td></tr>)}</tbody></table></div>
            </section>
        </>
    );
}

StokMaterial.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Stok Material'}>{page}</AdminLayout>;
