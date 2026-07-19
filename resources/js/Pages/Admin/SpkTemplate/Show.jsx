import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Building2, Edit3, Layers3, ListChecks, WalletCards } from 'lucide-react';
import { Button } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
}).format(Number(value ?? 0));

export default function Show({ title, description, template, indexUrl, editUrl, canUpdate }) {
    return (
        <>
            <Head title={`${title} - ${template.nama_template}`} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                        <div><p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">RAB Referensi • Upah Borongan Tukang</p><h2 className="mt-2 font-display text-3xl font-extrabold">{template.nama_template}</h2><p className="mt-2 text-ink-soft dark:text-white/60">{description}</p></div>
                        <div className="flex flex-wrap gap-2"><Button type="button" variant="outline" onClick={() => router.get(indexUrl)}><ArrowLeft size={17} /> Kembali</Button>{canUpdate && <Button type="button" onClick={() => router.get(editUrl)}><Edit3 size={17} /> Ubah Template</Button>}</div>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        { label: 'Perumahan', value: template.perumahan, icon: Building2 },
                        { label: 'Total Tahap', value: template.group_count, icon: Layers3 },
                        { label: 'Total Item', value: template.item_count, icon: ListChecks },
                        { label: 'Total Upah Borongan', value: money(template.total_nilai), icon: WalletCards, highlight: true },
                    ].map(({ label, value, icon: Icon, highlight }) => <div className={`rounded-lg border p-5 shadow-soft ${highlight ? 'border-primary/30 bg-primary/10' : 'border-white/80 bg-white/78 dark:border-white/10 dark:bg-white/8'}`} key={label}><div className="flex items-center gap-3"><Icon className="text-primary" size={21} /><p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">{label}</p></div><p className="mt-3 text-xl font-extrabold">{value}</p></div>)}
                </section>

                {template.catatan && <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"><p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Catatan Template</p><p className="mt-2 leading-7">{template.catatan}</p></section>}

                <section className="grid gap-5">
                    {(template.groups ?? []).map((group, groupIndex) => {
                        const subtotal = (group.items ?? []).reduce((sum, item) => sum + Number(item.harga_satuan || 0), 0);
                        return <article className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8" key={group.id ?? groupIndex}>
                            <div className="flex flex-col gap-3 border-b border-silver-deep/60 bg-silver-soft/70 px-5 py-4 dark:border-white/10 dark:bg-white/5 md:flex-row md:items-center md:justify-between"><div className="flex items-center gap-3"><span className="flex h-9 w-9 items-center justify-center rounded-full bg-ink font-extrabold text-white dark:bg-white dark:text-ink">{groupIndex + 1}</span><div><h3 className="font-extrabold">{group.judul_tahapan}</h3><p className="text-xs font-bold text-ink-soft">{group.items?.length ?? 0} item pekerjaan</p></div></div><p className="text-sm font-extrabold">Subtotal {money(subtotal)}</p></div>
                            <div className="overflow-x-auto"><table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10"><thead className="text-left text-xs uppercase tracking-[0.12em] text-ink-soft"><tr><th className="px-5 py-3">No</th><th className="px-5 py-3">Uraian Pekerjaan</th><th className="px-5 py-3 text-right">Upah Borongan</th></tr></thead><tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">{(group.items ?? []).map((item, itemIndex) => <tr key={item.id ?? itemIndex}><td className="px-5 py-4 font-bold">{itemIndex + 1}</td><td className="px-5 py-4 font-semibold">{item.nama_pekerjaan}</td><td className="px-5 py-4 text-right font-extrabold">{money(item.harga_satuan)}</td></tr>)}<tr className="bg-silver-soft/70 font-extrabold dark:bg-white/5"><td className="px-5 py-4 text-right" colSpan={2}>SUBTOTAL {group.judul_tahapan}</td><td className="px-5 py-4 text-right">{money(subtotal)}</td></tr></tbody></table></div>
                        </article>;
                    })}
                </section>

                <section className="flex flex-col gap-3 rounded-lg bg-ink p-6 text-white shadow-soft md:flex-row md:items-center md:justify-between dark:bg-white dark:text-ink"><div><p className="text-xs font-extrabold uppercase tracking-[0.14em] opacity-70">Grand Total Upah Borongan</p><p className="mt-2 text-3xl font-extrabold">{money(template.total_nilai)}</p></div><p className="text-sm font-bold opacity-70">{template.group_count} tahap • {template.item_count} item pekerjaan</p></section>
            </div>
        </>
    );
}

Show.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Detail Template Pekerjaan SPK'}>{page}</AdminLayout>;
