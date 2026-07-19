import { Head, router } from '@inertiajs/react';
import { CalendarRange, Download, FileText, Filter, LayoutDashboard, RotateCcw, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button, Dropdown, Input } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const humanize = (value) => String(value ?? '-').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
const dateLabel = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date(`${value}T00:00:00`)) : '-';

const groupKey = (transaction, mode) => {
    const date = new Date(`${transaction.date}T00:00:00`);
    if (mode === 'day') return transaction.date;
    if (mode === 'week') {
        const monday = new Date(date);
        monday.setDate(date.getDate() - ((date.getDay() + 6) % 7));
        return `Minggu ${dateLabel(monday.toISOString().slice(0, 10))}`;
    }
    if (mode === 'month') return new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(date);
    return 'Daftar Transaksi';
};

export default function Reports({ title, baseUrl, menu = [], filters, transactions = [], summary = [], options = {} }) {
    const [query, setQuery] = useState(filters);
    const update = (key, value) => setQuery((current) => ({ ...current, [key]: value }));
    const apply = (event) => { event?.preventDefault(); router.get(baseUrl, query, { preserveState: true, preserveScroll: true }); };
    const reset = () => router.get(baseUrl, { preset: 'month' });
    const exportUrl = (format) => `${baseUrl}/export/${format}?${new URLSearchParams(Object.entries(query).filter(([, value]) => value !== '' && value !== false && value !== null)).toString()}`;
    const grouped = useMemo(() => transactions.reduce((result, transaction) => {
        const key = groupKey(transaction, query.group_by);
        (result[key] ??= []).push(transaction);
        return result;
    }, {}), [transactions, query.group_by]);

    return <><Head title={title} /><div className="grid gap-6">
        <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8"><p className="text-xs font-black uppercase tracking-[0.16em] text-ink-soft">Pusat Analitik Inventaris</p><h1 className="mt-2 font-display text-3xl font-black">Laporan Pengambilan Barang</h1><p className="mt-2 text-sm text-ink-soft">Drill-down transaksi, barang, pemakai, lokasi, proyek, unit rumah, dan progress pengembalian dalam satu laporan.</p></section>

        <nav className="flex gap-2 overflow-x-auto rounded-lg border border-white/80 bg-white/78 p-3 shadow-soft dark:border-white/10 dark:bg-white/8">{menu.map((item) => <Button key={item.key} size="sm" variant={item.key === 'reports' ? 'primary' : 'ghost'} className="shrink-0" onClick={() => router.get(`/admin/inventaris-perusahaan/${item.key}`)}>{item.key === 'dashboard' ? <LayoutDashboard size={15} /> : <FileText size={15} />}{item.label}</Button>)}</nav>

        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">{summary.map((card) => <div className="rounded-xl border border-white/80 bg-white/80 p-4 shadow-soft dark:border-white/10 dark:bg-white/8" key={card.label}><p className="text-xs font-black uppercase tracking-wider text-ink-soft">{card.label}</p><p className="mt-2 text-2xl font-black">{card.value}</p></div>)}</section>

        <form className="rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8" onSubmit={apply}>
            <div className="mb-4 flex items-center justify-between"><div><h2 className="flex items-center gap-2 text-lg font-black"><Filter size={18} /> Filter Laporan</h2><p className="mt-1 text-xs font-semibold text-ink-soft">Semua filter juga diterapkan pada PDF dan Excel.</p></div><Button type="button" size="sm" variant="outline" onClick={reset}><RotateCcw size={15} /> Atur Ulang</Button></div>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Input label="Cari transaksi/barang/pemakai" value={query.search} onChange={(event) => update('search', event.target.value)} />
                <div className="grid gap-2"><span className="text-sm font-extrabold">Periode Cepat</span><Dropdown value={query.preset} options={[{ value: 'today', label: 'Hari Ini' }, { value: 'week', label: 'Minggu Ini' }, { value: 'month', label: 'Bulan Ini' }, { value: 'year', label: 'Tahun Ini' }, { value: 'all', label: 'Semua Periode' }]} onChange={(value) => setQuery((current) => ({ ...current, preset: value, date_from: '', date_to: '' }))} /></div>
                <Input label="Dari Tanggal" type="date" value={query.date_from ?? ''} onChange={(event) => update('date_from', event.target.value)} />
                <Input label="Sampai Tanggal" type="date" value={query.date_to ?? ''} onChange={(event) => update('date_to', event.target.value)} />
                <div className="grid gap-2"><span className="text-sm font-extrabold">Jenis Transaksi</span><Dropdown value={query.transaction_type} label="Semua jenis" options={[{ value: '', label: 'Semua Jenis' }, { value: 'loan', label: 'Peminjaman' }, { value: 'placement', label: 'Penempatan Aset' }, { value: 'consumption', label: 'Pemakaian Habis' }]} onChange={(value) => update('transaction_type', value)} /></div>
                <div className="grid gap-2"><span className="text-sm font-extrabold">Status</span><Dropdown value={query.status} label="Semua status" options={[{ value: '', label: 'Semua Status' }, ...(options.statuses ?? [])]} onChange={(value) => update('status', value)} /></div>
                <div className="grid gap-2"><span className="text-sm font-extrabold">Barang</span><Dropdown value={String(query.inventory_item_id)} label="Semua barang" options={[{ value: '', label: 'Semua Barang' }, ...(options.items ?? [])]} onChange={(value) => update('inventory_item_id', value)} /></div>
                <div className="grid gap-2"><span className="text-sm font-extrabold">Lokasi Asal</span><Dropdown value={String(query.source_location_id)} label="Semua lokasi" options={[{ value: '', label: 'Semua Lokasi' }, ...(options.locations ?? [])]} onChange={(value) => update('source_location_id', value)} /></div>
                <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown value={String(query.perumahan_id)} label="Semua perumahan" options={[{ value: '', label: 'Semua Perumahan' }, ...(options.perumahans ?? [])]} onChange={(value) => update('perumahan_id', value)} /></div>
                <div className="grid gap-2"><span className="text-sm font-extrabold">Kelompokkan</span><Dropdown value={query.group_by} options={[{ value: 'transaction', label: 'Per Transaksi' }, { value: 'day', label: 'Per Hari' }, { value: 'week', label: 'Per Minggu' }, { value: 'month', label: 'Per Bulan' }]} onChange={(value) => update('group_by', value)} /></div>
                <label className="flex min-h-12 items-center gap-3 self-end rounded-lg border border-silver-deep/60 px-4 font-bold"><input type="checkbox" checked={Boolean(query.overdue)} onChange={(event) => update('overdue', event.target.checked)} /> Hanya yang terlambat</label>
                <Button className="self-end" type="submit"><Search size={16} /> Terapkan Filter</Button>
            </div>
            <div className="mt-5 flex flex-wrap justify-end gap-2"><Button type="button" variant="outline" onClick={() => window.open(exportUrl('pdf'), '_blank')}><Download size={16} /> PDF</Button><Button type="button" variant="outline" onClick={() => window.open(exportUrl('excel'), '_blank')}><Download size={16} /> Excel</Button></div>
        </form>

        {Object.entries(grouped).map(([group, groupTransactions]) => <section className="grid gap-4" key={group}>
            {query.group_by !== 'transaction' && <div className="flex items-center gap-2 border-b border-silver-deep/60 pb-3"><CalendarRange size={19} /><h2 className="text-xl font-black">{group}</h2><span className="rounded-full bg-blue-100 px-3 py-1 text-xs font-black text-blue-700">{groupTransactions.length} transaksi</span></div>}
            {groupTransactions.map((transaction) => <article className="overflow-hidden rounded-xl border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8" key={transaction.id}>
                <div className="grid gap-4 border-b border-silver-deep/60 p-5 md:grid-cols-2 xl:grid-cols-4"><div><p className="text-xs font-black uppercase text-ink-soft">Transaksi</p><button className="mt-1 text-left text-lg font-black text-blue-700 hover:underline" type="button" onClick={() => router.get(`/admin/inventaris-perusahaan/loans/records/${transaction.id}`)}>{transaction.transaction_no}</button><p className="text-sm text-ink-soft">{dateLabel(transaction.date)} · {humanize(transaction.transaction_type)}</p></div><div><p className="text-xs font-black uppercase text-ink-soft">Penanggung Jawab / Pengambil</p><p className="mt-1 font-black">{transaction.borrower}</p><p className="text-sm text-ink-soft">Diambil oleh {transaction.taken_by_name || '-'} · Diserahkan {transaction.officer_name || '-'}</p></div><div><p className="text-xs font-black uppercase text-ink-soft">Tujuan</p><p className="mt-1 font-black">{transaction.project_name || transaction.destination_location || '-'}</p><p className="text-sm text-ink-soft">{transaction.house_number ? `Unit ${transaction.house_number} · ` : ''}{transaction.destination_location || '-'}</p></div><div><p className="text-xs font-black uppercase text-ink-soft">Status</p><span className={`mt-1 inline-flex rounded-full px-3 py-1 text-xs font-black ${transaction.is_overdue ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'}`}>{transaction.is_overdue ? 'Terlambat' : humanize(transaction.status)}</span><p className="mt-2 text-xs text-ink-soft">Rencana kembali: {dateLabel(transaction.planned_return_date)}</p></div></div>
                <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="bg-silver-soft/70 text-left text-xs uppercase text-ink-soft"><tr><th className="px-5 py-3">Nama Barang</th><th className="px-5 py-3">Unit Aset</th><th className="px-5 py-3">Jumlah Keluar</th><th className="px-5 py-3">Sudah Kembali</th><th className="px-5 py-3">Sisa</th><th className="px-5 py-3">Kondisi Keluar</th></tr></thead><tbody>{transaction.items.map((line, index) => <tr className="border-t border-silver-deep/50" key={`${transaction.id}-${index}`}><td className="px-5 py-3 font-bold">{line.item_name}<span className="ml-2 text-xs font-semibold text-ink-soft">({line.item_code})</span></td><td className="px-5 py-3">{line.kode_aset ? `Unit ${line.kode_aset}` : '-'}</td><td className="px-5 py-3">{line.quantity} {line.unit}</td><td className="px-5 py-3">{line.returned_quantity}</td><td className="px-5 py-3 font-black">{Number(line.quantity) - Number(line.returned_quantity)}</td><td className="px-5 py-3">{humanize(line.condition_out)}</td></tr>)}</tbody></table></div>
                <div className="border-t border-silver-deep/60 px-5 py-3 text-sm"><strong>Keperluan:</strong> {transaction.purpose}</div>
            </article>)}
        </section>)}

        {!transactions.length && <section className="rounded-xl border border-dashed border-silver-deep p-14 text-center"><FileText className="mx-auto text-ink-soft" size={36} /><h3 className="mt-3 text-lg font-black">Tidak ada transaksi pada filter ini.</h3><p className="mt-1 text-sm text-ink-soft">Ubah periode atau filter untuk menampilkan data.</p></section>}
    </div></>;
}

Reports.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Laporan Inventaris'}>{page}</AdminLayout>;
