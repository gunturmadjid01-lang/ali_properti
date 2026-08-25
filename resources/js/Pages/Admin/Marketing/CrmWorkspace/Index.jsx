import { Head, router } from '@inertiajs/react';
import { Edit3, Eye, Lock, Plus, Search, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';
import { Button, Dropdown, Input, TableActions } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

const labels = {
    visits: { number: 'visit_no', primary: 'objective', date: 'planned_at' },
    'action-plans': { number: 'action_no', primary: 'title', date: 'due_at' },
    'document-checklists': { number: 'checklist_no', primary: 'process_stage', date: 'updated_at' },
};

const hints = {
    visits: 'Menu ini untuk membuat jadwal aktivitas lapangan. Koordinat peta direkam otomatis saat marketing membuka Detail lalu menjalankan Check-in dan Check-out dengan izin lokasi browser.',
    'action-plans': 'Menu ini untuk rencana kerja/aktivitas lain yang tidak termasuk follow-up, survey unit, atau kunjungan GPS.',
    'document-checklists': 'Menu ini untuk memeriksa berkas customer. Daftar dokumen pada form bersumber dari Master Dokumen Pelanggan.',
};

function Pagination({ links = [] }) {
    return <div className="flex flex-wrap justify-end gap-2 border-t p-4">{links.map((link, index) => <Button key={index} size="sm" variant={link.active ? 'dark' : 'outline'} disabled={!link.url} onClick={() => link.url && router.visit(link.url, { preserveState: true })} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>;
}

export default function Index({ title, description, resource, baseUrl, rows, filters = {}, statusOptions = [], permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const columns = labels[resource];
    const filter = (event) => { event.preventDefault(); router.get(baseUrl, { search, status }, { preserveState: true, replace: true }); };
    const act = (row, action, method = 'post') => {
        if (!window.confirm(`${action} data ${row[columns.number]}?`)) return;
        router[method](`${baseUrl}/${row.id}/${action}`, {}, { preserveScroll: true });
    };
    return <>
        <Head title={title} />
        <div className="grid gap-6">
            <section className="rounded-lg border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div><p className="text-xs font-extrabold uppercase tracking-widest text-ink-soft">CRM Marketing</p><h1 className="mt-2 text-3xl font-extrabold">{title}</h1><p className="mt-2 max-w-3xl text-sm text-ink-soft">{description}</p>{hints[resource] && <p className="mt-3 max-w-3xl rounded-lg bg-blue-50 px-4 py-3 text-sm font-bold text-blue-900">{hints[resource]}</p>}</div>{permissions.canCreate && <Button onClick={() => router.visit(`${baseUrl}/create`)}><Plus size={17} /> Tambah</Button>}</div>
            </section>
            <section className="overflow-hidden rounded-lg border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8">
                <form className="grid gap-3 p-4 md:grid-cols-[1fr_260px_auto]" onSubmit={filter}><Input icon={<Search size={16} />} label="Cari" value={search} onChange={(event) => setSearch(event.target.value)} /><div className="grid gap-2"><span className="text-sm font-extrabold">Status</span><Dropdown value={status} options={[{value:'',label:'Semua Status'}, ...statusOptions]} onChange={setStatus} /></div><Button className="self-end" type="submit"><Search size={16} /> Terapkan</Button></form>
                <div className="overflow-x-auto"><table className="min-w-full text-sm"><thead className="bg-silver-soft text-left text-xs uppercase tracking-wider text-ink-soft"><tr><th className="px-4 py-3">Nomor</th><th className="px-4 py-3">Customer</th><th className="px-4 py-3">Marketing</th><th className="px-4 py-3">Informasi</th><th className="px-4 py-3">Tanggal</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Approval</th><th className="px-4 py-3 text-right">Aksi</th></tr></thead><tbody className="divide-y">
                    {rows.data.map((row) => <tr key={row.id}><td className="px-4 py-3 font-extrabold">{row[columns.number]}</td><td className="px-4 py-3">{row.customer}</td><td className="px-4 py-3">{row.marketing}</td><td className="max-w-sm px-4 py-3">{row[columns.primary] || '-'}</td><td className="px-4 py-3">{row[columns.date]?.replace('T', ' ') || '-'}</td><td className="px-4 py-3"><span className="rounded-full bg-silver px-3 py-1 text-xs font-bold">{row.status ?? row.validation_status}</span></td><td className="px-4 py-3"><div className="font-bold">{row.record_status}</div><div className="text-xs text-ink-soft">{row.approval_stage ?? row.approval_status}</div></td><td className="px-4 py-3 text-right"><TableActions>
                        <Button size="sm" variant="outline" onClick={() => router.visit(`${baseUrl}/${row.id}`)}><Eye size={15} /> Detail</Button>
                        {row.can_edit && <Button size="sm" variant="outline" onClick={() => router.visit(`${baseUrl}/${row.id}/edit`)}><Edit3 size={15} /> Edit</Button>}
                        {row.can_lock && <Button size="sm" variant="outline" onClick={() => act(row, 'lock')}><Lock size={15} /> Lock</Button>}
                        {row.can_unlock && <Button size="sm" variant="outline" onClick={() => act(row, 'unlock')}><Unlock size={15} /> Unlock</Button>}
                        {row.can_review && <><Button size="sm" onClick={() => act(row, 'review/approve')}>Approve</Button><Button size="sm" variant="outline" onClick={() => act(row, 'review/reject')}>Reject</Button></>}
                        {row.can_delete && <Button size="sm" variant="ghost" className="text-red-600" onClick={() => { if (window.confirm('Hapus draft ini?')) router.delete(`${baseUrl}/${row.id}`); }}><Trash2 size={15} /></Button>}
                    </TableActions></td></tr>)}
                    {rows.data.length === 0 && <tr><td colSpan="8" className="px-5 py-12 text-center font-bold text-ink-soft">Belum ada data.</td></tr>}
                </tbody></table></div><Pagination links={rows.links} />
            </section>
        </div>
    </>;
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'CRM'}>{page}</AdminLayout>;
