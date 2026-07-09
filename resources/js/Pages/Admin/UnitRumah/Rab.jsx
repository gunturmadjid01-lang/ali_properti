import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Edit3, FileDown, LoaderCircle, MinusCircle, Plus, Save, Trash2, XCircle } from 'lucide-react';
import { Fragment } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { Button, CurrencyInput, Input, ModalForm } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';
import { useResourcePermissions } from '../../../Utils/permissions';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

function calculateAmount(row) {
    const volume = Number(row?.volume || 0);
    const hargaSatuan = Number(row?.harga_satuan || 0);

    return String(row?.satuan ?? '').trim() === '%' ? (volume * hargaSatuan) / 100 : volume * hargaSatuan;
}

function stageItemTemplate() {
    return {
        kelompok_hpp_id: '',
        nama_pekerjaan: '',
        volume: 0,
        satuan: 'Ls',
        harga_satuan: 0,
        urutan: 0,
    };
}

function normalizeRow(row, index = 0) {
    return {
        ...row,
        draft_id: row?.draft_id ?? (row?.id ? `item-${row.id}` : `new-${Date.now()}-${index}`),
        tahapan_pembangunan_id: row?.tahapan_pembangunan_id ? String(row.tahapan_pembangunan_id) : '',
        kelompok_hpp_id: row?.kelompok_hpp_id ? String(row.kelompok_hpp_id) : '',
        nama_pekerjaan: row?.nama_pekerjaan ?? '',
        volume: row?.volume ?? 0,
        satuan: row?.satuan === '-' ? '' : (row?.satuan ?? 'Ls'),
        harga_satuan: row?.harga_satuan ?? 0,
        urutan: row?.urutan ?? index + 1,
        jumlah_realisasi: row?.jumlah_realisasi ?? 0,
    };
}

