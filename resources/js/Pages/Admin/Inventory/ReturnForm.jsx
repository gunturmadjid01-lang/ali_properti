import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, Save } from 'lucide-react';
import { Button, Dropdown, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const prepareItems = (transaction) => (transaction?.items ?? []).map((line) => ({
    ...line,
    resolution: 'complete_good',
    good_quantity: line.outstanding_quantity,
    damaged_quantity: 0,
    lost_quantity: 0,
    condition_in: 'good',
    notes: '',
    responsible_person: '',
    estimated_cost: 0,
}));

export default function ReturnForm({ title, indexUrl, actionUrl, locations = [], transactions = [], selectedLoanId = '', today }) {
    const initialTransaction = transactions.find((transaction) => transaction.value === selectedLoanId);
    const form = useForm({
        return_no: '', inventory_loan_id: initialTransaction?.value ?? '', date: today,
        return_location_id: initialTransaction?.source_location_id ?? '', notes: '', items: prepareItems(initialTransaction),
    });
    const selectedTransaction = transactions.find((transaction) => transaction.value === String(form.data.inventory_loan_id));

    const chooseTransaction = (value, transaction) => form.setData({
        ...form.data,
        inventory_loan_id: value,
        return_location_id: transaction?.source_location_id ?? '',
        items: prepareItems(transaction),
    });
    const updateLine = (index, patch) => form.setData('items', form.data.items.map((line, lineIndex) => lineIndex === index ? { ...line, ...patch } : line));
    const chooseResolution = (index, resolution) => {
        const line = form.data.items[index];
        const total = Number(line.outstanding_quantity);
        const next = { resolution, good_quantity: 0, damaged_quantity: 0, lost_quantity: 0 };
        if (resolution === 'complete_good') Object.assign(next, { good_quantity: total, condition_in: 'good' });
        if (resolution === 'partial_good') Object.assign(next, { good_quantity: line.inventory_type === 'unit' ? total : Math.min(1, total), condition_in: 'good' });
        if (resolution === 'damaged') Object.assign(next, { damaged_quantity: total, condition_in: 'damaged' });
        if (resolution === 'lost') Object.assign(next, { lost_quantity: total, condition_in: 'lost' });
        updateLine(index, next);
    };
    const submit = (event) => {
        event.preventDefault();
        form.post(actionUrl);
    };

    return <><Head title={title} /><div className="mx-auto grid max-w-7xl gap-6">
        <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div>
                <p className="text-xs font-black uppercase tracking-[0.16em] text-ink-soft">Inventaris Perusahaan</p>
                <h1 className="mt-2 font-display text-3xl font-black">Pengembalian dan Pemeriksaan</h1>
                <p className="mt-2 text-sm text-ink-soft">Pilih transaksi, lalu pertanggungjawabkan setiap sisa barang sebagai kembali baik, rusak, hilang, atau masih belum kembali.</p>
            </div><Button as={Link} href={indexUrl} variant="outline"><ArrowLeft size={16} /> Kembali</Button></div>
        </section>

        <form className="grid gap-6" onSubmit={submit}>
            <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <div className="grid gap-2 xl:col-span-2"><span className="text-sm font-extrabold">Transaksi Pengambilan *</span><Dropdown value={String(form.data.inventory_loan_id)} options={transactions} label="Pilih transaksi yang masih aktif" onChange={chooseTransaction} />{form.errors.inventory_loan_id && <p className="text-xs font-bold text-red-600">{form.errors.inventory_loan_id}</p>}</div>
                    <Input label="Nomor Pengembalian" readOnly value={form.data.return_no} placeholder="Dibuat otomatis" />
                    <Input label="Tanggal *" type="date" value={form.data.date} error={form.errors.date} onChange={(event) => form.setData('date', event.target.value)} />
                    <div className="grid gap-2 md:col-span-2"><span className="text-sm font-extrabold">Lokasi Barang Dikembalikan</span><Dropdown value={String(form.data.return_location_id)} options={locations} label="Pilih lokasi pengembalian" onChange={(value) => form.setData('return_location_id', value)} /></div>
                    {selectedTransaction && <div className="rounded-lg border border-gold/40 bg-gold/10 p-4 md:col-span-2"><p className="text-xs font-black uppercase text-ink-soft">Penanggung Jawab / Pengambil</p><p className="mt-1 font-black">{selectedTransaction.borrower} · {selectedTransaction.taken_by_name || '-'}</p><p className="mt-1 text-xs font-semibold text-ink-soft">Status saat ini: {selectedTransaction.status.replaceAll('_', ' ')}</p></div>}
                </div>
            </section>

            {selectedTransaction && <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                <div className="mb-5"><h2 className="flex items-center gap-2 text-xl font-black"><ClipboardCheck size={20} /> Pemeriksaan Barang</h2><p className="mt-1 text-sm text-ink-soft">Barang yang tidak diisi akan tetap tercatat sebagai belum kembali dan transaksi menjadi pengembalian sebagian.</p></div>
                <div className="grid gap-4">{form.data.items.map((line, index) => {
                    const isUnit = line.inventory_type === 'unit';
                    const processed = Number(line.good_quantity || 0) + Number(line.damaged_quantity || 0) + Number(line.lost_quantity || 0);
                    const over = processed > Number(line.outstanding_quantity);
                    return <div className="rounded-xl border border-silver-deep/60 bg-silver-soft/25 p-5" key={line.loan_item_id}>
                        <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><h3 className="font-black">{line.item_name}</h3><p className="mt-1 text-sm text-ink-soft">{isUnit ? `Unit Aset ${line.kode_aset}` : `${line.item_code} · ${line.unit}`} · Diambil {line.quantity} · Sudah kembali {line.returned_quantity} · <strong>Sisa {line.outstanding_quantity}</strong></p></div><span className={`rounded-full px-3 py-1 text-xs font-black ${over ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`}>Diproses {processed} / {line.outstanding_quantity}</span></div>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Hasil Pemeriksaan *</span><Dropdown value={line.resolution} options={[{ value: 'complete_good', label: 'Lengkap dan baik' }, { value: 'partial_good', label: 'Kembali sebagian' }, { value: 'damaged', label: 'Barang rusak' }, { value: 'lost', label: 'Barang hilang' }, { value: 'mixed', label: 'Kondisi campuran' }, { value: 'pending', label: 'Belum dikembalikan' }]} onChange={(value) => chooseResolution(index, value)} /></div>
                            <Input label="Kembali Baik" type="number" min="0" max={line.outstanding_quantity} readOnly={isUnit && line.resolution !== 'mixed'} value={line.good_quantity} onChange={(event) => updateLine(index, { good_quantity: event.target.value })} />
                            <Input label="Rusak" type="number" min="0" max={line.outstanding_quantity} readOnly={isUnit && line.resolution !== 'mixed'} value={line.damaged_quantity} onChange={(event) => updateLine(index, { damaged_quantity: event.target.value })} />
                            <Input label="Hilang" type="number" min="0" max={line.outstanding_quantity} readOnly={isUnit && line.resolution !== 'mixed'} value={line.lost_quantity} onChange={(event) => updateLine(index, { lost_quantity: event.target.value })} />
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Kondisi Saat Masuk</span><Dropdown value={line.condition_in} options={[{value:'good',label:'Baik'},{value:'fair',label:'Cukup Baik'},{value:'needs_service',label:'Perlu Perawatan'},{value:'damaged',label:'Rusak'},{value:'lost',label:'Hilang'}]} onChange={(value)=>updateLine(index,{condition_in:value})} /></div>
                            <Input label="Pihak Bertanggung Jawab" value={line.responsible_person} onChange={(event) => updateLine(index, { responsible_person: event.target.value })} />
                            <Input label="Estimasi Biaya" type="number" min="0" value={line.estimated_cost} onChange={(event) => updateLine(index, { estimated_cost: event.target.value })} />
                            <Input label="Catatan Pemeriksaan" value={line.notes} onChange={(event) => updateLine(index, { notes: event.target.value })} />
                        </div>
                        {over && <p className="mt-3 text-sm font-bold text-red-600">Total baik, rusak, dan hilang tidak boleh melebihi sisa barang.</p>}
                        {form.errors[`items.${index}.loan_item_id`] && <p className="mt-3 text-sm font-bold text-red-600">{form.errors[`items.${index}.loan_item_id`]}</p>}
                    </div>;
                })}</div>
            </section>}

            {!selectedTransaction && <section className="rounded-xl border border-dashed border-silver-deep p-12 text-center"><ClipboardCheck className="mx-auto text-ink-soft" size={32} /><p className="mt-3 font-black">Pilih transaksi untuk menampilkan sisa barang.</p></section>}

            <section className="grid gap-4 rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"><Textarea label="Catatan Pengembalian" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /><div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><Button as={Link} href={indexUrl} variant="outline">Batal</Button><Button type="submit" disabled={form.processing || !selectedTransaction}><Save size={16} /> {form.processing ? 'Memposting...' : 'Posting Pengembalian'}</Button></div></section>
        </form>
    </div></>;
}

ReturnForm.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Pengembalian Barang'}>{page}</AdminLayout>;
