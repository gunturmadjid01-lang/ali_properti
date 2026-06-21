import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, Eye, HandCoins, PackageCheck, Search, ShoppingCart } from 'lucide-react';
import { useState } from 'react';
import { Button, Dropdown, Input, Modal } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function FinanceIndex({ title, baseUrl, purchaseUrl, purchaseActionUrl, rows = { data: [] }, filters = {}, bankOptions = [] }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [detail, setDetail] = useState(null);
    const [releaseTarget, setReleaseTarget] = useState(null);
    const [paymentBankId, setPaymentBankId] = useState('');
    const postAction = (url, message) => {
        if (!window.confirm(message)) return;
        router.post(url, {}, { preserveScroll: true, onSuccess: () => setDetail(null) });
    };

    const workflowAction = (row) => {
        if (row.can_approve) {
            return <Button type="button" size="sm" onClick={() => postAction(`${purchaseActionUrl}/${row.purchase_id}/approve`, `Approve pembelian ${row.purchase_code}?`)}><CheckCircle2 size={15} /> Approve</Button>;
        }
        if (row.can_release) {
            return <Button type="button" size="sm" onClick={() => {
                setDetail(null);
                setReleaseTarget(row);
                setPaymentBankId(row.planned_master_bank_id || '');
            }}><HandCoins size={15} /> Cairkan Dana</Button>;
        }
        if (row.can_mark_purchased) {
            return <Button type="button" size="sm" onClick={() => postAction(`${purchaseActionUrl}/${row.purchase_id}/mark-purchased`, `Tandai pembelian ${row.purchase_code} sudah dibeli dan kirim ke pemeriksaan gudang?`)}><PackageCheck size={15} /> Sudah Dibeli</Button>;
        }
        if (row.can_process) {
            return <Button as={Link} href={`${purchaseUrl}?request_id=${row.id}`} size="sm"><ShoppingCart size={15} /> Proses Pembelian</Button>;
        }
        return <span className="self-center text-xs font-bold text-ink-soft">{row.purchase_status?.replaceAll('_', ' ') ?? 'Sudah diproses'}</span>;
    };

    return (
        <>
            <Head title={title} />
            <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                <div className="border-b border-silver-deep/60 p-5">
                    <h2 className="text-xl font-extrabold">{title}</h2>
                    <p className="mt-1 text-sm font-medium text-ink-soft">Daftar permintaan restock dari gudang. Setelah disetujui, permintaan ini diproses menjadi transaksi pembelian barang.</p>
                    <form className="mt-5 flex flex-col gap-3 md:flex-row md:items-end" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true }); }}>
                        <Input className="flex-1" label="Cari Kode / Status" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                </div>
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-silver-deep/60 text-sm">
                        <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft">
                            <tr>{['Kode', 'Tanggal', 'Gudang', 'Material', 'Pemohon', 'Status', 'Audit Pembelian', 'Keterangan', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr>
                        </thead>
                        <tbody className="divide-y divide-silver-deep/50">
                            {rows.data.map((row) => (
                                <tr key={row.id}>
                                    <td className="px-5 py-4 font-bold">{row.kode_request}</td>
                                    <td className="px-5 py-4">{row.tanggal}</td>
                                    <td className="px-5 py-4">{row.gudang}</td>
                                    <td className="px-5 py-4 font-bold">{row.items_count} item</td>
                                    <td className="px-5 py-4">{row.pemohon}</td>
                                    <td className="px-5 py-4 font-bold">{(row.purchase_status ?? row.status)?.replaceAll('_', ' ')}</td>
                                    <td className="min-w-48 px-5 py-4 text-xs"><span className="font-bold">Dibuat:</span> {row.purchase_created_by_name}<br /><span className="font-bold">Diubah:</span> {row.purchase_updated_by_name}<br /><span className="font-bold">Approve:</span> {row.purchase_approved_by_name}<br /><span className="font-bold">Cair:</span> {row.purchase_released_by_name}</td>
                                    <td className="px-5 py-4">{row.keterangan || '-'}</td>
                                    <td className="px-5 py-4">
                                        <div className="flex flex-wrap gap-2">
                                            <Button type="button" variant="outline" size="sm" onClick={() => setDetail(row)}><Eye size={15} /> Detail</Button>
                                            {workflowAction(row)}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <Modal
                open={Boolean(detail)}
                onClose={() => setDetail(null)}
                title={detail ? `Detail ${detail.kode_request}` : 'Detail Permintaan'}
                size="lg"
                footer={(
                    <>
                        <Button type="button" variant="outline" onClick={() => setDetail(null)}>Tutup</Button>
                        {detail && workflowAction(detail)}
                    </>
                )}
            >
                {detail && (
                    <div className="grid gap-5">
                        <div className="grid gap-3 rounded-lg border border-silver-deep/60 bg-silver-soft/60 p-4 dark:border-white/10 dark:bg-white/5 sm:grid-cols-2 lg:grid-cols-4">
                            <div><p className="text-xs font-bold uppercase tracking-wide text-ink-soft">Tanggal</p><p className="mt-1 font-extrabold">{detail.tanggal}</p></div>
                            <div><p className="text-xs font-bold uppercase tracking-wide text-ink-soft">Gudang</p><p className="mt-1 font-extrabold">{detail.gudang}</p></div>
                            <div><p className="text-xs font-bold uppercase tracking-wide text-ink-soft">Pemohon</p><p className="mt-1 font-extrabold">{detail.pemohon}</p></div>
                            <div><p className="text-xs font-bold uppercase tracking-wide text-ink-soft">Status Permintaan</p><p className="mt-1 font-extrabold">{detail.status?.replaceAll('_', ' ')}</p></div>
                        </div>
                        {detail.purchase_code && (
                            <div className="grid gap-3 rounded-lg border border-gold/30 bg-gold/10 p-4 sm:grid-cols-2">
                                <div><p className="text-xs font-bold uppercase tracking-wide text-ink-soft">Kode Pembelian</p><p className="mt-1 font-extrabold">{detail.purchase_code}</p></div>
                            <div><p className="text-xs font-bold uppercase tracking-wide text-ink-soft">Status Pembelian</p><p className="mt-1 font-extrabold">{detail.purchase_status?.replaceAll('_', ' ')}</p></div>
                            <div><p className="text-xs font-bold uppercase tracking-wide text-ink-soft">Rekening Rencana</p><p className="mt-1 font-extrabold">{detail.planned_bank}</p></div>
                            <div><p className="text-xs font-bold uppercase tracking-wide text-ink-soft">Rekening Pembayaran</p><p className="mt-1 font-extrabold">{detail.payment_bank}</p></div>
                            </div>
                        )}

                        <div className="overflow-hidden rounded-lg border border-silver-deep/60 dark:border-white/10">
                            <div className="border-b border-silver-deep/60 px-4 py-3 dark:border-white/10">
                                <h3 className="font-extrabold">Daftar Barang yang Diminta</h3>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-silver-deep/60 text-sm">
                                    <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5">
                                        <tr>
                                            <th className="px-4 py-3 font-extrabold">No</th>
                                            <th className="px-4 py-3 font-extrabold">Nama Material</th>
                                            <th className="px-4 py-3 text-right font-extrabold">Qty</th>
                                            <th className="px-4 py-3 font-extrabold">Satuan</th>
                                            <th className="px-4 py-3 font-extrabold">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                        {detail.items.map((item, index) => (
                                            <tr key={item.id}>
                                                <td className="px-4 py-3 font-bold">{index + 1}</td>
                                                <td className="px-4 py-3 font-extrabold">{item.barang}</td>
                                                <td className="px-4 py-3 text-right font-bold">{item.qty}</td>
                                                <td className="px-4 py-3">{item.satuan}</td>
                                                <td className="px-4 py-3">{item.catatan || '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div>
                            <p className="text-xs font-bold uppercase tracking-wide text-ink-soft">Keterangan Permintaan</p>
                            <p className="mt-2 rounded-lg border border-silver-deep/60 p-4 font-medium dark:border-white/10">{detail.keterangan || '-'}</p>
                        </div>
                    </div>
                )}
            </Modal>

            <Modal
                open={Boolean(releaseTarget)}
                onClose={() => setReleaseTarget(null)}
                title={releaseTarget ? `Cairkan Dana ${releaseTarget.purchase_code}` : 'Cairkan Dana'}
                footer={(
                    <>
                        <Button type="button" variant="outline" onClick={() => setReleaseTarget(null)}>Batal</Button>
                        <Button
                            type="button"
                            disabled={!paymentBankId}
                            onClick={() => router.post(`${purchaseActionUrl}/${releaseTarget.purchase_id}/release-fund`, {
                                payment_master_bank_id: paymentBankId,
                            }, {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setReleaseTarget(null);
                                    setPaymentBankId('');
                                },
                            })}
                        >
                            <HandCoins size={16} /> Cairkan Dana
                        </Button>
                    </>
                )}
            >
                <div className="grid gap-4">
                    <p className="text-sm font-medium text-ink-soft">Rekening rencana dapat dikonfirmasi atau diganti sebelum pencairan.</p>
                    <div className="grid gap-2">
                        <span className="text-sm font-extrabold">Rekening Sumber Dana</span>
                        <Dropdown value={paymentBankId} label="Pilih Rekening" options={bankOptions} onChange={setPaymentBankId} />
                    </div>
                </div>
            </Modal>
        </>
    );
}

FinanceIndex.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Approval Permintaan Pembelian Gudang'}>{page}</AdminLayout>;
