import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, ClipboardList, Fuel, Hammer, PlayCircle, RotateCcw, Save, Search, Wrench } from 'lucide-react';
import { useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, CurrencyInput, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const today = () => new Date().toISOString().slice(0, 10);
const nowInput = () => new Date().toISOString().slice(0, 16);
const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));

export default function Index({ title, description, baseUrl, assets = { data: [], links: [] }, requests = [], usageLogs = [], maintenanceLogs = [], options = {}, permissions = {}, filters = {} }) {
    const [tab, setTab] = useState('assets');
    const [search, setSearch] = useState(filters.search ?? '');
    const [editingAsset, setEditingAsset] = useState(null);
    const assetForm = useForm({ nama_aset: '', kategori: '', tipe_aset: 'alat_kecil', nomor_seri: '', plat_nomor: '', lokasi_sekarang: '', kondisi: 'baik', status: 'tersedia', nilai_aset: 0, hour_meter_terakhir: 0, odometer_terakhir: 0, penanggung_jawab_id: '', catatan: '' });
    const requestForm = useForm({ office_asset_id: '', perumahan_id: '', detail_rumah_id: '', nama_peminjam: '', tanggal_mulai: today(), tanggal_selesai_estimasi: '', tujuan_pemakaian: '', lokasi_pemakaian: '' });
    const usageForm = useForm({ office_asset_id: '', asset_usage_request_id: '', mulai_pakai: nowInput(), selesai_pakai: '', hour_meter_awal: 0, hour_meter_akhir: 0, odometer_awal: 0, odometer_akhir: 0, bbm_liter: 0, biaya_bbm: 0, operator: '', kondisi_sebelum: '', kondisi_sesudah: '', lokasi: '', pekerjaan: '', catatan: '' });
    const maintenanceForm = useForm({ office_asset_id: '', tanggal_servis: today(), jenis_servis: 'rutin', hour_meter: 0, odometer: 0, pekerjaan_servis: '', sparepart: '', biaya: 0, teknisi: '', jadwal_berikutnya: '', status: 'selesai' });
    const detailOptions = (options.detailRumahs ?? []).filter((row) => !requestForm.data.perumahan_id || row.perumahan_id === String(requestForm.data.perumahan_id));

    const submitAsset = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true, onSuccess: () => { assetForm.reset(); setEditingAsset(null); } };
        if (editingAsset) {
            assetForm.put(`${baseUrl}/assets/${editingAsset.id}`, requestOptions);
            return;
        }
        assetForm.post(`${baseUrl}/assets`, requestOptions);
    };

    const editAsset = (asset) => {
        setEditingAsset(asset);
        assetForm.setData({
            nama_aset: asset.nama_aset ?? '', kategori: asset.kategori ?? '', tipe_aset: asset.tipe_aset ?? 'alat_kecil',
            nomor_seri: asset.nomor_seri ?? '', plat_nomor: asset.plat_nomor ?? '', lokasi_sekarang: asset.lokasi_sekarang ?? '',
            kondisi: asset.kondisi ?? 'baik', status: asset.status ?? 'tersedia', nilai_aset: asset.nilai_aset ?? 0,
            hour_meter_terakhir: asset.hour_meter_terakhir ?? 0, odometer_terakhir: asset.odometer_terakhir ?? 0,
            penanggung_jawab_id: asset.penanggung_jawab_id ?? '', catatan: asset.catatan ?? '',
        });
        setTab('assets');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submitSimple = (event, form, url, success) => {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: success });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Inventaris</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <div className="flex flex-wrap gap-2">
                    {[['assets', 'Master Aset'], ['requests', 'Pengajuan Alat'], ['usage', 'Log Pemakaian'], ['maintenance', 'Servis']].map(([value, label]) => (
                        <Button key={value} type="button" variant={tab === value ? 'primary' : 'outline'} onClick={() => setTab(value)}>{label}</Button>
                    ))}
                </div>

                {tab === 'assets' && permissions.canManageAssets && (
                    <Form collapsible title={editingAsset ? `Edit ${editingAsset.kode_aset}` : 'Tambah Aset'} description="Admin mencatat aset kantor/proyek, termasuk alat berat dan kendaraan." onSubmit={submitAsset} actions={<Button type="submit" disabled={assetForm.processing}><Save size={16} /> {editingAsset ? 'Simpan Perubahan' : 'Simpan Aset'}</Button>}>
                        {Object.keys(assetForm.errors).length > 0 && <div className="rounded-lg bg-red-50 p-4 text-sm font-bold text-red-700">{Object.values(assetForm.errors).map((error) => <p key={error}>{error}</p>)}</div>}
                        <div className="grid gap-4 md:grid-cols-4">
                            <Input label="Nama Aset" value={assetForm.data.nama_aset} onChange={(event) => assetForm.setData('nama_aset', event.target.value)} />
                            <Input label="Kategori" value={assetForm.data.kategori} onChange={(event) => assetForm.setData('kategori', event.target.value)} />
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Tipe Aset</span><Dropdown value={assetForm.data.tipe_aset} options={options.assetTypes ?? []} onChange={(value) => assetForm.setData('tipe_aset', value)} /></div>
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Status</span><Dropdown value={assetForm.data.status} options={options.assetStatuses ?? []} onChange={(value) => assetForm.setData('status', value)} /></div>
                            <Input label="Nomor Seri" value={assetForm.data.nomor_seri} onChange={(event) => assetForm.setData('nomor_seri', event.target.value)} />
                            <Input label="Plat Nomor" value={assetForm.data.plat_nomor} onChange={(event) => assetForm.setData('plat_nomor', event.target.value)} />
                            <Input label="Lokasi Sekarang" value={assetForm.data.lokasi_sekarang} onChange={(event) => assetForm.setData('lokasi_sekarang', event.target.value)} />
                            <Input label="Kondisi" value={assetForm.data.kondisi} onChange={(event) => assetForm.setData('kondisi', event.target.value)} />
                            <CurrencyInput label="Nilai Aset" value={assetForm.data.nilai_aset} onChange={(value) => assetForm.setData('nilai_aset', value)} />
                            <Input label="Hour Meter Terakhir" type="number" value={assetForm.data.hour_meter_terakhir} onChange={(event) => assetForm.setData('hour_meter_terakhir', event.target.value)} />
                            <Input label="Odometer / KM Terakhir" type="number" value={assetForm.data.odometer_terakhir} onChange={(event) => assetForm.setData('odometer_terakhir', event.target.value)} />
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Penanggung Jawab</span><Dropdown label="Pilih User" value={assetForm.data.penanggung_jawab_id} options={options.users ?? []} onChange={(value) => assetForm.setData('penanggung_jawab_id', value)} /></div>
                        </div>
                        <Textarea label="Catatan" value={assetForm.data.catatan} onChange={(event) => assetForm.setData('catatan', event.target.value)} />
                    </Form>
                )}

                {tab === 'requests' && (
                    <Form collapsible title="Pengajuan Pemakaian Alat" description="Pengawas/admin mengajukan alat untuk pekerjaan lapangan." onSubmit={(event) => submitSimple(event, requestForm, `${baseUrl}/requests`, () => requestForm.reset())} actions={<Button type="submit" disabled={requestForm.processing}><ClipboardList size={16} /> Ajukan Alat</Button>}>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Aset</span><Dropdown label="Pilih Aset" value={requestForm.data.office_asset_id} options={options.assets ?? []} onChange={(value) => requestForm.setData('office_asset_id', value)} /></div>
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan</span><Dropdown label="Pilih Perumahan" value={requestForm.data.perumahan_id} options={options.perumahans ?? []} onChange={(value) => requestForm.setData({ ...requestForm.data, perumahan_id: value, detail_rumah_id: '' })} /></div>
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Unit</span><Dropdown label="Pilih Unit" value={requestForm.data.detail_rumah_id} options={detailOptions} onChange={(value) => requestForm.setData('detail_rumah_id', value)} /></div>
                            <Input label="Nama Peminjam" value={requestForm.data.nama_peminjam} onChange={(event) => requestForm.setData('nama_peminjam', event.target.value)} />
                            <Input label="Tanggal Mulai" type="date" value={requestForm.data.tanggal_mulai} onChange={(event) => requestForm.setData('tanggal_mulai', event.target.value)} />
                            <Input label="Estimasi Selesai" type="date" value={requestForm.data.tanggal_selesai_estimasi} onChange={(event) => requestForm.setData('tanggal_selesai_estimasi', event.target.value)} />
                            <Input label="Lokasi Pemakaian" value={requestForm.data.lokasi_pemakaian} onChange={(event) => requestForm.setData('lokasi_pemakaian', event.target.value)} />
                        </div>
                        <Textarea label="Tujuan Pemakaian" value={requestForm.data.tujuan_pemakaian} onChange={(event) => requestForm.setData('tujuan_pemakaian', event.target.value)} />
                    </Form>
                )}

                {tab === 'usage' && (
                    <Form collapsible title="Log Pemakaian Aset" description="Catat siapa yang pakai, durasi, hour meter, kilometer, dan BBM." onSubmit={(event) => submitSimple(event, usageForm, `${baseUrl}/usage`, () => usageForm.reset())} actions={<Button type="submit" disabled={usageForm.processing}><Fuel size={16} /> Simpan Log</Button>}>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Pengajuan</span><Dropdown label="Opsional" value={usageForm.data.asset_usage_request_id} options={options.approvedRequests ?? []} onChange={(value, selected) => usageForm.setData({ ...usageForm.data, asset_usage_request_id: value, office_asset_id: selected?.office_asset_id ?? usageForm.data.office_asset_id, lokasi: selected?.lokasi ?? usageForm.data.lokasi })} /></div>
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Aset</span><Dropdown label="Pilih Aset" value={usageForm.data.office_asset_id} options={options.assets ?? []} onChange={(value) => usageForm.setData('office_asset_id', value)} /></div>
                            <Input label="Mulai Pakai" type="datetime-local" value={usageForm.data.mulai_pakai} onChange={(event) => usageForm.setData('mulai_pakai', event.target.value)} />
                            <Input label="Selesai Pakai" type="datetime-local" value={usageForm.data.selesai_pakai} onChange={(event) => usageForm.setData('selesai_pakai', event.target.value)} />
                            <Input label="HM Awal" type="number" value={usageForm.data.hour_meter_awal} onChange={(event) => usageForm.setData('hour_meter_awal', event.target.value)} />
                            <Input label="HM Akhir" type="number" value={usageForm.data.hour_meter_akhir} onChange={(event) => usageForm.setData('hour_meter_akhir', event.target.value)} />
                            <Input label="KM Awal" type="number" value={usageForm.data.odometer_awal} onChange={(event) => usageForm.setData('odometer_awal', event.target.value)} />
                            <Input label="KM Akhir" type="number" value={usageForm.data.odometer_akhir} onChange={(event) => usageForm.setData('odometer_akhir', event.target.value)} />
                            <Input label="BBM Liter" type="number" value={usageForm.data.bbm_liter} onChange={(event) => usageForm.setData('bbm_liter', event.target.value)} />
                            <CurrencyInput label="Biaya BBM" value={usageForm.data.biaya_bbm} onChange={(value) => usageForm.setData('biaya_bbm', value)} />
                            <Input label="Operator" value={usageForm.data.operator} onChange={(event) => usageForm.setData('operator', event.target.value)} />
                            <Input label="Lokasi" value={usageForm.data.lokasi} onChange={(event) => usageForm.setData('lokasi', event.target.value)} />
                        </div>
                        <div className="grid gap-4 md:grid-cols-2"><Input label="Kondisi Sebelum" value={usageForm.data.kondisi_sebelum} onChange={(event) => usageForm.setData('kondisi_sebelum', event.target.value)} /><Input label="Kondisi Sesudah" value={usageForm.data.kondisi_sesudah} onChange={(event) => usageForm.setData('kondisi_sesudah', event.target.value)} /></div>
                        <Textarea label="Pekerjaan" value={usageForm.data.pekerjaan} onChange={(event) => usageForm.setData('pekerjaan', event.target.value)} />
                    </Form>
                )}

                {tab === 'maintenance' && permissions.canManageAssets && (
                    <Form collapsible title="Catatan Servis / Maintenance" description="Untuk alat berat, kendaraan, genset, dan aset lain yang butuh perawatan." onSubmit={(event) => submitSimple(event, maintenanceForm, `${baseUrl}/maintenance`, () => maintenanceForm.reset())} actions={<Button type="submit" disabled={maintenanceForm.processing}><Hammer size={16} /> Simpan Servis</Button>}>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Aset</span><Dropdown label="Pilih Aset" value={maintenanceForm.data.office_asset_id} options={options.assets ?? []} onChange={(value) => maintenanceForm.setData('office_asset_id', value)} /></div>
                            <Input label="Tanggal Servis" type="date" value={maintenanceForm.data.tanggal_servis} onChange={(event) => maintenanceForm.setData('tanggal_servis', event.target.value)} />
                            <Input label="Jenis Servis" value={maintenanceForm.data.jenis_servis} onChange={(event) => maintenanceForm.setData('jenis_servis', event.target.value)} />
                            <Input label="Teknisi" value={maintenanceForm.data.teknisi} onChange={(event) => maintenanceForm.setData('teknisi', event.target.value)} />
                            <Input label="Hour Meter" type="number" value={maintenanceForm.data.hour_meter} onChange={(event) => maintenanceForm.setData('hour_meter', event.target.value)} />
                            <Input label="Odometer / KM" type="number" value={maintenanceForm.data.odometer} onChange={(event) => maintenanceForm.setData('odometer', event.target.value)} />
                            <CurrencyInput label="Biaya" value={maintenanceForm.data.biaya} onChange={(value) => maintenanceForm.setData('biaya', value)} />
                            <Input label="Jadwal Berikutnya" type="date" value={maintenanceForm.data.jadwal_berikutnya} onChange={(event) => maintenanceForm.setData('jadwal_berikutnya', event.target.value)} />
                        </div>
                        <Textarea label="Pekerjaan Servis" value={maintenanceForm.data.pekerjaan_servis} onChange={(event) => maintenanceForm.setData('pekerjaan_servis', event.target.value)} />
                        <Textarea label="Sparepart" value={maintenanceForm.data.sparepart} onChange={(event) => maintenanceForm.setData('sparepart', event.target.value)} />
                    </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="grid gap-3 p-5 md:grid-cols-[1fr_auto]" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { search }, { preserveState: true, replace: true }); }}>
                        <Input label="Cari Aset" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <div className="flex items-end"><Button><Search size={16} /> Cari</Button></div>
                    </form>
                    {tab === 'assets' && <AssetTable rows={assets.data} links={assets.links} onEdit={editAsset} permissions={permissions} />}
                    {tab === 'requests' && <RequestTable rows={requests} baseUrl={baseUrl} permissions={permissions} />}
                    {tab === 'usage' && <UsageTable rows={usageLogs} />}
                    {tab === 'maintenance' && <MaintenanceTable rows={maintenanceLogs} />}
                </section>
            </div>
        </>
    );
}

