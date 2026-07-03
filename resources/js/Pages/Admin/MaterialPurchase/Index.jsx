import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, HandCoins, Lock, MinusCircle, PlusCircle, Save, Search, ShoppingCart, Unlock } from 'lucide-react';
import { useState } from 'react';
import { Button, Dropdown, Form, Input, Modal, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

function itemTemplate() {
    return { barang_material_id: '', qty: '', satuan: '', harga_satuan: '' };
}

export default function Index({ title, baseUrl, rows, filters = {}, options, selectedRequest = null, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [releaseRow, setReleaseRow] = useState(null);
    const [paymentBankId, setPaymentBankId] = useState('');
    const canCreate = permissions.canCreate ?? false;
    const canApprove = permissions.canApprove ?? false;
    const canRelease = permissions.canRelease ?? false;
    const canMarkPurchased = permissions.canMarkPurchased ?? false;
    const canReceive = permissions.canReceive ?? false;
    const canLock = permissions.canLock ?? false;
    const canUnlock = permissions.canUnlock ?? false;
    const form = useForm({
        tanggal: new Date().toISOString().slice(0, 10),
        material_purchase_request_id: selectedRequest ? String(selectedRequest.id) : '',
        supplier: '',
        metode_pembayaran: 'tunai',
        planned_master_bank_id: '',
        gudang_id: selectedRequest?.gudang_id ?? '',
        keterangan: selectedRequest
            ? `Pembelian dari permintaan gudang ${selectedRequest.kode_request}${selectedRequest.keterangan ? ` - ${selectedRequest.keterangan}` : ''}`
            : '',
        items: selectedRequest?.items?.length ? selectedRequest.items : [itemTemplate()],
    });

    const setItem = (index, key, value) => {
        form.setData('items', form.data.items.map((item, itemIndex) => {
            if (itemIndex !== index) return item;
            const next = { ...item, [key]: value };
            if (key === 'barang_material_id') {
                const barang = options.barangMaterials.find((option) => option.value === String(value));
                next.satuan = barang?.satuan ?? '';
                next.harga_satuan = barang?.harga_hpp ?? '';
            }
            return next;
        }));
    };

    const total = form.data.items.reduce((sum, item) => sum + Number(item.qty || 0) * Number(item.harga_satuan || 0), 0);

    const submit = (event) => {
        event.preventDefault();
        form.post(baseUrl, {
            preserveScroll: true,
            onSuccess: () => form.setData({
                tanggal: new Date().toISOString().slice(0, 10),
                material_purchase_request_id: '',
                supplier: '',
                metode_pembayaran: 'tunai',
                planned_master_bank_id: '',
                gudang_id: '',
                keterangan: '',
                items: [itemTemplate()],
            }),
        });
    };

    const post = (url, data = {}) => router.post(url, data, { preserveScroll: true });

    const openRelease = (row) => {
        setReleaseRow(row);
        setPaymentBankId(row.planned_master_bank_id || '');
    };

    const releaseFund = () => {
        if (!paymentBankId) return;
        router.post(`${baseUrl}/${releaseRow.id}/release-fund`, {
            payment_master_bank_id: paymentBankId,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setReleaseRow(null);
                setPaymentBankId('');
            },
        });
    };

    const lockRow = (row) => {
        if (!window.confirm(`Lock pembelian ${row.kode_pembelian}?`)) {
            return;
        }

        post(`${baseUrl}/${row.id}/lock`);
    };

    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock pembelian ${row.kode_pembelian}?`)) {
            return;
        }

        post(`${baseUrl}/${row.id}/unlock`);
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                {canCreate ? (
                    <Form collapsible title={title} description="Pembelian hanya menambah persediaan gudang. HPP proyek baru bertambah saat material keluar melalui permintaan yang telah disetujui." onSubmit={submit} actions={<Button type="submit" disabled={form.processing}><Save size={17} /> Buat Pembelian</Button>}>
                    {form.data.material_purchase_request_id && (
                        <div className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-bold text-emerald-700 dark:text-emerald-300">
                            Form diisi dari permintaan pembelian gudang. Qty dan harga masih dapat disesuaikan sebelum disimpan.
                        </div>
                    )}
                    <div className="grid gap-4 md:grid-cols-5">
                        <Input label="Tanggal" type="date" value={form.data.tanggal} error={form.errors.tanggal} onChange={(event) => form.setData('tanggal', event.target.value)} />
                        <Input label="Supplier" value={form.data.supplier} error={form.errors.supplier} onChange={(event) => form.setData('supplier', event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Metode Bayar</span><Dropdown value={form.data.metode_pembayaran} label="Pilih Metode" options={options.metodePembayaran} onChange={(value) => form.setData({ ...form.data, metode_pembayaran: value, planned_master_bank_id: value === 'hutang' ? '' : form.data.planned_master_bank_id })} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Gudang Tujuan</span><Dropdown value={form.data.gudang_id} label="Pilih Gudang" options={options.gudangs} onChange={(value) => form.setData('gudang_id', value)} /></div>
                        {form.data.metode_pembayaran === 'tunai' && <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Rekening Rencana</span>
                            <Dropdown value={form.data.planned_master_bank_id} label="Pilih Rekening" options={options.masterBanks ?? []} onChange={(value) => form.setData('planned_master_bank_id', value)} />
                            {form.errors.planned_master_bank_id && <p className="text-xs font-bold text-red-500">{form.errors.planned_master_bank_id}</p>}
                        </div>}
                    </div>
                    {form.errors.items && <p className="text-xs font-bold text-red-500">{form.errors.items}</p>}
                    <div className="grid gap-3">
                        <div className="flex justify-between"><h3 className="text-sm font-extrabold">Item Pembelian</h3><Button type="button" variant="outline" size="sm" onClick={() => form.setData('items', [...form.data.items, itemTemplate()])}><PlusCircle size={15} /> Tambah</Button></div>
                        {form.data.items.map((item, index) => (
                            <div className="grid gap-3 rounded-lg border border-silver-deep/70 p-3 md:grid-cols-[1.4fr_0.5fr_0.6fr_0.8fr_auto]" key={index}>
                                <div className="grid gap-2"><span className="text-xs font-extrabold text-ink-soft">Barang</span><Dropdown value={item.barang_material_id} label="Pilih Barang" options={options.barangMaterials} onChange={(value) => setItem(index, 'barang_material_id', value)} /></div>
                                <Input label="Qty" type="number" value={item.qty} onChange={(event) => setItem(index, 'qty', event.target.value)} />
                                <Input label="Satuan" value={item.satuan} onChange={(event) => setItem(index, 'satuan', event.target.value)} />
                                <Input label="Harga" type="number" value={item.harga_satuan} onChange={(event) => setItem(index, 'harga_satuan', event.target.value)} />
                                <div className="flex items-end"><Button type="button" variant="ghost" size="sm" className="text-red-600" disabled={form.data.items.length === 1} onClick={() => form.setData('items', form.data.items.filter((_, itemIndex) => itemIndex !== index))}><MinusCircle size={16} /></Button></div>
                            </div>
                        ))}
                        <div className="text-right text-lg font-extrabold">Total: {money(total)}</div>
                    </div>
                        <Textarea label="Keterangan" value={form.data.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} />
                    </Form>
                ) : (
                    <section className="rounded-lg border border-dashed border-silver-deep/70 bg-silver-soft/40 p-6 text-sm text-ink-soft dark:border-white/10 dark:bg-white/5">
                        Form pembelian disembunyikan karena role aktif tidak memiliki izin create pembelian barang.
                    </section>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true }); }}>
                        <Input className="md:max-w-md" label="Search" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft">
                                <tr>{['Kode', 'Permintaan', 'Tanggal', 'Gudang', 'Supplier', 'Rekening', 'Metode', 'Total', 'Audit', 'Lock', 'Status', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">{row.kode_pembelian}</td>
                                        <td className="px-5 py-4">{row.request}</td>
                                        <td className="px-5 py-4">{row.tanggal}</td>
                                        <td className="px-5 py-4">{row.gudang}</td>
                                        <td className="px-5 py-4">{row.supplier}</td>
                                        <td className="min-w-48 px-5 py-4">{row.payment_bank !== '-' ? row.payment_bank : row.planned_bank}</td>
                                        <td className="px-5 py-4 font-bold">{row.metode_pembayaran === 'hutang' ? 'Hutang Supplier' : 'Tunai'}</td>
                                        <td className="px-5 py-4 font-extrabold">{money(row.total_nominal)}</td>
                                        <td className="min-w-48 px-5 py-4 text-xs"><span className="font-bold">Dibuat:</span> {row.created_by_name}<br /><span className="font-bold">Diubah:</span> {row.updated_by_name}<br /><span className="font-bold">Approve:</span> {row.approved_by_name}<br /><span className="font-bold">Cair:</span> {row.fund_released_by_name}</td>
                                        <td className="px-5 py-4 font-bold">{row.record_status_label}</td>
                                        <td className="px-5 py-4 font-bold">{row.status}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                {row.record_status === 'locked' ? (
                                                    canUnlock && <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}><Unlock size={15} /> Unlock</Button>
                                                ) : (
                                                    <>
                                                        {canLock && <Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}><Lock size={15} /> Lock</Button>}
                                                        {canApprove && row.status === 'menunggu_approval_manager' && <Button type="button" size="sm" variant="outline" onClick={() => post(`${baseUrl}/${row.id}/approve`)}><CheckCircle2 size={15} /> Approve</Button>}
                                                        {canRelease && row.status === 'menunggu_pencairan_dana' && <Button type="button" size="sm" variant="outline" onClick={() => openRelease(row)}><HandCoins size={15} /> Cairkan</Button>}
                                                        {canMarkPurchased && row.status === 'dana_cair' && <Button type="button" size="sm" variant="outline" onClick={() => post(`${baseUrl}/${row.id}/mark-purchased`)}><ShoppingCart size={15} /> Sudah Dibeli</Button>}
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Modal
                open={Boolean(releaseRow) && canRelease}
                onClose={() => setReleaseRow(null)}
                title={releaseRow ? `Cairkan Dana ${releaseRow.kode_pembelian}` : 'Cairkan Dana'}
                footer={(
                    <>
                        <Button type="button" variant="outline" onClick={() => setReleaseRow(null)}>Batal</Button>
                        <Button type="button" disabled={!paymentBankId} onClick={releaseFund}><HandCoins size={16} /> Cairkan Dana</Button>
                    </>
                )}
            >
                <div className="grid gap-4">
                    <p className="text-sm font-medium text-ink-soft">Pilih rekening yang benar-benar digunakan. Rekening ini akan tersimpan pada jurnal dan cashflow pembelian.</p>
                    <div className="grid gap-2">
                        <span className="text-sm font-extrabold">Rekening Sumber Dana</span>
                        <Dropdown value={paymentBankId} label="Pilih Rekening" options={options.masterBanks ?? []} onChange={setPaymentBankId} />
                    </div>
                    {releaseRow && <div className="rounded-lg bg-silver-soft p-4 text-sm font-bold dark:bg-white/5">Total dicairkan: {money(releaseRow.total_nominal)}</div>}
                </div>
            </Modal>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Pembelian Barang'}>{page}</AdminLayout>;
