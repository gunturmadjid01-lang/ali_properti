import { Head, router } from '@inertiajs/react';
import { Bell, CheckCircle2 } from 'lucide-react';
import { Button } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function Index({ title, rows, baseUrl }) {
    return (
        <>
            <Head title={title} />
            <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                <div className="border-b border-silver-deep/60 p-5">
                    <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Inbox</p>
                    <h2 className="mt-1 text-xl font-extrabold">{title}</h2>
                </div>
                <div className="divide-y divide-silver-deep/50">
                    {rows.data.map((row) => (
                        <div className="flex flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between" key={row.id}>
                            <div className="min-w-0">
                                <div className="flex items-center gap-2">
                                    <Bell size={17} />
                                    <p className="font-extrabold">{row.title}</p>
                                    {!row.read_at && <span className="rounded-full bg-gold px-2 py-0.5 text-[10px] font-black text-ink">BARU</span>}
                                </div>
                                <p className="mt-1 text-sm font-semibold text-ink-soft">{row.message}</p>
                            </div>
                            {!row.read_at && (
                                <Button type="button" size="sm" variant="outline" onClick={() => router.post(`${baseUrl}/${row.id}/read`, {}, { preserveScroll: true })}>
                                    <CheckCircle2 size={15} /> Tandai Dibaca
                                </Button>
                            )}
                        </div>
                    ))}
                    {rows.data.length === 0 && <p className="p-8 text-center font-bold text-ink-soft">Belum ada notifikasi.</p>}
                </div>
            </section>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Notifikasi'}>{page}</AdminLayout>;
