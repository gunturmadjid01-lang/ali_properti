import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Edit3, Eye, FileText, Lock, Search, Trash2, Unlock, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, CurrencyInput, Dropdown, Form, Input, Modal, TableActions, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';
import { scopedTahapanOptions } from '../../../Utils/tahapanOptions';

const today = () => new Date().toISOString().slice(0, 10);

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

function emptyData(fields) {
    return fields.reduce((carry, field) => ({
        ...carry,
        [field.name]: field.default ?? (
            field.type === 'multi-select'
                ? []
                : field.type === 'date' && field.name === 'tanggal'
                    ? today()
                    : ['number', 'currency', 'computed-currency'].includes(field.type) ? '0' : ''
        ),
    }), {});
}

function ErrorSummary({ errors }) {
    const messages = Object.values(errors ?? {}).filter(Boolean);
    if (messages.length === 0) return null;
    return <div className="rounded-lg bg-red-50 p-4 text-sm font-bold text-red-700">{messages.map((message) => <p key={message}>{message}</p>)}</div>;
}

function FieldInput({ field, value, error, options, form, filteredUnits }) {
    const fieldLabel = field.name === 'jumlah_periode'
        ? `Jumlah ${form.data.tipe_upah === 'mingguan' ? 'Minggu' : form.data.tipe_upah === 'bulanan' ? 'Bulan' : 'Hari'}`
        : field.label;
    const tahapanOptions = scopedTahapanOptions(
        form.data.detail_rumah_id
            ? (options.tahapanPembangunansUnit ?? options.tahapanPembangunans ?? [])
            : (options.tahapanPembangunansKawasan ?? options.tahapanPembangunans ?? []),
        form.data.perumahan_id,
        form.data.detail_rumah_id,
    );

    if (field.name === 'detail_rumah_id') {
        return (
            <div className="grid gap-2">
                <span className="text-sm font-extrabold">{field.label}</span>
                <Dropdown label="Pilih Unit" value={value} options={filteredUnits} onChange={(next, selected) => {
                    form.setData({
                        ...form.data,
                        detail_rumah_id: next,
                        perumahan_id: selected?.perumahan_id ?? form.data.perumahan_id,
                        tahapan_pembangunan_id: '',
                        site_schedule_id: '',
                        progress_pembangunan_id: '',
                    });
                }} />
                {error && <span className="text-xs font-bold text-red-600">{error}</span>}
            </div>
        );
    }

    if (field.type === 'select') {
        return (
            <div className="grid gap-2">
                <span className="text-sm font-extrabold">{field.label}</span>
                <Dropdown
                    label={field.name === 'tahapan_pembangunan_id'
                        ? (form.data.detail_rumah_id ? 'Pilih Tahapan Rumah' : 'Pilih Tahapan Kawasan')
                        : field.label}
                    value={value}
                    options={field.name === 'tahapan_pembangunan_id' ? tahapanOptions : (options[field.optionsKey] ?? [])}
                    disabled={field.name === 'spk_kontraktor_id' && form.data.sumber_tenaga_kerja !== 'kontraktor'}
                    onChange={(next, selected) => {
                    if (field.name === 'perumahan_id') {
                        form.setData({
                            ...form.data,
                            perumahan_id: next,
                            detail_rumah_id: '',
                            tahapan_pembangunan_id: '',
                            site_schedule_id: '',
                            progress_pembangunan_id: '',
                        });
                        return;
                    }
                    if (field.name === 'site_schedule_id') {
                        form.setData({
                            ...form.data,
                            site_schedule_id: next,
                            perumahan_id: selected?.perumahan_id ?? form.data.perumahan_id,
                            detail_rumah_id: selected?.detail_rumah_id ?? form.data.detail_rumah_id,
                            tahapan_pembangunan_id: selected?.tahapan_pembangunan_id ?? form.data.tahapan_pembangunan_id,
                            progress_pembangunan_id: '',
                        });
                        return;
                    }
                    if (field.name === 'progress_pembangunan_id') {
                        form.setData({
                            ...form.data,
                            progress_pembangunan_id: next,
                            site_schedule_id: selected?.site_schedule_id ?? form.data.site_schedule_id,
                            perumahan_id: selected?.perumahan_id ?? form.data.perumahan_id,
                            detail_rumah_id: selected?.detail_rumah_id ?? form.data.detail_rumah_id,
                            tahapan_pembangunan_id: selected?.tahapan_pembangunan_id ?? form.data.tahapan_pembangunan_id,
                        });
                        return;
                    }
                    if (field.name === 'spk_kontraktor_id') {
                        form.setData({
                            ...form.data,
                            spk_kontraktor_id: next,
                            detail_rumah_id: selected?.detail_rumah_id || form.data.detail_rumah_id,
                            nilai_diajukan: selected?.nilai_kontrak ? String(selected.nilai_kontrak) : form.data.nilai_diajukan,
                        });
                        return;
                    }
                    if (field.name === 'sumber_tenaga_kerja' && next !== 'kontraktor') {
                        form.setData({ ...form.data, sumber_tenaga_kerja: next, spk_kontraktor_id: '' });
                        return;
                    }
                    form.setData(field.name, next);
                    }}
                />
                {error && <span className="text-xs font-bold text-red-600">{error}</span>}
            </div>
        );
    }

    if (field.type === 'multi-select') {
        const selectedValues = Array.isArray(value) ? value.map(String) : [];

        return (
            <div className="grid gap-2">
                <span className="text-sm font-extrabold">{fieldLabel}</span>
                <div className="grid max-h-48 gap-2 overflow-y-auto rounded-lg border border-silver-deep/70 bg-white/85 p-3 dark:border-white/10 dark:bg-white/8">
                    {(options[field.optionsKey] ?? []).map((option) => (
                        <label className="flex cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 hover:bg-silver-soft/70 dark:hover:bg-white/5" key={option.value}>
                            <input
                                type="checkbox"
                                checked={selectedValues.includes(String(option.value))}
                                onChange={(event) => {
                                    const next = event.target.checked
                                        ? [...selectedValues, String(option.value)]
                                        : selectedValues.filter((item) => item !== String(option.value));
                                    form.setData(field.name, next);
                                }}
                            />
                            <span className="text-sm font-semibold">{option.label}</span>
                        </label>
                    ))}
                    {(options[field.optionsKey] ?? []).length === 0 && <span className="text-sm text-ink-soft">Belum ada aset inventaris yang dapat dipilih.</span>}
                </div>
                {error && <span className="text-xs font-bold text-red-600">{error}</span>}
            </div>
        );
    }

    if (field.type === 'textarea') {
        return <Textarea label={fieldLabel} value={value} error={error} onChange={(event) => form.setData(field.name, event.target.value)} />;
    }

    if (field.type === 'currency') {
        return <CurrencyInput label={fieldLabel} value={value} error={error} onChange={(next) => form.setData(field.name, next)} />;
    }

    if (field.type === 'computed-currency') {
        return (
            <div className="grid gap-2">
                <span className="text-sm font-extrabold">{fieldLabel}</span>
                <div className="min-h-11 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 font-extrabold text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                    {money(value)}
                </div>
                <span className="text-xs font-semibold text-ink-soft">Dihitung otomatis dari upah pokok dan lembur.</span>
            </div>
        );
    }

    return <Input label={fieldLabel} type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text'} value={value} error={error} onChange={(event) => form.setData(field.name, event.target.value)} />;
}

