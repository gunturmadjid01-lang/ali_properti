import { Head, router, useForm } from '@inertiajs/react';
import { CalendarCheck, CheckCircle2, Edit3, Eye, Lock, Search, Trash2, Unlock, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import Pagination from '../../../../Components/Pagination';
import { Button, Dropdown, Form, Input, Modal, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

function ErrorSummary({ errors }) {
    const messages = Object.values(errors ?? {}).filter(Boolean);
    if (!messages.length) return null;
    return <div className="rounded-lg border border-red-300 bg-red-50 p-4 text-sm font-bold text-red-700">{messages.map((message) => <p key={message}>{message}</p>)}</div>;
}

export default function Index({ title, description, baseUrl, rows = { data: [], links: [] }, filters = {}, options = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [filterPerumahan, setFilterPerumahan] = useState(filters.perumahan_id ?? '');
    const [filterUnit, setFilterUnit] = useState(filters.detail_rumah_id ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [editing, setEditing] = useState(null);
    const [detail, setDetail] = useState(null);
    const [statusEditing, setStatusEditing] = useState(null);

    const form = useForm({
        costumer_id: '',
        perumahan_id: '',
        detail_rumah_id: '',
        tanggal_survey: '',
        metode_survey: 'kunjungan_lokasi',
        status: 'dijadwalkan',
        hasil_survey: '',
        catatan: '',
        rencana_follow_up_at: '',
    });

    const statusForm = useForm({
        status: 'dijadwalkan',
        tanggal_survey: '',
        hasil_survey: '',
        catatan: '',
        rencana_follow_up_at: '',
    });

    const perumahans = options.perumahans ?? [];
    const detailRumahs = options.detailRumahs ?? [];
    const unitOptions = useMemo(() => detailRumahs.filter((row) => !form.data.perumahan_id || row.perumahan_id === String(form.data.perumahan_id)), [detailRumahs, form.data.perumahan_id]);
    const filterUnitOptions = useMemo(() => detailRumahs.filter((row) => !filterPerumahan || row.perumahan_id === String(filterPerumahan)), [detailRumahs, filterPerumahan]);
    const statusFilterOptions = [{ value: '', label: 'Semua Status' }, ...(options.statusOptions ?? [])];

    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        form.setData({
            costumer_id: '',
            perumahan_id: '',
            detail_rumah_id: '',
            tanggal_survey: '',
            metode_survey: 'kunjungan_lokasi',
            status: 'dijadwalkan',
            hasil_survey: '',
            catatan: '',
            rencana_follow_up_at: '',
        });
    };

    const editRow = (row) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            costumer_id: row.costumer_id ?? '',
            perumahan_id: row.perumahan_id ?? '',
            detail_rumah_id: row.detail_rumah_id ?? '',
            tanggal_survey: row.tanggal_survey ?? '',
            metode_survey: row.metode_survey ?? 'kunjungan_lokasi',
            status: row.status ?? 'dijadwalkan',
            hasil_survey: row.hasil_survey ?? '',
            catatan: row.catatan ?? '',
            rencana_follow_up_at: row.rencana_follow_up_at ?? '',
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const openStatusModal = (row) => {
        setStatusEditing(row);
        statusForm.clearErrors();
        statusForm.setData({
            status: row.status ?? 'dijadwalkan',
            tanggal_survey: row.tanggal_survey ?? '',
            hasil_survey: row.hasil_survey ?? '',
            catatan: row.catatan ?? '',
            rencana_follow_up_at: row.rencana_follow_up_at ?? '',
        });
    };

    const closeStatusModal = () => {
        setStatusEditing(null);
        statusForm.clearErrors();
    };

    const submitStatus = (event) => {
        event.preventDefault();

        if (!statusEditing) {
            return;
        }

        statusForm.put(`${baseUrl}/${statusEditing.id}/status`, {
            preserveScroll: true,
            onSuccess: closeStatusModal,
        });
    };

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true, onSuccess: resetForm };

        if (editing) {
            form.put(`${baseUrl}/${editing.id}`, requestOptions);
            return;
        }

        form.post(baseUrl, requestOptions);
    };

    const filterRows = (event) => {
        event.preventDefault();
        router.get(baseUrl, {
            search,
            status,
            perumahan_id: filterPerumahan,
            detail_rumah_id: filterUnit,
            date_from: dateFrom,
            date_to: dateTo,
        }, {
            preserveState: true,
            replace: true,
        });
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
                    title={editing ? `Edit ${editing.kode_survey}` : 'Tambah Jadwal Survey'}
                    description="Catat rencana customer datang melihat lokasi/unit, lalu update hasil survey setelah kunjungan selesai."
                    onSubmit={submit}
                    actions={(
                        <>
                            {editing && <Button type="button" variant="outline" onClick={resetForm}><X size={15} /> Batal</Button>}
                            <Button type="submit" disabled={form.processing}><CalendarCheck size={17} /> {editing ? 'Simpan Perubahan' : 'Simpan Survey'}</Button>
                        </>
                    )}
                >
                    <ErrorSummary errors={form.errors} />
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Customer</span>
                            <Dropdown label="Pilih Customer" value={form.data.costumer_id} options={options.customers ?? []} onChange={(value) => form.setData('costumer_id', value)} />
                            {form.errors.costumer_id && <span className="text-xs font-bold text-red-600">{form.errors.costumer_id}</span>}
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Perumahan</span>
                            <Dropdown label="Pilih Perumahan" value={form.data.perumahan_id} options={perumahans} onChange={(value) => form.setData({ ...form.data, perumahan_id: value, detail_rumah_id: '' })} />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Unit Diminati</span>
                            <Dropdown label="Opsional" value={form.data.detail_rumah_id} options={unitOptions} onChange={(_, selected) => form.setData({ ...form.data, detail_rumah_id: selected?.value ?? '', perumahan_id: selected?.perumahan_id ?? form.data.perumahan_id })} />
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Input label="Tanggal Survey" type="datetime-local" value={form.data.tanggal_survey} error={form.errors.tanggal_survey} onChange={(event) => form.setData('tanggal_survey', event.target.value)} />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Metode Survey</span>
                            <Dropdown label="Pilih Metode" value={form.data.metode_survey} options={options.methodOptions ?? []} onChange={(value) => form.setData('metode_survey', value)} />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Status</span>
                            <Dropdown label="Pilih Status" value={form.data.status} options={options.statusOptions ?? []} onChange={(value) => form.setData('status', value)} />
                        </div>
                    </div>
                    <p className="rounded-lg bg-silver-soft px-4 py-3 text-sm font-bold text-ink-soft dark:bg-white/6 dark:text-white/60">
                        Marketing pendamping otomatis menggunakan user yang membuat jadwal survey.
                    </p>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Textarea label="Hasil Survey" value={form.data.hasil_survey} error={form.errors.hasil_survey} onChange={(event) => form.setData('hasil_survey', event.target.value)} />
                        <Textarea label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                    </div>
                    <Input label="Rencana Follow Up Berikutnya" type="datetime-local" value={form.data.rencana_follow_up_at} error={form.errors.rencana_follow_up_at} onChange={(event) => form.setData('rencana_follow_up_at', event.target.value)} />
                </Form>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="grid gap-3 p-5 xl:grid-cols-[1.2fr_1fr_1fr_1fr_150px_150px_auto]" onSubmit={filterRows}>
                        <Input label="Cari Survey / Customer" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Status</span><Dropdown value={status} label="Semua Status" options={statusFilterOptions} onChange={setStatus} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown value={filterPerumahan} label="Semua Perumahan" options={[{ value: '', label: 'Semua Perumahan' }, ...perumahans]} onChange={(value) => { setFilterPerumahan(value); setFilterUnit(''); }} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Unit</span><Dropdown value={filterUnit} label="Semua Unit" options={[{ value: '', label: 'Semua Unit' }, ...filterUnitOptions]} onChange={setFilterUnit} /></div>
                        <Input label="Dari" type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
                        <Input label="Sampai" type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
                        <div className="flex items-end"><Button type="submit"><Search size={16} /> Cari</Button></div>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                <tr>{['Kode', 'Tanggal', 'Customer', 'Lokasi', 'Marketing', 'Status', 'Follow Up', 'Audit', 'Aksi'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">{row.kode_survey}</td>
                                        <td className="px-5 py-4">{row.tanggal_survey_display}</td>
                                        <td className="px-5 py-4 font-bold">{row.customer}<br /><span className="text-xs font-semibold text-ink-soft">{row.telepon}</span></td>
                                        <td className="px-5 py-4">{row.perumahan}<br /><span className="text-xs font-semibold text-ink-soft">{row.unit}</span></td>
                                        <td className="px-5 py-4">{row.marketing}</td>
                                        <td className="px-5 py-4 font-bold">{row.status_label}</td>
                                        <td className="px-5 py-4">{row.rencana_follow_up_display || '-'}</td>
                                        <td className="min-w-44 px-5 py-4 text-xs"><span className="font-bold">Dibuat:</span> {row.created_by_name}<br /><span className="font-bold">Diubah:</span> {row.updated_by_name}<br /><span className="font-bold">Lock:</span> {row.record_status}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                <Button type="button" size="sm" variant="outline" onClick={() => setDetail(row)}><Eye size={14} /> Detail</Button>
                                                <Button type="button" size="sm" variant="outline" onClick={() => openStatusModal(row)}><CheckCircle2 size={14} /> Update Status</Button>
                                                {row.can_edit && <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={14} /> Edit</Button>}
                                                {row.can_delete && <Button type="button" size="sm" variant="outline" className="text-red-600" onClick={() => window.confirm('Hapus jadwal survey ini?') && router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true })}><Trash2 size={14} /></Button>}
                                                {row.can_lock && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true })}><Lock size={14} /> Lock</Button>}
                                                {row.can_unlock && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true })}><Unlock size={14} /> Unlock</Button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={9}>Belum ada jadwal survey.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

            <Modal open={Boolean(detail)} onClose={() => setDetail(null)} title={detail ? `Detail ${detail.kode_survey}` : 'Detail Survey'} footer={<Button type="button" variant="outline" onClick={() => setDetail(null)}>Tutup</Button>}>
                {detail && (
                    <div className="grid gap-3 text-sm">
                        <p><b>Customer:</b> {detail.customer} ({detail.kode_customer})</p>
                        <p><b>Telepon:</b> {detail.telepon}</p>
                        <p><b>Jadwal:</b> {detail.tanggal_survey_display}</p>
                        <p><b>Lokasi:</b> {detail.perumahan} - {detail.unit}</p>
                        <p><b>Metode:</b> {detail.metode_survey_label}</p>
                        <p><b>Status:</b> {detail.status_label}</p>
                        <p><b>Marketing:</b> {detail.marketing}</p>
                        <p><b>Hasil:</b> {detail.hasil_survey || '-'}</p>
                        <p><b>Catatan:</b> {detail.catatan || '-'}</p>
                        <p><b>Follow Up Berikutnya:</b> {detail.rencana_follow_up_display || '-'}</p>
                    </div>
                )}
            </Modal>

            <Modal
                open={Boolean(statusEditing)}
                onClose={closeStatusModal}
                title={statusEditing ? `Update Status ${statusEditing.kode_survey}` : 'Update Status Survey'}
                footer={(
                    <>
                        <Button type="button" variant="outline" onClick={closeStatusModal}>Batal</Button>
                        <Button type="button" disabled={statusForm.processing} onClick={submitStatus}>
                            <CheckCircle2 size={16} /> {statusForm.processing ? 'Menyimpan...' : 'Simpan Status'}
                        </Button>
                    </>
                )}
            >
                <form className="grid gap-4" onSubmit={submitStatus}>
                    <ErrorSummary errors={statusForm.errors} />
                    {statusEditing && (
                        <div className="rounded-lg border border-silver-deep/70 bg-silver-soft p-4 text-sm dark:border-white/10 dark:bg-white/6">
                            <p className="font-extrabold">{statusEditing.customer}</p>
                            <p className="mt-1 text-ink-soft dark:text-white/60">{statusEditing.tanggal_survey_display} - {statusEditing.perumahan} - {statusEditing.unit}</p>
                            <p className="mt-1 text-xs font-bold text-ink-soft dark:text-white/45">Lock jadwal: {statusEditing.record_status}</p>
                        </div>
                    )}
                    <div className="grid gap-2">
                        <span className="text-sm font-extrabold">Status Survey</span>
                        <Dropdown label="Pilih Status" value={statusForm.data.status} options={options.statusOptions ?? []} onChange={(value) => statusForm.setData('status', value)} />
                        {statusForm.errors.status && <span className="text-xs font-bold text-red-600">{statusForm.errors.status}</span>}
                    </div>
                    {statusForm.data.status === 'reschedule' && (
                        <Input
                            label="Tanggal dan Jam Survey Baru"
                            type="datetime-local"
                            value={statusForm.data.tanggal_survey}
                            error={statusForm.errors.tanggal_survey}
                            onChange={(event) => statusForm.setData('tanggal_survey', event.target.value)}
                        />
                    )}
                    <Textarea label="Hasil Survey" value={statusForm.data.hasil_survey} error={statusForm.errors.hasil_survey} onChange={(event) => statusForm.setData('hasil_survey', event.target.value)} />
                    <Textarea label="Catatan" value={statusForm.data.catatan} error={statusForm.errors.catatan} onChange={(event) => statusForm.setData('catatan', event.target.value)} />
                    <Input label="Rencana Follow Up Berikutnya" type="datetime-local" value={statusForm.data.rencana_follow_up_at} error={statusForm.errors.rencana_follow_up_at} onChange={(event) => statusForm.setData('rencana_follow_up_at', event.target.value)} />
                </form>
            </Modal>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Jadwal Survey'}>{page}</AdminLayout>;
