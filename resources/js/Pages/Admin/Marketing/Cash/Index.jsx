import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, CreditCard, LoaderCircle, Lock, PlusCircle, Save, Search, ShieldCheck, Unlock, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button, CurrencyInput, Dropdown, Form, Input, Modal, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function Pagination({ links = [] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
            {links.map((link, index) => (
                <Button
                    className={!link.url ? 'pointer-events-none opacity-45' : ''}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                    key={`${link.label}-${index}`}
                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })}
                    size="sm"
                    type="button"
                    variant={link.active ? 'dark' : 'outline'}
                />
            ))}
        </div>
    );
}

function FormErrorSummary({ errors }) {
    const messages = Object.values(errors ?? {}).filter(Boolean);

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            <p>Data belum bisa disimpan. Periksa bagian berikut:</p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {messages.map((message, index) => <li key={`${message}-${index}`}>{message}</li>)}
            </ul>
        </div>
    );
}

function PaymentModal({ open, onClose, sale, baseUrl, methods }) {
    const form = useForm({
        tanggal_pembayaran: new Date().toISOString().slice(0, 10),
        nominal: '',
        metode_pembayaran: 'transfer',
        keterangan: '',
        bukti_pembayaran: null,
    });

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(`${baseUrl}/${sale?.id}/payments`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: close,
        });
    };

    if (!sale) {
        return null;
    }

    return (
        <Modal open={open} onClose={close} title={`Tambah Pembayaran ${sale.kode_cash}`} size="lg">
            <Form
                collapsible={false}
                className="border-0 bg-transparent p-0 shadow-none dark:bg-transparent"
                contentClassName="md:grid-cols-2"
                onSubmit={submit}
                actions={(
                    <>
                        <Button type="button" variant="outline" onClick={close}>
                            <XCircle size={17} /> Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />}
                            Simpan Pembayaran
                        </Button>
                    </>
                )}
            >
                <Input label="Tanggal Pembayaran" type="date" value={form.data.tanggal_pembayaran} error={form.errors.tanggal_pembayaran} onChange={(event) => form.setData('tanggal_pembayaran', event.target.value)} />
                <CurrencyInput label="Nominal" value={form.data.nominal} error={form.errors.nominal} onChange={(value) => form.setData('nominal', value)} />
                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Metode Pembayaran</span>
                    <Dropdown value={form.data.metode_pembayaran} options={methods} onChange={(value) => form.setData('metode_pembayaran', value)} />
                    {form.errors.metode_pembayaran && <span className="text-xs font-bold text-red-600">{form.errors.metode_pembayaran}</span>}
                </div>
                <div className="grid gap-2">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Bukti Pembayaran</span>
                    <input
                        type="file"
                        accept="image/*"
                        className="min-h-11 w-full rounded-lg border border-silver-deep/70 bg-white/85 px-4 py-2.5 text-sm font-semibold text-ink outline-none file:mr-4 file:rounded-md file:border-0 file:bg-ink file:px-4 file:py-2 file:text-sm file:font-bold file:text-white dark:border-white/10 dark:bg-white/8 dark:text-white"
                        onChange={(event) => form.setData('bukti_pembayaran', event.target.files?.[0] ?? null)}
                    />
                    {form.errors.bukti_pembayaran && <span className="text-xs font-bold text-red-600">{form.errors.bukti_pembayaran}</span>}
                </div>
                <Textarea className="md:col-span-2" label="Keterangan" value={form.data.keterangan} error={form.errors.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} />
            </Form>
        </Modal>
    );
}