function AssetTable({ rows, links, onEdit, permissions }) {
    return <><div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft"><tr>{['Kode', 'Aset', 'Tipe', 'Status', 'Lokasi', 'HM/KM', 'Nilai', 'Aksi'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr></thead><tbody className="divide-y divide-silver-deep/50">{rows.map((row) => <tr key={row.id}><td className="px-5 py-4 font-bold">{row.kode_aset}</td><td className="px-5 py-4">{row.nama_aset}<br /><span className="text-xs text-ink-soft">{row.kategori} - {row.kondisi}</span></td><td className="px-5 py-4 font-bold">{row.tipe_aset}</td><td className="px-5 py-4 font-bold">{row.status}</td><td className="px-5 py-4">{row.lokasi_sekarang || '-'}</td><td className="px-5 py-4">HM {row.hour_meter_terakhir}<br />KM {row.odometer_terakhir}</td><td className="px-5 py-4 font-bold">{money(row.nilai_aset)}</td><td className="px-5 py-4">{permissions.canManageAssets && <Button type="button" size="sm" variant="outline" onClick={() => onEdit(row)}><Wrench size={14} /> Edit</Button>}</td></tr>)}{rows.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={8}>Belum ada aset.</td></tr>}</tbody></table></div><Pagination links={links} /></>;
}

