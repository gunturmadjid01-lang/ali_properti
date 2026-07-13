import { Head, router } from '@inertiajs/react';
import { FileBarChart, FileText } from 'lucide-react';
import { Button } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function Index({ title, description, groups = [] }) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Pusat Laporan</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {groups.map((group) => (
                        <button
                            type="button"
                            key={group.key}
                            onClick={() => router.visit(group.href)}
                            className="rounded-lg border border-white/80 bg-white/78 p-5 text-left shadow-soft transition hover:-translate-y-0.5 hover:border-emerald-400/60 dark:border-white/10 dark:bg-white/8"
                        >
                            <span className="flex h-11 w-11 items-center justify-center rounded-lg border border-silver-deep/60 bg-silver-soft/70 dark:border-white/10 dark:bg-white/10">
                                <FileBarChart size={20} />
                            </span>
                            <h3 className="mt-4 font-display text-xl font-extrabold">{group.title}</h3>
                            <p className="mt-2 min-h-16 text-sm leading-6 text-ink-soft dark:text-white/55">{group.description}</p>
                            <div className="mt-4">
                                <Button type="button" size="sm" variant="outline">
                                    <FileText size={15} />
                                    Buka Laporan
                                </Button>
                            </div>
                        </button>
                    ))}
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Laporan'}>{page}</AdminLayout>;