export default function Rab({
    title,
    unit,
    perumahan,
    rows = [],
    initialTargetIds = [],
    options = {},
    baseUrl,
    detailUrl,
    hppUrl,
    pdfUrl,
    stageUrl,
    stageBaseUrl,
    hppOwner = {},
}) {
    const permissions = useResourcePermissions('rab-unit', hppUrl);
    const canManage = permissions.canManage;
    const canCreateStage = permissions.canCreateExact || permissions.canManage;
    const canEditStage = permissions.canUpdateExact || permissions.canManage;
    const [draftRows, setDraftRows] = useState(() => rows.map(normalizeRow));
    const [selectedTargets, setSelectedTargets] = useState(() => (
        [...new Set([String(unit.id), ...initialTargetIds.map(String)])]
    ));
    const [saving, setSaving] = useState(false);
    const [stageOpen, setStageOpen] = useState(false);
    const [editingStage, setEditingStage] = useState(null);
    const stageForm = useForm({
        konteks: 'unit',
        nama_tahapan: '',
        urutan: (options.tahapanHpps?.length ?? 0) + 1,
        perumahan_id: hppOwner.perumahan_id ?? String(perumahan.id ?? ''),
        detail_rumah_id: hppOwner.detail_rumah_id ?? String(unit.id),
        target_detail_rumah_ids: selectedTargets,
        items: [stageItemTemplate()],
    });

    const stages = options.tahapanHpps ?? [];
    const selectedUnitRows = useMemo(() => (
        (options.unitTargets ?? []).filter((target) => selectedTargets.includes(String(target.value)))
    ), [options.unitTargets, selectedTargets]);
    const selectedUnitLabels = selectedUnitRows.map((target) => target.label);
    const selectedUnitTitle = selectedUnitLabels.length > 0 ? selectedUnitLabels.join(', ') : unit.label;

    useEffect(() => {
        setSelectedTargets([...new Set([String(unit.id), ...initialTargetIds.map(String)])]);
    }, [unit.id, initialTargetIds.join(',')]);

    const groups = useMemo(() => stages.map((stage) => {
        const groupRows = draftRows
            .filter((row) => String(row.tahapan_pembangunan_id) === String(stage.value))
            .sort((a, b) => Number(a.urutan ?? 0) - Number(b.urutan ?? 0));
        const subtotal = groupRows.reduce((total, row) => total + calculateAmount(row), 0);

        return { ...stage, rows: groupRows, subtotal };
    }), [draftRows, stages]);
    const totalRab = groups.reduce((total, group) => total + group.subtotal, 0);

    const updateRow = (draftId, key, value) => {
        setDraftRows((current) => current.map((row) => (
            row.draft_id === draftId ? normalizeRow({ ...row, [key]: value }) : row
        )));
    };

    const addRow = (stage) => {
        setDraftRows((current) => {
            const stageRows = current.filter((row) => String(row.tahapan_pembangunan_id) === String(stage.value));
            const nextOrder = stageRows.reduce((highest, row) => Math.max(highest, Number(row.urutan) || 0), 0) + 1;

            return [
                ...current,
                normalizeRow({
                    draft_id: `new-${Date.now()}-${current.length}`,
                    tahapan_pembangunan_id: String(stage.value),
                    tahapan_nama: stage.nama_tahapan,
                    nama_pekerjaan: '',
                    volume: 0,
                    satuan: 'Ls',
                    harga_satuan: 0,
                    urutan: nextOrder,
                }, current.length),
            ];
        });
    };

    const removeRow = (draftId) => {
        setDraftRows((current) => current.filter((row) => row.draft_id !== draftId));
    };

    const toggleTarget = (targetId) => {
        const id = String(targetId);

        if (id === String(unit.id)) {
            return;
        }

        setSelectedTargets((current) => (
            current.includes(id) ? current.filter((value) => value !== id) : [...current, id]
        ));
    };

    const submit = () => {
        setSaving(true);
        router.put(hppUrl, {
            items: draftRows.map((row, index) => ({
                kelompok_hpp_id: row.kelompok_hpp_id ?? '',
                tahapan_pembangunan_id: row.tahapan_pembangunan_id,
                nama_pekerjaan: row.nama_pekerjaan,
                volume: row.volume ?? 0,
                satuan: row.satuan ?? '',
                harga_satuan: row.harga_satuan ?? 0,
                urutan: row.urutan ?? index + 1,
            })),
            target_detail_rumah_ids: selectedTargets,
        }, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    const openCreateStage = () => {
        setEditingStage(null);
        stageForm.setData({
            konteks: 'unit',
            nama_tahapan: '',
            urutan: (options.tahapanHpps?.length ?? 0) + 1,
            perumahan_id: hppOwner.perumahan_id ?? String(perumahan.id ?? ''),
            detail_rumah_id: hppOwner.detail_rumah_id ?? String(unit.id),
            target_detail_rumah_ids: selectedTargets,
            items: [stageItemTemplate()],
        });
        stageForm.clearErrors();
        setStageOpen(true);
    };

    const openEditStage = (stage) => {
        setEditingStage(stage);
        stageForm.setData({
            konteks: 'unit',
            nama_tahapan: stage.nama_tahapan ?? stage.label ?? '',
            urutan: stage.urutan ?? 1,
            perumahan_id: hppOwner.perumahan_id ?? String(perumahan.id ?? ''),
            detail_rumah_id: hppOwner.detail_rumah_id ?? String(unit.id),
            target_detail_rumah_ids: selectedTargets,
            items: [stageItemTemplate()],
        });
        stageForm.clearErrors();
        setStageOpen(true);
    };

    const submitStage = (event) => {
        event.preventDefault();
        const requestOptions = {
            preserveScroll: true,
            onSuccess: () => {
                setStageOpen(false);
                setEditingStage(null);
                stageForm.reset('nama_tahapan');
                stageForm.setData('items', [stageItemTemplate()]);
            },
        };

        if (editingStage) {
            stageForm.put(`${stageBaseUrl}/${editingStage.value}`, requestOptions);
            return;
        }

        stageForm.post(stageUrl, requestOptions);
    };

    return (
        <>
            <Head title={`${title} ${unit.label}`} />
            <div className="grid gap-5">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="flex flex-wrap gap-2">
                        <Button as={Link} href={baseUrl} variant="ghost" size="sm"><ArrowLeft size={16} /> Daftar Unit</Button>
                        <Button as={Link} href={detailUrl} variant="outline" size="sm">Detail Rumah</Button>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canCreateStage && (
                            <Button type="button" variant="outline" size="sm" onClick={openCreateStage}>
                                <Plus size={16} /> Tambah Tahap
                            </Button>
                        )}
                        <Button as="a" href={pdfUrl} variant="outline" size="sm"><FileDown size={16} /> Export PDF</Button>
                        {canManage && (
                            <Button type="button" onClick={submit} disabled={saving}>
                                {saving ? <LoaderCircle className="animate-spin" size={16} /> : <Save size={16} />}
                                {saving ? 'Menyimpan...' : 'Simpan RAB'}
                            </Button>
                        )}
                    </div>
                </div>

                {canManage && (
                    <section className="rounded-lg border border-white/80 bg-white/78 p-4 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="text-xs font-extrabold uppercase text-ink-soft">Terapkan RAB ke Unit</p>
                            <span className="text-sm font-extrabold">{selectedTargets.length} unit dipilih</span>
                        </div>
                        <div className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            {(options.unitTargets ?? []).map((target) => (
                                <label className="flex min-h-11 items-center gap-3 rounded-lg border border-silver-deep/60 bg-white/70 px-3 text-sm font-bold dark:border-white/10 dark:bg-white/6" key={target.value}>
                                    <input
                                        type="checkbox"
                                        checked={selectedTargets.includes(String(target.value))}
                                        disabled={String(target.value) === String(unit.id)}
                                        onChange={() => toggleTarget(target.value)}
                                    />
                                    <span className="truncate">{target.label}</span>
                                </label>
                            ))}
                        </div>
                        <div className="mt-4 flex flex-wrap gap-2 border-t border-silver-deep/60 pt-4 dark:border-white/10">
                            {selectedUnitRows.map((target) => (
                                <span className="rounded-md bg-ink px-3 py-1.5 text-xs font-extrabold text-white dark:bg-white dark:text-ink" key={`selected-${target.value}`}>
                                    {target.label}
                                </span>
                            ))}
                        </div>
                    </section>
                )}

                <section className="overflow-hidden rounded-lg border border-silver-deep/70 bg-white shadow-soft dark:border-white/10 dark:bg-white">
                    <div className="px-6 py-5 text-center text-ink">
                        <h1 className="text-xl font-black tracking-wide">RAB PER UNIT RUMAH {perumahan.nama_perusahaan ?? ''}</h1>
                        <p className="mt-1 text-sm font-extrabold">UNIT RUMAH {selectedUnitTitle}</p>
                    </div>

                    <div className="grid gap-1 px-6 pb-4 text-sm font-bold text-ink md:grid-cols-[160px_1fr]">
                        <span>OWNER</span><span>: PT. ALI PROPERTY INDONESIA</span>
                        <span>PEKERJAAN</span><span>: {perumahan.nama_perusahaan ?? '-'}</span>
                        <span>LOKASI</span><span>: {perumahan.alamat ?? '-'}</span>
                        <span>TAHUN ANGGARAN</span><span>: {new Date().getFullYear()}</span>
                        <span>UNIT DIPILIH</span><span>: {selectedTargets.length} unit</span>
                        <span>TIPE / LUAS SUMBER</span><span>: {unit.tipe_rumah ?? '-'} / LT {unit.luas_tanah ?? '-'} / LB {unit.luas_bangunan ?? '-'}</span>
                    </div>

                    <div className="overflow-x-auto px-4 pb-6">
                        <table className="min-w-[980px] w-full border-collapse text-xs text-ink">
                            <colgroup>
                                <col className="w-12" />
                                <col />
                                <col className="w-20" />
                                <col className="w-24" />
                                <col className="w-40" />
                                <col className="w-40" />
                                {canManage && <col className="w-16" />}
                            </colgroup>
                            <thead>
                                <tr className="bg-[#d9d9d9]">
                                    {['NO', 'ITEM PEKERJAAN', 'Satuan', 'Jumlah', 'Harga satuan', 'Total harga', ...(canManage ? ['AKSI'] : [])].map((column) => (
                                        <th className="border border-ink px-2 py-2 text-center font-black" key={column}>{column}</th>
                                    ))}
                                </tr>
                                <tr>
                                    {['1', '2', '3', '4', '5', '6', ...(canManage ? [''] : [])].map((column, index) => (
                                        <th className="border border-ink px-2 py-1 text-center font-black" key={`${column}-${index}`}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {groups.map((group, groupIndex) => (
                                    <Fragment key={`group-${group.value}`}>
                                        <tr className="bg-[#999999]">
                                            <td className="border border-ink px-2 py-2 text-center font-black">{groupIndex + 1}</td>
                                            <td className="border border-ink px-2 py-2 font-black" colSpan={canManage ? 6 : 5}>
                                                <div className="flex items-center justify-between gap-3">
                                                    <span>{group.nama_tahapan}</span>
                                                    {canEditStage && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            title="Edit tahap"
                                                            onClick={() => openEditStage(group)}
                                                        >
                                                            <Edit3 size={14} /> Edit Tahap
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                        {group.rows.map((row, index) => (
                                            <tr key={row.draft_id}>
                                                <td className="border border-ink px-2 py-1 text-center">{index + 1}</td>
                                                <td className="border border-ink px-2 py-1">
                                                    {canManage ? (
                                                        <Input value={row.nama_pekerjaan} onChange={(event) => updateRow(row.draft_id, 'nama_pekerjaan', event.target.value)} />
                                                    ) : row.nama_pekerjaan}
                                                </td>
                                                <td className="w-20 min-w-20 max-w-20 border border-ink px-1 py-1 text-center">
                                                    {canManage ? (
                                                        <Input
                                                            inputClassName="!min-h-9 !px-2 text-center"
                                                            value={row.satuan}
                                                            onChange={(event) => updateRow(row.draft_id, 'satuan', event.target.value)}
                                                        />
                                                    ) : row.satuan}
                                                </td>
                                                <td className="border border-ink px-2 py-1 text-right">
                                                    {canManage ? <Input type="number" step="0.01" value={row.volume} onChange={(event) => updateRow(row.draft_id, 'volume', event.target.value)} /> : Number(row.volume ?? 0).toLocaleString('id-ID')}
                                                </td>
                                                <td className="border border-ink px-2 py-1 text-right">
                                                    {canManage ? <CurrencyInput value={row.harga_satuan} onChange={(value) => updateRow(row.draft_id, 'harga_satuan', value)} /> : money(row.harga_satuan)}
                                                </td>
                                                <td className="border border-ink px-2 py-1 text-right font-bold">{money(calculateAmount(row))}</td>
                                                {canManage && (
                                                    <td className="border border-ink px-2 py-1 text-center">
                                                        <Button type="button" variant="ghost" size="sm" onClick={() => removeRow(row.draft_id)}><Trash2 size={14} /></Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                        {canManage && (
                                            <tr key={`add-${group.value}`}>
                                                <td className="border border-ink px-2 py-1 text-center" colSpan={canManage ? 7 : 6}>
                                                    <Button type="button" variant="outline" size="sm" onClick={() => addRow(group)}><Plus size={14} /> Tambah Item {group.nama_tahapan}</Button>
                                                </td>
                                            </tr>
                                        )}
                                        <tr className="font-black" key={`subtotal-${group.value}`}>
                                            <td className="border border-ink px-2 py-2 text-center" colSpan={5}>TOTAL</td>
                                            <td className="border border-ink bg-[#92d050] px-2 py-2 text-right">{money(group.subtotal)}</td>
                                            {canManage && <td className="border border-ink" />}
                                        </tr>
                                    </Fragment>
                                ))}
                                <tr className="bg-[#d9d9d9] font-black">
                                    <td className="border border-ink px-2 py-3" colSpan={5}>TOTAL</td>
                                    <td className="border border-ink px-2 py-3 text-right">{money(totalRab)}</td>
                                    {canManage && <td className="border border-ink" />}
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div className="grid grid-cols-2 px-6 pb-10 pt-4 text-sm font-bold text-ink">
                        <div />
                        <div className="text-center">
                            <p>PT. ALI PROPERTY INDONESIA</p>
                            <p className="mt-3">DIREKTUR</p>
                            <p className="mt-20 underline">MUHAMMAD ALI BESTARI SH, MH</p>
                        </div>
                    </div>
                </section>
            </div>

            {stageOpen && canCreateStage && (
                <ModalForm
                    open={stageOpen}
                    onClose={() => {
                        setStageOpen(false);
                        setEditingStage(null);
                    }}
                    title={`${editingStage ? 'Edit' : 'Tambah'} Tahap RAB Unit`}
                    description="Atur nama dan posisi tahap pada RAB unit rumah."
                    onSubmit={submitStage}
                    actions={(
                        <>
                            <Button variant="outline" type="button" onClick={() => {
                                setStageOpen(false);
                                setEditingStage(null);
                            }}>
                                <XCircle size={17} /> Batal
                            </Button>
                            <Button type="submit" disabled={stageForm.processing}>
                                {stageForm.processing ? <LoaderCircle className="animate-spin" size={17} /> : (editingStage ? <Save size={17} /> : <Plus size={17} />)}
                                {stageForm.processing ? 'Menyimpan...' : (editingStage ? 'Simpan Tahap' : 'Tambah Tahap')}
                            </Button>
                        </>
                    )}
                >
                    <div className="grid gap-4 md:grid-cols-[1fr_140px]">
                        <Input
                            label="Nama Tahap"
                            value={stageForm.data.nama_tahapan}
                            error={stageForm.errors.nama_tahapan}
                            onChange={(event) => stageForm.setData('nama_tahapan', event.target.value)}
                        />
                        <Input
                            label="Urutan"
                            type="number"
                            min="1"
                            value={stageForm.data.urutan}
                            error={stageForm.errors.urutan}
                            onChange={(event) => stageForm.setData('urutan', event.target.value)}
                        />
                    </div>
                    {!editingStage && (
                        <div className="grid gap-3 rounded-lg border border-silver-deep/60 p-4 dark:border-white/10">
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-sm font-extrabold">Item Pekerjaan Awal</p>
                                <Button type="button" variant="outline" size="sm" onClick={() => stageForm.setData('items', [...stageForm.data.items, stageItemTemplate()])}>
                                    <Plus size={14} /> Tambah Item
                                </Button>
                            </div>
                            {(stageForm.data.items ?? []).map((item, index) => (
                                <div className="grid gap-3 rounded-lg bg-silver-soft/80 p-3 dark:bg-white/5 md:grid-cols-2 xl:grid-cols-[1.6fr_0.65fr_0.65fr_0.9fr_auto]" key={index}>
                                    <Input label={`Nama Pekerjaan ${index + 1}`} value={item.nama_pekerjaan} onChange={(event) => stageForm.setData('items', stageForm.data.items.map((row, rowIndex) => rowIndex === index ? { ...row, nama_pekerjaan: event.target.value } : row))} />
                                    <Input label="Volume" type="number" value={item.volume} onChange={(event) => stageForm.setData('items', stageForm.data.items.map((row, rowIndex) => rowIndex === index ? { ...row, volume: event.target.value } : row))} />
                                    <Input label="Satuan" value={item.satuan} onChange={(event) => stageForm.setData('items', stageForm.data.items.map((row, rowIndex) => rowIndex === index ? { ...row, satuan: event.target.value } : row))} />
                                    <CurrencyInput label="Harga Satuan" value={item.harga_satuan} onChange={(value) => stageForm.setData('items', stageForm.data.items.map((row, rowIndex) => rowIndex === index ? { ...row, harga_satuan: value } : row))} />
                                    <div className="flex items-end justify-end">
                                        <Button type="button" variant="ghost" className="text-red-600" disabled={stageForm.data.items.length === 1} onClick={() => stageForm.setData('items', stageForm.data.items.filter((_, rowIndex) => rowIndex !== index))}>
                                            <MinusCircle size={14} />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </ModalForm>
            )}
        </>
    );
}

Rab.layout = (page) => <AdminLayout title={page?.props?.title ?? 'RAB Unit Rumah'}>{page}</AdminLayout>;
