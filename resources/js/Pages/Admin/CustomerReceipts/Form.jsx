import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button, Input } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const money = value => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
const purposes = {
    booking_fee: 'Booking Fee', down_payment: 'Uang Muka / DP', invoice_payment: 'Bayar Tagihan',
    accelerated_payment: 'Percepatan Tagihan', overpayment: 'Pembayaran Lebih', other: 'Penerimaan Lainnya',
};

export default function Form({ title, transactions, banks, defaults = {}, storeUrl }) {
    const form = useForm({
        sales_transaction_id: defaults.transaction || '', master_bank_id: '',
        payment_date: new Date().toISOString().slice(0, 10), amount: '', payment_method: 'transfer',
        receipt_purpose: defaults.purpose || 'invoice_payment', bank_reference: '', sender_bank: '',
        sender_name: '', proof: null, notes: '', allocations: [],
    });
    const transaction = transactions.find(item => item.value === String(form.data.sales_transaction_id));
    const allocated = form.data.allocations.reduce((sum, item) => sum + Number(item.amount || 0), 0);
    const add = () => form.setData('allocations', [...form.data.allocations, { payment_schedule_id: '', amount: '' }]);
    const update = (index, key, value) => form.setData('allocations', form.data.allocations.map((item, i) => i === index ? { ...item, [key]: value } : item));

    return <><Head title={title}/><form onSubmit={event => { event.preventDefault(); form.post(storeUrl, { forceFormData: true }); }} className="grid gap-6">
        <header className="rounded-xl border bg-white/80 p-6 dark:bg-white/8"><h1 className="text-3xl font-black">{title}</h1><p className="mt-2 text-ink-soft">Satu pintu untuk Booking Fee, DP, pembayaran tagihan, percepatan, dan pembayaran lebih. Piutang dan jurnal baru berubah setelah persetujuan akhir.</p></header>
        <section className="grid gap-4 rounded-xl border bg-white/80 p-6 md:grid-cols-2 dark:bg-white/8">
            <label>Transaksi<select className="mt-1 w-full rounded-lg border p-3" value={form.data.sales_transaction_id} onChange={e => form.setData({ ...form.data, sales_transaction_id: e.target.value, allocations: [] })}><option value="">Pilih kode dan nama pelanggan</option>{transactions.map(item => <option value={item.value} key={item.value}>{item.label}</option>)}</select></label>
            <label>Jenis Penerimaan<select className="mt-1 w-full rounded-lg border p-3" value={form.data.receipt_purpose} onChange={e => form.setData('receipt_purpose', e.target.value)}>{Object.entries(purposes).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label>
            <label>Rekening Penerima<select className="mt-1 w-full rounded-lg border p-3" value={form.data.master_bank_id} onChange={e => form.setData('master_bank_id', e.target.value)}><option value="">Pilih rekening</option>{banks.map(item => <option value={item.value} key={item.value}>{item.label}</option>)}</select></label>
            <label>Tanggal<Input type="date" value={form.data.payment_date} onChange={e => form.setData('payment_date', e.target.value)}/></label>
            <label>Total Diterima<Input type="number" min="1" value={form.data.amount} onChange={e => form.setData('amount', e.target.value)}/></label>
            <label>Metode<select className="mt-1 w-full rounded-lg border p-3" value={form.data.payment_method} onChange={e => form.setData('payment_method', e.target.value)}>{['transfer','cash','giro','virtual_account','lainnya'].map(value => <option key={value}>{value}</option>)}</select></label>
            <label>Referensi Bank<Input value={form.data.bank_reference} onChange={e => form.setData('bank_reference', e.target.value)}/></label>
            <label>Bank Pengirim<Input value={form.data.sender_bank} onChange={e => form.setData('sender_bank', e.target.value)}/></label>
            <label>Nama Pengirim<Input value={form.data.sender_name} onChange={e => form.setData('sender_name', e.target.value)}/></label>
            <label>Bukti Transfer<Input type="file" accept="image/*,.pdf" onChange={e => form.setData('proof', e.target.files[0])}/></label>
            <label className="md:col-span-2">Catatan<Input value={form.data.notes} onChange={e => form.setData('notes', e.target.value)}/></label>
        </section>
        {transaction && <section className="rounded-xl border bg-white/80 p-6 dark:bg-white/8"><div className="flex justify-between"><div><h2 className="text-xl font-black">Alokasi Tagihan</h2><p className="text-sm text-ink-soft">Kosongkan bila masih berupa deposit. Tagihan masa depan dapat dipilih untuk pembayaran dipercepat.</p></div><Button type="button" onClick={add}><Plus size={15}/>Tambah</Button></div><div className="mt-4 grid gap-3">{form.data.allocations.map((allocation, index) => <div className="grid gap-2 md:grid-cols-[1fr_220px_44px]" key={index}><select className="rounded-lg border p-3" value={allocation.payment_schedule_id} onChange={e => update(index, 'payment_schedule_id', e.target.value)}><option value="">Pilih tagihan</option>{transaction.schedules.map(schedule => <option value={schedule.value} key={schedule.value}>{schedule.label} — Sisa {money(schedule.remaining)}</option>)}</select><Input type="number" min="0.01" value={allocation.amount} onChange={e => update(index, 'amount', e.target.value)}/><Button type="button" variant="outline" onClick={() => form.setData('allocations', form.data.allocations.filter((_, i) => i !== index))}><Trash2 size={15}/></Button></div>)}</div><div className="mt-5 rounded-lg bg-slate-100 p-4 font-bold">Dialokasikan {money(allocated)} · Deposit belum teralokasi {money(Math.max(0, Number(form.data.amount || 0) - allocated))}</div></section>}
        <div>{Object.values(form.errors).map((error, index) => <p className="text-sm text-red-600" key={index}>{error}</p>)}<Button disabled={form.processing} className="mt-3">Simpan Draf</Button></div>
    </form></>;
}
Form.layout = page => <AdminLayout title={page?.props?.title ?? 'Input Penerimaan Pelanggan'}>{page}</AdminLayout>;
