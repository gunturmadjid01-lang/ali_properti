import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit3 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';
import { useResourcePermissions } from '../../../../Utils/permissions';
import HppFormModal from './HppFormModal';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function Hpp({
    title = 'HPP Perumahan',
    backLabel = 'Perumahan',
    metaLine,
    perumahan = {},
    rows = [],
    summary = { jumlah_rab: 0, jumlah_realisasi: 0, sisa_anggaran: 0 },
    options,
    baseUrl,
    detailUrl,
    hppUrl,
}) {
    const [editing, setEditing] = useState(null);
    const [search, setSearch] = useState('');
    const [pageSize, setPageSize] = useState('10');
    const hppPermissions = useResourcePermissions('hpp', hppUrl);
    const unitPermissions = useResourcePermissions('detail-rumah', baseUrl);
    const canEditHpp = hppPermissions.canUpdate || unitPermissions.canUpdate;
    const pageTitle = `${title} ${perumahan.nama_perusahaan ?? ''}`.trim();

    const filteredRows = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        if (!keyword) {
            return rows;
        }

        return rows.filter((row) => [
            row.tanggal,
            row.kelompok_hpp_nama,
            row.volume,
            row.satuan,
            row.harga_satuan,
            row.jumlah_rab,
            row.jumlah_realisasi,
            row.sisa_anggaran,
        ].some((value) => String(value ?? '').toLowerCase().includes(keyword)));
    }, [rows, search]);

    const visibleRows = pageSize === 'all' ? filteredRows : filteredRows.slice(0, Number(pageSize));

    return (
        <>
            <Head title={pageTitle} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div className="mb-3 flex flex-wrap gap-2">
                                <Button as={Link} href={baseUrl} variant="ghost" size="sm">
                                    <ArrowLeft size={16} /> {backLabel}
                                </Button>
                                <Button as={Link} href={detailUrl} variant="outline" size="sm">
                                    Detail Rumah
                                </Button>
                            </div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">{title}</p>
                            <h2 className="mt-2 font-display text-3xl font-extrabold">{perumahan.nama_perusahaan}</h2>
                            <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">
                                {metaLine ?? `${perumahan.cabang ?? '-'} | ${perumahan.alamat ?? '-'}`}
                            </p>
                        </div>
                    </div>

                    <div className="mt-5 grid gap-3 md:grid-cols-3">
                        <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Jumlah RAB</p>
                            <p className="mt-1 text-xl font-extrabold">{money(summary.jumlah_rab)}</p>
                        </div>
                        <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Jumlah Realisasi</p>
                            <p className="mt-1 text-xl font-extrabold">{money(summary.jumlah_realisasi)}</p>
                        </div>
                        <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Sisa Anggaran</p>
                            <p className="mt-1 text-xl font-extrabold">{money(summary.sisa_anggaran)}</p>
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-3 border-b border-silver-deep/60 px-5 py-4 dark:border-white/10 md:flex-row md:items-center md:justify-between">
                        <label className="flex items-center gap-2 text-xs font-bold text-ink-soft">
                            Show
                            <select
                                className="rounded-lg border border-silver-deep bg-white px-3 py-2 text-xs font-extrabold text-ink outline-none transition focus:border-ink dark:border-white/10 dark:bg-white/8 dark:text-white"
                                value={pageSize}
                                onChange={(event) => setPageSize(event.target.value)}
                            >
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="all">Semua</option>
                            </select>
                            entries
                        </label>
                        <label className="flex items-center gap-2 text-xs font-bold text-ink-soft">
                            Search:
                            <input
                                className="w-full rounded-lg border border-silver-deep bg-white px-3 py-2 text-xs font-semibold text-ink outline-none transition placeholder:text-ink-soft/60 focus:border-ink dark:border-white/10 dark:bg-white/8 dark:text-white md:w-72"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Cari kelompok HPP..."
                            />
                        </label>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Tanggal', 'Kelompok', 'Volume', 'Satuan', 'Harga Satuan', 'Jumlah RAB', 'Jumlah Realisasi', 'Sisa Anggaran', ...(canEditHpp ? ['Aksi'] : [])].map((column) => (
                                        <th className="px-4 py-3 font-extrabold" key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {visibleRows.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id ? `hpp-item-${row.id}` : `hpp-master-${row.kelompok_hpp_id}`}>
                                        <td className="px-4 py-3 font-semibold">{row.tanggal ?? '-'}</td>
                                        <td className="px-4 py-3 font-semibold">{row.kelompok_hpp_nama ?? '-'}</td>
                                        <td className="px-4 py-3 font-semibold">{row.volume}</td>
                                        <td className="px-4 py-3 font-semibold">{row.satuan}</td>
                                        <td className="px-4 py-3 font-semibold">{money(row.harga_satuan)}</td>
                                        <td className="px-4 py-3 font-extrabold">{money(row.jumlah_rab)}</td>
                                        <td className="px-4 py-3 font-extrabold">{money(row.jumlah_realisasi)}</td>
                                        <td className="px-4 py-3 font-extrabold">{money(row.sisa_anggaran)}</td>
                                        {canEditHpp && (
                                            <td className="px-4 py-3">
                                                <Button variant="outline" size="sm" type="button" onClick={() => setEditing({ mode: 'edit', row })}>
                                                    <Edit3 size={15} /> Edit
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {visibleRows.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={canEditHpp ? 9 : 8}>
                                            Data HPP tidak ditemukan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {editing && canEditHpp && (
                <HppFormModal
                    open={Boolean(editing)}
                    title={`Edit HPP ${editing.row.kelompok_hpp_nama}`}
                    actionUrl={`${hppUrl}/${editing.row.id ?? `new-${editing.row.kelompok_hpp_id}`}`}
                    items={[editing.row]}
                    options={options}
                    singleItem
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

Hpp.layout = (page) => <AdminLayout title={page?.props?.title ?? 'HPP Perumahan'}>{page}</AdminLayout>;

export default Hpp;
