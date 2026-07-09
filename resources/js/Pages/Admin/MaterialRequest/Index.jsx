import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Edit3, Lock, MinusCircle, PlusCircle, Save, Search, Send, Trash2, Unlock, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';
import { scopedTahapanOptions } from '../../../Utils/tahapanOptions';

function itemTemplate() {
    return { barang_material_id: '', qty: '', satuan: '', catatan: '' };
}

export default function Index({ title, baseUrl, rows = { data: [], links: [] }, filters = {}, options = {}, templateEndpoint = '', canCreate = false, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState(null);
    const [templateSummary, setTemplateSummary] = useState(null);
    const [templateLoading, setTemplateLoading] = useState(false);
    const lastTemplateKeyRef = useRef('');
    const canUpdate = permissions.canUpdate ?? false;
    const canDelete = permissions.canDelete ?? false;
    const canApproveGudang = permissions.canApproveGudang ?? false;
    const canApproveOwner = permissions.canApproveOwner ?? false;
    const canLock = permissions.canLock ?? false;
    const canUnlock = permissions.canUnlock ?? false;
    const canIssue = permissions.canIssue ?? false;
    const form = useForm({
        tanggal: new Date().toISOString().slice(0, 10),
        gudang_id: '',
        perumahan_id: '',
        detail_rumah_id: '',
        tahapan_pembangunan_id: '',
        site_schedule_id: '',
        progress_diakui: '',
        keterangan: '',
        items: [itemTemplate()],
    });
    const formatCurrency = (value) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

    const detailRumahOptions = useMemo(() => {
        if (!form.data.perumahan_id) return options.detailRumahs ?? [];
        return (options.detailRumahs ?? []).filter((item) => item.perumahan_id === String(form.data.perumahan_id));
    }, [form.data.perumahan_id, options.detailRumahs]);

    const siteScheduleOptions = useMemo(() => {
        return (options.siteSchedules ?? []).filter((item) => {
            if (form.data.perumahan_id && item.perumahan_id !== String(form.data.perumahan_id)) return false;
            if (form.data.detail_rumah_id && item.detail_rumah_id !== String(form.data.detail_rumah_id)) return false;
            return true;
        });
    }, [form.data.perumahan_id, form.data.detail_rumah_id, options.siteSchedules]);

    const itemOptions = useMemo(() => {
        if (templateSummary?.items?.length) {
            return templateSummary.items.map((item, index) => ({
                value: String(item.barang_material_id ?? `${index}`),
                label: `${item.tahapan ? `${item.tahapan} - ` : ''}${item.barang_label ?? item.nama_pekerjaan ?? 'Item'}`,
                satuan: item.satuan ?? '',
                harga_satuan: item.harga_satuan ?? 0,
                volume: item.volume ?? 0,
            }));
        }

        return options.barangMaterials ?? [];
    }, [options.barangMaterials, templateSummary]);

    const tahapanOptions = useMemo(
        () => scopedTahapanOptions(
            options.tahapanPembangunans ?? [],
            form.data.perumahan_id,
            form.data.detail_rumah_id,
        ),
        [form.data.detail_rumah_id, form.data.perumahan_id, options.tahapanPembangunans],
    );

    const setItem = (index, key, value) => {
        form.setData('items', form.data.items.map((item, itemIndex) => {
            if (itemIndex !== index) return item;
            const next = { ...item, [key]: value };
            if (key === 'barang_material_id') {
                const selectedItem = itemOptions.find((option) => option.value === String(value));
                next.satuan = selectedItem?.satuan ?? '';
            }
            return next;
        }));
    };

    const applyTemplate = async ({ force = false } = {}) => {
        if (!form.data.perumahan_id) {
            window.alert('Pilih perumahan terlebih dahulu supaya item tahapan bisa dimuat.');
            return;
        }

        const currentKey = [form.data.perumahan_id, form.data.detail_rumah_id, form.data.tahapan_pembangunan_id].join('|');
        if (!force && currentKey === lastTemplateKeyRef.current) {
            return;
        }

        const params = new URLSearchParams();
        params.set('perumahan_id', form.data.perumahan_id);
        if (form.data.detail_rumah_id) {
            params.set('detail_rumah_id', form.data.detail_rumah_id);
        }
        if (form.data.tahapan_pembangunan_id) {
            params.set('tahapan_pembangunan_id', form.data.tahapan_pembangunan_id);
        }

        try {
            setTemplateLoading(true);
            const response = await fetch(`${templateEndpoint}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Item tahapan tidak bisa dimuat.');
            }

            const payload = await response.json();
            const template = payload?.data ?? payload;

            if (!template?.items?.length) {
                lastTemplateKeyRef.current = currentKey;
                setTemplateSummary(template);
                window.alert('Tahapan ini belum punya item untuk ditampilkan.');
                return;
            }

            setTemplateSummary(template);
            lastTemplateKeyRef.current = currentKey;
            form.setData({
                ...form.data,
                perumahan_id: String(template.perumahan_id ?? form.data.perumahan_id ?? ''),
                detail_rumah_id: String(template.detail_rumah_id ?? form.data.detail_rumah_id ?? ''),
                tahapan_pembangunan_id: String(template.tahapan_pembangunan_id ?? form.data.tahapan_pembangunan_id ?? ''),
                items: form.data.items?.length ? form.data.items : [itemTemplate()],
            });
        } catch (error) {
            window.alert(error?.message ?? 'Gagal memuat item tahapan.');
        } finally {
            setTemplateLoading(false);
        }
    };

    useEffect(() => {
        if (!form.data.perumahan_id || !form.data.tahapan_pembangunan_id) {
            return;
        }

        const currentKey = [form.data.perumahan_id, form.data.detail_rumah_id, form.data.tahapan_pembangunan_id].join('|');
        if (currentKey === lastTemplateKeyRef.current) {
            return;
        }

        void applyTemplate();
    }, [form.data.detail_rumah_id, form.data.perumahan_id, form.data.tahapan_pembangunan_id]);

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true, onSuccess: resetForm };
        if (editing) {
            form.put(`${baseUrl}/${editing.id}`, requestOptions);
            return;
        }
        form.post(baseUrl, requestOptions);
    };

    const resetForm = () => {
        setEditing(null);
        setTemplateSummary(null);
        lastTemplateKeyRef.current = '';
        form.reset();
        form.clearErrors();
        form.setData('tanggal', new Date().toISOString().slice(0, 10));
    };

    const editRow = (row) => {
        setEditing(row);
        form.clearErrors();
        setTemplateSummary(null);
        lastTemplateKeyRef.current = '';
        form.setData({
            tanggal: row.tanggal ?? '',
            gudang_id: row.gudang_id ?? '',
            perumahan_id: row.perumahan_id ?? '',
            detail_rumah_id: row.detail_rumah_id ?? '',
            tahapan_pembangunan_id: row.tahapan_pembangunan_id ?? '',
            site_schedule_id: row.site_schedule_id ?? '',
            progress_diakui: row.progress_diakui ?? '',
            keterangan: row.keterangan ?? '',
            items: row.items?.length ? row.items : [itemTemplate()],
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const lockRow = (row) => {
        if (!window.confirm(`Lock permintaan ${row.kode_request}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockRow = (row) => {
        if (!window.confirm(`Buka lock permintaan ${row.kode_request}?`)) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                {canCreate && <Form collapsible title={editing ? `Edit ${editing.kode_request}` : title} description="Pengawas mengajukan material. Stok baru keluar otomatis setelah gudang dan owner sama-sama menyetujui." onSubmit={submit} actions={<>{editing && <Button type="button" variant="outline" onClick={resetForm}><X size={16} /> Batal Edit</Button>}<Button type="submit" disabled={form.processing}><Save size={17} /> {editing ? 'Simpan Perubahan' : 'Kirim Permintaan'}</Button></>}>
                    <div className="grid gap-4 md:grid-cols-4">
                        <Input label="Tanggal" type="date" value={form.data.tanggal} error={form.errors.tanggal} onChange={(event) => form.setData('tanggal', event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Gudang</span><Dropdown value={form.data.gudang_id} label="Pilih Gudang" options={options.gudangs} onChange={(value) => form.setData('gudang_id', value)} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown value={form.data.perumahan_id} label="Pilih Perumahan" options={options.perumahans} onChange={(value) => { setTemplateSummary(null); lastTemplateKeyRef.current = ''; form.setData({ ...form.data, perumahan_id: value, detail_rumah_id: '' }); }} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Unit Rumah</span><Dropdown value={form.data.detail_rumah_id} label="Pilih Unit" options={detailRumahOptions} onChange={(value, selected) => { setTemplateSummary(null); lastTemplateKeyRef.current = ''; form.setData({ ...form.data, detail_rumah_id: value, perumahan_id: selected?.perumahan_id ?? form.data.perumahan_id }); }} /></div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-4">
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Tahapan</span><Dropdown value={form.data.tahapan_pembangunan_id} label="Pilih Tahapan" options={tahapanOptions} onChange={(value) => { setTemplateSummary(null); lastTemplateKeyRef.current = ''; form.setData('tahapan_pembangunan_id', value); }} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Jadwal Kerja</span><Dropdown value={form.data.site_schedule_id} label="Opsional pilih jadwal" options={siteScheduleOptions} onChange={(value, selected) => { setTemplateSummary(null); lastTemplateKeyRef.current = ''; form.setData({ ...form.data, site_schedule_id: value, tahapan_pembangunan_id: selected?.tahapan_pembangunan_id ?? form.data.tahapan_pembangunan_id }); }} /></div>
                        <Input label="Progress Diakui (%) - opsional" value={form.data.progress_diakui} error={form.errors.progress_diakui} onChange={(event) => form.setData('progress_diakui', event.target.value.replace(/[^0-9.,]/g, ''))} />
                    </div>

                    {templateSummary && (
                        <div className="grid gap-2 rounded-lg border border-emerald-400/25 bg-emerald-50 px-4 py-3 text-sm text-emerald-950 dark:border-emerald-400/20 dark:bg-emerald-500/10 dark:text-emerald-50">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div className="text-xs font-extrabold uppercase tracking-[0.14em] opacity-70">Tahapan aktif</div>
                                    <div className="text-base font-extrabold">
                                        {templateSummary.scope === 'unit' ? 'Unit' : 'Kawasan'} - {templateSummary.tahapan}
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-4 text-xs font-bold">
                                    <span>Nilai RAB: {formatCurrency(templateSummary.total_rab)}</span>
                                    <span>Budget tahap: {formatCurrency(templateSummary.stage_budget)}</span>
                                    <span>Estimasi progres: {Number(templateSummary.estimated_progress ?? 0)}%</span>
                                </div>
                            </div>
                            <div className="text-xs font-semibold opacity-80">{templateSummary.items?.length ?? 0} item tahapan siap dipilih</div>
                        </div>
                    )}

                    <div className="grid gap-3">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <h3 className="text-sm font-extrabold">Item Barang</h3>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!form.data.perumahan_id || templateLoading}
                                    onClick={() => applyTemplate({ force: true })}
                                >
                                    <CheckCircle2 size={15} />
                                    {templateLoading ? 'Memuat...' : 'Muat Item Tahapan'}
                                </Button>
                                <Button type="button" variant="outline" size="sm" onClick={() => form.setData('items', [...form.data.items, itemTemplate()])}><PlusCircle size={15} /> Tambah</Button>
                            </div>
                        </div>
                        {form.data.items.map((item, index) => (
                            <div className="grid gap-3 rounded-lg border border-silver-deep/70 p-3 md:grid-cols-[1.5fr_0.6fr_0.6fr_auto]" key={index}>
                                <div className="grid gap-2"><span className="text-xs font-extrabold text-ink-soft">Item Tahapan</span><Dropdown value={item.barang_material_id} label={templateSummary?.items?.length ? 'Pilih Item Tahapan' : 'Muat tahapan dulu'} options={itemOptions} disabled={!itemOptions.length} onChange={(value) => setItem(index, 'barang_material_id', value)} /></div>
                                <Input label="Qty" type="number" value={item.qty} onChange={(event) => setItem(index, 'qty', event.target.value)} />
                                <Input label="Satuan" value={item.satuan} onChange={(event) => setItem(index, 'satuan', event.target.value)} />
                                <div className="flex items-end justify-end"><Button type="button" variant="ghost" size="sm" className="text-red-600" disabled={form.data.items.length === 1} onClick={() => form.setData('items', form.data.items.filter((_, itemIndex) => itemIndex !== index))}><MinusCircle size={16} /></Button></div>
                            </div>
                        ))}
                    </div>
                    <Textarea label="Keterangan" value={form.data.keterangan} error={form.errors.keterangan} onChange={(event) => form.setData('keterangan', event.target.value)} />
                </Form>}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true }); }}>
                        <Input className="md:max-w-md" label="Search" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft">
                                <tr>{['Kode', 'Tanggal', 'Gudang', 'Unit', 'Tahapan/Jadwal', 'Progress', 'Barang', 'Approval Gudang', 'Approval Owner', 'Audit', 'Status', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">{row.kode_request}</td>
                                        <td className="px-5 py-4">{row.tanggal}</td>
                                        <td className="px-5 py-4">{row.gudang}</td>
                                        <td className="px-5 py-4">{row.unit}</td>
                                        <td className="px-5 py-4">{row.tahapan}<br /><span className="text-xs font-bold text-ink-soft">{row.jadwal}</span></td>
                                        <td className="px-5 py-4 font-bold">{Number(row.progress_diakui ?? 0)}%</td>
                                        <td className="px-5 py-4">{row.items_text}</td>
                                        <td className="px-5 py-4 font-bold">{row.approved_at_gudang ? `${row.approved_by_gudang} · ${row.approved_at_gudang}` : 'Menunggu'}</td>
                                        <td className="px-5 py-4 font-bold">{row.approved_at_owner ? `${row.approved_by_owner} · ${row.approved_at_owner}` : 'Menunggu'}</td>
                                        <td className="min-w-44 px-5 py-4 text-xs"><span className="font-bold">Dibuat:</span> {row.created_by_name}<br /><span className="font-bold">Diubah:</span> {row.updated_by_name}<br />{row.issued_at && <><span className="font-bold">Dikeluarkan:</span> {row.issued_by_name}</>}</td>
                                        <td className="px-5 py-4 font-bold">{row.status}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                {canUpdate && row.can_edit && <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={15} /> Edit</Button>}
                                                {canDelete && row.can_delete && <Button type="button" size="sm" variant="outline" className="text-red-600" onClick={() => window.confirm(`Hapus ${row.kode_request}?`) && router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true })}><Trash2 size={15} /> Hapus</Button>}
                                                {canLock && row.can_lock && <Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}><Lock size={15} /> Lock</Button>}
                                                {canUnlock && row.can_unlock && row.record_status === 'locked' && <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}><Unlock size={15} /> Unlock</Button>}
                                                {canApproveGudang && row.can_approve_gudang && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/approve`, {}, { preserveScroll: true })}><CheckCircle2 size={15} /> Approve Gudang</Button>}
                                                {canApproveOwner && row.can_approve_owner && <Button type="button" size="sm" onClick={() => router.post(`${baseUrl}/${row.id}/approve-owner`, {}, { preserveScroll: true })}><CheckCircle2 size={15} /> Approve Owner</Button>}
                                                {canIssue && row.can_issue && <Button type="button" size="sm" onClick={() => window.confirm(`Kirim barang untuk ${row.kode_request}?`) && router.post(`${baseUrl}/${row.id}/issue`, {}, { preserveScroll: true })}><Send size={15} /> Kirim Barang</Button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Permintaan Barang'}>{page}</AdminLayout>;

