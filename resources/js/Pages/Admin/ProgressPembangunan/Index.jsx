import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Edit3, Eye, LoaderCircle, LockKeyhole, Save, Search, Trash2, Unlock, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, Dropdown, Form, Input, Modal, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

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

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

export default function Index({ title, description, baseUrl, rows, filters = {}, options }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [filterPerumahan, setFilterPerumahan] = useState(filters.perumahan_id ?? '');
    const [filterUnit, setFilterUnit] = useState(filters.detail_rumah_id ?? '');
    const [filterTahapan, setFilterTahapan] = useState(filters.tahapan_pembangunan_id ?? '');
    const [editing, setEditing] = useState(null);
    const [detail, setDetail] = useState(null);
    const { auth } = usePage().props;
    const roles = auth?.user?.roles ?? [];
    const canManageLock = roles.some((role) => ['owner', 'super_admin'].includes(role));
    const form = useForm({
        perumahan_id: '',
        detail_rumah_id: '',
        tahapan_pembangunan_id: '',
        site_schedule_id: '',
        nama_progress: '',
        tanggal: new Date().toISOString().slice(0, 10),
        persentase: '',
        keterangan: '',
        foto: null,
    });

    const detailRumahOptions = useMemo(() => {
        if (!form.data.perumahan_id) {
            return options.detailRumahs;
        }

        return options.detailRumahs.filter((item) => item.perumahan_id === String(form.data.perumahan_id));
    }, [form.data.perumahan_id, options.detailRumahs]);
    const scheduleOptions = useMemo(() => (options.siteSchedules ?? []).filter((item) => {
        if (form.data.detail_rumah_id && item.detail_rumah_id !== String(form.data.detail_rumah_id)) {
            return false;
        }
        if (form.data.tahapan_pembangunan_id && item.tahapan_pembangunan_id !== String(form.data.tahapan_pembangunan_id)) {
            return false;
        }
        return true;
    }), [form.data.detail_rumah_id, form.data.tahapan_pembangunan_id, options.siteSchedules]);

    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        form.setData('tanggal', new Date().toISOString().slice(0, 10));
    };

    const editRow = (row) => {
        setEditing(row);
        form.setData({
            perumahan_id: row.perumahan_id ?? '',
            detail_rumah_id: row.detail_rumah_id ?? '',
            tahapan_pembangunan_id: row.tahapan_pembangunan_id ?? '',
            site_schedule_id: row.site_schedule_id ?? '',
            nama_progress: row.nama_progress ?? '',
            tanggal: row.tanggal ?? new Date().toISOString().slice(0, 10),
            persentase: row.persentase ?? '',
            keterangan: row.keterangan ?? '',
            foto: null,
        });
    };

    const destroyRow = (row) => {
        if (!window.confirm('Hapus progress pembangunan ini?')) {
            return;
        }

        router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    const submit = (event) => {
        event.preventDefault();

        const optionsSubmit = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: resetForm,
        };

        if (editing) {
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`${baseUrl}/${editing.id}`, optionsSubmit);
            return;
        }

        form.transform((data) => data);
        form.post(baseUrl, optionsSubmit);
    };

    const approveRow = (row) => {
        router.post(`${baseUrl}/${row.id}/approve`, {}, { preserveScroll: true });
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

                <Form
                    collapsible
                    title="Tambah Progress Pembangunan"
                    description="Pengawas mengisi progress lapangan dan bukti foto. Manager lalu menyetujui sebelum progress dihitung ke unit."
                    onSubmit={submit}
                    actions={(
                        <>
                            {editing && (
                                <Button type="button" variant="outline" onClick={resetForm}>
                                    <X size={17} />
                                    Batal Edit
                                </Button>
                            )}
                            <Button type="submit" disabled={form.processing}>
                            {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />}
                                {editing ? 'Simpan Perubahan' : 'Kirim Progress'}
                            </Button>
                        </>
                    )}
                >
                    <FormErrorSummary errors={form.errors} />
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Perumahan</span>
                            <Dropdown
                                value={form.data.perumahan_id}
                                label="Pilih Perumahan"
                                options={options.perumahans}
                                onChange={(value) => form.setData({ ...form.data, perumahan_id: value, detail_rumah_id: '' })}
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Unit Rumah</span>
                            <Dropdown
                                value={form.data.detail_rumah_id}
                                label="Pilih Unit"
                                options={detailRumahOptions}
                                onChange={(value, selected) => form.setData({
                                    ...form.data,
                                    detail_rumah_id: value,
                                    perumahan_id: selected?.perumahan_id ?? form.data.perumahan_id,
                                    site_schedule_id: '',
                                    nama_progress: '',
                                })}
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Tahapan Pembangunan</span>
                            <Dropdown
                                value={form.data.tahapan_pembangunan_id}
                                label="Pilih Tahapan"
                                options={options.tahapanPembangunans}
                                onChange={(value) => form.setData({ ...form.data, tahapan_pembangunan_id: value, site_schedule_id: '', nama_progress: '' })}
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Nama Progress</span>
                            <Dropdown
                                label={form.data.detail_rumah_id && form.data.tahapan_pembangunan_id ? 'Pilih dari Jadwal Lapangan' : 'Pilih unit dan tahapan dulu'}
                                value={form.data.site_schedule_id}
                                options={scheduleOptions}
                                disabled={!form.data.detail_rumah_id || !form.data.tahapan_pembangunan_id}
                                onChange={(value, selected) => form.setData({
                                    ...form.data,
                                    site_schedule_id: value,
                                    nama_progress: selected?.nama_pekerjaan ?? '',
                                })}
                            />
                            {form.errors.site_schedule_id && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.site_schedule_id}</span>}
                            {form.errors.nama_progress && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.nama_progress}</span>}
                        </div>
                        <Input
                            label="Tanggal"
                            type="date"
                            value={form.data.tanggal}
                            error={form.errors.tanggal}
                            onChange={(event) => form.setData('tanggal', event.target.value)}
                        />
                        <Input
                            label="Progress (%)"
                            type="number"
                            min="0"
                            max="100"
                            value={form.data.persentase}
                            error={form.errors.persentase}
                            onChange={(event) => form.setData('persentase', event.target.value)}
                        />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Bukti Foto</span>
                            <input
                                type="file"
                                accept="image/*"
                                className="min-h-11 w-full rounded-lg border border-silver-deep/70 bg-white/85 px-4 py-2.5 text-sm font-semibold text-ink outline-none file:mr-4 file:rounded-md file:border-0 file:bg-ink file:px-4 file:py-2 file:text-sm file:font-bold file:text-white dark:border-white/10 dark:bg-white/8 dark:text-white"
                                onChange={(event) => form.setData('foto', event.target.files?.[0] ?? null)}
                            />
                            {form.errors.foto && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.foto}</span>}
                        </div>
                    </div>

                    <Textarea
                        label="Keterangan"
                        value={form.data.keterangan}
                        error={form.errors.keterangan}
                        onChange={(event) => form.setData('keterangan', event.target.value)}
                    />
                </Form>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 lg:grid-cols-[1.2fr_1fr_1fr_1fr_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(baseUrl, {
                                search,
                                perumahan_id: filterPerumahan,
                                detail_rumah_id: filterUnit,
                                tahapan_pembangunan_id: filterTahapan,
                            }, { preserveScroll: true, preserveState: true, replace: true });
                        }}
                    >
                        <Input
                            label="Search"
                            value={search}
                            placeholder="Cari perumahan, blok, atau unit..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown value={filterPerumahan} label="Semua Perumahan" options={[{ value: '', label: 'Semua Perumahan' }, ...options.perumahans]} onChange={(value) => { setFilterPerumahan(value); setFilterUnit(''); }} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Unit</span><Dropdown value={filterUnit} label="Semua Unit" options={[{ value: '', label: 'Semua Unit' }, ...options.detailRumahs.filter((item) => !filterPerumahan || item.perumahan_id === String(filterPerumahan))]} onChange={setFilterUnit} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Tahapan</span><Dropdown value={filterTahapan} label="Semua Tahapan" options={[{ value: '', label: 'Semua Tahapan' }, ...options.tahapanPembangunans]} onChange={setFilterTahapan} /></div>
                        <div className="flex items-end"><Button className="w-full" type="submit"><Search size={17} /> Cari</Button></div>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Tanggal', 'Progress', 'Perumahan', 'Unit', 'Tahapan', 'Nilai', 'Approval', 'Audit', 'Aksi'].map((column) => (
                                        <th className="px-5 py-4 font-extrabold" key={column}>
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">{row.tanggal}</td>
                                        <td className="px-5 py-4 font-bold">{row.nama_progress}</td>
                                        <td className="px-5 py-4">{row.perumahan}</td>
                                        <td className="px-5 py-4">{row.unit}</td>
                                        <td className="px-5 py-4">{row.tahapan}</td>
                                        <td className="px-5 py-4 font-bold">{row.persentase}% ({Number(row.persentase_total ?? 0).toFixed(2)}%)</td>
                                        <td className="px-5 py-4 font-bold">{row.approval_label}</td>
                                        <td className="min-w-44 px-5 py-4 text-xs"><span className="font-bold">Dibuat:</span> {row.created_by_name}<br /><span className="font-bold">Diubah:</span> {row.updated_by_name}<br /><span className="font-bold">Approve:</span> {row.approved_by}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                <Button type="button" size="sm" variant="outline" onClick={() => setDetail(row)}><Eye size={15} /> Detail</Button>
                                                {row.can_edit && row.approval_status !== 'approved' && (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}>
                                                        <Edit3 size={15} />
                                                        Edit
                                                    </Button>
                                                )}
                                                {row.can_delete && row.approval_status !== 'approved' && (
                                                    <Button type="button" size="sm" variant="outline" className="text-red-600" onClick={() => destroyRow(row)}>
                                                        <Trash2 size={15} />
                                                        Hapus
                                                    </Button>
                                                )}
                                                {row.can_unlock && row.record_status === 'locked' && (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true })}>
                                                        <Unlock size={15} />
                                                        Unlock
                                                    </Button>
                                                )}
                                                {row.can_lock && (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true })}>
                                                        <LockKeyhole size={15} />
                                                        Lock
                                                    </Button>
                                                )}
                                                {row.can_approve && row.approval_status === 'menunggu_approval_manager' && (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => approveRow(row)}>
                                                        <CheckCircle2 size={15} />
                                                        Approve
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={9}>
                                            Belum ada progress pembangunan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>
            <Modal open={Boolean(detail)} onClose={() => setDetail(null)} title={detail ? `Detail ${detail.nama_progress}` : 'Detail Progress'} footer={<Button type="button" variant="outline" onClick={() => setDetail(null)}>Tutup</Button>}>
                {detail && <div className="grid gap-4 text-sm">
                    <div className="grid gap-3 md:grid-cols-2">
                        <div><p className="text-xs font-bold uppercase text-ink-soft">Tanggal</p><p className="font-extrabold">{detail.tanggal}</p></div>
                        <div><p className="text-xs font-bold uppercase text-ink-soft">Approval</p><p className="font-extrabold">{detail.approval_label}</p></div>
                        <div><p className="text-xs font-bold uppercase text-ink-soft">Lokasi</p><p className="font-extrabold">{detail.perumahan} - {detail.unit}</p></div>
                        <div><p className="text-xs font-bold uppercase text-ink-soft">Tahapan</p><p className="font-extrabold">{detail.tahapan}</p></div>
                        <div><p className="text-xs font-bold uppercase text-ink-soft">Progress Input</p><p className="font-extrabold">{detail.persentase}%</p></div>
                        <div><p className="text-xs font-bold uppercase text-ink-soft">Kontribusi Total</p><p className="font-extrabold">{Number(detail.persentase_total ?? 0).toFixed(2)}%</p></div>
                    </div>
                    <div><p className="text-xs font-bold uppercase text-ink-soft">Keterangan</p><p className="mt-1 rounded-lg border border-silver-deep/60 p-3 dark:border-white/10">{detail.keterangan}</p></div>
                    {detail.foto_url && <a className="font-bold text-emerald-600 underline decoration-dotted underline-offset-4 dark:text-emerald-300" href={detail.foto_url} target="_blank" rel="noreferrer">Lihat Foto</a>}
                </div>}
            </Modal>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Progress Pembangunan'}>{page}</AdminLayout>;