function RequestTable({ rows, baseUrl, permissions }) {
    return <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft"><tr>{['Kode', 'Aset', 'Peminjam', 'Lokasi', 'Tanggal', 'Tujuan', 'Status', 'Aksi'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr></thead><tbody className="divide-y divide-silver-deep/50">{rows.map((row) => <tr key={row.id}><td className="px-5 py-4 font-bold">{row.kode_pengajuan}</td><td className="px-5 py-4">{row.aset}</td><td className="px-5 py-4 font-bold">{row.nama_peminjam}<br /><span className="text-xs font-normal text-ink-soft">Input: {row.requested_by}</span></td><td className="px-5 py-4">{row.perumahan}<br /><span className="text-xs text-ink-soft">{row.unit} - {row.lokasi_pemakaian || '-'}</span></td><td className="px-5 py-4">{row.tanggal_mulai} s/d {row.tanggal_selesai_estimasi || '-'}</td><td className="max-w-sm px-5 py-4">{row.tujuan_pemakaian}</td><td className="px-5 py-4 font-bold">{row.status}</td><td className="px-5 py-4"><div className="flex flex-wrap gap-2">{permissions.canApprove && row.status === 'diajukan' && <Button type="button" size="sm" onClick={() => router.post(`${baseUrl}/requests/${row.id}/approve`, {}, { preserveScroll: true })}><CheckCircle2 size={14} /> Approve</Button>}{permissions.canManageAssets && row.status === 'disetujui' && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/requests/${row.id}/issue`, {}, { preserveScroll: true })}><PlayCircle size={14} /> Serahkan</Button>}{permissions.canManageAssets && row.status === 'dipakai' && <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/requests/${row.id}/return`, {}, { preserveScroll: true })}><RotateCcw size={14} /> Kembali</Button>}</div></td></tr>)}{rows.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={8}>Belum ada pengajuan alat.</td></tr>}</tbody></table></div>;
}

