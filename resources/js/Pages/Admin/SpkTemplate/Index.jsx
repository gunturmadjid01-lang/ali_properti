import { Head, router } from '@inertiajs/react';
import { Edit3, Eye, PlusCircle, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button, Dropdown, Input } from '../../../Components/UI';
import Pagination from '../../../Components/Pagination';
import AdminLayout from '../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
}).format(Number(value ?? 0));

export default function Index({ title, description, context, baseUrl, createUrl, rows, filters = {}, options = {}, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedContext, setSelectedContext] = useState(context ?? 'perumahan');

    useEffect(() => setSelectedContext(context ?? 'perumahan'), [context]);

    const applyFilter = (nextContext = selectedContext) => {
        router.get(baseUrl, { context: nextContext, search }, { preserveState: true, replace: true });
    };

    const destroyRow = (row) => {
        if (window.confirm(`Hapus template ${row.nama_template}?`)) {
            router.delete(`${baseUrl}/${row.id}?context=${selectedContext}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Manajemen Proyek</p>
                            <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                            <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                        </div>
                        {permissions.canCreate && (
                            <Button type="button" onClick={() => router.get(createUrl)}>
                                <PlusCircle size={17} /> Tambah Template
                            </Button>
                        )}
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="grid gap-4 border-b border-silver-deep/60 p-5 dark:border-white/10 md:grid-cols-[220px_1fr_auto] md:items-end" onSubmit={(event) => { event.preventDefault(); applyFilter(); }}>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Konteks Template</span>
                            <Dropdown value={selectedContext} options={options.contexts ?? []} onChange={(value) => { setSelectedContext(value); router.get(baseUrl, { context: value }, { replace: true }); }} />
                        </div>
                        <Input label="Pencarian" value={search} placeholder="Cari nama template atau perumahan..." onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Perumahan', 'Nama Template', 'Tahap', 'Item', 'Nilai Template', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {(rows.data ?? []).map((row) => (
                                    <tr className="transition hover:bg-silver-soft/60 dark:hover:bg-white/5" key={row.id}>
                                        <td className="px-5 py-4 font-semibold">{row.perumahan}</td>
                                        <td className="px-5 py-4 font-extrabold">{row.nama_template}</td>
                                        <td className="px-5 py-4 font-bold">{row.group_count}</td>
                                        <td className="px-5 py-4 font-bold">{row.item_count}</td>
                                        <td className="px-5 py-4 font-extrabold">{money(row.total_nilai)}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                <Button type="button" size="sm" variant="outline" onClick={() => router.get(`${baseUrl}/${row.id}`)}><Eye size={15} /> Detail</Button>
                                                {row.can_edit && <Button type="button" size="sm" variant="outline" onClick={() => router.get(`${baseUrl}/${row.id}/edit`)}><Edit3 size={15} /> Edit</Button>}
                                                {row.can_delete && <Button type="button" size="sm" variant="outline" className="text-red-600" onClick={() => destroyRow(row)}><Trash2 size={15} /> Hapus</Button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {(rows.data ?? []).length === 0 && <tr><td className="px-5 py-12 text-center font-bold text-ink-soft" colSpan={6}>Belum ada template pekerjaan.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links ?? []} />
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Template Pekerjaan SPK'}>{page}</AdminLayout>;
