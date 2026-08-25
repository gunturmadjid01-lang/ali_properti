import { Head, router } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Building2, CalendarDays, ClipboardCheck, HardHat, Home, ShieldCheck, TrendingUp } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button, Dropdown } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const icons = { schedule: CalendarDays, progress: TrendingUp, report: ClipboardCheck, material: Building2, manpower: HardHat, quality: ShieldCheck, defect: AlertTriangle, safety: ShieldCheck, change: ClipboardCheck, handover: Home };

export default function Index({ title, options = {}, cards = [], context = {}, unit = null }) {
    const [perumahan, setPerumahan] = useState(context.perumahan_id ?? '');
    const [detailRumah, setDetailRumah] = useState(context.detail_rumah_id ?? '');
    const [schedule, setSchedule] = useState(context.site_schedule_id ?? '');
    const units = useMemo(() => (options.detailRumahs ?? []).filter((row) => !perumahan || row.perumahan_id === String(perumahan)), [options.detailRumahs, perumahan]);
    const schedules = useMemo(() => (options.siteSchedules ?? []).filter((row) => (!perumahan || row.perumahan_id === String(perumahan)) && (!detailRumah || row.detail_rumah_id === String(detailRumah))), [options.siteSchedules, perumahan, detailRumah]);
    const query = () => ({ perumahan_id: perumahan, detail_rumah_id: detailRumah, site_schedule_id: schedule });
    const openCard = (href) => router.get(href, query());

    return <>
        <Head title={title} />
        <div className="grid gap-6">
            <section className="rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 p-7 text-white shadow-xl">
                <p className="text-xs font-black uppercase tracking-[0.22em] text-emerald-300">Pusat kerja lapangan</p>
                <h1 className="mt-3 font-display text-3xl font-black md:text-4xl">Workspace Harian Pengawas</h1>
                <p className="mt-3 max-w-3xl text-sm leading-7 text-white/70">Pilih konteks proyek satu kali. Semua input berikutnya membawa perumahan, unit, dan jadwal yang sama agar progress, material, QC, defect, K3, serta serah terima tidak terpisah.</p>
            </section>

            <section className="grid gap-4 rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8 lg:grid-cols-4">
                <Dropdown label="Pilih Perumahan" value={perumahan} options={[{ value: '', label: 'Semua Perumahan' }, ...(options.perumahans ?? [])]} onChange={(value) => { setPerumahan(value); setDetailRumah(''); setSchedule(''); }} />
                <Dropdown label="Pilih Unit" value={detailRumah} options={[{ value: '', label: 'Semua Unit' }, ...units]} onChange={(value) => { setDetailRumah(value); setSchedule(''); }} />
                <Dropdown label="Pilih Jadwal / Pekerjaan" value={schedule} options={[{ value: '', label: 'Semua Jadwal' }, ...schedules]} onChange={setSchedule} />
                <div className="flex items-end"><Button className="w-full" onClick={() => router.get('/admin/pengawasan', query(), { preserveState: true, replace: true })}><HardHat size={17} /> Terapkan Konteks</Button></div>
            </section>

            {unit && <section className="grid gap-3 rounded-xl border border-emerald-200 bg-emerald-50/80 p-5 text-emerald-950 shadow-soft dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100 md:grid-cols-4">
                <div><p className="text-xs font-black uppercase opacity-60">Perumahan</p><strong>{unit.perumahan}</strong></div>
                <div><p className="text-xs font-black uppercase opacity-60">Unit</p><strong>{unit.label}</strong></div>
                <div><p className="text-xs font-black uppercase opacity-60">Progress terakhir</p><strong>{unit.progress}%</strong></div>
                <div><p className="text-xs font-black uppercase opacity-60">Status pembangunan</p><strong>{unit.status || '-'}</strong></div>
            </section>}

            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {cards.map((card) => {
                    const Icon = icons[card.key] ?? ClipboardCheck;
                    return <button type="button" onClick={() => openCard(card.href)} key={card.key} className="group rounded-xl border border-white/80 bg-white/80 p-5 text-left shadow-soft transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-lg dark:border-white/10 dark:bg-white/8">
                        <div className="flex items-start justify-between"><span className="rounded-lg bg-emerald-100 p-3 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300"><Icon size={22} /></span><ArrowRight className="text-ink-soft transition group-hover:translate-x-1" size={18} /></div>
                        <p className="mt-5 text-sm font-bold text-ink-soft">{card.label}</p><p className="mt-1 text-3xl font-black">{card.count}</p>
                    </button>;
                })}
            </section>
        </div>
    </>;
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Workspace Harian Pengawas'}>{page}</AdminLayout>;
