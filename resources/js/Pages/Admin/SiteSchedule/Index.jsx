import { Head, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, Edit3, Eye, Lock, Search, Trash2, Unlock, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, Dropdown, Form, Input, Modal, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function Index({ title, baseUrl, rows = { data: [], links: [] }, filters = {}, options = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [filterPerumahan, setFilterPerumahan] = useState(filters.perumahan_id ?? '');
    const [filterUnit, setFilterUnit] = useState(filters.detail_rumah_id ?? '');
    const [editing, setEditing] = useState(null);
    const [detail, setDetail] = useState(null);
    const roles = usePage().props.auth?.user?.roles ?? [];
    const canManageLock = roles.some((role) => ['owner', 'super_admin'].includes(role));
    const form = useForm({ perumahan_id: '', detail_rumah_id: '', tahapan_pembangunan_id: '', nama_pekerjaan: '', tanggal_mulai: new Date().toISOString().slice(0, 10), tanggal_target: '', target_progress: 100, realisasi_progress: 0, status: 'direncanakan', kendala: '', catatan: '' });
    const perumahans = options.perumahans ?? [];
    const detailRumahs = options.detailRumahs ?? [];
    const tahapanPembangunans = options.tahapanPembangunans ?? [];
    const unitOptions = useMemo(() => detailRumahs.filter((row) => !form.data.perumahan_id || row.perumahan_id === String(form.data.perumahan_id)), [form.data.perumahan_id, detailRumahs]);
    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        form.setData('tanggal_mulai', new Date().toISOString().slice(0, 10));
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
    const editRow = (row) => {
        setEditing(row);
        form.setData({
            perumahan_id: row.perumahan_id ?? '', detail_rumah_id: row.detail_rumah_id ?? '',
            tahapan_pembangunan_id: row.tahapan_pembangunan_id ?? '', nama_pekerjaan: row.nama_pekerjaan ?? '',
            tanggal_mulai: row.tanggal_mulai ?? '', tanggal_target: row.tanggal_target ?? '',
            target_progress: row.target_progress ?? 100, realisasi_progress: row.realisasi_progress ?? 0,
            status: row.status ?? 'direncanakan', kendala: row.kendala ?? '', catatan: row.catatan ?? '',
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <Form collapsible title={editing ? `Edit ${editing.kode_jadwal}` : 'Rencana Kerja Jangka Pendek'} description="Bandingkan target dengan realisasi dan tandai pekerjaan yang terlambat atau tertahan." onSubmit={submit} actions={<>{editing && <Button type="button" variant="outline" onClick={resetForm}><X size={14} /> Batal</Button>}<Button type="submit"><CalendarClock size={17} /> {editing ? 'Simpan Perubahan' : 'Simpan Jadwal'}</Button></>}>
                    {Object.keys(form.errors).length > 0 && <div className="rounded-lg bg-red-50 p-4 text-sm font-bold text-red-700">{Object.values(form.errors).map((error) => <p key={error}>{error}</p>)}</div>}
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown label="Pilih Perumahan" value={form.data.perumahan_id} options={perumahans} onChange={(value) => form.setData({ ...form.data, perumahan_id: value, detail_rumah_id: '' })} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Unit</span><Dropdown label="Kawasan / Unit" value={form.data.detail_rumah_id} options={unitOptions} onChange={(value) => form.setData('detail_rumah_id', value)} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Tahapan</span><Dropdown label="Pilih Tahapan" value={form.data.tahapan_pembangunan_id} options={tahapanPembangunans} onChange={(value) => form.setData('tahapan_pembangunan_id', value)} /></div>
                    </div>
                    <Input label="Nama Pekerjaan / Target" value={form.data.nama_pekerjaan} onChange={(event) => form.setData('nama_pekerjaan', event.target.value)} />
                    <div className="grid gap-4 md:grid-cols-4"><Input label="Tanggal Mulai" type="date" value={form.data.tanggal_mulai} onChange={(event) => form.setData('tanggal_mulai', event.target.value)} /><Input label="Tanggal Target" type="date" value={form.data.tanggal_target} onChange={(event) => form.setData('tanggal_target', event.target.value)} /><Input label="Target %" type="number" min="0" max="100" value={form.data.target_progress} onChange={(event) => form.setData('target_progress', event.target.value)} /><Input label="Realisasi %" type="number" min="0" max="100" value={form.data.realisasi_progress} onChange={(event) => form.setData('realisasi_progress', event.target.value)} /></div>
                    <div className="grid gap-2"><span className="text-sm font-extrabold">Status</span><Dropdown value={form.data.status} options={[{ value: 'direncanakan', label: 'Direncanakan' }, { value: 'berjalan', label: 'Berjalan' }, { value: 'terlambat', label: 'Terlambat' }, { value: 'tertahan', label: 'Tertahan' }, { value: 'selesai', label: 'Selesai' }]} onChange={(value) => form.setData('status', value)} /></div>
                    <div className="grid gap-4 md:grid-cols-2"><Textarea label="Kendala" value={form.data.kendala} onChange={(event) => form.setData('kendala', event.target.value)} /><Textarea label="Catatan" value={form.data.catatan} onChange={(event) => form.setData('catatan', event.target.value)} /></div>
                </Form>
                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="grid gap-3 p-5 lg:grid-cols-[1.2fr_1fr_1fr_auto]" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search, perumahan_id: filterPerumahan, detail_rumah_id: filterUnit }, { preserveState: true, replace: true }); }}>
                        <Input label="Cari Jadwal" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown value={filterPerumahan} label="Semua Perumahan" options={[{ value: '', label: 'Semua Perumahan' }, ...perumahans]} onChange={(value) => { setFilterPerumahan(value); setFilterUnit(''); }} /></div>
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Unit</span><Dropdown value={filterUnit} label="Semua Unit" options={[{ value: '', label: 'Semua Unit' }, ...detailRumahs.filter((row) => !filterPerumahan || row.perumahan_id === String(filterPerumahan))]} onChange={setFilterUnit} /></div>
                        <div className="flex items-end"><Button className="w-full"><Search size={16} /> Cari</Button></div>
                    </form>
                    <div className="overflow-x-auto"><table className="min-w-full text-sm">
                        <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft"><tr>{['Pekerjaan', 'Lokasi', 'Periode', 'Target / Realisasi', 'Status', 'Kendala', 'Audit', 'Aksi'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr></thead>
                        <tbody className="divide-y divide-silver-deep/50">{rows.data.map((row) => <tr className={row.terlambat ? 'bg-red-50/60 dark:bg-red-500/5' : ''} key={row.id}>
                            <td className="px-5 py-4 font-bold">{row.nama_pekerjaan}<br /><span className="text-xs text-ink-soft">{row.tahapan}</span></td><td className="px-5 py-4">{row.perumahan}<br /><span className="text-xs text-ink-soft">{row.unit}</span></td>
                            <td className="px-5 py-4">{row.tanggal_mulai} s/d {row.tanggal_target}</td><td className="px-5 py-4 font-bold">{row.target_progress}% / {row.realisasi_progress}%</td><td className="px-5 py-4 font-bold">{row.terlambat ? 'Terlambat' : row.status}</td><td className="max-w-xs px-5 py-4">{row.kendala || '-'}</td>
                            <td className="min-w-44 px-5 py-4 text-xs"><span className="font-bold">Dibuat:</span> {row.created_by_name}<br /><span className="font-bold">Diubah:</span> {row.updated_by_name}</td>
                            <td className="px-5 py-4"><div className="flex flex-wrap gap-2">
                                <Button type="button" size="sm" variant="outline" onClick={() => setDetail(row)}><Eye size={14} /> Detail</Button>
                                {row.can_edit && <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={14} /> Edit</Button>}
                                {row.can_delete && <Button type="button" size="sm" variant="outline" className="text-red-600" onClick={() => window.confirm('Hapus jadwal?') && router.delete(`${baseUrl}/${row.id}`)}><Trash2 size={14} /></Button>}
                                {row.can_lock && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/lock`)}><Lock size={14} /> Lock</Button>}
                                {row.can_unlock && row.record_status === 'locked' && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/unlock`)}><Unlock size={14} /> Unlock</Button>}
                            </div></td>
                        </tr>)}{rows.data.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={8}>Belum ada jadwal lapangan.</td></tr>}</tbody>
                    </table></div>
                    <Pagination links={rows.links} />
                </section>
            </div>
            <Modal open={Boolean(detail)} onClose={() => setDetail(null)} title={detail ? `Detail ${detail.kode_jadwal}` : 'Detail Jadwal'} footer={<Button type="button" variant="outline" onClick={() => setDetail(null)}>Tutup</Button>}>
                {detail && <div className="grid gap-3 text-sm">
                    <p><b>Pekerjaan:</b> {detail.nama_pekerjaan}</p>
                    <p><b>Lokasi:</b> {detail.perumahan} - {detail.unit} - {detail.tahapan}</p>
                    <p><b>Periode:</b> {detail.tanggal_mulai} s/d {detail.tanggal_target}</p>
                    <p><b>Progress:</b> Target {detail.target_progress}% / Realisasi {detail.realisasi_progress}%</p>
                    <p><b>Status:</b> {detail.terlambat ? 'Terlambat' : detail.status}</p>
                    <p><b>Kendala:</b> {detail.kendala || '-'}</p>
                    <p><b>Catatan:</b> {detail.catatan || '-'}</p>
                </div>}
            </Modal>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Jadwal Lapangan'}>{page}</AdminLayout>;
