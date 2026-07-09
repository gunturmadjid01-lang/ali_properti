import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Edit3, Eye, FileText, Lock, Search, Trash2, Unlock, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, Dropdown, Form, Input, Modal, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';
import { scopedTahapanOptions } from '../../../Utils/tahapanOptions';

export default function Index({ title, baseUrl, rows = { data: [], links: [] }, filters = {}, options = {}, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [filterPerumahan, setFilterPerumahan] = useState(filters.perumahan_id ?? '');
    const [filterUnit, setFilterUnit] = useState(filters.detail_rumah_id ?? '');
    const [editing, setEditing] = useState(null);
    const [detail, setDetail] = useState(null);
    const canCreate = permissions.canCreate ?? false;
    const canUpdate = permissions.canUpdate ?? false;
    const canDelete = permissions.canDelete ?? false;
    const canLock = permissions.canLock ?? false;
    const canUnlock = permissions.canUnlock ?? false;
    const form = useForm({
        jenis_laporan: 'harian', tanggal: new Date().toISOString().slice(0, 10), periode_mulai: '', periode_selesai: '',
        perumahan_id: '', detail_rumah_id: '', tahapan_pembangunan_id: '', site_schedule_id: '', progress_pembangunan_id: '', cuaca: '', jumlah_pekerja: 0,
        kontraktor: '', pekerjaan_selesai: '', pekerjaan_tertahan: '', kendala: '', koordinasi: '', rencana_berikutnya: '', lampiran: null,
    });
    const perumahans = options.perumahans ?? [];
    const detailRumahs = options.detailRumahs ?? [];
    const tahapanPembangunansUnit = options.tahapanPembangunansUnit ?? options.tahapanPembangunans ?? [];
    const tahapanPembangunansKawasan = options.tahapanPembangunansKawasan ?? options.tahapanPembangunans ?? [];
    const resolveScopedValue = (selectedValue, fallbackValue) => ((selectedValue !== undefined && selectedValue !== null && selectedValue !== '') ? selectedValue : fallbackValue);
    const tahapanPembangunans = useMemo(
        () => scopedTahapanOptions(
            form.data.detail_rumah_id ? tahapanPembangunansUnit : tahapanPembangunansKawasan,
            form.data.perumahan_id,
            form.data.detail_rumah_id,
        ),
        [form.data.detail_rumah_id, form.data.perumahan_id, tahapanPembangunansKawasan, tahapanPembangunansUnit],
    );
    const unitOptions = useMemo(() => detailRumahs.filter((row) => !form.data.perumahan_id || row.perumahan_id === String(form.data.perumahan_id)), [form.data.perumahan_id, detailRumahs]);
    const scheduleOptions = useMemo(() => (options.siteSchedules ?? []).filter((row) => !form.data.detail_rumah_id || row.detail_rumah_id === String(form.data.detail_rumah_id)), [form.data.detail_rumah_id, options.siteSchedules]);
    const progressOptions = useMemo(() => (options.progressPembangunans ?? []).filter((row) => !form.data.detail_rumah_id || row.detail_rumah_id === String(form.data.detail_rumah_id)), [form.data.detail_rumah_id, options.progressPembangunans]);

    useEffect(() => {
        if (!form.data.tahapan_pembangunan_id) {
            return;
        }

        const valid = tahapanPembangunans.some((option) => option.value === String(form.data.tahapan_pembangunan_id));

        if (!valid) {
            form.setData('tahapan_pembangunan_id', '');
        }
    }, [form, form.data.tahapan_pembangunan_id, tahapanPembangunans]);
    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { forceFormData: true, preserveScroll: true, onSuccess: resetForm };
        if (editing) {
            form.put(`${baseUrl}/${editing.id}`, requestOptions);
            return;
        }
        form.post(baseUrl, requestOptions);
    };
    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        form.setData('tanggal', new Date().toISOString().slice(0, 10));
    };
    const editRow = (row) => {
        setEditing(row);
        form.setData({
            jenis_laporan: row.jenis_laporan ?? 'harian', tanggal: row.tanggal ?? '', periode_mulai: row.periode_mulai ?? '', periode_selesai: row.periode_selesai ?? '',
            perumahan_id: row.perumahan_id ?? '', detail_rumah_id: row.detail_rumah_id ?? '', tahapan_pembangunan_id: row.tahapan_pembangunan_id ?? '', site_schedule_id: row.site_schedule_id ?? '', progress_pembangunan_id: row.progress_pembangunan_id ?? '',
            cuaca: row.cuaca ?? '', jumlah_pekerja: row.jumlah_pekerja ?? 0, kontraktor: row.kontraktor ?? '',
            pekerjaan_selesai: row.pekerjaan_selesai ?? '', pekerjaan_tertahan: row.pekerjaan_tertahan ?? '', kendala: row.kendala ?? '',
            koordinasi: row.koordinasi ?? '', rencana_berikutnya: row.rencana_berikutnya ?? '', lampiran: null,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                {(canCreate || (editing && canUpdate)) && (
                <Form collapsible title={editing ? `Edit ${editing.kode_laporan}` : 'Input Laporan Harian / Mingguan'} description="Berfungsi sekaligus sebagai site diary, laporan pekerjaan, kendala, koordinasi, dan rencana kerja berikutnya." onSubmit={submit} actions={<>{editing && canUpdate && <Button type="button" variant="outline" onClick={resetForm}><X size={15} /> Batal</Button>}<Button type="submit" disabled={form.processing}><FileText size={17} /> {editing ? 'Simpan Perubahan' : 'Simpan Laporan'}</Button></>}>
                    {Object.keys(form.errors).length > 0 && <div className="rounded-lg bg-red-50 p-4 text-sm font-bold text-red-700">{Object.values(form.errors).map((error) => <p key={error}>{error}</p>)}</div>}
                    <div className="grid gap-4 md:grid-cols-4">
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Jenis Laporan</span><Dropdown value={form.data.jenis_laporan} options={[{ value: 'harian', label: 'Harian' }, { value: 'mingguan', label: 'Mingguan' }]} onChange={(value) => form.setData('jenis_laporan', value)} /></div>
                        <Input label="Tanggal Laporan" type="date" value={form.data.tanggal} onChange={(event) => form.setData('tanggal', event.target.value)} />
                        {form.data.jenis_laporan === 'mingguan' && <Input label="Periode Mulai" type="date" value={form.data.periode_mulai} onChange={(event) => form.setData('periode_mulai', event.target.value)} />}
                        {form.data.jenis_laporan === 'mingguan' && <Input label="Periode Selesai" type="date" value={form.data.periode_selesai} onChange={(event) => form.setData('periode_selesai', event.target.value)} />}
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown label="Pilih Perumahan" value={form.data.perumahan_id} options={perumahans} onChange={(value) => form.setData({ ...form.data, perumahan_id: value, detail_rumah_id: '', tahapan_pembangunan_id: '', site_schedule_id: '', progress_pembangunan_id: '' })} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Unit (Opsional)</span><Dropdown label="Kawasan / Pilih Unit" value={form.data.detail_rumah_id} options={unitOptions} onChange={(value, selected) => form.setData({ ...form.data, detail_rumah_id: value, perumahan_id: resolveScopedValue(selected?.perumahan_id, form.data.perumahan_id), tahapan_pembangunan_id: '', site_schedule_id: '', progress_pembangunan_id: '' })} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Tahapan</span><Dropdown label={form.data.detail_rumah_id ? 'Pilih Tahapan Rumah' : 'Pilih Tahapan Kawasan'} value={form.data.tahapan_pembangunan_id} options={tahapanPembangunans} onChange={(value) => form.setData('tahapan_pembangunan_id', value)} /></div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Jadwal Lapangan Terkait</span><Dropdown label="Pilih Jadwal" value={form.data.site_schedule_id} options={scheduleOptions} onChange={(value, selected) => form.setData({ ...form.data, site_schedule_id: value, perumahan_id: resolveScopedValue(selected?.perumahan_id, form.data.perumahan_id), detail_rumah_id: resolveScopedValue(selected?.detail_rumah_id, form.data.detail_rumah_id), tahapan_pembangunan_id: resolveScopedValue(selected?.tahapan_pembangunan_id, form.data.tahapan_pembangunan_id), pekerjaan_selesai: form.data.pekerjaan_selesai || selected?.nama_pekerjaan || '' })} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Progress Terkait</span><Dropdown label="Pilih Progress Approved" value={form.data.progress_pembangunan_id} options={progressOptions} onChange={(value, selected) => form.setData({ ...form.data, progress_pembangunan_id: value, site_schedule_id: resolveScopedValue(selected?.site_schedule_id, form.data.site_schedule_id), perumahan_id: resolveScopedValue(selected?.perumahan_id, form.data.perumahan_id), detail_rumah_id: resolveScopedValue(selected?.detail_rumah_id, form.data.detail_rumah_id), tahapan_pembangunan_id: resolveScopedValue(selected?.tahapan_pembangunan_id, form.data.tahapan_pembangunan_id), pekerjaan_selesai: form.data.pekerjaan_selesai || selected?.nama_progress || '' })} /></div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Input label="Cuaca" value={form.data.cuaca} onChange={(event) => form.setData('cuaca', event.target.value)} />
                        <Input label="Jumlah Pekerja" type="number" min="0" value={form.data.jumlah_pekerja} onChange={(event) => form.setData('jumlah_pekerja', event.target.value)} />
                        <Input label="Tukang / Kontraktor" value={form.data.kontraktor} onChange={(event) => form.setData('kontraktor', event.target.value)} />
                    </div>
                    <Textarea label="Pekerjaan Selesai" value={form.data.pekerjaan_selesai} onChange={(event) => form.setData('pekerjaan_selesai', event.target.value)} />
                    <div className="grid gap-4 md:grid-cols-2"><Textarea label="Pekerjaan Tertahan" value={form.data.pekerjaan_tertahan} onChange={(event) => form.setData('pekerjaan_tertahan', event.target.value)} /><Textarea label="Kendala Lapangan" value={form.data.kendala} onChange={(event) => form.setData('kendala', event.target.value)} /></div>
                    <div className="grid gap-4 md:grid-cols-2"><Textarea label="Koordinasi / Keputusan" value={form.data.koordinasi} onChange={(event) => form.setData('koordinasi', event.target.value)} /><Textarea label="Rencana Berikutnya" value={form.data.rencana_berikutnya} onChange={(event) => form.setData('rencana_berikutnya', event.target.value)} /></div>
                    <div className="grid gap-2"><span className="text-sm font-extrabold">Lampiran Foto / Dokumen</span><input type="file" className="min-h-11 rounded-lg border border-silver-deep/70 p-2" onChange={(event) => form.setData('lampiran', event.target.files?.[0] ?? null)} /></div>
                </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="grid gap-3 p-5 lg:grid-cols-[1.2fr_1fr_1fr_auto]" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search, perumahan_id: filterPerumahan, detail_rumah_id: filterUnit }, { preserveState: true, replace: true }); }}>
                        <Input label="Cari Laporan" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown value={filterPerumahan} label="Semua Perumahan" options={[{ value: '', label: 'Semua Perumahan' }, ...perumahans]} onChange={(value) => { setFilterPerumahan(value); setFilterUnit(''); }} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Unit</span><Dropdown value={filterUnit} label="Semua Unit" options={[{ value: '', label: 'Semua Unit' }, ...detailRumahs.filter((row) => !filterPerumahan || row.perumahan_id === String(filterPerumahan))]} onChange={setFilterUnit} /></div>
                        <div className="flex items-end"><Button className="w-full"><Search size={16} /> Cari</Button></div>
                    </form>
                    <div className="overflow-x-auto"><table className="min-w-full text-sm">
                        <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft"><tr>{['Tanggal', 'Lokasi', 'Pekerjaan', 'Kendala / Rencana', 'Approval', 'Audit', 'Aksi'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr></thead>
                        <tbody className="divide-y divide-silver-deep/50">{rows.data.map((row) => <tr key={row.id}>
                            <td className="px-5 py-4 font-bold">{row.tanggal}<br /><span className="text-xs uppercase text-ink-soft">{row.jenis_laporan}</span></td>
                            <td className="px-5 py-4">{row.perumahan}<br /><span className="text-xs text-ink-soft">{row.unit} · {row.tahapan}</span></td>
                            <td className="max-w-md px-5 py-4">{row.pekerjaan_selesai}<br /><span className="text-xs text-ink-soft">{row.jumlah_pekerja} pekerja - {row.cuaca || '-'} {row.progress !== '-' ? `- ${row.progress}` : row.jadwal !== '-' ? `- ${row.jadwal}` : ''}</span></td>
                            <td className="max-w-sm px-5 py-4">{row.kendala || '-'}<br /><span className="text-xs text-ink-soft">Berikutnya: {row.rencana_berikutnya || '-'}</span></td>
                            <td className="px-5 py-4 font-bold">{row.approval_status}</td>
                            <td className="min-w-44 px-5 py-4 text-xs"><span className="font-bold">Dibuat:</span> {row.created_by_name}<br /><span className="font-bold">Diubah:</span> {row.updated_by_name}<br /><span className="font-bold">Approve:</span> {row.approved_by_name}</td>
                            <td className="px-5 py-4"><div className="flex flex-wrap gap-2">
                                <Button type="button" size="sm" variant="outline" onClick={() => setDetail(row)}><Eye size={14} /> Detail</Button>
                                {row.lampiran_url && <Button as="a" href={row.lampiran_url} target="_blank" size="sm" variant="outline">Lampiran</Button>}
                                {canUpdate && row.can_edit && <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={14} /> Edit</Button>}
                                {row.can_approve && row.approval_status !== 'approved' && <Button type="button" size="sm" onClick={() => router.post(`${baseUrl}/${row.id}/approve`)}><CheckCircle2 size={14} /> Approve</Button>}
                                {canDelete && row.can_delete && <Button type="button" size="sm" variant="outline" className="text-red-600" onClick={() => window.confirm('Hapus laporan?') && router.delete(`${baseUrl}/${row.id}`)}><Trash2 size={14} /></Button>}
                                {canLock && row.can_lock && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/lock`)}><Lock size={14} /> Lock</Button>}
                                {canUnlock && row.can_unlock && row.record_status === 'locked' && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/unlock`)}><Unlock size={14} /> Unlock</Button>}
                            </div></td>
                        </tr>)}{rows.data.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={7}>Belum ada laporan lapangan.</td></tr>}</tbody>
                    </table></div>
                    <Pagination links={rows.links} />
                </section>
            </div>
            <Modal open={Boolean(detail)} onClose={() => setDetail(null)} title={detail ? `Detail ${detail.kode_laporan}` : 'Detail Laporan'} footer={<Button type="button" variant="outline" onClick={() => setDetail(null)}>Tutup</Button>}>
                {detail && <div className="grid gap-3 text-sm">
                    <p><b>Lokasi:</b> {detail.perumahan} - {detail.unit} - {detail.tahapan}</p>
                    <p><b>Pekerjaan selesai:</b> {detail.pekerjaan_selesai}</p>
                    <p><b>Pekerjaan tertahan:</b> {detail.pekerjaan_tertahan || '-'}</p>
                    <p><b>Kendala:</b> {detail.kendala || '-'}</p>
                    <p><b>Koordinasi:</b> {detail.koordinasi || '-'}</p>
                    <p><b>Rencana berikutnya:</b> {detail.rencana_berikutnya || '-'}</p>
                </div>}
            </Modal>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Laporan Lapangan'}>{page}</AdminLayout>;
