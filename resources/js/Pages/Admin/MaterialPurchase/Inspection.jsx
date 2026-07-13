import { Head, Link, router } from '@inertiajs/react';
import { Check, Eye, Search, X } from 'lucide-react';
import { useState } from 'react';
import { Button, Dropdown, Input, Modal } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const statusLabel = {
    menunggu_approval: 'Menunggu Approval',
    pending: 'Belum Diperiksa',
    sesuai: 'Sesuai',
    tidak_sesuai: 'Tidak Sesuai',
    approved: 'Approved',
    dibeli: 'Sudah Dibeli',
    menunggu_pengecekan: 'Menunggu Pengecekan',
    menunggu_pemeriksaan_gudang: 'Menunggu Pemeriksaan',
    pengecekan_selesai: 'Pengecekan Selesai',
    diterima_logistik: 'Diterima Logistik',
    diterima_sebagian: 'Diterima Sebagian',
    ditolak_gudang: 'Ditolak Gudang',
};

function Pagination({ links = [] }) {
    if (!links.length) return null;

    return (
        <div className="flex flex-wrap gap-2 border-t border-silver-deep/60 p-4 dark:border-white/10">
            {links.map((link, index) => (
                <Link
                    className={`rounded-md px-3 py-2 text-sm font-extrabold transition ${link.active ? 'bg-ink text-white dark:bg-white dark:text-ink' : 'bg-silver-soft text-ink-soft hover:bg-silver dark:bg-white/8 dark:text-white/70 dark:hover:bg-white/12'} ${!link.url ? 'pointer-events-none opacity-45' : ''}`}
                    href={link.url ?? '#'}
                    preserveScroll
                    preserveState
                    key={`${link.label}-${index}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

export default function Inspection({ title, baseUrl, rows = { data: [], links: [] }, filters = {}, options = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [gudangId, setGudangId] = useState(filters.gudang_id ?? '');
    const [detail, setDetail] = useState(null);
    const [inspectionInputs, setInspectionInputs] = useState({});
    const [arrivalDate, setArrivalDate] = useState('');

    const applyFilter = (event) => {
        event.preventDefault();
        router.get(baseUrl, {
            search,
            date_from: dateFrom,
            date_to: dateTo,
            status,
            gudang_id: gudangId,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const openDetail = (purchase) => {
        const nextInputs = {};
        purchase.items.forEach((item) => {
            nextInputs[item.id] = {
                qty_diterima: item.inspection_status === 'pending' ? item.qty : item.qty_diterima,
                catatan: item.inspection_note ?? '',
            };
        });
        setInspectionInputs(nextInputs);
        setArrivalDate(purchase.tanggal_barang_masuk || purchase.tanggal || '');
        setDetail(purchase);
    };

    const setInspectionInput = (itemId, key, value) => {
        setInspectionInputs((current) => ({
            ...current,
            [itemId]: {
                ...(current[itemId] ?? {}),
                [key]: value,
            },
        }));
    };

    const inspect = (purchase, item, inspectionStatus, forcedQty = null) => {
        const input = inspectionInputs[item.id] ?? {};
        const qtyDiterima = forcedQty !== null ? forcedQty : (inspectionStatus === 'sesuai' ? item.qty : Number(input.qty_diterima ?? 0));
        const label = qtyDiterima > 0 && qtyDiterima < Number(item.qty) ? `terima sebagian ${qtyDiterima} ${item.satuan}` : (inspectionStatus === 'sesuai' ? 'terima sesuai' : 'tolak/tidak sesuai');

        if (!window.confirm(`Proses ${item.barang}: ${label}? Keputusan ini tidak dapat diproses dua kali.`)) return;

        router.post(`${baseUrl}/${purchase.id}/item/${item.id}`, {
            status: inspectionStatus,
            qty_diterima: qtyDiterima,
            tanggal_barang_masuk: arrivalDate,
            catatan: input.catatan ?? '',
        }, {
            preserveScroll: true,
            onSuccess: () => setDetail(null),
        });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <h2 className="text-xl font-extrabold">{title}</h2>
                    <p className="mt-1 text-sm font-medium text-ink-soft">Periksa pembelian per item. Hanya item yang dinyatakan sesuai yang masuk ke stok gudang.</p>
                    <form className="mt-5 grid gap-3 lg:grid-cols-[1.4fr_0.8fr_0.8fr_1fr_1fr_auto]" onSubmit={applyFilter}>
                        <Input label="Cari Kode Pembelian / Supplier" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Input label="Dari Tanggal" type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
                        <Input label="Sampai Tanggal" type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Gudang</span>
                            <Dropdown value={gudangId} label="Semua Gudang" options={[{ value: '', label: 'Semua Gudang' }, ...(options.gudangs ?? [])]} onChange={setGudangId} />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Status</span>
                            <Dropdown value={status} label="Semua Status" options={options.statuses ?? []} onChange={setStatus} />
                        </div>
                        <div className="flex items-end">
                            <Button className="w-full" type="submit"><Search size={17} /> Cari</Button>
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5">
                                <tr>
                                    {['Tanggal', 'Barang Masuk', 'Kode Pembelian', 'Gudang', 'Supplier', 'Item', 'Pemeriksaan', 'Penerima', 'Status', 'Aksi'].map((column) => (
                                        <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((purchase) => (
                                        <tr key={purchase.id}>
                                            <td className="px-5 py-4 font-bold">{purchase.tanggal}</td>
                                            <td className="px-5 py-4 font-bold">{purchase.tanggal_barang_masuk || '-'}</td>
                                            <td className="px-5 py-4 font-extrabold">{purchase.kode_pembelian}</td>
                                            <td className="px-5 py-4">{purchase.gudang}</td>
                                        <td className="px-5 py-4">{purchase.supplier}</td>
                                        <td className="px-5 py-4 font-bold">{purchase.items_count} item</td>
                                        <td className="px-5 py-4 text-xs font-bold">
                                            <span className="text-emerald-600">{purchase.accepted_count} sesuai</span>
                                            <span className="mx-2 text-ink-soft">/</span>
                                            <span className="text-red-500">{purchase.rejected_count} tidak</span>
                                            <span className="mx-2 text-ink-soft">/</span>
                                            <span className="text-amber-500">{purchase.pending_count} pending</span>
                                        </td>
                                        <td className="px-5 py-4">{purchase.received_by_name}</td>
                                        <td className="px-5 py-4 font-extrabold">{statusLabel[purchase.status] ?? purchase.status}</td>
                                        <td className="px-5 py-4">
                                            <Button type="button" size="sm" variant="outline" onClick={() => openDetail(purchase)}><Eye size={15} /> Detail</Button>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={10}>Belum ada pembelian yang perlu diperiksa.</td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

            <Modal
                open={Boolean(detail)}
                onClose={() => setDetail(null)}
                title={detail ? `Detail ${detail.kode_pembelian}` : 'Detail Pemeriksaan'}
                size="lg"
                footer={<Button type="button" variant="outline" onClick={() => setDetail(null)}>Tutup</Button>}
            >
                {detail && (
                    <div className="grid gap-5">
                        <div className="grid gap-3 rounded-lg border border-silver-deep/60 bg-silver-soft/60 p-4 dark:border-white/10 dark:bg-white/5 md:grid-cols-5">
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Tanggal</p><p className="mt-1 font-extrabold">{detail.tanggal}</p></div>
                            <label className="grid gap-1">
                                <span className="text-xs font-bold uppercase text-ink-soft">Tanggal Barang Masuk</span>
                                <input
                                    className="h-10 rounded-lg border border-silver-deep/70 bg-white/85 px-3 text-sm font-bold text-ink outline-none dark:border-white/10 dark:bg-white/8 dark:text-white"
                                    type="date"
                                    value={arrivalDate}
                                    onChange={(event) => setArrivalDate(event.target.value)}
                                />
                            </label>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Gudang</p><p className="mt-1 font-extrabold">{detail.gudang}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Supplier</p><p className="mt-1 font-extrabold">{detail.supplier}</p></div>
                            <div><p className="text-xs font-bold uppercase text-ink-soft">Status</p><p className="mt-1 font-extrabold">{statusLabel[detail.status] ?? detail.status}</p></div>
                        </div>
                        <div className="overflow-x-auto rounded-lg border border-silver-deep/60 dark:border-white/10">
                            <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                                <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5">
                                    <tr>
                                        {['Material', 'Dipesan', 'Diterima', 'Hasil', 'Catatan', 'Aksi'].map((column) => (
                                            <th className="px-4 py-3 font-extrabold" key={column}>{column}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {detail.items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-3 font-extrabold">{item.barang}</td>
                                            <td className="px-4 py-3 font-bold">{item.qty} {item.satuan}</td>
                                            <td className="px-4 py-3 font-bold">{item.qty_diterima} {item.satuan}</td>
                                            <td className={`px-4 py-3 font-extrabold ${item.inspection_status === 'tidak_sesuai' ? 'text-red-500' : item.inspection_status === 'sesuai' ? 'text-emerald-500' : 'text-amber-500'}`}>{statusLabel[item.inspection_status] ?? item.inspection_status}</td>
                                            <td className="px-4 py-3">{item.inspection_note || '-'}</td>
                                            <td className="px-4 py-3">
                                                {item.inspection_status === 'pending' ? (
                                                    <div className="grid min-w-72 gap-2">
                                                        <div className="grid grid-cols-[110px_1fr] gap-2">
                                                            <input
                                                                className="h-9 rounded-lg border border-silver-deep/70 bg-white/85 px-3 text-right text-xs font-bold text-ink outline-none dark:border-white/10 dark:bg-white/8 dark:text-white"
                                                                type="number"
                                                                min="0"
                                                                max={item.qty}
                                                                step="0.01"
                                                                value={inspectionInputs[item.id]?.qty_diterima ?? item.qty}
                                                                onChange={(event) => setInspectionInput(item.id, 'qty_diterima', event.target.value)}
                                                            />
                                                            <input
                                                                className="h-9 rounded-lg border border-silver-deep/70 bg-white/85 px-3 text-xs font-bold text-ink outline-none dark:border-white/10 dark:bg-white/8 dark:text-white"
                                                                placeholder="Catatan rusak/kurang"
                                                                value={inspectionInputs[item.id]?.catatan ?? ''}
                                                                onChange={(event) => setInspectionInput(item.id, 'catatan', event.target.value)}
                                                            />
                                                        </div>
                                                        <div className="flex flex-wrap gap-2">
                                                            <Button type="button" size="sm" className="bg-emerald-600 text-white hover:bg-emerald-700" onClick={() => inspect(detail, item, 'sesuai')}><Check size={16} /> Semua Sesuai</Button>
                                                            <Button type="button" size="sm" className="bg-amber-600 text-white hover:bg-amber-700" onClick={() => inspect(detail, item, 'tidak_sesuai')}><Check size={16} /> Terima Qty</Button>
                                                            <Button type="button" size="sm" className="bg-red-600 text-white hover:bg-red-700" onClick={() => inspect(detail, item, 'tidak_sesuai', 0)}><X size={16} /> Tolak</Button>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs font-bold text-ink-soft">{item.checked_at || 'Sudah dicek'}</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </Modal>
        </>
    );
}

Inspection.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Pemeriksaan Barang Masuk'}>{page}</AdminLayout>;
