import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, LoaderCircle, MinusCircle, PlusCircle, Save, Search, Trash2, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button, CurrencyInput, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

function paymentTemplate() {
    return { tanggal_jatuh_tempo: '', tanggal_pembayaran: '', nominal: '', keterangan: '' };
}

function additionTemplate() {
    return {
        kategori_penambahan: 'lainnya',
        judul_penambahan: '',
        deskripsi: '',
        volume: '',
        satuan: '',
        harga_satuan: '',
        total: '',
        keterangan: '',
    };
}

function FormErrorSummary({ errors }) {
    const messages = Object.values(errors ?? {}).filter(Boolean);
    if (messages.length === 0) return null;

    return (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            <p>Data belum bisa disimpan. Periksa bagian berikut:</p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {messages.map((message, index) => <li key={`${message}-${index}`}>{message}</li>)}
            </ul>
        </div>
    );
}

export default function Index({ title, description, baseUrl, pageUrl = baseUrl, rows, filters = {}, options, approvalOnly = false }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState(null);
    const form = useForm({
        kontraktor_id: '',
        perumahan_id: '',
        detail_rumah_id: '',
        judul_pekerjaan: '',
        jenis_pekerjaan: 'rumah',
        tanggal_spk: new Date().toISOString().slice(0, 10),
        tanggal_mulai: '',
        tanggal_selesai: '',
        nilai_kontrak_dasar: '',
        nilai_kontrak: '',
        metode_pembayaran: 'cash',
        approval_role: 'manager',
        lingkup_pekerjaan: '',
        catatan: '',
        status: 'draft',
        additions: [additionTemplate()],
        payments: [paymentTemplate()],
    });

    const detailRumahOptions = useMemo(() => {
        if (!form.data.perumahan_id) return options.detailRumahs;
        return options.detailRumahs.filter((item) => item.perumahan_id === String(form.data.perumahan_id));
    }, [form.data.perumahan_id, options.detailRumahs]);

    const additionsTotal = (form.data.additions ?? []).reduce((sum, item) => {
        const volume = Number(item.volume || 0);
        const hargaSatuan = Number(item.harga_satuan || 0);
        const total = item.total ? Number(item.total || 0) : volume * hargaSatuan;
        return sum + total;
    }, 0);
    const nilaiDasarKontrak = Number(form.data.nilai_kontrak_dasar || 0);
    const totalKontrak = nilaiDasarKontrak + additionsTotal;
    const totalPayment = form.data.payments.reduce((sum, item) => sum + Number(item.nominal || 0), 0);
    const paymentDifference = totalKontrak - totalPayment;
    const paymentIsBalanced = form.data.metode_pembayaran === 'cash' || Math.round(paymentDifference) === 0;

    useEffect(() => {
        if (Number(form.data.nilai_kontrak || 0) !== totalKontrak) {
            form.setData('nilai_kontrak', totalKontrak);
        }
        if (form.data.metode_pembayaran === 'cash') {
            const currentPayment = form.data.payments[0] ?? paymentTemplate();
            const nextNominal = totalKontrak;
            if (Number(currentPayment.nominal || 0) !== nextNominal || currentPayment.tanggal_jatuh_tempo !== form.data.tanggal_spk) {
                form.setData('payments', [{
                    ...currentPayment,
                    tanggal_jatuh_tempo: form.data.tanggal_spk,
                    tanggal_pembayaran: currentPayment.tanggal_pembayaran || form.data.tanggal_spk,
                    nominal: nextNominal,
                    keterangan: currentPayment.keterangan || 'Pembayaran cash / sekaligus.',
                }]);
            }
        }
    }, [totalKontrak, form.data.metode_pembayaran, form.data.tanggal_spk]);

    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const setPayment = (index, key, value) => {
        form.setData('payments', form.data.payments.map((item, itemIndex) => itemIndex === index ? { ...item, [key]: value } : item));
    };

    const setAddition = (index, key, value) => {
        form.setData('additions', form.data.additions.map((item, itemIndex) => {
            if (itemIndex !== index) return item;

            const next = { ...item, [key]: value };
            const volume = Number(next.volume || 0);
            const hargaSatuan = Number(next.harga_satuan || 0);
            next.total = volume * hargaSatuan;
            return next;
        }));
    };

    const setMetodePembayaran = (value) => {
        form.setData({
            ...form.data,
            metode_pembayaran: value,
            payments: value === 'cash'
                ? [{ ...paymentTemplate(), tanggal_jatuh_tempo: form.data.tanggal_spk, tanggal_pembayaran: form.data.tanggal_spk, nominal: totalKontrak, keterangan: 'Pembayaran cash / sekaligus.' }]
                : form.data.payments.length
                    ? form.data.payments
                    : [{ ...paymentTemplate(), tanggal_jatuh_tempo: form.data.tanggal_spk }],
        });
    };

    const setNilaiDasarKontrak = (value) => {
        form.setData('nilai_kontrak_dasar', value);
        if (form.data.metode_pembayaran === 'cash') {
            form.setData('payments', [{
                ...(form.data.payments[0] ?? paymentTemplate()),
                tanggal_jatuh_tempo: form.data.tanggal_spk,
                tanggal_pembayaran: form.data.tanggal_spk,
                nominal: Number(value || 0) + additionsTotal,
            }]);
        }
    };

    const editRow = (row) => {
        setEditing(row);
        form.setData({
            kontraktor_id: row.kontraktor_id ?? '',
            perumahan_id: row.perumahan_id ?? '',
            detail_rumah_id: row.detail_rumah_id ?? '',
            judul_pekerjaan: row.judul_pekerjaan ?? '',
            jenis_pekerjaan: row.jenis_pekerjaan ?? 'rumah',
            tanggal_spk: row.tanggal_spk ?? new Date().toISOString().slice(0, 10),
            tanggal_mulai: row.tanggal_mulai ?? '',
            tanggal_selesai: row.tanggal_selesai ?? '',
            nilai_kontrak_dasar: row.nilai_kontrak_dasar ?? '',
            nilai_kontrak: row.nilai_kontrak ?? '',
            metode_pembayaran: row.metode_pembayaran ?? 'cash',
            approval_role: row.approval_role ?? 'manager',
            lingkup_pekerjaan: row.lingkup_pekerjaan ?? '',
            catatan: row.catatan ?? '',
            status: row.status ?? 'draft',
            additions: row.additions?.length ? row.additions.map((addition) => ({
                kategori_penambahan: addition.kategori_penambahan ?? 'lainnya',
                judul_penambahan: addition.judul_penambahan ?? '',
                deskripsi: addition.deskripsi ?? '',
                volume: addition.volume ?? '',
                satuan: addition.satuan ?? '',
                harga_satuan: addition.harga_satuan ?? '',
                total: addition.total ?? '',
                keterangan: addition.keterangan ?? '',
            })) : [additionTemplate()],
            payments: row.payments?.length ? row.payments.map((payment) => ({
                tanggal_jatuh_tempo: payment.tanggal_jatuh_tempo ?? '',
                tanggal_pembayaran: payment.tanggal_pembayaran ?? '',
                nominal: payment.nominal ?? '',
                keterangan: payment.keterangan ?? '',
            })) : [paymentTemplate()],
        });
    };

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true, onSuccess: resetForm };
        editing ? form.put(`${baseUrl}/${editing.id}`, requestOptions) : form.post(baseUrl, requestOptions);
    };

    const destroyRow = (row) => {
        if (!window.confirm(`Hapus SPK ${row.nomor_spk}?`)) return;
        router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    const postPaymentAction = (row, payment, action) => {
        router.post(`${baseUrl}/${row.id}/payments/${payment.id}/${action}`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Manajemen Proyek</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                    {!approvalOnly && (
                    <Form
                        collapsible
                        title={editing ? 'Edit SPK Kontraktor' : 'Tambah SPK Kontraktor'}
                        description="SPK digunakan sebagai surat perjanjian pekerjaan dan jadwal pembayaran kontraktor."
                        onSubmit={submit}
                        actions={(
                            <>
                                {editing && <Button type="button" variant="outline" onClick={resetForm}><X size={17} /> Batal Edit</Button>}
                                <Button type="submit" disabled={form.processing || !paymentIsBalanced}>
                                    {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />}
                                    {editing ? 'Simpan Perubahan' : 'Buat SPK'}
                                </Button>
                            </>
                        )}
                    >
                    <FormErrorSummary errors={form.errors} />
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Kontraktor</span><Dropdown value={form.data.kontraktor_id} label="Pilih Kontraktor" options={options.kontraktors} onChange={(value) => form.setData('kontraktor_id', value)} />{form.errors.kontraktor_id && <span className="text-xs font-bold text-red-600">{form.errors.kontraktor_id}</span>}</div>
                        <Input label="Judul Pekerjaan" value={form.data.judul_pekerjaan} error={form.errors.judul_pekerjaan} onChange={(event) => form.setData('judul_pekerjaan', event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Jenis Pekerjaan</span><Dropdown value={form.data.jenis_pekerjaan} options={options.jenisPekerjaan} onChange={(value) => form.setData('jenis_pekerjaan', value)} /></div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-4">
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Approval SPK</span>
                            <Dropdown value={form.data.approval_role} options={options.approvalRoles} onChange={(value) => form.setData('approval_role', value)} />
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-4">
                        <Input label="Tanggal SPK" type="date" value={form.data.tanggal_spk} error={form.errors.tanggal_spk} onChange={(event) => form.setData('tanggal_spk', event.target.value)} />
                        <Input label="Tanggal Mulai" type="date" value={form.data.tanggal_mulai} error={form.errors.tanggal_mulai} onChange={(event) => form.setData('tanggal_mulai', event.target.value)} />
                        <Input label="Tanggal Selesai" type="date" value={form.data.tanggal_selesai} error={form.errors.tanggal_selesai} onChange={(event) => form.setData('tanggal_selesai', event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Status SPK</span><Dropdown value={form.data.status} options={options.status} onChange={(value) => form.setData('status', value)} /></div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown value={form.data.perumahan_id} label="Pilih Perumahan" options={options.perumahans} onChange={(value) => form.setData({ ...form.data, perumahan_id: value, detail_rumah_id: '' })} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Unit Rumah</span><Dropdown value={form.data.detail_rumah_id} label="Opsional" options={detailRumahOptions} onChange={(value, selected) => form.setData({ ...form.data, detail_rumah_id: value, perumahan_id: selected?.perumahan_id ?? form.data.perumahan_id })} /></div>
                        <CurrencyInput label="Nilai Dasar Kontrak" value={form.data.nilai_kontrak_dasar} error={form.errors.nilai_kontrak_dasar} onChange={setNilaiDasarKontrak} />
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg border border-silver-deep/70 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Tambahan Pekerjaan</p>
                            <p className="mt-1 text-sm text-ink-soft dark:text-white/60">Penambahan lahan, pekerjaan tambahan, atau item lain akan otomatis masuk ke total pengajuan kredit.</p>
                        </div>
                        <div className="rounded-lg border border-silver-deep/70 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Total Penambahan</p>
                            <p className="mt-2 text-2xl font-extrabold">{money(additionsTotal)}</p>
                        </div>
                        <div className="rounded-lg border border-silver-deep/70 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Total Pengajuan Kredit</p>
                            <p className="mt-2 text-2xl font-extrabold">{money(totalKontrak)}</p>
                        </div>
                    </div>
                    <Textarea label="Lingkup Pekerjaan" value={form.data.lingkup_pekerjaan} error={form.errors.lingkup_pekerjaan} onChange={(event) => form.setData('lingkup_pekerjaan', event.target.value)} />

                    <div className="grid gap-4 rounded-lg border border-silver-deep/70 p-4 dark:border-white/10">
                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <p className="text-sm font-extrabold">Tambahan Pekerjaan / Penambahan Lahan</p>
                                <p className="text-xs text-ink-soft dark:text-white/60">Tambahkan item baru jika ada perluasan lahan atau pekerjaan tambahan lain.</p>
                            </div>
                            <Button type="button" variant="outline" onClick={() => form.setData('additions', [...form.data.additions, additionTemplate()])}>
                                <PlusCircle size={16} /> Tambah Penambahan
                            </Button>
                        </div>

                        {form.data.additions.map((addition, index) => (
                            <div className="grid gap-3 rounded-lg bg-silver-soft/80 p-4 dark:bg-white/5 md:grid-cols-2 xl:grid-cols-6" key={index}>
                                <div className="grid gap-2">
                                    <span className="text-sm font-extrabold">Kategori</span>
                                    <Dropdown value={addition.kategori_penambahan} options={options.kategoriPenambahan} onChange={(value) => setAddition(index, 'kategori_penambahan', value)} />
                                </div>
                                <Input label="Judul Penambahan" value={addition.judul_penambahan} onChange={(event) => setAddition(index, 'judul_penambahan', event.target.value)} />
                                <Input label="Volume" value={addition.volume} onChange={(event) => setAddition(index, 'volume', event.target.value)} />
                                <Input label="Satuan" value={addition.satuan} onChange={(event) => setAddition(index, 'satuan', event.target.value)} />
                                <CurrencyInput label="Harga Satuan" value={addition.harga_satuan} onChange={(value) => setAddition(index, 'harga_satuan', value)} />
                                <CurrencyInput label="Total" value={addition.total || (Number(addition.volume || 0) * Number(addition.harga_satuan || 0))} readOnly />
                                <Input className="xl:col-span-3" label="Deskripsi" value={addition.deskripsi} onChange={(event) => setAddition(index, 'deskripsi', event.target.value)} />
                                <Textarea className="xl:col-span-2" label="Keterangan" value={addition.keterangan} onChange={(event) => setAddition(index, 'keterangan', event.target.value)} />
                                <div className="flex items-end justify-end xl:col-span-1">
                                    <Button type="button" variant="ghost" size="sm" className="text-red-600" disabled={form.data.additions.length === 1} onClick={() => form.setData('additions', form.data.additions.filter((_, additionIndex) => additionIndex !== index))}>
                                        <MinusCircle size={16} />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="grid gap-3 rounded-lg border border-silver-deep/70 p-4 dark:border-white/10">
                        <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                            <div className="grid gap-2 md:w-64">
                                <span className="text-sm font-extrabold">Metode Pembayaran</span>
                                <Dropdown value={form.data.metode_pembayaran} options={options.metodePembayaran} onChange={setMetodePembayaran} />
                                <p className="text-xs text-ink-soft dark:text-white/60">
                                    Cash / Sekaligus = pembayaran 1 kali. Cicil / Termin = pembayaran bertahap dengan jatuh tempo tiap termin.
                                </p>
                            </div>
                            {form.data.metode_pembayaran === 'cicil' && <Button type="button" variant="outline" onClick={() => form.setData('payments', [...form.data.payments, { ...paymentTemplate(), tanggal_jatuh_tempo: form.data.tanggal_spk }])}><PlusCircle size={16} /> Tambah Termin</Button>}
                        </div>

                        {form.data.payments.map((payment, index) => (
                            <div className="grid gap-3 rounded-lg bg-silver-soft/80 p-3 dark:bg-white/5 md:grid-cols-[0.4fr_0.8fr_0.9fr_1fr_auto]" key={index}>
                                <Input label="Termin" value={index + 1} readOnly />
                                <Input label="Jatuh Tempo" type="date" value={payment.tanggal_jatuh_tempo} onChange={(event) => setPayment(index, 'tanggal_jatuh_tempo', event.target.value)} />
                                <Input label="Tanggal Bayar" type="date" value={payment.tanggal_pembayaran} onChange={(event) => setPayment(index, 'tanggal_pembayaran', event.target.value)} />
                                <CurrencyInput label="Nominal" value={payment.nominal} onChange={(value) => setPayment(index, 'nominal', value)} readOnly={form.data.metode_pembayaran === 'cash'} />
                                <Input label="Keterangan" value={payment.keterangan} onChange={(event) => setPayment(index, 'keterangan', event.target.value)} />
                                <div className="flex items-end justify-end"><Button type="button" variant="ghost" size="sm" className="text-red-600" disabled={form.data.metode_pembayaran === 'cash' || form.data.payments.length === 1} onClick={() => form.setData('payments', form.data.payments.filter((_, paymentIndex) => paymentIndex !== index))}><MinusCircle size={16} /></Button></div>
                            </div>
                        ))}
                        <div className="grid gap-1 text-right text-sm font-extrabold">
                            <span className="text-ink-soft">Total jadwal pembayaran: {money(form.data.metode_pembayaran === 'cash' ? totalKontrak : totalPayment)}</span>
                            {form.data.metode_pembayaran === 'cicil' && (
                                <span className={paymentIsBalanced ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300'}>
                                    {paymentIsBalanced ? 'Total termin sudah sesuai total pengajuan kredit.' : `Selisih termin: ${money(Math.abs(paymentDifference))} ${paymentDifference > 0 ? 'kurang' : 'lebih'}.`}
                                </span>
                            )}
                            {form.errors.payments && <span className="text-red-600 dark:text-red-300">{form.errors.payments}</span>}
                        </div>
                    </div>

                    <Textarea label="Catatan SPK" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                    </Form>
                    )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between" onSubmit={(event) => { event.preventDefault(); router.get(pageUrl, { search }, { preserveScroll: true, preserveState: true, replace: true }); }}>
                        <Input className="md:max-w-md" label="Search" value={search} placeholder="Cari nomor SPK, pekerjaan, kontraktor..." onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>{['Nomor SPK', 'Kontraktor', 'Pekerjaan', 'Lokasi', 'Nilai Dasar', 'Tambahan', 'Total', 'Metode', 'Approval', 'Jadwal Pembayaran', 'Status SPK', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-extrabold">{row.nomor_spk}</td>
                                        <td className="px-5 py-4 font-semibold">{row.kontraktor}</td>
                                        <td className="px-5 py-4">{row.judul_pekerjaan}</td>
                                        <td className="px-5 py-4">{row.perumahan} / {row.unit}</td>
                                        <td className="px-5 py-4 font-extrabold">{money(row.nilai_kontrak_dasar)}</td>
                                        <td className="px-5 py-4 font-extrabold">{money(row.total_penambahan)}</td>
                                        <td className="px-5 py-4 font-extrabold">{money(row.nilai_kontrak)}</td>
                                        <td className="px-5 py-4 font-bold">{row.metode_pembayaran === 'cash' ? 'Cash / Sekaligus' : 'Cicil / Termin'}</td>
                                        <td className="px-5 py-4 font-bold">{row.approval_role === 'admin' ? 'Admin' : 'Manager'}</td>
                                        <td className="px-5 py-4">
                                            <div className="grid min-w-[320px] gap-2">
                                                {row.payments.map((payment) => (
                                                    <div className="rounded-lg border border-silver-deep/60 p-2 dark:border-white/10" key={`${row.id}-${payment.termin_ke}`}>
                                                        <p className="font-extrabold">Termin {payment.termin_ke}: {money(payment.nominal)}</p>
                                                        <p className="text-xs font-bold text-ink-soft">Jatuh tempo: {payment.tanggal_jatuh_tempo ?? '-'} | Bayar: {payment.tanggal_pembayaran ?? '-'} | {payment.status_label}</p>
                                                        <div className="mt-2 flex flex-wrap gap-2">
                                                            {payment.status === 'menunggu_approval_manager' && <Button type="button" size="sm" variant="outline" onClick={() => postPaymentAction(row, payment, 'approve')}>Approve Manager</Button>}
                                                            {payment.status === 'menunggu_pencairan_owner' && <Button type="button" size="sm" onClick={() => postPaymentAction(row, payment, 'release')}>Cairkan Owner</Button>}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 font-bold">{row.status}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                {!approvalOnly && (
                                                    <>
                                                        {row.can_edit && <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={15} /> Edit</Button>}
                                                        {row.record_status === 'locked'
                                                            ? <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true })}>Unlock</Button>
                                                            : <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true })}>Lock</Button>}
                                                        {row.can_delete && <Button type="button" size="sm" variant="outline" onClick={() => destroyRow(row)}><Trash2 size={15} /> Hapus</Button>}
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={12}>Belum ada SPK kontraktor.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'SPK Kontraktor'}>{page}</AdminLayout>;
