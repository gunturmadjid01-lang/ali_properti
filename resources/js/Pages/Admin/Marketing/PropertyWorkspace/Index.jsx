import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Index({ projects, filters }) {
  const submit = (e) => { e.preventDefault(); router.get(route('admin.marketing.property-workspace.index'), { search: e.currentTarget.search.value }, { preserveState: true }); };
  return <AdminLayout><Head title="Ruang Properti CRM" /><div className="p-6 space-y-5">
    <div><h1 className="text-2xl font-semibold text-slate-900">Ruang Properti CRM</h1><p className="text-sm text-slate-500">Ringkasan proyek dan ketersediaan unit untuk Marketing dan Admin Sales.</p></div>
    <form onSubmit={submit} className="flex gap-2"><input name="search" defaultValue={filters.search} placeholder="Cari nama proyek" className="w-full max-w-md rounded-lg border-slate-300" /><button className="rounded-lg bg-slate-900 px-4 py-2 text-white">Cari</button></form>
    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{projects.data.map((p) => <Link key={p.id} href={route('admin.perumahan.detail', p.id)} className="rounded-xl border bg-white p-5 shadow-sm hover:border-indigo-400"><div className="font-semibold text-slate-900">{p.nama_perusahaan}</div><div className="mt-4 grid grid-cols-3 gap-2 text-center text-sm"><div><div className="text-xl font-bold">{p.total_unit ?? 0}</div><div className="text-slate-500">Total unit</div></div><div><div className="text-xl font-bold text-emerald-600">{p.unit_tersedia ?? 0}</div><div className="text-slate-500">Tersedia</div></div><div><div className="text-xl font-bold text-slate-700">{p.unit_terjual ?? 0}</div><div className="text-slate-500">Terjual</div></div></div></Link>)}</div>
    {!projects.data.length && <div className="rounded-xl border border-dashed p-10 text-center text-slate-500">Belum ada proyek yang sesuai.</div>}
  </div></AdminLayout>;
}