function fieldIsVisible(field, data) {
    if (field.showWhen) {
        return Object.entries(field.showWhen).every(([name, values]) => values.includes(data[name]));
    }
    if (field.hideWhen) {
        return !Object.entries(field.hideWhen).some(([name, values]) => values.includes(data[name]));
    }
    return true;
}

const sectionMeta = {
    defect: {
        eyebrow: 'Punch list dan koreksi pekerjaan',
        description: 'Catat defect per unit, hubungkan ke QC dan tahapan pembangunan, lalu lock saat siap direview.',
        formLabel: 'Input Defect',
        tableHint: 'Defect terbuka jadi pengikat untuk status siap huni.',
    },
    'perubahan-pekerjaan': {
        eyebrow: 'Perubahan scope pekerjaan',
        description: 'Pakai form ini untuk perubahan volume, spek, atau penyesuaian pekerjaan yang harus disetujui.',
        formLabel: 'Input Perubahan Pekerjaan',
        tableHint: 'Perubahan yang locked ikut jalur approval sebelum memengaruhi proyek.',
    },
    'tenaga-kerja-alat': {
        eyebrow: 'Log tenaga kerja dan alat',
        description: 'Pisahkan sumber tenaga kerja, alat, dan upah supaya histori proyek tetap bisa diaudit.',
        formLabel: 'Input Tenaga Kerja & Alat',
        tableHint: 'Form ini lebih operasional, jadi tidak memakai approval final.',
    },
    k3: {
        eyebrow: 'Keselamatan kerja lapangan',
        description: 'Rekam temuan K3, tindakan, dan status risiko agar bisa ditelusuri bersama status unit.',
        formLabel: 'Input K3 / Safety',
        tableHint: 'Temuan K3 menjadi bagian dari sinkron status kelayakan unit.',
    },
    'serah-terima-internal': {
        eyebrow: 'Tahap akhir sebelum serah ke marketing/customer',
        description: 'Gunakan form ini saat unit sudah mendekati siap huni supaya progress dan status pembangunan ikut terkunci.',
        formLabel: 'Input Serah Terima Internal',
        tableHint: 'Section ini yang paling dekat ke status siap huni.',
    },
};

