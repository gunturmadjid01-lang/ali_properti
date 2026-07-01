import { Head, router, useForm } from '@inertiajs/react';
import {
    AlarmClock, BadgeDollarSign, BarChart3, CheckCircle2, ClipboardList,
    Copy, FileCheck2, Megaphone, PencilLine, Plus, Printer, ReceiptText,
    Lock, Target, Trash2, Unlock, Users, XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button, CurrencyInput, Dropdown, Input, Modal, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
}).format(Number(value ?? 0));

const badge = (status) => {
    const danger = ['ditolak', 'dibatalkan', 'jatuh_tempo', 'revisi'].includes(status);
    const success = ['aktif', 'selesai', 'lunas', 'valid', 'dibayar', 'disetujui'].includes(status);
    return `rounded-full px-3 py-1 text-xs font-extrabold ${danger
        ? 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'
        : success
            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
            : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200'}`;
};

function Card({ children, className = '' }) {
    return <section className={`rounded-2xl border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/7 ${className}`}>{children}</section>;
}

function Empty({ text = 'Belum ada data.' }) {
    return <div className="px-5 py-10 text-center text-sm font-bold text-ink-soft dark:text-white/50">{text}</div>;
}

function Actions({ row, onEdit, onDelete, onLock, type, canEdit = true, canDelete = true, canLock = true }) {
    return (
        <div className="flex justify-end gap-2">
            {onEdit && canEdit && <Button size="sm" variant="outline" type="button" onClick={() => onEdit(row, type)}><PencilLine size={14} /></Button>}
            {onLock && canLock && (
                <Button size="sm" variant="outline" type="button" onClick={() => onLock(row, type)}>
                    {row.record_status === 'locked' ? <Unlock size={14} /> : <Lock size={14} />}
                </Button>
            )}
            {onDelete && canDelete && row.record_status !== 'locked' && !row.is_system && (
                <Button size="sm" variant="outline" type="button" onClick={() => onDelete(row, type)}><Trash2 size={14} /></Button>
            )}
        </div>
    );
}

