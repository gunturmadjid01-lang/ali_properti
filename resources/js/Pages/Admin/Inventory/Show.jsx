import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Boxes, Building2, MapPin, PackageCheck, UserRound } from 'lucide-react';
import { Button } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const humanize = (value) => {
    if (value === null || value === undefined || value === '') return '-';
    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
};

function HistoryTable({ title, columns, rows = [], emptyText }) {
    return <section className="overflow-hidden rounded-xl border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8">
        <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10"><h3 className="font-extrabold">{title}</h3></div>
        <div className="overflow-x-auto"><table className="min-w-full text-sm">
            <thead className="bg-silver-soft/70 text-left text-xs uppercase tracking-wider text-ink-soft"><tr>{columns.map((column) => <th className="whitespace-nowrap px-4 py-3" key={column.key}>{column.label}</th>)}</tr></thead>
            <tbody>{rows.map((row, index) => <tr className="border-t border-silver-deep/50 dark:border-white/10" key={row.id ?? `${title}-${index}`}>{columns.map((column) => <td className="max-w-sm px-4 py-3" key={column.key}>{column.render ? column.render(row) : humanize(row[column.key])}</td>)}</tr>)}
                {!rows.length && <tr><td className="px-5 py-10 text-center font-semibold text-ink-soft" colSpan={columns.length}>{emptyText}</td></tr>}
            </tbody>
        </table></div>
    </section>;
}

export default function InventoryShow({ title, kind, record, metrics = [], units = [], locationStocks = [], loans = [], returns = [], indexUrl }) {
    const heading = kind === 'item' ? record.name : kind === 'unit' ? record.item_name : record.transaction_no;
    const eyebrow = kind === 'item' ? record.code : kind === 'unit' ? record.kode_aset : 'Peminjaman Inventaris';
    const icons = [Boxes, PackageCheck, UserRound, MapPin];

    const unitColumns = [
        { key: 'kode_aset', label: 'Kode Unit' },
        { key: 'nomor_seri', label: 'Nomor Seri' },
        { key: 'status', label: 'Status' },
        { key: 'location_name', label: 'Lokasi Saat Ini' },
        { key: 'borrower', label: 'Pemakai Saat Ini' },
        { key: 'project_name', label: 'Perumahan' },
        { key: 'house_number', label: 'Unit Rumah', render: (row) => row.house_number ? `Unit ${row.house_number}` : '-' },
    ];
    const loanColumns = [
        { key: 'transaction_no', label: 'Nomor' },
        { key: 'date', label: 'Tanggal' },
        { key: 'item_name', label: 'Nama Barang' },
        { key: 'kode_aset', label: 'Unit Aset', render: (row) => row.kode_aset ? `Unit ${row.kode_aset}` : '-' },
        { key: 'quantity', label: 'Jumlah' },
        { key: 'borrower', label: 'Peminjam' },
        { key: 'project_name', label: 'Perumahan' },
        { key: 'house_number', label: 'Unit Rumah', render: (row) => row.house_number ? `Unit ${row.house_number}` : '-' },
        { key: 'location_name', label: 'Lokasi Pemakaian' },
        { key: 'status', label: 'Status' },
    ];
    const stockColumns = [
        { key: 'location_name', label: 'Lokasi' },
        { key: 'total_stock', label: 'Fisik' },
        { key: 'available_stock', label: 'Tersedia' },
        { key: 'borrowed_stock', label: 'Dipinjam/Ditempatkan' },
        { key: 'damaged_stock', label: 'Rusak' },
        { key: 'lost_stock', label: 'Hilang' },
    ];
    const returnColumns = [
        { key: 'return_no', label: 'Nomor Pengembalian' },
        { key: 'date', label: 'Tanggal' },
        { key: 'item_name', label: 'Nama Barang' },
        { key: 'kode_aset', label: 'Unit Aset', render: (row) => row.kode_aset ? `Unit ${row.kode_aset}` : '-' },
        { key: 'good_quantity', label: 'Baik' },
        { key: 'damaged_quantity', label: 'Rusak' },
        { key: 'lost_quantity', label: 'Hilang' },
        { key: 'outcome', label: 'Hasil' },
        { key: 'return_location', label: 'Lokasi Pengembalian' },
        { key: 'notes', label: 'Catatan' },
    ];

    return <><Head title={`${title} - ${heading}`} /><div className="grid gap-6">
        <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div>
                <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-ink-soft">{eyebrow}</p>
                <h1 className="mt-2 font-display text-3xl font-extrabold">{heading}</h1>
                <p className="mt-2 text-sm text-ink-soft">Stok, unit fisik, pemakai, perumahan, dan riwayat transaksi berada dalam satu jejak data.</p>
            </div><Button variant="outline" onClick={() => router.get(indexUrl)}><ArrowLeft size={16} /> Kembali</Button></div>
        </section>

        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{metrics.map((metric, index) => { const Icon = icons[index] ?? Building2; return <div className="rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8" key={metric.label}><Icon size={20} /><p className="mt-3 text-xs font-extrabold uppercase tracking-wider text-ink-soft">{metric.label}</p><p className="mt-2 text-xl font-extrabold">{humanize(metric.value)}</p></div>; })}</section>

        <section className="grid gap-4 rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8 md:grid-cols-2 xl:grid-cols-4">
            {(kind === 'item' ? [['Nama Barang', record.name], ['Kategori', record.category_name], ['Jenis Inventaris', record.inventory_type], ['Satuan', record.unit], ['Merk', record.brand], ['Model', record.model]] : kind === 'unit' ? [['Nama Barang', record.item_name], ['Kode Barang', record.item_code], ['Kode Unit', record.kode_aset], ['Nomor Seri', record.nomor_seri], ['Lokasi', record.location_name], ['Kondisi', record.condition]] : [['Nomor Pengambilan', record.transaction_no], ['Jenis Transaksi', record.transaction_type], ['Penanggung Jawab', record.borrower], ['Yang Mengambil', record.taken_by_name], ['Nomor HP Pengambil', record.taken_by_phone], ['Divisi', record.division], ['Perumahan', record.project_name], ['Unit Rumah', record.house_number ? `Unit ${record.house_number}` : '-'], ['Keperluan', record.purpose]]).map(([label, value]) => <div key={label}><p className="text-xs font-extrabold uppercase text-ink-soft">{label}</p><p className="mt-1 font-bold">{humanize(value)}</p></div>)}
        </section>

        {kind === 'item' && <HistoryTable title="Stok per Lokasi" columns={stockColumns} rows={locationStocks} emptyText="Belum ada saldo pada lokasi inventaris." />}
        {kind === 'item' && <HistoryTable title="Daftar Unit Fisik" columns={unitColumns} rows={units} emptyText={record.inventory_type === 'unit' ? 'Belum ada Unit Aset yang didaftarkan.' : 'Barang berdasarkan jumlah tidak memakai nomor Unit Aset.'} />}
        <HistoryTable title={kind === 'loan' ? 'Barang yang Dipinjam' : 'Riwayat Peminjaman dan Pemakaian'} columns={loanColumns} rows={loans} emptyText="Belum ada riwayat peminjaman." />
        {kind === 'loan' && <HistoryTable title="Riwayat Pengembalian" columns={returnColumns} rows={returns} emptyText="Barang belum dikembalikan." />}
    </div></>;
}

InventoryShow.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Detail Inventaris'}>{page}</AdminLayout>;