export default function Index({ title, section, sections = [], baseUrl, rows = { data: [], links: [] }, filters = {}, fields = [], options = {}, config = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [filterPerumahan, setFilterPerumahan] = useState(filters.perumahan_id ?? '');
    const [filterUnit, setFilterUnit] = useState(filters.detail_rumah_id ?? '');
    const [editing, setEditing] = useState(null);
    const [detail, setDetail] = useState(null);
    const form = useForm({ ...emptyData(fields), foto: null });

    const filteredFormUnits = useMemo(() => (options.detailRumahs ?? []).filter((row) => !form.data.perumahan_id || row.perumahan_id === String(form.data.perumahan_id)), [form.data.perumahan_id, options.detailRumahs]);
    const filteredFilterUnits = useMemo(() => (options.detailRumahs ?? []).filter((row) => !filterPerumahan || row.perumahan_id === String(filterPerumahan)), [filterPerumahan, options.detailRumahs]);
    const filteredSchedules = useMemo(() => (options.siteSchedules ?? []).filter((row) => {
        if (form.data.perumahan_id && row.perumahan_id !== String(form.data.perumahan_id)) {
            return false;
        }
        if (form.data.detail_rumah_id && row.detail_rumah_id !== String(form.data.detail_rumah_id)) {
            return false;
        }
        return true;
    }), [form.data.detail_rumah_id, form.data.perumahan_id, options.siteSchedules]);
    const filteredProgress = useMemo(() => (options.progressPembangunans ?? []).filter((row) => {
        if (form.data.perumahan_id && row.perumahan_id && row.perumahan_id !== String(form.data.perumahan_id)) {
            return false;
        }
        if (form.data.detail_rumah_id && row.detail_rumah_id !== String(form.data.detail_rumah_id)) {
            return false;
        }
        return true;
    }), [form.data.detail_rumah_id, form.data.perumahan_id, options.progressPembangunans]);
    const calculatedWage = useMemo(() => {
        if (section !== 'tenaga-kerja-alat') return 0;
        const mandor = Number(form.data.mandor || 0);
        const tukang = Number(form.data.tukang || 0);
        const kenek = Number(form.data.kenek || 0);
        const workers = mandor + tukang + kenek;
        const periods = Number(form.data.jumlah_periode || 0);
        const baseWage = form.data.tipe_upah === 'borongan'
            ? Number(form.data.nilai_borongan || 0)
            : (
                mandor * Number(form.data.tarif_mandor || 0)
                + tukang * Number(form.data.tarif_tukang || 0)
                + kenek * Number(form.data.tarif_kenek || 0)
            ) * periods;
        const overtime = workers * Number(form.data.jam_lembur || 0) * Number(form.data.tarif_lembur || 0);
        return baseWage + overtime;
    }, [
        section,
        form.data.mandor,
        form.data.tukang,
        form.data.kenek,
        form.data.tipe_upah,
        form.data.jumlah_periode,
        form.data.tarif_mandor,
        form.data.tarif_tukang,
        form.data.tarif_kenek,
        form.data.nilai_borongan,
        form.data.jam_lembur,
        form.data.tarif_lembur,
    ]);

    useEffect(() => {
        if (section === 'tenaga-kerja-alat' && Number(form.data.nilai_upah || 0) !== calculatedWage) {
            form.setData('nilai_upah', String(calculatedWage));
        }
    }, [calculatedWage, section]);

    const resetForm = () => {
        setEditing(null);
        form.setData({ ...emptyData(fields), foto: null });
        form.clearErrors();
    };

    const editRow = (row) => {
        const next = emptyData(fields);
        fields.forEach((field) => {
            const currentValue = row.detail?.[field.name];
            next[field.name] = field.type === 'multi-select'
                ? (Array.isArray(currentValue) ? currentValue.map(String) : [])
                : currentValue === null || currentValue === undefined ? '' : String(currentValue);
        });
        setEditing(row);
        form.setData({ ...next, foto: null });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { forceFormData: true, preserveScroll: true, onSuccess: resetForm };
        if (editing) {
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`${baseUrl}/${editing.id}`, requestOptions);
            return;
        }
        form.transform((data) => data);
        form.post(baseUrl, requestOptions);
    };

    const searchRows = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search, perumahan_id: filterPerumahan, detail_rumah_id: filterUnit }, { preserveState: true, replace: true, preserveScroll: true });
    };

    const canCreate = config.canCreate ?? false;
    const canUpdate = config.canUpdate ?? false;
    const canDelete = config.canDelete ?? false;
    const canApprove = config.canApprove ?? false;
    const canLock = config.canLock ?? false;
    const canUnlock = config.canUnlock ?? false;
    const meta = sectionMeta[section] ?? {};

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">{meta.eyebrow ?? 'Pengawasan Lapangan'}</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{meta.description ?? 'Data ini tersambung ke unit, progress, SPK, dan approval lapangan sesuai kebutuhan menu.'}</p>
                </section>

                {sections.length > 0 && (
                    <section className="flex flex-wrap gap-3 rounded-lg border border-white/70 bg-white/70 p-4 shadow-soft dark:border-white/10 dark:bg-white/8">
                        {sections.map((item) => (
                            <Button
                                as="a"
                                href={item.link}
                                key={item.key}
                                variant={item.key === section ? 'primary' : 'outline'}
                                className="min-w-0"
                            >
                                {item.title}
                            </Button>
                        ))}
                    </section>
                )}

                {(canCreate || (editing && canUpdate)) && (
                <Form
                    collapsible
                    title={editing ? `Edit ${editing.kode}` : (meta.formLabel ?? `Input ${title}`)}
                    description={meta.tableHint ?? 'Isi data lapangan sesuai kejadian/realisasi, lalu manajer atau owner dapat melakukan approval bila dibutuhkan.'}
                    onSubmit={submit}
                    actions={<>{editing && canUpdate && <Button type="button" variant="outline" onClick={resetForm}><X size={15} /> Batal</Button>}<Button type="submit" disabled={form.processing}><FileText size={17} /> {editing ? 'Simpan Perubahan' : 'Simpan'}</Button></>}
                >
                    <ErrorSummary errors={form.errors} />
                    <div className="grid gap-4 md:grid-cols-3">
                        {fields.filter((field) => fieldIsVisible(field, form.data)).map((field) => (
                            <div className={field.type === 'textarea' ? 'md:col-span-3' : ''} key={field.name}>
                                <FieldInput
                                    field={field}
                                    value={form.data[field.name] ?? ''}
                                    error={form.errors[field.name]}
                                    options={{
                                        ...options,
                                        siteSchedules: filteredSchedules,
                                        progressPembangunans: filteredProgress,
                                    }}
                                    form={form}
                                    filteredUnits={filteredFormUnits}
                                />
                            </div>
                        ))}
                        {config.photo && (
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">Foto Bukti</span>
                                <input type="file" accept="image/*" className="min-h-11 rounded-lg border border-silver-deep/70 p-2" onChange={(event) => form.setData('foto', event.target.files?.[0] ?? null)} />
                                {form.errors.foto && <span className="text-xs font-bold text-red-600">{form.errors.foto}</span>}
                            </div>
                        )}
                    </div>
                </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b border-silver-deep/40 px-5 py-4">
                        <p className="text-sm font-extrabold">{meta.tableHint ?? 'Tabel data pengawasan.'}</p>
                    </div>
                    <form className="grid gap-3 p-5 lg:grid-cols-[1.2fr_1fr_1fr_auto]" onSubmit={searchRows}>
                        <Input label="Cari" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown value={filterPerumahan} label="Semua Perumahan" options={[{ value: '', label: 'Semua Perumahan' }, ...(options.perumahans ?? [])]} onChange={(value) => { setFilterPerumahan(value); setFilterUnit(''); }} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Unit</span><Dropdown value={filterUnit} label="Semua Unit" options={[{ value: '', label: 'Semua Unit' }, ...filteredFilterUnits]} onChange={setFilterUnit} /></div>
                        <div className="flex items-end"><Button className="w-full" type="submit"><Search size={16} /> Cari</Button></div>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                <tr>{['Tanggal', 'Kode', 'Lokasi', 'Ringkasan', 'Status', 'Persetujuan', 'Audit', 'Aksi'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">{row.tanggal}</td>
                                        <td className="px-5 py-4 font-bold">{row.kode}</td>
                                        <td className="px-5 py-4">{row.perumahan}<br /><span className="text-xs text-ink-soft">{row.unit} {row.tahapan !== '-' ? `- ${row.tahapan}` : ''}</span></td>
                                        <td className="max-w-md px-5 py-4">{row.summary}<br /><span className="text-xs text-ink-soft">{row.spk !== '-' ? `SPK: ${row.spk}` : ''} {row.progress !== '-' ? `Progress: ${row.progress}` : ''} {row.qc !== '-' ? `QC: ${row.qc}` : ''}</span></td>
                                        <td className="px-5 py-4 font-bold">{row.status}</td>
                                        <td className="px-5 py-4 font-bold">{config.approval ? row.approval_status : '-'}</td>
                                        <td className="min-w-44 px-5 py-4 text-xs"><span className="font-bold">Dibuat:</span> {row.created_by_name}<br /><span className="font-bold">Diubah:</span> {row.updated_by_name}<br /><span className="font-bold">Setujui:</span> {row.approved_by_name}</td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                <Button type="button" size="sm" variant="outline" onClick={() => setDetail(row)}><Eye size={14} /> Detail</Button>
                                                {row.foto_url && <Button as="a" href={row.foto_url} target="_blank" size="sm" variant="outline">Foto</Button>}
                                                {canUpdate && row.can_edit && <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={14} /> Ubah</Button>}
                                                {canApprove && row.can_approve && row.approval_status !== 'approved' && <Button type="button" size="sm" onClick={() => router.post(`${baseUrl}/${row.id}/approve`, {}, { preserveScroll: true })}><CheckCircle2 size={14} /></Button>}
                                                {canDelete && row.can_delete && <Button type="button" size="sm" variant="outline" className="text-red-600" onClick={() => window.confirm('Hapus data ini?') && router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true })}><Trash2 size={14} /></Button>}
                                                {canLock && row.can_lock && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true })}><Lock size={14} /> Kunci</Button>}
                                                {canUnlock && row.can_unlock && row.record_status === 'locked' && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true })}><Unlock size={14} /> Unlock</Button>}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={8}>Belum ada data.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

            <Modal open={Boolean(detail)} onClose={() => setDetail(null)} title={detail ? `Detail ${detail.kode}` : 'Detail'} footer={<Button type="button" variant="outline" onClick={() => setDetail(null)}>Tutup</Button>}>
                {detail && <div className="grid gap-3 text-sm">
                    <p><b>Lokasi:</b> {detail.perumahan} - {detail.unit}</p>
                    <p><b>Ringkasan:</b> {detail.summary}</p>
                    <div className="grid gap-2 rounded-lg border border-silver-deep/60 p-3 text-xs dark:border-white/10">
                        {Object.entries(detail.detail ?? {}).map(([key, value]) => <p key={key}><b>{key}:</b> {typeof value === 'number' && key.includes('nilai') ? money(value) : String(value ?? '-')}</p>)}
                    </div>
                </div>}
            </Modal>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Pengawasan Lapangan'}>{page}</AdminLayout>;
