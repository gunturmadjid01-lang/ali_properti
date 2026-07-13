import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, LoaderCircle, RotateCcw, Search, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Button, CurrencyInput, Dropdown, Input, Modal, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value ?? 0));

function statusClass(status) {
    if (status === 'disetujui') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
    if (status === 'ditolak') return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300';
    if (status === 'menunggu_manager' || status === 'menunggu_owner') return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200';
    return 'bg-silver text-ink-soft dark:bg-white/10 dark:text-white/60';
}

function RefundRequestModal({ open, row, bankOptions, onClose }) {
    const form = useForm({
        alasan_batal: row?.alasan_batal ?? 'Customer batal',
        refund_amount: row?.refundable_paid ? String(row.refundable_paid) : '',
        refund_master_bank_id: '',
        refund_at: new Date().toISOString().slice(0, 10),
        catatan: '',
    });

    if (!open || !row) return null;

    const submit = (event) => {
        event.preventDefault();
        form.post(`/admin/keuangan/refund-spr/${row.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Modal open={open} onClose={onClose} title={`Ajukan Refund ${row.kode_spr}`} size="lg">
            <form className="grid gap-4" onSubmit={submit}>
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                    Maksimal refund: {money(row.refundable_paid)}
                </div>
                <Input label="Alasan Batal" value={form.data.alasan_batal} error={form.errors.alasan_batal} onChange={(event) => form.setData('alasan_batal', event.target.value)} />
                <CurrencyInput label="Jumlah Refund" value={form.data.refund_amount} error={form.errors.refund_amount} onChange={(value) => form.setData('refund_amount', value)} />
                <Dropdown label="Bank/Kas Pengembalian" value={form.data.refund_master_bank_id} options={bankOptions} onChange={(value) => form.setData('refund_master_bank_id', value)} />
                {form.errors.refund_master_bank_id && <span className="text-xs font-bold text-red-600">{form.errors.refund_master_bank_id}</span>}
                <Input label="Tanggal Rencana Pengembalian" type="date" value={form.data.refund_at} error={form.errors.refund_at} onChange={(event) => form.setData('refund_at', event.target.value)} />
                <Textarea label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={onClose}>Batal</Button>
                    <Button disabled={form.processing} type="submit">
                        {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <RotateCcw size={17} />}
                        Ajukan Refund
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function ApprovalModal({ open, row, action, onClose }) {
    const form = useForm({ note: '' });
    if (!open || !row) return null;

    const submit = (event) => {
        event.preventDefault();
        const url = action === 'manager'
            ? `/admin/refund-spr/${row.id}/approve-manager`
            : action === 'owner'
                ? `/admin/refund-spr/${row.id}/approve-owner`
                : `/admin/refund-spr/${row.id}/reject`;

        form.post(url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Modal open={open} onClose={onClose} title={`${action === 'reject' ? 'Tolak' : 'Approve'} Refund ${row.kode_spr}`} size="lg">
            <form className="grid gap-4" onSubmit={submit}>
                <div className="grid gap-2 rounded-lg border border-silver-deep/60 bg-silver-soft/50 p-4 text-sm dark:border-white/10 dark:bg-white/5">
                    <b>{row.customer}</b>
                    <span>{row.unit} - {row.perumahan}</span>
                    <span className="font-extrabold">{money(row.refund_amount)}</span>
                </div>
                <Textarea label={action === 'reject' ? 'Alasan Penolakan' : 'Catatan Approval'} value={form.data.note} error={form.errors.note} onChange={(event) => form.setData('note', event.target.value)} />
                <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={onClose}>Batal</Button>
                    <Button disabled={form.processing} type="submit" variant={action === 'reject' ? 'outline' : 'dark'}>
                        {action === 'reject' ? <XCircle size={17} /> : <CheckCircle2 size={17} />}
                        {action === 'reject' ? 'Tolak' : 'Approve'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Refund({ title, description, baseUrl, filters = {}, rows = [], bankOptions = [], mode = 'finance' }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [requestRow, setRequestRow] = useState(null);
    const [approval, setApproval] = useState({ row: null, action: '' });

    const submitSearch = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">{mode === 'approval' ? 'Approval' : 'Keuangan'}</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-end md:justify-between" onSubmit={submitSearch}>
                        <Input className="md:max-w-md" icon={<Search size={17} />} label="Cari SPR / Customer" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['SPR', 'Customer', 'Unit', 'Booking', 'Uang Muka', 'Refund', 'Status', 'Aksi'].map((column) => (
                                        <th className={`px-4 py-3 font-extrabold ${column === 'Aksi' ? 'text-right' : ''}`} key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                        <td className="px-4 py-3 font-bold">{row.kode_spr}</td>
                                        <td className="px-4 py-3 font-semibold">{row.customer}</td>
                                        <td className="px-4 py-3 font-semibold">{row.unit}<br /><span className="text-ink-soft">{row.perumahan}</span></td>
                                        <td className="px-4 py-3 font-semibold">{money(row.booking_paid)}</td>
                                        <td className="px-4 py-3 font-semibold">{money(row.dp_paid)}</td>
                                        <td className="px-4 py-3 font-semibold">{money(row.refund_amount || row.refundable_paid)}</td>
                                        <td className="px-4 py-3 font-semibold">
                                            <span className={`rounded-full px-3 py-1 text-[11px] font-extrabold ${statusClass(row.refund_status)}`}>{row.refund_status_label}</span>
                                            {row.refund_approval_note && <pre className="mt-2 max-w-xs whitespace-pre-wrap text-[11px] text-ink-soft dark:text-white/55">{row.refund_approval_note}</pre>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                {row.can_request && <Button as={Link} href={`/admin/refund-spr/request/${row.id}`} size="sm"><RotateCcw size={15} /> Refund</Button>}
                                                {row.can_approve_manager && <Button as={Link} href={`/admin/refund-spr/${row.id}/review/manager`} size="sm"><CheckCircle2 size={15} /> Approve Manajer</Button>}
                                                {row.can_approve_owner && <Button as={Link} href={`/admin/refund-spr/${row.id}/review/owner`} size="sm"><CheckCircle2 size={15} /> Approve Owner</Button>}
                                                {row.can_reject && <Button as={Link} href={`/admin/refund-spr/${row.id}/review/reject`} size="sm" variant="outline"><XCircle size={15} /> Tolak</Button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.length === 0 && (
                                    <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={8}>Belum ada data refund.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

        </>
    );
}

Refund.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Refund SPR'}>{page}</AdminLayout>;