function Dashboard({ data }) {
    const stats = [
        ['Total Lead', data.stats?.lead, Users],
        ['Reminder Mendesak', data.stats?.follow_up_due, AlarmClock],
        ['SPR Bulan Ini', data.stats?.spr_month, ReceiptText],
        ['KPR Aktif', data.stats?.kpr_active, FileCheck2],
        ['Booking Akan Habis', data.stats?.booking_expiring, ClipboardList],
        ['Tagihan Jatuh Tempo', data.stats?.overdue, BadgeDollarSign],
    ];

    return (
        <div className="grid gap-6">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {stats.map(([label, value, Icon]) => (
                    <Card className="p-5" key={label}>
                        <div className="flex items-center justify-between">
                            <span className="grid h-11 w-11 place-items-center rounded-xl bg-ink text-white dark:bg-white dark:text-ink"><Icon size={20} /></span>
                            <strong className="text-3xl">{value ?? 0}</strong>
                        </div>
                        <p className="mt-3 text-sm font-bold text-ink-soft dark:text-white/55">{label}</p>
                    </Card>
                ))}
            </div>
            <div className="grid gap-6 xl:grid-cols-2">
                <Card>
                    <h3 className="border-b border-silver-deep/60 px-5 py-4 text-lg font-extrabold dark:border-white/10">Performa Marketing Bulan Ini</h3>
                    <div className="divide-y divide-silver-deep/50 dark:divide-white/10">
                        {(data.performance ?? []).map((row) => (
                            <div className="grid grid-cols-[1fr_auto_auto] gap-4 px-5 py-4" key={row.id}>
                                <strong>{row.name}</strong><span>SPR: <b>{row.spr}</b></span><span>KPR: <b>{row.kpr}</b></span>
                            </div>
                        ))}
                        {(data.performance ?? []).length === 0 && <Empty text="Belum ada aktivitas marketing bulan ini." />}
                    </div>
                </Card>
                <Card>
                    <h3 className="border-b border-silver-deep/60 px-5 py-4 text-lg font-extrabold dark:border-white/10">Aktivitas Terbaru</h3>
                    <div className="divide-y divide-silver-deep/50 dark:divide-white/10">
                        {(data.recent ?? []).map((row) => (
                            <div className="flex items-center justify-between gap-4 px-5 py-4" key={row.id}>
                                <div><strong>{row.customer}</strong><p className="text-xs text-ink-soft">{row.user} · {row.at}</p></div>
                                <span className={badge(row.status)}>{row.status}</span>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>
        </div>
    );
}

function Pipeline({ data }) {
    const [search, setSearch] = useState('');
    const keyword = search.trim().toLowerCase();
    const columns = (data.columns ?? []).map((column) => ({
        ...column,
        filteredCustomers: column.customers.filter((row) => {
            if (!keyword) return true;

            return [
                row.nama,
                row.kode,
                row.telepon,
                row.source,
                row.campaign,
            ].some((value) => String(value ?? '').toLowerCase().includes(keyword));
        }),
    }));

    return (
        <div className="grid gap-4">
            <Card className="p-4">
                <div className="grid gap-3 md:grid-cols-[minmax(260px,520px)_1fr] md:items-end">
                    <Input
                        icon={<Users size={16} />}
                        label="Cari Customer di Pipeline"
                        placeholder="Nama, kode, telepon, sumber, atau campaign"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <p className="text-sm font-semibold leading-6 text-ink-soft dark:text-white/55">
                        Setiap kolom dapat di-scroll sendiri. Angka pada header menunjukkan total customer pada tahap tersebut.
                    </p>
                </div>
            </Card>

            <div className="overflow-x-auto rounded-2xl pb-3">
                <div className="flex h-[68vh] min-h-[520px] min-w-max gap-4">
                    {columns.map((column) => (
                        <Card className="flex h-full w-[300px] flex-col overflow-hidden" key={column.value}>
                            <div className="sticky top-0 z-10 flex shrink-0 items-center justify-between border-b border-silver-deep/60 bg-white/95 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-graphite/95">
                                <strong>{column.label}</strong>
                                <div className="flex items-center gap-2">
                                    {keyword && column.filteredCustomers.length !== column.customers.length && (
                                        <span className="text-xs font-bold text-ink-soft">{column.filteredCustomers.length}/</span>
                                    )}
                                    <span className="rounded-full bg-ink px-2.5 py-1 text-xs font-extrabold text-white dark:bg-white dark:text-ink">{column.customers.length}</span>
                                </div>
                            </div>
                            <div className="grid min-h-0 flex-1 content-start gap-3 overflow-y-auto p-3">
                                {column.filteredCustomers.map((row) => (
                                    <article className="rounded-xl border border-silver-deep/60 bg-silver-soft/70 p-4 dark:border-white/10 dark:bg-white/5" key={row.id}>
                                        <strong>{row.nama}</strong>
                                        <p className="mt-1 text-xs font-bold text-ink-soft">{row.kode} · {row.telepon || '-'}</p>
                                        <div className="mt-3 grid gap-1 text-xs text-ink-soft dark:text-white/55">
                                            <span>Sumber: {row.source}</span>
                                            <span>Campaign: {row.campaign}</span>
                                            <span>Aktivitas: {row.last_activity}</span>
                                        </div>
                                    </article>
                                ))}
                                {column.filteredCustomers.length === 0 && (
                                    <div className="grid min-h-32 place-items-center rounded-xl border border-dashed border-silver-deep/70 px-4 text-center text-xs font-bold text-ink-soft dark:border-white/10">
                                        {keyword ? 'Customer tidak ditemukan pada tahap ini.' : 'Belum ada customer.'}
                                    </div>
                                )}
                            </div>
                        </Card>
                    ))}
                </div>
            </div>
        </div>
    );
}

function CampaignTable({ data, onEdit, onDelete, onLock, permissions = {} }) {
    return (
        <Card className="overflow-hidden">
            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-silver-soft text-left text-xs uppercase tracking-wider text-ink-soft dark:bg-white/5">
                        <tr>{['Perumahan', 'Campaign', 'Periode', 'Kanal', 'Anggaran', 'Realisasi', 'Lead / Target', 'Status', 'Aksi'].map((x) => <th className="px-4 py-3" key={x}>{x}</th>)}</tr>
                    </thead>
                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                        {(data.rows ?? []).map((row) => (
                            <tr key={row.id}>
                                <td className="px-4 py-4 font-semibold">{row.perumahan}</td>
                                <td className="px-4 py-4"><b>{row.nama_campaign}</b><br /><span className="text-xs text-ink-soft">{row.kode_campaign}</span></td>
                                <td className="px-4 py-4">{row.tanggal_mulai}<br />{row.tanggal_selesai || '-'}</td>
                                <td className="px-4 py-4">{row.kanal}</td>
                                <td className="px-4 py-4">{money(row.anggaran)}</td>
                                <td className="px-4 py-4">{money(row.realisasi_biaya)}</td>
                                <td className="px-4 py-4">{row.lead_count} / {row.target_lead}</td>
                                <td className="px-4 py-4"><span className={badge(row.status)}>{row.status}</span></td>
                                <td className="px-4 py-4"><Actions row={row} onEdit={onEdit} onDelete={onDelete} onLock={onLock} canEdit={permissions.canUpdate} canDelete={permissions.canDelete} canLock={permissions.canUnlock} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {(data.rows ?? []).length === 0 && <Empty />}
        </Card>
    );
}

function ReminderTable({ data, onEdit, onDelete, baseUrl, permissions = {} }) {
    return (
        <Card className="overflow-hidden">
            <div className="divide-y divide-silver-deep/50 dark:divide-white/10">
                {(data.rows ?? []).map((row) => (
                    <div className={`grid gap-4 p-5 md:grid-cols-[1.2fr_1fr_180px_auto] md:items-center ${row.is_overdue ? 'bg-red-50/70 dark:bg-red-500/5' : ''}`} key={row.id}>
                        <div><strong>{row.judul}</strong><p className="text-xs text-ink-soft">{row.customer} · {row.telepon}</p></div>
                        <div className="text-sm"><b>{row.remind_at?.replace('T', ' ')}</b><p className="text-xs text-ink-soft">{row.user}</p></div>
                        <span className={badge(row.status)}>{row.status}</span>
                        <div className="flex gap-2">
                            {row.status === 'menunggu' && permissions.canUpdate && <Button size="sm" type="button" onClick={() => router.post(`${baseUrl}/${row.id}/complete`, {}, { preserveScroll: true })}><CheckCircle2 size={14} /> Selesai</Button>}
                            <Actions row={row} onEdit={onEdit} onDelete={onDelete} canEdit={permissions.canUpdate} canDelete={permissions.canDelete} />
                        </div>
                    </div>
                ))}
            </div>
            {(data.rows ?? []).length === 0 && <Empty />}
        </Card>
    );
}

function Documents({ data, baseUrl, permissions = {} }) {
    const [reviewRow, setReviewRow] = useState(null);
    const form = useForm({ document_type: '', document_id: '', status: 'valid', catatan_revisi: '' });
    const open = (row) => {
        setReviewRow(row);
        form.setData({ document_type: row.document_type, document_id: row.id, status: row.status, catatan_revisi: row.catatan_revisi ?? '' });
    };
    const submit = (event) => {
        event.preventDefault();
        form.post(`${baseUrl}/review`, { preserveScroll: true, onSuccess: () => setReviewRow(null) });
    };
    return (
        <>
            <Card className="overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-silver-soft text-left text-xs uppercase tracking-wider text-ink-soft dark:bg-white/5"><tr>{['Sumber', 'Referensi', 'Customer', 'Dokumen', 'File', 'Status', 'Aksi'].map((x) => <th className="px-4 py-3" key={x}>{x}</th>)}</tr></thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {(data.rows ?? []).map((row) => (
                                <tr key={`${row.document_type}-${row.id}`}>
                                    <td className="px-4 py-4 font-bold">{row.source}</td><td className="px-4 py-4">{row.reference}</td><td className="px-4 py-4">{row.customer}</td>
                                    <td className="px-4 py-4">{row.document}</td><td className="px-4 py-4"><a className="font-bold text-emerald-600 underline" href={row.url} target="_blank" rel="noreferrer">{row.file}</a></td>
                                    <td className="px-4 py-4"><span className={badge(row.status)}>{row.status}</span>{row.catatan_revisi && <p className="mt-1 text-xs text-red-600">{row.catatan_revisi}</p>}</td>
                                    <td className="px-4 py-4">{permissions.canUpdate && <Button size="sm" variant="outline" onClick={() => open(row)}><FileCheck2 size={14} /> Review</Button>}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
            {permissions.canUpdate && <Modal open={Boolean(reviewRow)} onClose={() => setReviewRow(null)} title="Validasi Berkas" size="md">
                <form className="grid gap-4" onSubmit={submit}>
                    <Dropdown label="Status" value={form.data.status} options={['menunggu', 'valid', 'revisi', 'ditolak'].map((x) => ({ value: x, label: x.replace('_', ' ') }))} onChange={(value) => form.setData('status', value)} />
                    <Textarea label="Catatan Revisi" value={form.data.catatan_revisi} onChange={(e) => form.setData('catatan_revisi', e.target.value)} />
                    <div className="flex justify-end"><Button disabled={form.processing}>Simpan Review</Button></div>
                </form>
            </Modal>}
        </>
    );
}

function Receivables({ data }) {
    return (
        <div className="grid gap-6">
            <div className="grid gap-4 md:grid-cols-4">
                {[
                    ['Total Tagihan', money(data.summary?.tagihan)],
                    ['Total Dibayar', money(data.summary?.dibayar)],
                    ['Sisa Piutang', money(data.summary?.sisa)],
                    ['Jatuh Tempo', data.summary?.jatuh_tempo ?? 0],
                ].map(([label, value]) => <Card className="p-5" key={label}><p className="text-xs font-bold uppercase text-ink-soft">{label}</p><strong className="mt-2 block text-xl">{value}</strong></Card>)}
            </div>
            <Card className="overflow-hidden">
                <h3 className="border-b border-silver-deep/60 px-5 py-4 text-lg font-extrabold dark:border-white/10">Aging Piutang</h3>
                <div className="overflow-x-auto"><table className="min-w-full text-sm">
                    <thead className="bg-silver-soft text-left text-xs uppercase text-ink-soft dark:bg-white/5"><tr>{['SPR', 'Customer', 'Tagihan', 'Jatuh Tempo', 'Tagihan', 'Dibayar', 'Sisa', 'Status'].map((x) => <th className="px-4 py-3" key={x}>{x}</th>)}</tr></thead>
                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">{(data.schedules ?? []).map((row) => <tr key={row.id}>
                        <td className="px-4 py-4 font-bold">{row.kode_spr}</td><td className="px-4 py-4">{row.customer}</td><td className="px-4 py-4">{row.jenis}{row.termin_ke ? ` #${row.termin_ke}` : ''}</td>
                        <td className="px-4 py-4">{row.tanggal_jatuh_tempo}</td><td className="px-4 py-4">{money(row.nominal_tagihan)}</td><td className="px-4 py-4">{money(row.nominal_dibayar)}</td><td className="px-4 py-4">{money(row.sisa)}</td>
                        <td className="px-4 py-4"><span className={badge(row.status)}>{row.status}</span></td>
                    </tr>)}</tbody>
                </table></div>
            </Card>
            <Card className="overflow-hidden">
                <h3 className="border-b border-silver-deep/60 px-5 py-4 text-lg font-extrabold dark:border-white/10">Kwitansi Pembayaran</h3>
                <div className="divide-y divide-silver-deep/50 dark:divide-white/10">{(data.receipts ?? []).map((row) => (
                    <div className="grid gap-3 px-5 py-4 md:grid-cols-[160px_1fr_150px_180px_auto] md:items-center" key={row.id}>
                        <b>{row.nomor}</b><span>{row.customer}<br /><small>{row.kode_spr}</small></span><span>{row.tanggal}</span><b>{money(row.nominal)}</b>
                        <Button as="a" href={row.print_url} target="_blank" size="sm" variant="outline"><Printer size={14} /> Cetak</Button>
                    </div>
                ))}</div>
            </Card>
        </div>
    );
}

function TargetCommission({ data, onEdit, onDelete, onLock, setCreateType, permissions = {} }) {
    return (
        <div className="grid gap-6">
            {permissions.canCreate && (
                <div className="flex flex-wrap gap-2">
                    <Button onClick={() => setCreateType('target')}><Target size={16} /> Tambah Target</Button>
                    <Button variant="outline" onClick={() => setCreateType('commission')}><BadgeDollarSign size={16} /> Tambah Komisi</Button>
                </div>
            )}
            <Card className="overflow-hidden">
                <h3 className="border-b border-silver-deep/60 px-5 py-4 text-lg font-extrabold dark:border-white/10">Target dan KPI</h3>
                <div className="overflow-x-auto"><table className="min-w-full text-sm">
                    <thead className="bg-silver-soft text-left text-xs uppercase text-ink-soft dark:bg-white/5"><tr>{['Perumahan', 'Marketing', 'Periode', 'Lead', 'Survey', 'SPR', 'Closing', 'Nilai', 'Aksi'].map((x) => <th className="px-4 py-3" key={x}>{x}</th>)}</tr></thead>
                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">{(data.targets ?? []).map((row) => <tr key={row.id}>
                        <td className="px-4 py-4 font-semibold">{row.perumahan}</td><td className="px-4 py-4 font-bold">{row.user}</td><td className="px-4 py-4">{row.bulan}/{row.tahun}</td><td className="px-4 py-4">{row.target_lead}</td><td className="px-4 py-4">{row.target_survey}</td><td className="px-4 py-4">{row.target_spr}</td><td className="px-4 py-4">{row.target_closing}</td><td className="px-4 py-4">{money(row.target_nilai_penjualan)}</td><td className="px-4 py-4"><Actions row={row} type="target" onEdit={onEdit} onDelete={onDelete} onLock={onLock} canEdit={permissions.canUpdate} canDelete={permissions.canDelete} canLock={permissions.canUnlock} /></td>
                    </tr>)}</tbody>
                </table></div>
            </Card>
            <Card className="overflow-hidden">
                <h3 className="border-b border-silver-deep/60 px-5 py-4 text-lg font-extrabold dark:border-white/10">Komisi Marketing</h3>
                <div className="overflow-x-auto"><table className="min-w-full text-sm">
                    <thead className="bg-silver-soft text-left text-xs uppercase text-ink-soft dark:bg-white/5"><tr>{['Perumahan', 'SPR', 'Marketing', 'Dasar', 'Persen', 'Komisi', 'Status', 'Aksi'].map((x) => <th className="px-4 py-3" key={x}>{x}</th>)}</tr></thead>
                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">{(data.commissions ?? []).map((row) => <tr key={row.id}>
                        <td className="px-4 py-4 font-semibold">{row.perumahan}</td><td className="px-4 py-4 font-bold">{row.spr}</td><td className="px-4 py-4">{row.user}</td><td className="px-4 py-4">{money(row.dasar_perhitungan)}</td><td className="px-4 py-4">{row.persentase}%</td><td className="px-4 py-4 font-bold">{money(row.nominal)}</td><td className="px-4 py-4"><span className={badge(row.status)}>{row.status}</span></td><td className="px-4 py-4"><Actions row={row} type="commission" onEdit={onEdit} onDelete={onDelete} onLock={onLock} canEdit={permissions.canUpdate} canDelete={permissions.canDelete} canLock={permissions.canUnlock} /></td>
                    </tr>)}</tbody>
                </table></div>
            </Card>
        </div>
    );
}

function Templates({ data, onEdit, onDelete, onLock, permissions = {} }) {
    const copy = async (text) => navigator.clipboard?.writeText(text);
    return <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{(data.rows ?? []).map((row) => (
        <Card className="p-5" key={row.id}>
            <div className="flex items-start justify-between gap-3"><div><strong>{row.nama_template}</strong><p className="text-xs uppercase text-ink-soft">{row.kanal} · {row.tahapan || 'umum'}</p></div><span className={badge(row.status)}>{row.status}</span></div>
            <p className="mt-4 min-h-24 whitespace-pre-wrap rounded-xl bg-silver-soft/80 p-3 text-sm dark:bg-white/5">{row.isi_template}</p>
            <div className="mt-4 flex justify-between gap-2"><Button size="sm" variant="outline" onClick={() => copy(row.isi_template)}><Copy size={14} /> Salin</Button><Actions row={row} onEdit={onEdit} onDelete={onDelete} onLock={onLock} canEdit={permissions.canUpdate} canDelete={permissions.canDelete} canLock={permissions.canUnlock} /></div>
        </Card>
    ))}</div>;
}

function CrudModal({ section, row, type, options, baseUrl, onClose }) {
    const defaults = useMemo(() => {
        if (section === 'campaign') return { nama_campaign: '', kanal: 'facebook', tanggal_mulai: new Date().toISOString().slice(0, 10), tanggal_selesai: '', anggaran: '', realisasi_biaya: '', target_lead: '', status: 'draft', keterangan: '' };
        if (section === 'reminder') return { costumer_id: '', user_id: '', jenis: 'follow_up', judul: '', remind_at: '', status: 'menunggu', catatan: '' };
        if (section === 'template') return { nama_template: '', kanal: 'whatsapp', tahapan: '', isi_template: '', status: 'aktif' };
        if (type === 'commission') return { type: 'commission', spr_id: '', user_id: '', dasar_perhitungan: '', persentase: '', status: 'draft', tanggal_jatuh_tempo: '', tanggal_dibayar: '', catatan: '' };
        return { type: 'target', user_id: '', tahun: new Date().getFullYear(), bulan: new Date().getMonth() + 1, target_lead: 0, target_survey: 0, target_spr: 0, target_closing: 0, target_nilai_penjualan: '', catatan: '' };
    }, [section, type]);
    const form = useForm(row ? { ...defaults, ...row, type: type ?? row.type } : defaults);
    const submit = (event) => {
        event.preventDefault();
        const url = row ? `${baseUrl}/${row.id}` : baseUrl;
        form.transform((data) => ({ ...data, type: type ?? data.type }));
        (row ? form.put : form.post)(url, { preserveScroll: true, onSuccess: onClose });
    };
    const spr = options.sprs?.find((item) => String(item.value) === String(form.data.spr_id));
    return (
        <Modal open onClose={onClose} title={`${row ? 'Edit' : 'Tambah'} ${type === 'commission' ? 'Komisi' : type === 'target' ? 'Target' : ''}`} size="lg">
            <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
                {section === 'campaign' && <>
                    <Input label="Nama Campaign" value={form.data.nama_campaign} error={form.errors.nama_campaign} onChange={(e) => form.setData('nama_campaign', e.target.value)} />
                    <Input label="Kanal Promosi" value={form.data.kanal} error={form.errors.kanal} onChange={(e) => form.setData('kanal', e.target.value)} />
                    <Input type="date" label="Tanggal Mulai" value={form.data.tanggal_mulai} error={form.errors.tanggal_mulai} onChange={(e) => form.setData('tanggal_mulai', e.target.value)} />
                    <Input type="date" label="Tanggal Selesai" value={form.data.tanggal_selesai} error={form.errors.tanggal_selesai} onChange={(e) => form.setData('tanggal_selesai', e.target.value)} />
                    <CurrencyInput label="Anggaran" value={form.data.anggaran} error={form.errors.anggaran} onChange={(v) => form.setData('anggaran', v)} />
                    <CurrencyInput label="Realisasi Biaya" value={form.data.realisasi_biaya} error={form.errors.realisasi_biaya} onChange={(v) => form.setData('realisasi_biaya', v)} />
                    <Input type="number" label="Target Lead" value={form.data.target_lead} onChange={(e) => form.setData('target_lead', e.target.value)} />
                    <Dropdown label="Status" value={form.data.status} options={['draft', 'aktif', 'selesai', 'dibatalkan'].map((x) => ({ value: x, label: x }))} onChange={(v) => form.setData('status', v)} />
                    <Textarea className="md:col-span-2" label="Keterangan" value={form.data.keterangan} onChange={(e) => form.setData('keterangan', e.target.value)} />
                </>}
                {section === 'reminder' && <>
                    <Dropdown label="Customer" value={String(form.data.costumer_id ?? '')} options={options.customers ?? []} onChange={(v) => form.setData('costumer_id', v)} />
                    <Dropdown label="Petugas" value={String(form.data.user_id ?? '')} options={options.users ?? []} onChange={(v) => form.setData('user_id', v)} />
                    <Input label="Jenis" value={form.data.jenis} onChange={(e) => form.setData('jenis', e.target.value)} />
                    <Input label="Judul" value={form.data.judul} error={form.errors.judul} onChange={(e) => form.setData('judul', e.target.value)} />
                    <Input type="datetime-local" label="Waktu Reminder" value={form.data.remind_at} error={form.errors.remind_at} onChange={(e) => form.setData('remind_at', e.target.value)} />
                    <Dropdown label="Status" value={form.data.status} options={['menunggu', 'selesai', 'dibatalkan'].map((x) => ({ value: x, label: x }))} onChange={(v) => form.setData('status', v)} />
                    <Textarea className="md:col-span-2" label="Catatan" value={form.data.catatan} onChange={(e) => form.setData('catatan', e.target.value)} />
                </>}
                {section === 'template' && <>
                    <Input label="Nama Template" value={form.data.nama_template} error={form.errors.nama_template} onChange={(e) => form.setData('nama_template', e.target.value)} />
                    <Dropdown label="Kanal" value={form.data.kanal} options={['whatsapp', 'sms', 'email'].map((x) => ({ value: x, label: x }))} onChange={(v) => form.setData('kanal', v)} />
                    <Input label="Tahapan" value={form.data.tahapan} onChange={(e) => form.setData('tahapan', e.target.value)} />
                    <Dropdown label="Status" value={form.data.status} options={['aktif', 'nonaktif'].map((x) => ({ value: x, label: x }))} onChange={(v) => form.setData('status', v)} />
                    <Textarea className="md:col-span-2" label="Isi Template" value={form.data.isi_template} error={form.errors.isi_template} onChange={(e) => form.setData('isi_template', e.target.value)} />
                </>}
                {section === 'target-komisi' && type === 'target' && <>
                    <Dropdown className="md:col-span-2" label="Marketing" value={String(form.data.user_id ?? '')} options={options.users ?? []} onChange={(v) => form.setData('user_id', v)} />
                    <Input type="number" label="Tahun" value={form.data.tahun} onChange={(e) => form.setData('tahun', e.target.value)} />
                    <Input type="number" label="Bulan" min="1" max="12" value={form.data.bulan} onChange={(e) => form.setData('bulan', e.target.value)} />
                    {['target_lead', 'target_survey', 'target_spr', 'target_closing'].map((field) => <Input key={field} type="number" label={field.replaceAll('_', ' ')} value={form.data[field]} onChange={(e) => form.setData(field, e.target.value)} />)}
                    <CurrencyInput className="md:col-span-2" label="Target Nilai Penjualan" value={form.data.target_nilai_penjualan} onChange={(v) => form.setData('target_nilai_penjualan', v)} />
                </>}
                {section === 'target-komisi' && type === 'commission' && <>
                    <Dropdown className="md:col-span-2" label="SPR" value={String(form.data.spr_id ?? '')} options={options.sprs ?? []} onChange={(v) => {
                        const selected = options.sprs?.find((x) => String(x.value) === String(v));
                        form.setData((data) => ({ ...data, spr_id: v, dasar_perhitungan: selected?.amount ?? data.dasar_perhitungan, user_id: selected?.user_id ?? data.user_id }));
                    }} />
                    <Dropdown label="Marketing" value={String(form.data.user_id ?? '')} options={options.users ?? []} onChange={(v) => form.setData('user_id', v)} />
                    <CurrencyInput label="Dasar Perhitungan" value={form.data.dasar_perhitungan || spr?.amount || ''} onChange={(v) => form.setData('dasar_perhitungan', v)} />
                    <Input type="number" step="0.01" label="Persentase %" value={form.data.persentase} onChange={(e) => form.setData('persentase', e.target.value)} />
                    <Dropdown label="Status" value={form.data.status} options={['draft', 'diajukan', 'disetujui', 'dibayar', 'dibatalkan'].map((x) => ({ value: x, label: x }))} onChange={(v) => form.setData('status', v)} />
                    <Input type="date" label="Jatuh Tempo" value={form.data.tanggal_jatuh_tempo} onChange={(e) => form.setData('tanggal_jatuh_tempo', e.target.value)} />
                    <Input type="date" label="Tanggal Dibayar" value={form.data.tanggal_dibayar} onChange={(e) => form.setData('tanggal_dibayar', e.target.value)} />
                </>}
                <div className="flex justify-end gap-2 md:col-span-2"><Button type="button" variant="outline" onClick={onClose}><XCircle size={15} /> Batal</Button><Button disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan'}</Button></div>
            </form>
        </Modal>
    );
}

export default function Index({ title, section, baseUrl, data = {}, options = {}, permissions = {} }) {
    const [editing, setEditing] = useState(null);
    const [createType, setCreateType] = useState(null);
    const canCreate = Boolean(permissions.canCreate);
    const deleteRow = (row, type) => {
        if (!window.confirm('Hapus data ini?')) return;
        router.delete(`${baseUrl}/${row.id}`, { data: { type }, preserveScroll: true });
    };
    const editRow = (row, type) => setEditing({ row, type });
    const lockRow = (row, type) => {
        const modelSection = type === 'target' || type === 'commission' ? type : section;
        const action = row.record_status === 'locked' ? 'unlock' : 'lock';
        router.post(`/admin/marketing/operasional/${modelSection}/${row.id}/${action}`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <Card className="p-6">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div><p className="text-xs font-extrabold uppercase tracking-[0.18em] text-gold-deep">Marketing Workspace</p><h1 className="mt-2 font-display text-3xl font-extrabold">{title}</h1></div>
                        <div className="flex flex-wrap gap-2">
                            {section === 'dashboard' && <Button variant="outline" onClick={() => router.post('/admin/marketing/operasional/booking-expired/process')}><AlarmClock size={16} /> Proses Booking Expired</Button>}
                            {canCreate && <Button onClick={() => setCreateType(section)}><Plus size={16} /> Tambah Data</Button>}
                        </div>
                    </div>
                </Card>
                {section === 'dashboard' && <Dashboard data={data} />}
                {section === 'pipeline' && <Pipeline data={data} />}
                {section === 'campaign' && <CampaignTable data={data} onEdit={editRow} onDelete={deleteRow} onLock={lockRow} permissions={permissions} />}
                {section === 'reminder' && <ReminderTable data={data} onEdit={editRow} onDelete={deleteRow} baseUrl={baseUrl} permissions={permissions} />}
                {section === 'dokumen' && <Documents data={data} baseUrl={baseUrl} permissions={permissions} />}
                {section === 'piutang' && <Receivables data={data} />}
                {section === 'target-komisi' && <TargetCommission data={data} onEdit={editRow} onDelete={deleteRow} onLock={lockRow} setCreateType={setCreateType} permissions={permissions} />}
                {section === 'template' && <Templates data={data} onEdit={editRow} onDelete={deleteRow} onLock={lockRow} permissions={permissions} />}
            </div>
            {(createType || editing) && (
                <CrudModal
                    section={section}
                    type={editing?.type ?? (createType === 'commission' || createType === 'target' ? createType : null)}
                    row={editing?.row ?? null}
                    options={options}
                    baseUrl={baseUrl}
                    onClose={() => { setCreateType(null); setEditing(null); }}
                />
            )}
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Marketing Workspace'}>{page}</AdminLayout>;
