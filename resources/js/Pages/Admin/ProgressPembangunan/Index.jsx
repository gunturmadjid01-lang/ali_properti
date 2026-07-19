import { Head, router } from '@inertiajs/react';
import { CheckCircle2, Eye, LockKeyhole, PlusCircle, Search, Trash2, Unlock } from 'lucide-react';
import { useMemo, useState } from 'react';
import Pagination from '../../../Components/Pagination';
import { Button, Dropdown, Input, TableActions } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

export default function Index({ title, description, baseUrl, rows, filters = {}, options = {}, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [filterPerumahan, setFilterPerumahan] = useState(filters.perumahan_id ?? '');
    const [filterUnit, setFilterUnit] = useState(filters.detail_rumah_id ?? '');

    const canCreate = permissions.canCreate ?? false;
    const canDelete = permissions.canDelete ?? false;
    const canApprove = permissions.canApprove ?? false;
    const canLock = permissions.canLock ?? false;
    const canUnlock = permissions.canUnlock ?? false;

    const perumahanOptions = options.perumahans ?? [];
    const detailRumahOptions = useMemo(() => {
        if (!filterPerumahan) {
            return options.detailRumahs ?? [];
        }

        return (options.detailRumahs ?? []).filter((item) => item.perumahan_id === String(filterPerumahan));
    }, [filterPerumahan, options.detailRumahs]);
    const submitFilters = (event) => {
        event.preventDefault();
        router.get(baseUrl, {
            search,
            perumahan_id: filterPerumahan,
            detail_rumah_id: filterUnit,
        }, { preserveScroll: true, preserveState: true, replace: true });
    };

    const resetFilters = () => {
        setSearch('');
        setFilterPerumahan('');
        setFilterUnit('');
        router.get(baseUrl, {}, { preserveScroll: true, preserveState: true, replace: true });
    };

    const destroyRow = (row) => {
        if (!window.confirm('Hapus progress pembangunan ini?')) {
            return;
        }

        router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    const approveRow = (row) => {
        router.post(`${baseUrl}/${row.id}/approve`, {}, { preserveScroll: true });
    };

    const lockRow = (row) => {
        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockRow = (row) => {
        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Manajemen Proyek</p>
                            <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                            <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                        </div>
                        {canCreate && (
                            <Button type="button" onClick={() => router.visit('/admin/progress-pembangunan/create')}>
                                <PlusCircle size={16} />
                                Tambah Progress
                            </Button>
                        )}
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 lg:grid-cols-[1.2fr_1fr_1fr_auto_auto]"
                        onSubmit={submitFilters}
                    >
                        <Input
                            label="Pencarian"
                            value={search}
                            placeholder="Cari perumahan, blok, atau unit..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Perumahan</span>
                            <Dropdown
                                value={filterPerumahan}
                                label="Semua Perumahan"
                                options={[{ value: '', label: 'Semua Perumahan' }, ...perumahanOptions]}
                                onChange={(value) => {
                                    setFilterPerumahan(value);
                                    setFilterUnit('');
                                }}
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Unit</span>
                            <Dropdown
                                value={filterUnit}
                                label="Semua Unit"
                                options={[{ value: '', label: 'Semua Unit' }, ...detailRumahOptions]}
                                onChange={setFilterUnit}
                            />
                        </div>
                        <div className="flex items-end">
                            <Button className="w-full" type="submit">
                                <Search size={17} />
                                Cari
                            </Button>
                        </div>
                        <div className="flex items-end">
                            <Button className="w-full" type="button" variant="outline" onClick={resetFilters}>
                                Atur Ulang
                            </Button>
                        </div>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Tanggal', 'Kemajuan', 'Sumber', 'Perumahan', 'Unit', 'Item Jadwal', 'Nilai', 'Persetujuan', 'Audit', 'Aksi'].map((column) => (
                                        <th className="px-5 py-4 font-extrabold" key={column}>
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">{row.tanggal}</td>
                                        <td className="px-5 py-4 font-bold">{row.nama_progress}</td>
                                        <td className="px-5 py-4 text-xs font-bold text-ink-soft">{row.source_label || 'Input Manual'}</td>
                                        <td className="px-5 py-4">{row.perumahan}</td>
                                        <td className="px-5 py-4">{row.unit}</td>
                                        <td className="px-5 py-4">{row.tahapan}</td>
                                        <td className="px-5 py-4 font-bold">{row.persentase}% ({Number(row.persentase_total ?? 0).toFixed(2)}%)</td>
                                        <td className="px-5 py-4 font-bold">{row.approval_label}</td>
                                        <td className="min-w-44 px-5 py-4 text-xs">
                                            <span className="font-bold">Dibuat:</span> {row.created_by_name}<br />
                                            <span className="font-bold">Diubah:</span> {row.updated_by_name}<br />
                                            <span className="font-bold">Setujui:</span> {row.approved_by}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                <Button type="button" size="sm" variant="outline" onClick={() => router.visit(`${baseUrl}/${row.id}`)}>
                                                    <Eye size={15} />
                                                    Detail
                                                </Button>
                                                {canDelete && row.can_delete && row.approval_status !== 'approved' && (
                                                    <Button type="button" size="sm" variant="outline" className="text-red-600" onClick={() => destroyRow(row)}>
                                                        <Trash2 size={15} />
                                                        Hapus
                                                    </Button>
                                                )}
                                                {canUnlock && row.can_unlock && row.record_status === 'locked' && (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}>
                                                        <Unlock size={15} />
                                                        Unlock
                                                    </Button>
                                                )}
                                                {canLock && row.can_lock && (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}>
                                                        <LockKeyhole size={15} />
                                                        Lock
                                                    </Button>
                                                )}
                                                {canApprove && row.can_approve && row.approval_status === 'menunggu_approval_manager' && (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => approveRow(row)}>
                                                        <CheckCircle2 size={15} />
                                                        Approve
                                                    </Button>
                                                )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={10}>
                                            Belum ada progress pembangunan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Kemajuan Pembangunan'}>{page}</AdminLayout>;
