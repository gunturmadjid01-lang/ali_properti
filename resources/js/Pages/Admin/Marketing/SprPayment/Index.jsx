import { Head, router, useForm } from '@inertiajs/react';
import { Ban, CreditCard, FileText, LoaderCircle, Save, Search, ShieldCheck, Wallet } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button, CurrencyInput, Dropdown, Input, Modal, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function paymentBadge(status) {
    if (status === 'dikonfirmasi') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
    if (status === 'ditolak') return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300';
    return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200';
}

function PaymentModal({ open, onClose, row, type = 'booking', baseUrl, bankOptions = [], canConfirmPayments = false, canInputPayments = true }) {
    const isBooking = type === 'booking';
    const isDp = type === 'dp';
    const isOther = type === 'other';
    const defaultNominal = isBooking
        ? row?.booking_remaining ?? row?.booking_fee ?? 0
        : isDp
            ? row?.dp_remaining ?? row?.uang_muka ?? 0
            : '';
    const form = useForm({
        master_bank_id: '',
        tanggal_pembayaran: new Date().toISOString().slice(0, 10),
        nominal: String(defaultNominal || ''),
        keterangan: '',
        bukti_pembayaran: null,
    });

    useEffect(() => {
        if (!open || !row) {
            return;
        }

        form.setData('master_bank_id', '');
        form.setData('tanggal_pembayaran', new Date().toISOString().slice(0, 10));
        form.setData('nominal', String(defaultNominal || ''));
        form.setData('keterangan', '');
        form.setData('bukti_pembayaran', null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, row?.id, type]);

    if (!open || !row) {
        return null;
    }

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(
            `${baseUrl}/${row.id}/${isBooking ? 'booking-fee' : isDp ? 'uang-muka' : 'lainnya'}`,
            {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: close,
            },
        );
    };

    const currentPayments = (row.payments ?? []).filter((payment) => payment.jenis_pembayaran === (isBooking ? 'booking_fee' : isDp ? 'uang_muka' : 'lainnya'));
    const title = isBooking ? 'Pembayaran Booking Fee' : isDp ? 'Pembayaran Uang Muka' : 'Pembayaran Lainnya';
    const paymentLabel = isBooking ? 'Booking Fee' : isDp ? 'Uang Muka' : 'Lainnya';
    const remaining = isBooking ? Number(row.booking_remaining ?? 0) : isDp ? Number(row.dp_remaining ?? 0) : null;
    const dpLimit = Number(row.dp_installments_limit ?? 0);
    const dpUsed = Number(row.dp_installments_used ?? 0);
    const canSubmitPayment = !isDp || dpLimit <= 0 || dpUsed < dpLimit;

    return (
        <Modal open={open} onClose={close} title={`${title} - ${row.kode_spr}`} size="full">
            <div className={`grid gap-6 ${canInputPayments ? 'xl:grid-cols-[1fr_1.1fr]' : ''}`}>
                <section className="grid gap-4 rounded-lg border border-silver-deep/60 bg-silver-soft/30 p-4 dark:border-white/10 dark:bg-white/5">
                    <div className="grid gap-3 md:grid-cols-2">
                        <div className="rounded-lg border border-white/60 bg-white/80 p-4 dark:border-white/10 dark:bg-white/5">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft">Customer</p>
                            <p className="mt-1 text-sm font-extrabold text-ink dark:text-white">{row.customer}</p>
                            <p className="text-xs font-semibold text-ink-soft dark:text-white/55">{row.no_identitas}</p>
                        </div>
                        <div className="rounded-lg border border-white/60 bg-white/80 p-4 dark:border-white/10 dark:bg-white/5">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft">Unit</p>
                            <p className="mt-1 text-sm font-extrabold text-ink dark:text-white">{row.unit}</p>
                            <p className="text-xs font-semibold text-ink-soft dark:text-white/55">{row.perumahan}</p>
                        </div>
                    </div>

                    <div className="grid gap-3 md:grid-cols-3">
                        <div className="rounded-lg border border-white/60 bg-white/80 p-4 dark:border-white/10 dark:bg-white/5">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft">{isOther ? 'Total Pembayaran' : 'Target'}</p>
                            <p className="mt-1 text-sm font-extrabold text-ink dark:text-white">{money(isBooking ? row.booking_fee : isDp ? row.uang_muka : row.other_paid)}</p>
                        </div>
                        <div className="rounded-lg border border-white/60 bg-white/80 p-4 dark:border-white/10 dark:bg-white/5">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft">Sudah Dibayar</p>
                            <p className="mt-1 text-sm font-extrabold text-ink dark:text-white">{money(isBooking ? row.booking_paid : isDp ? row.dp_paid : row.other_paid)}</p>
                        </div>
                        <div className="rounded-lg border border-white/60 bg-white/80 p-4 dark:border-white/10 dark:bg-white/5">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-ink-soft">{isOther ? 'Keterangan' : 'Sisa'}</p>
                            <p className="mt-1 text-sm font-extrabold text-ink dark:text-white">{isOther ? paymentLabel : money(isBooking ? row.booking_remaining : row.dp_remaining)}</p>
                        </div>
                    </div>

                    {canInputPayments && (
                        <form className="grid gap-4" onSubmit={submit}>
                            <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                                <p>Sisa yang bisa diinput: {remaining === null ? '-' : money(remaining)}</p>
                                {isDp && dpLimit > 0 && <p className="mt-1">Termin Uang Muka: {dpUsed}/{dpLimit} kali sudah diinput.</p>}
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Rekening Bank</span>
                                <Dropdown value={form.data.master_bank_id} options={bankOptions} onChange={(value) => form.setData('master_bank_id', value)} />
                                {form.errors.master_bank_id && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.master_bank_id}</span>}
                            </div>
                            <Input label="Tanggal Pembayaran" type="date" value={form.data.tanggal_pembayaran} error={form.errors.tanggal_pembayaran} onChange={(event) => form.setData('tanggal_pembayaran', event.target.value)} />
                            <CurrencyInput label="Nominal" value={form.data.nominal} error={form.errors.nominal} onChange={(value) => form.setData('nominal', remaining === null ? value : String(Math.min(Number(value || 0), remaining)))} />
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Bukti Pembayaran</span>
                                <input
                                    className="min-h-11 w-full rounded-lg border border-silver-deep/70 bg-white/85 px-4 py-2.5 text-sm font-semibold text-ink outline-none file:mr-4 file:rounded-md file:border-0 file:bg-ink file:px-4 file:py-2 file:text-sm file:font-bold file:text-white dark:border-white/10 dark:bg-white/8 dark:text-white"
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) => form.setData('bukti_pembayaran', event.target.files?.[0] ?? null)}
                                />
                                {form.errors.bukti_pembayaran && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.bukti_pembayaran}</span>}
                            </div>
                            <Textarea label="Keterangan" value={form.data.keterangan} error={form.errors.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} />
                            <div className="flex justify-end gap-3">
                                <Button type="button" variant="outline" onClick={close}>Batal</Button>
                            <Button type="submit" disabled={form.processing || remaining <= 0 || !canSubmitPayment}>
                                {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />}
                                Simpan
                                </Button>
                            </div>
                        </form>
                    )}
                </section>

                <section className="grid gap-4 rounded-lg border border-silver-deep/60 bg-white/80 p-4 dark:border-white/10 dark:bg-white/5">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-extrabold text-ink dark:text-white">Riwayat Pembayaran</p>
                            <p className="text-xs font-semibold text-ink-soft dark:text-white/55">{paymentLabel}</p>
                        </div>
                        <p className="text-xs font-bold text-ink-soft dark:text-white/55">{currentPayments.length} data</p>
                    </div>
                    <div className="grid gap-3">
                        {currentPayments.length > 0 ? currentPayments.map((payment, index) => (
                            <div key={payment.id} className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                                <div className="flex items-center justify-between gap-3">
                                    <p className="text-sm font-extrabold text-ink dark:text-white"># {index + 1} {payment.tanggal_pembayaran}</p>
                                    <p className="text-sm font-extrabold text-emerald-600 dark:text-emerald-300">{money(payment.nominal)}</p>
                                </div>
                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                    <span className={`rounded-full px-3 py-1 text-[11px] font-extrabold ${paymentBadge(payment.status)}`}>{payment.status_label}</span>
                                    <span className="text-[11px] font-semibold text-ink-soft dark:text-white/55">Input: {payment.created_by}</span>
                                    <span className="text-[11px] font-semibold text-ink-soft dark:text-white/55">Dibuat: {payment.created_at ?? '-'}</span>
                                    <span className="text-[11px] font-semibold text-ink-soft dark:text-white/55">Diupdate: {payment.updated_at ?? '-'}</span>
                                    {payment.status !== 'menunggu_konfirmasi' && <span className="text-[11px] font-semibold text-ink-soft dark:text-white/55">Admin: {payment.confirmed_by}</span>}
                                    {payment.confirmed_at && <span className="text-[11px] font-semibold text-ink-soft dark:text-white/55">Konfirmasi: {payment.confirmed_at}</span>}
                                </div>
                                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">Bank: {payment.bank}</p>
                                {payment.keterangan && <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">{payment.keterangan}</p>}
                                {payment.confirmation_note && <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">Catatan admin: {payment.confirmation_note}</p>}
                                {payment.bukti_url && (
                                    <a className="mt-2 inline-flex text-xs font-bold text-emerald-600 underline decoration-dotted underline-offset-4 dark:text-emerald-300" href={payment.bukti_url} target="_blank" rel="noreferrer">
                                        Lihat bukti
                                    </a>
                                )}
                                {canConfirmPayments && payment.status === 'menunggu_konfirmasi' && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Button size="sm" type="button" onClick={() => router.post(`${baseUrl}/payment/${payment.id}/confirm`, {}, { preserveScroll: true })}>
                                            <ShieldCheck size={14} /> Konfirmasi Masuk
                                        </Button>
                                        <Button size="sm" type="button" variant="outline" className="text-red-600" onClick={() => router.post(`${baseUrl}/payment/${payment.id}/reject`, {}, { preserveScroll: true })}>
                                            <Ban size={14} /> Tolak
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )) : (
                            <p className="rounded-lg border border-dashed border-silver-deep/60 px-4 py-8 text-center text-sm font-bold text-ink-soft dark:border-white/10 dark:text-white/55">
                                Belum ada pembayaran.
                            </p>
                        )}
                    </div>
                </section>
            </div>
        </Modal>
    );
}

function CancelModal({ open, onClose, row, baseUrl, bankOptions = [] }) {
    const form = useForm({
        alasan_batal: 'Tidak ada dana',
        catatan: '',
        refund_amount: '0',
        refund_master_bank_id: '',
        refund_at: new Date().toISOString().slice(0, 10),
    });

    useEffect(() => {
        if (!open || !row) {
            return;
        }

        form.setData('alasan_batal', 'Tidak ada dana');
        form.setData('catatan', '');
        form.setData('refund_amount', '0');
        form.setData('refund_master_bank_id', '');
        form.setData('refund_at', new Date().toISOString().slice(0, 10));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, row?.id]);

    if (!open || !row) {
        return null;
    }

    const submit = (event) => {
        event.preventDefault();
        form.post(`${baseUrl}/${row.id}/cancel`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const reasons = [
        { value: 'Tidak ada dana', label: 'Tidak ada dana' },
        { value: 'Customer batal', label: 'Customer batal' },
        { value: 'Dokumen tidak lengkap', label: 'Dokumen tidak lengkap' },
        { value: 'Lainnya', label: 'Lainnya' },
    ];

    return (
        <Modal open={open} onClose={onClose} title={`Cancel SPR ${row.kode_spr}`} size="lg">
            <form className="grid gap-4" onSubmit={submit}>
                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Alasan Batal</span>
                    <Dropdown value={form.data.alasan_batal} options={reasons} onChange={(value) => form.setData('alasan_batal', value)} />
                    {form.errors.alasan_batal && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.alasan_batal}</span>}
                </div>
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                    Total pembayaran dikonfirmasi yang bisa dikembalikan: {money(row.refundable_paid ?? 0)}
                </div>
                <CurrencyInput label="Jumlah Pengembalian" value={form.data.refund_amount} error={form.errors.refund_amount} onChange={(value) => form.setData('refund_amount', value)} />
                {Number(form.data.refund_amount || 0) > 0 && (
                    <>
                        <Dropdown label="Bank/Kas Pengembalian" value={form.data.refund_master_bank_id} options={bankOptions} onChange={(value) => form.setData('refund_master_bank_id', value)} />
                        {form.errors.refund_master_bank_id && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.refund_master_bank_id}</span>}
                        <Input label="Tanggal Pengembalian" type="date" value={form.data.refund_at} error={form.errors.refund_at} onChange={(event) => form.setData('refund_at', event.target.value)} />
                    </>
                )}
                <Textarea label="Catatan Tambahan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={onClose}>Batal</Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Ban size={17} />}
                        Cancel SPR
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

export default function Index({ title, description, baseUrl, filters = {}, bookingRows = [], dpRows = [], bankOptions = [], tabs = [], canConfirmPayments = false, canInputPayments = true, areaLabel = 'Marketing' }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [activeTab, setActiveTab] = useState(filters.tab ?? 'booking');
    const [paymentRow, setPaymentRow] = useState(null);
    const [paymentType, setPaymentType] = useState('booking');
    const [cancelRow, setCancelRow] = useState(null);

    const currentRows = activeTab === 'dp' ? dpRows : bookingRows;

    const submitSearch = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search, tab: activeTab }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">{areaLabel}</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <section className="rounded-lg border border-white/80 bg-white/78 p-4 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between" onSubmit={submitSearch}>
                        <Input className="md:max-w-md" icon={<Search size={17} />} label="Cari SPR / Customer / Unit" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit">
                            <Search size={17} /> Cari
                        </Button>
                    </form>

                    <div className="mt-4 flex flex-wrap gap-2 border-b border-silver-deep/60 pb-4 dark:border-white/10">
                        {tabs.map((tab) => (
                            <Button
                                key={tab.value}
                                type="button"
                                variant={activeTab === tab.value ? 'dark' : 'outline'}
                                onClick={() => {
                                    setActiveTab(tab.value);
                                    router.get(baseUrl, { search, tab: tab.value }, { preserveScroll: true, preserveState: true, replace: true });
                                }}
                            >
                                {tab.label}
                            </Button>
                        ))}
                    </div>

                    <div className="mt-4 overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {(activeTab === 'booking'
                                        ? ['SPR', 'Customer', 'Unit', 'Booking Fee', 'Dibayar', 'Sisa', 'Dibuat', 'Diupdate', 'Status', 'Aksi']
                                        : activeTab === 'dp'
                                            ? ['SPR', 'Customer', 'Unit', 'DP', 'Dibayar', 'Sisa', 'Dibuat', 'Diupdate', 'Status', 'Aksi']
                                            : ['SPR', 'Customer', 'Unit', 'Total', 'Sudah Dibayar', 'Keterangan', 'Dibuat', 'Diupdate', 'Status', 'Aksi']
                                    ).map((column) => (
                                        <th className={`px-4 py-3 font-extrabold ${column === 'Aksi' ? 'text-right' : ''}`} key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {currentRows.map((row) => (
                                    <tr className="transition hover:bg-silver/60 dark:hover:bg-white/5" key={row.id}>
                                        <td className="px-4 py-3 font-bold">{row.kode_spr}</td>
                                        <td className="px-4 py-3 font-semibold">
                                            {row.customer}
                                            <p className="text-[11px] font-semibold text-ink-soft dark:text-white/45">{row.no_identitas}</p>
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {row.unit}
                                            <p className="text-[11px] font-semibold text-ink-soft dark:text-white/45">{row.perumahan}</p>
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {activeTab === 'booking'
                                                ? money(row.booking_fee)
                                                : activeTab === 'dp'
                                                    ? money(row.uang_muka)
                                                    : money(row.other_paid)}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {activeTab === 'booking'
                                                ? money(row.booking_paid)
                                                : activeTab === 'dp'
                                                    ? money(row.dp_paid)
                                                    : money(row.other_paid)}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {activeTab === 'booking'
                                                ? money(row.booking_remaining)
                                                : activeTab === 'dp'
                                                    ? money(row.dp_remaining)
                                                    : row.other_status}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">{row.created_at ?? '-'}</td>
                                        <td className="px-4 py-3 font-semibold">{row.updated_at ?? '-'}</td>
                                        <td className="px-4 py-3 font-semibold">
                                            <p>
                                                {!canInputPayments
                                                    ? (activeTab === 'booking' ? row.booking_confirmation_status : activeTab === 'dp' ? row.dp_confirmation_status : row.other_status)
                                                    : (activeTab === 'booking' ? row.booking_status : activeTab === 'dp' ? row.dp_status : row.other_status)}
                                            </p>
                                            {activeTab === 'booking' && row.alasan_batal && <p className="text-[11px] font-semibold text-rose-500">{row.alasan_batal}</p>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button type="button" size="sm" onClick={() => {
                                                    setPaymentRow(row);
                                                    setPaymentType(activeTab === 'booking' ? 'booking' : activeTab === 'dp' ? 'dp' : 'other');
                                                }}>
                                                    {canInputPayments ? <CreditCard size={15} /> : <FileText size={15} />}
                                                    {canInputPayments ? 'Bayar' : 'Detail'}
                                                </Button>
                                                {canInputPayments && (
                                                    activeTab === 'booking' ? (
                                                        <Button type="button" size="sm" variant="outline" onClick={() => setCancelRow(row)}>
                                                            <Ban size={15} /> Cancel
                                                        </Button>
                                                    ) : activeTab === 'dp' ? (
                                                        <Button type="button" size="sm" variant="outline" onClick={() => setPaymentRow(row)}>
                                                            <FileText size={15} /> Riwayat
                                                        </Button>
                                                    ) : (
                                                        <Button type="button" size="sm" variant="outline" onClick={() => setPaymentRow(row)}>
                                                            <FileText size={15} /> Riwayat
                                                        </Button>
                                                    )
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {currentRows.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={10}>
                                            Belum ada data pembayaran.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <PaymentModal
                bankOptions={bankOptions}
                baseUrl={baseUrl}
                onClose={() => setPaymentRow(null)}
                open={Boolean(paymentRow)}
                row={paymentRow}
                type={paymentType}
                canConfirmPayments={canConfirmPayments}
                canInputPayments={canInputPayments}
            />

            {canInputPayments && (
                <CancelModal
                    baseUrl={baseUrl}
                    bankOptions={bankOptions}
                    onClose={() => setCancelRow(null)}
                    open={Boolean(cancelRow)}
                    row={cancelRow}
                />
            )}
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Pembayaran SPR'}>{page}</AdminLayout>;
