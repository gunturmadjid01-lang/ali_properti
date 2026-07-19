import { Head, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Save, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button, Dropdown, Form, Input, Modal, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const money = (value) => `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
const number = (value) => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });

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

export default function Create({ title, description, baseUrl, indexUrl, options = {}, permissions = {} }) {
    const [materialRequestModalOpen, setMaterialRequestModalOpen] = useState(false);
    const canCreate = permissions.canCreate ?? false;
    const form = useForm({
        perumahan_id: '',
        detail_rumah_id: '',
        site_schedule_id: '',
        schedule_stage_key: '',
        schedule_item_key: '',
        tanggal: new Date().toISOString().slice(0, 10),
        persentase: '',
        keterangan: '',
        foto: null,
        site_report: {
            cuaca: '',
            jumlah_pekerja: '',
            kontraktor: '',
            pekerjaan_selesai: '',
            pekerjaan_tertahan: '',
            kendala: '',
            koordinasi: '',
            rencana_berikutnya: '',
            lampiran: null,
        },
        material_request_ids: [],
        material_usage_items: [],
    });

    const detailRumahOptions = useMemo(() => {
        if (!form.data.perumahan_id) {
            return options.detailRumahs ?? [];
        }

        return (options.detailRumahs ?? []).filter((item) => item.perumahan_id === String(form.data.perumahan_id));
    }, [form.data.perumahan_id, options.detailRumahs]);
    const scheduleOptions = useMemo(() => (options.siteSchedules ?? []).filter((item) => {
        if (form.data.perumahan_id && item.perumahan_id !== String(form.data.perumahan_id)) {
            return false;
        }
        if (form.data.detail_rumah_id) {
            if (item.detail_rumah_id !== String(form.data.detail_rumah_id)) {
                return false;
            }
        } else if (item.detail_rumah_id) {
            return false;
        }
        return true;
    }), [form.data.detail_rumah_id, form.data.perumahan_id, options.siteSchedules]);
    const selectedSchedule = useMemo(
        () => scheduleOptions.find((item) => item.value === String(form.data.site_schedule_id)),
        [form.data.site_schedule_id, scheduleOptions],
    );
    const scheduleStageOptions = selectedSchedule?.stages ?? [];
    const selectedStage = useMemo(
        () => scheduleStageOptions.find((item) => item.value === String(form.data.schedule_stage_key)),
        [form.data.schedule_stage_key, scheduleStageOptions],
    );
    const scheduleItemOptions = selectedStage?.items ?? [];
    const approvedMaterialRequestOptions = useMemo(() => (options.approvedMaterialRequests ?? []).filter((item) => {
        if (form.data.perumahan_id && item.perumahan_id !== String(form.data.perumahan_id)) {
            return false;
        }
        if (form.data.detail_rumah_id) {
            return item.detail_rumah_id === String(form.data.detail_rumah_id);
        }
        return !item.detail_rumah_id;
    }), [form.data.detail_rumah_id, form.data.perumahan_id, options.approvedMaterialRequests]);
    const selectedMaterialRequestRows = useMemo(() => approvedMaterialRequestOptions.filter((item) => (form.data.material_request_ids ?? []).includes(item.value)), [approvedMaterialRequestOptions, form.data.material_request_ids]);
    const hppItemOptions = useMemo(() => (options.hppItems ?? []).filter((item) => {
        if (!form.data.detail_rumah_id) {
            return false;
        }

        return item.detail_rumah_id === String(form.data.detail_rumah_id);
    }), [form.data.detail_rumah_id, options.hppItems]);
    const materialUsageRows = form.data.material_usage_items ?? [];

    useEffect(() => {
        if (!form.data.schedule_stage_key) {
            return;
        }

        const valid = scheduleStageOptions.some((option) => option.value === String(form.data.schedule_stage_key));

        if (!valid) {
            form.setData('schedule_stage_key', '');
            form.setData('schedule_item_key', '');
            form.setData('material_request_ids', []);
        }
    }, [form, form.data.schedule_stage_key, scheduleStageOptions]);

    useEffect(() => {
        if (!form.data.schedule_item_key) {
            return;
        }

        const valid = scheduleItemOptions.some((option) => option.value === String(form.data.schedule_item_key));

        if (!valid) {
            form.setData('schedule_item_key', '');
            form.setData('material_request_ids', []);
        }
    }, [form, form.data.schedule_item_key, scheduleItemOptions]);

    useEffect(() => {
        const allowed = new Set(approvedMaterialRequestOptions.map((item) => item.value));
        const current = form.data.material_request_ids ?? [];
        const filtered = current.filter((id) => allowed.has(id));

        if (filtered.length !== current.length) {
            form.setData('material_request_ids', filtered);
        }
    }, [approvedMaterialRequestOptions, form, form.data.material_request_ids]);

    useEffect(() => {
        const selectedDetails = selectedMaterialRequestRows.flatMap((request) => (request.details ?? []).map((detail) => ({
            material_request_id: request.value,
            material_request_detail_id: detail.id,
            barang_material_id: detail.barang_material_id,
            nama_barang: detail.nama_barang,
            kode_barang: detail.kode_barang,
            qty_issued: detail.qty_issued,
            satuan: detail.satuan,
            harga_hpp: detail.harga_hpp,
        })));
        const existing = new Map((form.data.material_usage_items ?? []).map((item) => [String(item.material_request_detail_id), item]));
        const next = selectedDetails.map((detail) => ({
            ...detail,
            qty: existing.get(String(detail.material_request_detail_id))?.qty ?? '',
            detail_rumah_hpp_item_id: existing.get(String(detail.material_request_detail_id))?.detail_rumah_hpp_item_id ?? '',
        }));

        const currentKeys = (form.data.material_usage_items ?? []).map((item) => String(item.material_request_detail_id)).join('|');
        const nextKeys = next.map((item) => String(item.material_request_detail_id)).join('|');

        if (currentKeys !== nextKeys) {
            form.setData('material_usage_items', next);
        }
    }, [selectedMaterialRequestRows, form, form.data.material_usage_items]);

    const resetForm = () => {
        form.reset();
        form.clearErrors();
        form.setData('tanggal', new Date().toISOString().slice(0, 10));
        form.setData('material_request_ids', []);
        form.setData('material_usage_items', []);
        form.setData('site_report', {
            cuaca: '',
            jumlah_pekerja: '',
            kontraktor: '',
            pekerjaan_selesai: '',
            pekerjaan_tertahan: '',
            kendala: '',
            koordinasi: '',
            rencana_berikutnya: '',
            lampiran: null,
        });
    };

    const clearMaterialRequests = () => {
        form.setData({ ...form.data, material_request_ids: [], material_usage_items: [] });
    };

    const setMaterialUsageItem = (index, key, value) => {
        const next = [...(form.data.material_usage_items ?? [])];
        next[index] = { ...next[index], [key]: value };
        form.setData('material_usage_items', next);
    };

    const setSiteReport = (key, value) => {
        form.setData('site_report', {
            ...(form.data.site_report ?? {}),
            [key]: value,
        });
    };

    const toggleMaterialRequest = (value) => {
        const current = form.data.material_request_ids ?? [];
        const next = current.includes(value)
            ? current.filter((item) => item !== value)
            : [...current, value];

        form.setData('material_request_ids', next);
    };

    const submit = (event) => {
        event.preventDefault();

        form.post(baseUrl, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: resetForm,
        });
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

                {canCreate ? (
                    <Form
                        collapsible
                        title="Form Kemajuan Pembangunan"
                        description="Isi progress lapangan per tahap, pilih jadwal kerja yang sesuai, lalu kaitkan permintaan material yang sudah keluar dari gudang bila memang ada pemakaian."
                        onSubmit={submit}
                        actions={(
                            <>
                                <Button type="button" variant="outline" onClick={() => router.visit(indexUrl)}>
                                    <X size={17} />
                                    Kembali
                                </Button>
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />}
                                    Simpan Progress
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
                                    options={options.perumahans ?? []}
                                    onChange={(value) => form.setData({
                                        ...form.data,
                                        perumahan_id: value,
                                        detail_rumah_id: '',
                                        site_schedule_id: '',
                                        schedule_stage_key: '',
                                        schedule_item_key: '',
                                        material_request_ids: [],
                                        material_usage_items: [],
                                    })}
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
                                        schedule_stage_key: '',
                                        schedule_item_key: '',
                                        material_request_ids: [],
                                        material_usage_items: [],
                                    })}
                                />
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">Jadwal Kerja</span>
                                <Dropdown
                                    label={form.data.perumahan_id ? 'Pilih Jadwal Lapangan' : 'Pilih perumahan dulu'}
                                    value={form.data.site_schedule_id}
                                    options={scheduleOptions}
                                    disabled={!form.data.perumahan_id}
                                    onChange={(value) => form.setData({
                                        ...form.data,
                                        site_schedule_id: value,
                                        schedule_stage_key: '',
                                        schedule_item_key: '',
                                        material_request_ids: [],
                                        material_usage_items: [],
                                    })}
                                />
                                {form.errors.site_schedule_id && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.site_schedule_id}</span>}
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">Tahap Jadwal Kerja</span>
                                <Dropdown
                                    label={form.data.site_schedule_id ? 'Pilih tahap' : 'Pilih jadwal kerja dulu'}
                                    value={form.data.schedule_stage_key}
                                    options={scheduleStageOptions}
                                    disabled={!form.data.site_schedule_id}
                                    onChange={(value, selected) => form.setData({
                                        ...form.data,
                                        schedule_stage_key: value,
                                        schedule_item_key: '',
                                        material_request_ids: [],
                                        material_usage_items: [],
                                    })}
                                />
                                {form.errors.schedule_stage_key && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.schedule_stage_key}</span>}
                                <span className="text-xs font-semibold text-ink-soft dark:text-white/45">
                                    Pilih tahap dari jadwal, lalu pilih item pekerjaan di bawahnya.
                                </span>
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">Item Pekerjaan</span>
                                <Dropdown
                                    label={form.data.schedule_stage_key ? 'Pilih item dari tahap' : 'Pilih tahap dulu'}
                                    value={form.data.schedule_item_key}
                                    options={scheduleItemOptions}
                                    disabled={!form.data.schedule_stage_key}
                                    onChange={(value) => form.setData({
                                        ...form.data,
                                        schedule_item_key: value,
                                        material_request_ids: [],
                                        material_usage_items: [],
                                    })}
                                />
                                {form.errors.schedule_item_key && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.schedule_item_key}</span>}
                                <span className="text-xs font-semibold text-ink-soft dark:text-white/45">
                                    Progress (%) akan menaikkan realisasi item pekerjaan ini setelah disetujui.
                                </span>
                            </div>
                            <Input
                                label="Tanggal"
                                type="date"
                                value={form.data.tanggal}
                                error={form.errors.tanggal}
                                onChange={(event) => form.setData('tanggal', event.target.value)}
                            />
                            <Input
                                label="Kemajuan (%)"
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

                        <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p className="text-sm font-extrabold">Pemakaian Material</p>
                                    <p className="text-xs text-ink-soft dark:text-white/50">
                                        Pilih permintaan material yang sudah disetujui dan barangnya sudah keluar dari gudang untuk unit ini. Realisasi HPP naik dari pemakaian materialnya.
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setMaterialRequestModalOpen(true)}
                                        disabled={!form.data.perumahan_id || !form.data.site_schedule_id || !form.data.schedule_item_key || approvedMaterialRequestOptions.length === 0}
                                    >
                                        <Search size={16} />
                                        Pilih Permintaan
                                    </Button>
                                    {selectedMaterialRequestRows.length > 0 && (
                                        <Button type="button" variant="ghost" onClick={clearMaterialRequests}>
                                            Kosongkan
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {selectedMaterialRequestRows.length > 0 ? selectedMaterialRequestRows.map((item) => (
                                    <span key={item.value} className="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-200">
                                        {item.label}
                                    </span>
                                )) : (
                                    <span className="text-xs font-semibold text-ink-soft dark:text-white/45">
                                        Belum ada permintaan material yang dipilih.
                                    </span>
                                )}
                            </div>
                            {form.errors.material_request_ids && <span className="mt-2 block text-xs font-bold text-red-600 dark:text-red-300">{form.errors.material_request_ids}</span>}
                            {form.errors.material_usage_items && <span className="mt-2 block text-xs font-bold text-red-600 dark:text-red-300">{form.errors.material_usage_items}</span>}
                            {materialUsageRows.length > 0 && (
                                <div className="mt-4 overflow-hidden rounded-lg border border-silver-deep/60 dark:border-white/10">
                                    <div className="max-h-[420px] overflow-auto">
                                        <table className="min-w-[1180px] divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                                            <thead className="sticky top-0 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-graphite dark:text-white/55">
                                                <tr>
                                                    {['Material', 'Keluar', 'Jml Pemakaian', 'Item HPP / RAB Unit', 'RAB', 'Realisasi', 'Sisa', 'Kemajuan'].map((column) => (
                                                        <th className="px-3 py-3 font-extrabold" key={column}>{column}</th>
                                                    ))}
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                                {materialUsageRows.map((row, index) => {
                                                    const hppItem = hppItemOptions.find((item) => item.value === String(row.detail_rumah_hpp_item_id));
                                                    const qty = Number(row.qty || 0);
                                                    const additional = qty * Number(row.harga_hpp || 0);
                                                    const nextRealisasi = Number(hppItem?.realisasi || 0) + additional;
                                                    const rab = Number(hppItem?.jumlah_rab || 0);
                                                    const sisa = Math.max(0, rab - nextRealisasi);
                                                    const progress = rab > 0 ? Math.min(100, (nextRealisasi / rab) * 100) : 0;
                                                    return (
                                                        <tr key={row.material_request_detail_id}>
                                                            <td className="px-3 py-3">
                                                                <div className="font-extrabold">{row.nama_barang}</div>
                                                                <div className="text-[11px] font-semibold text-ink-soft dark:text-white/45">{row.kode_barang || '-'}</div>
                                                            </td>
                                                            <td className="px-3 py-3 font-bold">{number(row.qty_issued)} {row.satuan}</td>
                                                            <td className="px-3 py-3">
                                                                <input
                                                                    type="number"
                                                                    min="0"
                                                                    step="1"
                                                                    max={row.qty_issued}
                                                                    value={row.qty}
                                                                    className="h-10 w-28 rounded-md border border-silver-deep/70 bg-white/90 px-3 text-right text-sm font-bold outline-none dark:border-white/10 dark:bg-white/8"
                                                                    onChange={(event) => setMaterialUsageItem(index, 'qty', event.target.value)}
                                                                />
                                                            </td>
                                                            <td className="min-w-72 px-3 py-3">
                                                                <Dropdown
                                                                    label="Pilih item HPP"
                                                                    value={row.detail_rumah_hpp_item_id}
                                                                    options={hppItemOptions}
                                                                    onChange={(value) => setMaterialUsageItem(index, 'detail_rumah_hpp_item_id', value)}
                                                                />
                                                            </td>
                                                            <td className="px-3 py-3 font-bold">{hppItem ? money(rab) : '-'}</td>
                                                            <td className="px-3 py-3 font-bold text-emerald-700 dark:text-emerald-300">{hppItem ? money(nextRealisasi) : '-'}</td>
                                                            <td className="px-3 py-3 font-bold">{hppItem ? money(sisa) : '-'}</td>
                                                            <td className="px-3 py-3 font-bold">{hppItem ? `${number(progress)}%` : '-'}</td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                            <div>
                                <p className="text-sm font-extrabold">Laporan Lapangan</p>
                                <p className="text-xs text-ink-soft dark:text-white/50">
                                    Laporan ini tersimpan otomatis sebagai bagian dari progress yang sedang diinput.
                                </p>
                            </div>
                            <div className="mt-4 grid gap-4 md:grid-cols-3">
                                <Input
                                    label="Cuaca"
                                    value={form.data.site_report?.cuaca ?? ''}
                                    error={form.errors['site_report.cuaca']}
                                    onChange={(event) => setSiteReport('cuaca', event.target.value)}
                                />
                                <Input
                                    label="Jumlah Pekerja"
                                    type="number"
                                    min="0"
                                    step="1"
                                    value={form.data.site_report?.jumlah_pekerja ?? ''}
                                    error={form.errors['site_report.jumlah_pekerja']}
                                    onChange={(event) => setSiteReport('jumlah_pekerja', event.target.value)}
                                />
                                <Input
                                    label="Kontraktor / Tukang"
                                    value={form.data.site_report?.kontraktor ?? ''}
                                    error={form.errors['site_report.kontraktor']}
                                    onChange={(event) => setSiteReport('kontraktor', event.target.value)}
                                />
                            </div>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <Textarea
                                    label="Pekerjaan Selesai"
                                    value={form.data.site_report?.pekerjaan_selesai ?? ''}
                                    error={form.errors['site_report.pekerjaan_selesai']}
                                    onChange={(event) => setSiteReport('pekerjaan_selesai', event.target.value)}
                                />
                                <Textarea
                                    label="Pekerjaan Tertahan"
                                    value={form.data.site_report?.pekerjaan_tertahan ?? ''}
                                    error={form.errors['site_report.pekerjaan_tertahan']}
                                    onChange={(event) => setSiteReport('pekerjaan_tertahan', event.target.value)}
                                />
                                <Textarea
                                    label="Kendala"
                                    value={form.data.site_report?.kendala ?? ''}
                                    error={form.errors['site_report.kendala']}
                                    onChange={(event) => setSiteReport('kendala', event.target.value)}
                                />
                                <Textarea
                                    label="Koordinasi"
                                    value={form.data.site_report?.koordinasi ?? ''}
                                    error={form.errors['site_report.koordinasi']}
                                    onChange={(event) => setSiteReport('koordinasi', event.target.value)}
                                />
                                <Textarea
                                    label="Rencana Berikutnya"
                                    value={form.data.site_report?.rencana_berikutnya ?? ''}
                                    error={form.errors['site_report.rencana_berikutnya']}
                                    onChange={(event) => setSiteReport('rencana_berikutnya', event.target.value)}
                                />
                                <div className="grid gap-2">
                                    <span className="text-sm font-extrabold">Lampiran Laporan</span>
                                    <input
                                        type="file"
                                        className="min-h-11 w-full rounded-lg border border-silver-deep/70 bg-white/85 px-4 py-2.5 text-sm font-semibold text-ink outline-none file:mr-4 file:rounded-md file:border-0 file:bg-ink file:px-4 file:py-2 file:text-sm file:font-bold file:text-white dark:border-white/10 dark:bg-white/8 dark:text-white"
                                        onChange={(event) => setSiteReport('lampiran', event.target.files?.[0] ?? null)}
                                    />
                                    {form.errors['site_report.lampiran'] && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors['site_report.lampiran']}</span>}
                                </div>
                            </div>
                        </div>

                        <Textarea
                            label="Keterangan"
                            value={form.data.keterangan}
                            error={form.errors.keterangan}
                            onChange={(event) => form.setData('keterangan', event.target.value)}
                        />
                    </Form>
                ) : (
                    <section className="rounded-lg border border-dashed border-silver-deep/70 bg-silver-soft/40 p-6 text-sm text-ink-soft dark:border-white/10 dark:bg-white/5">
                        Form progress disembunyikan karena role aktif tidak memiliki izin create progress pembangunan.
                    </section>
                )}
            </div>

            <Modal
                open={materialRequestModalOpen}
                onClose={() => setMaterialRequestModalOpen(false)}
                title="Pilih Permintaan Material"
                size="xl"
                footer={(
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" onClick={clearMaterialRequests} disabled={selectedMaterialRequestRows.length === 0}>
                            Kosongkan
                        </Button>
                        <Button type="button" variant="outline" onClick={() => setMaterialRequestModalOpen(false)}>
                            Tutup
                        </Button>
                    </div>
                )}
            >
                <div className="grid gap-4">
                    <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 text-sm text-ink-soft dark:border-white/10 dark:bg-white/5 dark:text-white/60">
                        Permintaan yang tampil di sini sudah lolos approval gudang dan owner, serta cocok dengan perumahan dan unit progress yang sedang dipilih.
                    </div>
                    <div className="overflow-hidden rounded-lg border border-silver-deep/60 dark:border-white/10">
                        <div className="max-h-[52vh] overflow-auto">
                            <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                                <thead className="sticky top-0 bg-silver-soft/95 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-graphite dark:text-white/50">
                                    <tr>
                                        <th className="px-4 py-3 font-extrabold">Pilih</th>
                                        <th className="px-4 py-3 font-extrabold">Kode</th>
                                        <th className="px-4 py-3 font-extrabold">Tanggal</th>
                                        <th className="px-4 py-3 font-extrabold">Lokasi</th>
                                        <th className="px-4 py-3 font-extrabold">Tahapan</th>
                                        <th className="px-4 py-3 font-extrabold">Item</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {approvedMaterialRequestOptions.map((item) => {
                                        const checked = (form.data.material_request_ids ?? []).includes(item.value);

                                        return (
                                            <tr key={item.value} className={checked ? 'bg-emerald-500/8' : ''}>
                                                <td className="px-4 py-3">
                                                    <input
                                                        type="checkbox"
                                                        className="h-4 w-4 rounded border-silver-deep/70 text-emerald-600 focus:ring-emerald-500"
                                                        checked={checked}
                                                        onChange={() => toggleMaterialRequest(item.value)}
                                                    />
                                                </td>
                                                <td className="px-4 py-3 font-bold">{item.label}</td>
                                                <td className="px-4 py-3">{item.tanggal ?? '-'}</td>
                                                <td className="px-4 py-3">{item.perumahan_label}{item.unit_label ? ` - ${item.unit_label}` : ''}</td>
                                                <td className="px-4 py-3">{item.tahapan_label ?? '-'}</td>
                                                <td className="px-4 py-3 text-xs text-ink-soft dark:text-white/60">
                                                    <div>{item.items_text || '-'}</div>
                                                    {item.issued_at && <div className="mt-1 font-bold text-emerald-700 dark:text-emerald-300">Keluar gudang: {item.issued_at}</div>}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {approvedMaterialRequestOptions.length === 0 && (
                                        <tr>
                                            <td className="px-4 py-8 text-center font-bold text-ink-soft dark:text-white/45" colSpan={6}>
                                                Belum ada permintaan material yang siap dipakai untuk progress ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </Modal>
        </>
    );
}

Create.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Kemajuan Pembangunan'}>{page}</AdminLayout>;