function UsageTable({ rows }) {
    return <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft"><tr>{['Kode', 'Aset', 'Pemakai', 'Waktu', 'HM/KM', 'BBM', 'Pekerjaan'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr></thead><tbody className="divide-y divide-silver-deep/50">{rows.map((row) => <tr key={row.id}><td className="px-5 py-4 font-bold">{row.kode_log}</td><td className="px-5 py-4">{row.aset}</td><td className="px-5 py-4">{row.used_by}<br /><span className="text-xs text-ink-soft">{row.operator || '-'}</span></td><td className="px-5 py-4">{row.mulai_pakai}<br /><span className="text-xs text-ink-soft">{row.selesai_pakai || '-'} | {row.durasi_jam} jam</span></td><td className="px-5 py-4">HM {row.hm}<br />KM {row.km}</td><td className="px-5 py-4">{row.bbm_liter} liter<br /><span className="text-xs text-ink-soft">{money(row.biaya_bbm)}</span></td><td className="max-w-sm px-5 py-4">{row.pekerjaan || '-'}</td></tr>)}{rows.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={7}>Belum ada log pemakaian.</td></tr>}</tbody></table></div>;
}

function MaintenanceTable({ rows }) {
    return <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft"><tr>{['Kode', 'Aset', 'Tanggal', 'Jenis', 'HM/KM', 'Pekerjaan', 'Biaya', 'Berikutnya'].map((label) => <th className="px-5 py-4" key={label}>{label}</th>)}</tr></thead><tbody className="divide-y divide-silver-deep/50">{rows.map((row) => <tr key={row.id}><td className="px-5 py-4 font-bold">{row.kode_servis}</td><td className="px-5 py-4">{row.aset}<br /><span className="text-xs text-ink-soft">{row.teknisi || '-'}</span></td><td className="px-5 py-4">{row.tanggal_servis}</td><td className="px-5 py-4 font-bold">{row.jenis_servis}</td><td className="px-5 py-4">HM {row.hm}<br />KM {row.km}</td><td className="max-w-sm px-5 py-4">{row.pekerjaan_servis}</td><td className="px-5 py-4 font-bold">{money(row.biaya)}</td><td className="px-5 py-4">{row.jadwal_berikutnya || '-'}</td></tr>)}{rows.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={8}>Belum ada catatan servis.</td></tr>}</tbody></table></div>;
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Inventaris Aset'}>{page}</AdminLayout>;
