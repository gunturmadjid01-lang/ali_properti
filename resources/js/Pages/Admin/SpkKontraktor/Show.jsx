import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Building2, CalendarDays, CheckCircle2, CircleDollarSign, FileText, Layers3, ListChecks, MapPin, UsersRound, WalletCards } from 'lucide-react';
import { Button } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
}).format(Number(value ?? 0));

const date = (value, withTime = false) => {
    if (!value) return '-';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return value;
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit', month: 'long', year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(parsed);
};

const label = (value) => String(value ?? '-').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

function StatusBadge({ children, tone = 'neutral' }) {
    const tones = {
        success: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300',
        warning: 'bg-amber-500/12 text-amber-700 dark:text-amber-300',
        danger: 'bg-rose-500/12 text-rose-700 dark:text-rose-300',
        neutral: 'bg-silver-soft text-ink-soft dark:bg-white/10 dark:text-white/70',
    };
    return <span className={`inline-flex rounded-full px-3 py-1 text-xs font-extrabold ${tones[tone]}`}>{children}</span>;
}

export default function Show({ title, description, spk, indexUrl }) {
    const paidPercentage = spk.nilai_kontrak > 0 ? Math.min(100, (Number(spk.paid_total) / Number(spk.nilai_kontrak)) * 100) : 0;
    const statusTone = spk.status === 'selesai' ? 'success' : spk.status === 'batal' ? 'danger' : spk.status === 'aktif' ? 'warning' : 'neutral';

    return (
        <>
            <Head title={`${title} - ${spk.nomor_spk}`} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Input Surat Perjanjian Kerja</p>
                            <div className="mt-2 flex flex-wrap items-center gap-3">
                                <h2 className="font-display text-3xl font-extrabold">{spk.nomor_spk}</h2>
                                <StatusBadge tone={statusTone}>{label(spk.status)}</StatusBadge>
                                <StatusBadge tone={spk.record_status === 'locked' ? 'warning' : 'neutral'}>{spk.record_status === 'locked' ? 'Dokumen Dikunci' : 'Dokumen Draft'}</StatusBadge>
                            </div>
                            <p className="mt-2 text-lg font-bold">{spk.judul_pekerjaan}</p>
                            <p className="mt-1 text-ink-soft dark:text-white/60">{description}</p>
                        </div>
                        <Button type="button" variant="outline" onClick={() => router.get(indexUrl)}><ArrowLeft size={17} /> Kembali ke Input SPK</Button>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        { icon: WalletCards, caption: 'Nilai Kontrak', value: money(spk.nilai_kontrak), highlight: true },
                        { icon: Layers3, caption: 'Tahapan Pekerjaan', value: `${spk.group_count} tahap` },
                        { icon: ListChecks, caption: 'Item Pekerjaan', value: `${spk.item_count} item` },
                        { icon: CircleDollarSign, caption: 'Termin Pembayaran', value: `${spk.payment_count} termin` },
                    ].map(({ icon: Icon, caption, value, highlight }) => (
                        <div className={`rounded-lg border p-5 shadow-soft ${highlight ? 'border-primary/30 bg-primary/10' : 'border-white/80 bg-white/78 dark:border-white/10 dark:bg-white/8'}`} key={caption}>
                            <div className="flex items-center gap-3"><Icon className="text-primary" size={21} /><p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">{caption}</p></div>
                            <p className="mt-3 text-xl font-extrabold">{value}</p>
                        </div>
                    ))}
                </section>

                <section className="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                    <div className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex items-center gap-3"><FileText className="text-primary" size={21} /><h3 className="text-lg font-extrabold">Informasi Kontrak</h3></div>
                        <dl className="mt-5 grid gap-x-8 gap-y-5 md:grid-cols-2">
                            {[
                                ['Jenis Pekerjaan', label(spk.jenis_pekerjaan)],
                                ['Sumber Tenaga Kerja', spk.sumber_tenaga_kerja],
                                ['Kontraktor / Pelaksana', spk.kontraktor],
                                ['Metode Pembayaran', spk.metode_pembayaran === 'cash' ? 'Cash / Sekaligus' : 'Cicil / Termin'],
                                ['Role Approval', spk.approval_role === 'admin' ? 'Admin' : 'Manajer'],
                                ['Tanggal SPK', date(spk.tanggal_spk)],
                                ['Mulai Pekerjaan', date(spk.tanggal_mulai)],
                                ['Target Selesai', date(spk.tanggal_selesai)],
                            ].map(([term, value]) => <div className="border-b border-silver-deep/50 pb-3 dark:border-white/10" key={term}><dt className="text-xs font-extrabold uppercase tracking-[0.1em] text-ink-soft">{term}</dt><dd className="mt-1 font-bold">{value || '-'}</dd></div>)}
                        </dl>
                    </div>

                    <div className="grid gap-4">
                        <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <div className="flex items-center gap-3"><Building2 className="text-primary" size={20} /><h3 className="font-extrabold">Lokasi Pekerjaan</h3></div>
                            <p className="mt-4 font-extrabold">{spk.perumahan}</p>
                            <p className="mt-2 flex items-center gap-2 text-sm font-bold text-ink-soft dark:text-white/60"><MapPin size={15} /> {spk.unit}</p>
                        </div>
                        <div className={`rounded-lg border p-5 shadow-soft ${spk.hpp_plan_exists ? 'border-emerald-500/25 bg-emerald-500/8' : 'border-amber-500/25 bg-amber-500/8'}`}>
                            <div className="flex items-center gap-3"><CheckCircle2 size={20} /><h3 className="font-extrabold">Rencana HPP</h3></div>
                            <p className="mt-3 text-xl font-extrabold">{spk.hpp_plan_exists ? money(spk.hpp_plan_total) : 'Belum diisi'}</p>
                            <p className="mt-1 text-xs font-bold text-ink-soft">Acuan HPP untuk {spk.hpp_plan_label}</p>
                        </div>
                    </div>
                </section>

                <section className="grid gap-5">
                    <div><h3 className="text-xl font-extrabold">Rincian Tahapan Pekerjaan</h3><p className="mt-1 text-sm text-ink-soft dark:text-white/60">Daftar item dan nilai upah yang membentuk total SPK.</p></div>
                    {(spk.groups ?? []).map((group, groupIndex) => (
                        <article className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8" key={`${group.title}-${groupIndex}`}>
                            <div className="flex flex-col gap-3 border-b border-silver-deep/60 bg-silver-soft/70 px-5 py-4 dark:border-white/10 dark:bg-white/5 md:flex-row md:items-center md:justify-between">
                                <div className="flex items-center gap-3"><span className="flex h-9 w-9 items-center justify-center rounded-full bg-ink font-extrabold text-white dark:bg-white dark:text-ink">{groupIndex + 1}</span><div><h4 className="font-extrabold">{group.title}</h4><p className="text-xs font-bold text-ink-soft">{group.items?.length ?? 0} item pekerjaan</p></div></div>
                                <p className="text-sm font-extrabold">Subtotal {money(group.total)}</p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                                    <thead className="text-left text-xs uppercase tracking-[0.12em] text-ink-soft"><tr><th className="px-5 py-3">No</th><th className="px-5 py-3">Uraian Pekerjaan</th><th className="px-5 py-3">Volume</th><th className="px-5 py-3 text-right">Harga</th><th className="px-5 py-3 text-right">Total</th></tr></thead>
                                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                        {(group.items ?? []).map((item, itemIndex) => <tr key={item.id}><td className="px-5 py-4 font-bold">{itemIndex + 1}</td><td className="px-5 py-4 font-semibold">{item.nama_pekerjaan}</td><td className="px-5 py-4">{Number(item.volume).toLocaleString('id-ID')} {item.satuan}</td><td className="px-5 py-4 text-right font-bold">{money(item.harga_satuan)}</td><td className="px-5 py-4 text-right font-extrabold">{money(item.total)}</td></tr>)}
                                        <tr className="bg-silver-soft/70 font-extrabold dark:bg-white/5"><td className="px-5 py-4 text-right" colSpan={4}>SUBTOTAL {group.title}</td><td className="px-5 py-4 text-right">{money(group.total)}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    ))}
                </section>

                <section className="grid gap-6 xl:grid-cols-[1fr_0.45fr]">
                    <div className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10"><h3 className="text-lg font-extrabold">Jadwal Pembayaran</h3><p className="mt-1 text-sm text-ink-soft">Status setiap termin SPK.</p></div>
                        <div className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {(spk.payments ?? []).map((payment) => <div className="grid gap-4 p-5 md:grid-cols-[1fr_auto]" key={payment.id}><div><div className="flex flex-wrap items-center gap-2"><p className="font-extrabold">Termin {payment.termin_ke}</p><StatusBadge tone={payment.status === 'dana_cair' ? 'success' : payment.status.includes('approval') ? 'warning' : 'neutral'}>{payment.status_label}</StatusBadge></div><p className="mt-2 text-sm text-ink-soft">Jatuh tempo {date(payment.tanggal_jatuh_tempo)} • Dibayar {date(payment.tanggal_pembayaran)}</p><p className="mt-1 text-sm">{payment.keterangan || '-'}</p>{payment.opname && <p className="mt-1 text-xs font-bold text-ink-soft">Referensi opname: {payment.opname}</p>}</div><p className="text-lg font-extrabold">{money(payment.nominal)}</p></div>)}
                        </div>
                    </div>
                    <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Realisasi Pembayaran</p><p className="mt-2 text-2xl font-extrabold">{money(spk.paid_total)}</p><p className="mt-1 text-sm font-bold text-ink-soft">dari {money(spk.nilai_kontrak)}</p>
                        <div className="mt-5 h-3 overflow-hidden rounded-full bg-silver-deep/60 dark:bg-white/10"><div className="h-full rounded-full bg-emerald-500" style={{ width: `${paidPercentage}%` }} /></div><p className="mt-2 text-right text-sm font-extrabold">{paidPercentage.toFixed(1)}%</p>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"><h3 className="font-extrabold">Lingkup Pekerjaan</h3><p className="mt-3 whitespace-pre-wrap leading-7 text-ink-soft dark:text-white/65">{spk.lingkup_pekerjaan || '-'}</p></div>
                    <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"><h3 className="font-extrabold">Catatan SPK</h3><p className="mt-3 whitespace-pre-wrap leading-7 text-ink-soft dark:text-white/65">{spk.catatan || '-'}</p></div>
                </section>

                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex items-center gap-3"><UsersRound className="text-primary" size={20} /><h3 className="font-extrabold">Riwayat Dokumen</h3></div>
                    <div className="mt-4 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                        <div><p className="font-extrabold">Dibuat</p><p className="text-ink-soft">{spk.created_by || '-'} • {date(spk.created_at, true)}</p></div>
                        <div><p className="font-extrabold">Terakhir diperbarui</p><p className="text-ink-soft">{spk.updated_by || '-'} • {date(spk.updated_at, true)}</p></div>
                        <div><p className="font-extrabold">Disetujui</p><p className="text-ink-soft">{spk.approved_by || '-'} • {date(spk.approved_at, true)}</p></div>
                        <div><p className="font-extrabold">Dikunci</p><p className="text-ink-soft">{spk.locked_by || '-'} • {date(spk.locked_at, true)}</p></div>
                    </div>
                </section>
            </div>
        </>
    );
}

Show.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Detail Input SPK'}>{page}</AdminLayout>;