export default function Index({ title, description, baseUrl, rows, filters = {}, options = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [paymentSale, setPaymentSale] = useState(null);
    const form = useForm({
        spr_id: '',
        tanggal_transaksi: new Date().toISOString().slice(0, 10),
        catatan: '',
    });

    const selectedSpr = useMemo(
        () => options.sprOptions?.find((item) => item.value === form.data.spr_id) ?? null,
        [form.data.spr_id, options.sprOptions],
    );

    const submit = (event) => {
        event.preventDefault();
        form.post(baseUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                form.setData('tanggal_transaksi', new Date().toISOString().slice(0, 10));
            },
        });
    };

    const submitSearch = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const lockRow = (row) => {
        if (!window.confirm(`Lock transaksi cash ${row.kode_cash}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock transaksi cash ${row.kode_cash}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    const handoverRow = (row) => {
        if (!window.confirm(`Tandai serah terima untuk ${row.kode_cash}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/handover`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Marketing</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <Form
                    collapsible
                    title="Buat Transaksi Cash"
                    description="Pilih SPR cash yang sudah disetujui. Data customer, unit, dan harga akan ikut terbaca otomatis."
                    onSubmit={submit}
                    actions={(
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <PlusCircle size={17} />}
                            Buat Transaksi
                        </Button>
                    )}
                >
                    <FormErrorSummary errors={form.errors} />
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2 md:col-span-2">
                            <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">SPR Cash</span>
                            <Dropdown
                                value={form.data.spr_id}
                                label="Pilih SPR Cash"
                                options={options.sprOptions ?? []}
                                onChange={(value) => form.setData('spr_id', value)}
                            />
                            {form.errors.spr_id && <span className="text-xs font-bold text-red-600">{form.errors.spr_id}</span>}
                        </div>
                        <Input
                            label="Tanggal Transaksi"
                            type="date"
                            value={form.data.tanggal_transaksi}
                            error={form.errors.tanggal_transaksi}
                            onChange={(event) => form.setData('tanggal_transaksi', event.target.value)}
                        />
                    </div>

                    {selectedSpr && (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            {[
                                ['Customer', selectedSpr.customer],
                                ['Unit', selectedSpr.unit],
                                ['Perumahan', selectedSpr.perumahan],
                                ['Harga Jual', money(selectedSpr.harga_jual)],
                                ['Booking Fee', money(selectedSpr.booking_fee)],
                                ['Uang Muka', money(selectedSpr.uang_muka)],
                                ['Sisa Sementara', money(selectedSpr.sisa_sementara)],
                            ].map(([label, value]) => (
                                <div className="rounded-lg border border-silver-deep/50 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5" key={label}>
                                    <p className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">{label}</p>
                                    <p className="mt-1 text-sm font-bold text-ink dark:text-white">{value}</p>
                                </div>
                            ))}
                        </div>
                    )}

                    <Textarea label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                </Form>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between"
                        onSubmit={submitSearch}
                    >
                        <Input className="md:max-w-md" icon={<Search size={17} />} label="Cari Transaksi Cash" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit">
                            <Search size={17} />
                            Cari
                        </Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Kode', 'SPR', 'Customer', 'Unit', 'Harga Rumah', 'Dibayar', 'Sisa', 'Status', 'Lock', 'Aksi'].map((column) => (
                                        <th className={`px-4 py-3 font-extrabold ${column === 'Aksi' ? 'text-right' : ''}`} key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                        <td className="px-4 py-3 font-bold">{row.kode_cash}</td>
                                        <td className="px-4 py-3 font-semibold">{row.kode_spr}</td>
                                        <td className="px-4 py-3 font-semibold">{row.customer}</td>
                                        <td className="px-4 py-3 font-semibold">{row.unit}</td>
                                        <td className="px-4 py-3 font-semibold">{money(row.harga_rumah)}</td>
                                        <td className="px-4 py-3 font-semibold">{money(row.total_dibayar)}</td>
                                        <td className="px-4 py-3 font-semibold">{money(row.sisa_tagihan)}</td>
                                        <td className="px-4 py-3 font-semibold">{row.status_label}</td>
                                        <td className="px-4 py-3 font-semibold">{row.record_status_label}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                {row.record_status === 'locked' ? (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}>
                                                        <Unlock size={15} />
                                                    </Button>
                                                ) : (
                                                    <>
                                                        <Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}>
                                                            <Lock size={15} />
                                                        </Button>
                                                        <Button type="button" size="sm" onClick={() => setPaymentSale(row)}>
                                                            <CreditCard size={15} /> Bayar
                                                        </Button>
                                                    </>
                                                )}
                                                {row.can_handover && (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => handoverRow(row)}>
                                                        <ShieldCheck size={15} /> Serah Terima
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={10}>
                                            Belum ada transaksi cash.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

            <PaymentModal
                baseUrl={baseUrl}
                methods={options.paymentMethods ?? []}
                onClose={() => setPaymentSale(null)}
                open={Boolean(paymentSale)}
                sale={paymentSale}
            />
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Transaksi Cash'}>{page}</AdminLayout>;
