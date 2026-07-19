import { Head, router } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { useState } from 'react';
import { Button, Dropdown, Input } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function DataPreview({ title, data }) {
    const entries = Object.entries(data ?? {}).filter(([, value]) => value !== null && value !== '');

    return (
        <div className="rounded-lg border border-silver-deep/70 bg-white/70 p-4 dark:border-white/10 dark:bg-white/8">
            <h4 className="text-sm font-extrabold text-ink dark:text-white">{title}</h4>
            <div className="mt-3 grid gap-2 text-xs">
                {entries.slice(0, 10).map(([key, value]) => (
                    <div className="grid gap-1 rounded-md bg-silver-soft/70 p-2 dark:bg-white/5" key={key}>
                        <span className="font-extrabold uppercase tracking-[0.08em] text-ink-soft dark:text-white/45">{key}</span>
                        <span className="break-words font-bold text-ink/80 dark:text-white/70">
                            {Array.isArray(value) ? value.join(', ') : String(value)}
                        </span>
                    </div>
                ))}
                {entries.length === 0 && <p className="font-bold text-ink-soft dark:text-white/50">Tidak ada data.</p>}
            </div>
        </div>
    );
}

function StatusBadge({ status }) {
    const classes = {
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200',
        approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-400/15 dark:text-red-200',
    };

    return <span className={`rounded-full px-3 py-1 text-xs font-extrabold ${classes[status] ?? classes.pending}`}>{status}</span>;
}

function Pagination({ links = [] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
            {links.map((link, index) => (
                <Button
                    as="a"
                    className={!link.url ? 'pointer-events-none opacity-45' : ''}
                    href={link.url ?? '#'}
                    key={`${link.label}-${index}`}
                    size="sm"
                    variant={link.active ? 'dark' : 'outline'}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

function ApprovalRow({ row }) {
    return (
        <article className="rounded-lg border border-silver-deep/70 bg-white/76 p-5 dark:border-white/10 dark:bg-white/8">
            <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={row.status} />
                    </div>
                    <p className="mt-3 text-xs font-extrabold uppercase tracking-wider text-ink-soft">{row.module_label}</p>
                    <h3 className="mt-1 text-lg font-extrabold">{row.business_title}</h3>
                    <p className="mt-1 text-xs font-extrabold text-amber-700 dark:text-amber-300">Tahap {row.current_step} dari {row.total_steps}</p>
                    <p className="mt-1 text-sm font-semibold text-ink-soft dark:text-white/55">
                        Diajukan oleh {row.requested_by ?? '-'} pada {row.created_at}
                    </p>
                    {row.reviewed_by && (
                        <p className="mt-1 text-sm font-semibold text-ink-soft dark:text-white/55">
                            Diproses oleh {row.reviewed_by} pada {row.reviewed_at}
                        </p>
                    )}
                </div>
                {row.business_detail_url ? (
                    <Button variant="outline" type="button" onClick={() => router.get(row.business_detail_url)}>
                        <Eye size={17}/> Lihat Detail
                    </Button>
                ) : (
                    <span className="rounded-lg border border-dashed px-3 py-2 text-xs font-bold text-ink-soft">Detail sumber belum tersedia</span>
                )}
            </div>

            {(row.step_history ?? []).length > 0 && (
                <div className="mt-4 rounded-lg bg-silver-soft/70 p-4 text-xs dark:bg-white/5">
                    <p className="font-extrabold">Riwayat Tahap</p>
                    {(row.step_history ?? []).map((item, index) => (
                        <p className="mt-1 font-semibold text-ink-soft dark:text-white/60" key={`${item.step}-${index}`}>
                            Tahap {item.step}: {item.decision} oleh {item.user_name ?? '-'} {item.note ? `— ${item.note}` : ''}
                        </p>
                    ))}
                </div>
            )}

            <div className="mt-5"><DataPreview title="Ringkasan Transaksi" data={row.business_summary} /></div>
        </article>
    );
}

function Index({ title, baseUrl, filters, rows, statusOptions }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'pending');

    const submit = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search, status }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Persetujuan</p>
                    <h2 className="mt-1 text-xl font-extrabold">{title}</h2>
                    <p className="mt-1 max-w-2xl text-sm leading-6 text-ink-soft dark:text-white/60">
                        Semua perubahan data yang membutuhkan approval akan masuk di halaman ini sebelum diterapkan ke database utama.
                    </p>
                </section>

                <section className="rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 md:flex-row md:items-end" onSubmit={submit}>
                        <Input className="md:max-w-md" label="Pencarian" value={search} onChange={(event) => setSearch(event.target.value)} />
                        <div className="grid gap-2 md:w-56">
                            <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Status</span>
                            <Dropdown value={status} options={statusOptions} onChange={setStatus} />
                        </div>
                        <Button type="submit">
                            <Search size={17} /> Cari
                        </Button>
                    </form>

                    <div className="grid gap-4 p-5 pt-0">
                        {rows.data.map((row) => <ApprovalRow row={row} key={row.id} />)}
                        {rows.data.length === 0 && (
                            <div className="rounded-lg border border-dashed border-silver-deep p-10 text-center font-bold text-ink-soft dark:border-white/10 dark:text-white/50">
                                Belum ada request approval.
                            </div>
                        )}
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Admin'}>{page}</AdminLayout>;

export default Index;
