import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronRight, ClipboardList, PackageSearch, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import Accordion from '../../../../Components/UI/Accordion';
import { Button, Input } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';
import HppFormModal from './HppFormModal';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function badgeClass(status) {
    if (status === 'approved' || status === 'selesai') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200';
    }
    if (status === 'diajukan' || status === 'menunggu_approval_manager') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200';
    }
    if (status === 'ditolak') {
        return 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-200';
    }
    return 'bg-silver-soft text-ink-soft dark:bg-white/10 dark:text-white/70';
}

export default function UnitDetail({
    title,
    baseUrl,
    hppDetailUrl,
    hppRows = [],
    hppSummary = { jumlah_rab: 0, jumlah_realisasi: 0, sisa_anggaran: 0 },
    hppUrl,
    permissions = {},
    perumahan = {},
    rumah = {},
    progressRows = [],
    materialRows = [],
    logistikRows = [],
    filters = {},
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editingHpp, setEditingHpp] = useState(null);
    const { options = {} } = usePage().props;
    const canManageHpp = Boolean(permissions?.canManageHpp);
    const showHppSection = rumah?.status_pembangunan !== 'kapling';

    const keyword = search.trim().toLowerCase();

    const filteredProgress = useMemo(() => {
        if (!keyword) return progressRows;
        return progressRows.filter((row) => [
            row.tahapan,
            row.keterangan,
            row.status_label,
            row.input_oleh,
            row.approved_by,
        ].some((value) => String(value ?? '').toLowerCase().includes(keyword)));
    }, [keyword, progressRows]);

    const filteredRequests = useMemo(() => {
        if (!keyword) return materialRows;
        return materialRows.filter((row) => [
            row.kode_request,
            row.gudang,
            row.tahapan,
            row.status_label,
            row.items_text,
            row.keterangan,
        ].some((value) => String(value ?? '').toLowerCase().includes(keyword)));
    }, [keyword, materialRows]);

    const filteredLogistik = useMemo(() => {
        if (!keyword) return logistikRows;
        return logistikRows.filter((row) => [
            row.kode_transaksi,
            row.gudang,
            row.keterangan,
            row.items_text,
            row.jenis,
        ].some((value) => String(value ?? '').toLowerCase().includes(keyword)));
    }, [keyword, logistikRows]);

    const filteredHppRows = useMemo(() => {
        if (!keyword) return hppRows;
        return hppRows.filter((row) => [
            row.tanggal,
            row.kelompok_hpp_nama,
            row.volume,
            row.satuan,
            row.harga_satuan,
            row.jumlah_rab,
            row.jumlah_realisasi,
            row.sisa_anggaran,
        ].some((value) => String(value ?? '').toLowerCase().includes(keyword)));
    }, [keyword, hppRows]);

    const tahapanSummary = useMemo(() => {
        const grouped = {};

        filteredProgress.forEach((row) => {
            const key = row.tahapan_id ?? row.tahapan;
            if (!grouped[key]) {
                grouped[key] = {
                    tahapan_id: row.tahapan_id ?? row.tahapan,
                    tahapan: row.tahapan,
                    bobot_tahapan: Number(row.bobot_tahapan ?? 0),
                    total_input: 0,
                    total_kontribusi: 0,
                    rows: [],
                };
            }

            grouped[key].total_input += Number(row.persentase ?? 0);
            grouped[key].total_kontribusi += Number(row.persentase_total ?? 0);
            grouped[key].rows.push(row);
        });

        return Object.values(grouped);
    }, [filteredProgress]);

    const requestApprove = (row) => {
        router.post(row.approve_url, {}, { preserveScroll: true });
    };

    const hppAccordion = {
        title: 'HPP Unit Rumah',
        content: (
            <div className="grid gap-5">
                <div className="grid gap-3 md:grid-cols-3">
                    <div className="rounded-lg border border-silver-deep/50 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                        <p className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">Jumlah RAB</p>
                        <p className="mt-1 text-lg font-extrabold text-ink dark:text-white">{money(hppSummary.jumlah_rab)}</p>
                    </div>
                    <div className="rounded-lg border border-silver-deep/50 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                        <p className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">Jumlah Realisasi</p>
                        <p className="mt-1 text-lg font-extrabold text-ink dark:text-white">{money(hppSummary.jumlah_realisasi)}</p>
                    </div>
                    <div className="rounded-lg border border-silver-deep/50 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                        <p className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">Sisa Anggaran</p>
                        <p className="mt-1 text-lg font-extrabold text-ink dark:text-white">{money(hppSummary.sisa_anggaran)}</p>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Button as={Link} href={hppDetailUrl} variant="outline" size="sm">
                        Lihat Halaman HPP Penuh
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border border-silver-deep/50 dark:border-white/10">
                    <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                        <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                            <tr>
                                {['Tanggal', 'Kelompok', 'Volume', 'Satuan', 'Harga Satuan', 'Jumlah RAB', 'Jumlah Realisasi', 'Sisa Anggaran', ...(canManageHpp ? ['Aksi'] : [])].map((column) => (
                                    <th className="px-4 py-3 font-extrabold" key={column}>{column}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {filteredHppRows.map((row) => (
                                <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id ? `hpp-item-${row.id}` : `hpp-master-${row.kelompok_hpp_id}`}>
                                    <td className="px-4 py-3 font-semibold">{row.tanggal ?? '-'}</td>
                                    <td className="px-4 py-3 font-semibold">{row.kelompok_hpp_nama ?? '-'}</td>
                                    <td className="px-4 py-3 font-semibold">{row.volume}</td>
                                    <td className="px-4 py-3 font-semibold">{row.satuan}</td>
                                    <td className="px-4 py-3 font-semibold">{money(row.harga_satuan)}</td>
                                    <td className="px-4 py-3 font-extrabold">{money(row.jumlah_rab)}</td>
                                    <td className="px-4 py-3 font-extrabold">{money(row.jumlah_realisasi)}</td>
                                    <td className="px-4 py-3 font-extrabold">{money(row.sisa_anggaran)}</td>
                                    {canManageHpp && (
                                        <td className="px-4 py-3">
                                            <Button type="button" size="sm" variant="outline" onClick={() => setEditingHpp(row)}>
                                                <ClipboardList size={15} /> Edit HPP
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {filteredHppRows.length === 0 && (
                                <tr>
                                    <td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={canManageHpp ? 9 : 8}>
                                        Data HPP tidak ditemukan.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        ),
    };

    const profileAccordion = {
        title: 'Profile Unit',
        content: (
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {[
                    ['Perumahan', perumahan.nama_perusahaan],
                    ['Cabang', perumahan.cabang ?? '-'],
                    ['Alamat', perumahan.alamat ?? '-'],
                    ['Blok', `${rumah.kode_nlok ?? '-'} / ${rumah.nomor_rumah ?? '-'}`],
                    ['Tipe', rumah.tipe_rumah ?? '-'],
                    ['Model Unit', rumah.model_unit ?? '-'],
                    ['Luas Tanah', rumah.luas_tanah ?? '-'],
                    ['Luas Bangunan', rumah.luas_bangunan ?? '-'],
                    ['Harga Jual', money(rumah.harga_jual)],
                    ['Status Bangun', rumah.status_pembangunan ?? '-'],
                    ['Progress Terakhir', `${rumah.progress_terakhir ?? 0}%`],
                    ['Status Penjualan', rumah.status_penjualan ?? '-'],
                    ['Dibuat Oleh', rumah.created_by ?? '-'],
                    ['Diupdate Oleh', rumah.updated_by ?? '-'],
                    ['Tanggal Mulai', rumah.tanggal_mulai_bangun ?? '-'],
                    ['Tanggal Selesai', rumah.tanggal_selesai_bangun ?? '-'],
                ].map(([label, value]) => (
                    <div className="rounded-lg border border-silver-deep/50 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5" key={label}>
                        <p className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">{label}</p>
                        <p className="mt-1 text-sm font-bold text-ink dark:text-white">{value}</p>
                    </div>
                ))}
                <div className="rounded-lg border border-silver-deep/50 bg-silver-soft/50 p-4 md:col-span-2 xl:col-span-3 dark:border-white/10 dark:bg-white/5">
                    <p className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">Catatan</p>
                    <p className="mt-1 whitespace-pre-line text-sm font-semibold text-ink dark:text-white">{rumah.catatan ?? '-'}</p>
                </div>
            </div>
        ),
    };

    const progressAccordion = {
        title: 'Detail Progress Pembangunan',
        content: (
            <div className="grid gap-4">
                {tahapanSummary.length === 0 ? (
                    <p className="text-sm font-bold text-ink-soft">Belum ada progress pembangunan.</p>
                ) : tahapanSummary.map((group) => (
                    <div className="rounded-lg border border-silver-deep/50 p-4 dark:border-white/10" key={group.tahapan_id}>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p className="text-sm font-extrabold text-ink dark:text-white">{group.tahapan}</p>
                                <p className="text-xs font-bold text-ink-soft">Total input: {group.total_input}% dari 100% | Kontribusi ke unit: {group.total_kontribusi.toFixed(2)}%</p>
                            </div>
                            <div className="rounded-full bg-ink px-3 py-1 text-xs font-extrabold text-white dark:bg-white dark:text-ink">
                                Bobot Tahap {Number(group.bobot_tahapan ?? 0)}%
                            </div>
                        </div>
                        <div className="mt-4 grid gap-3">
                            {group.rows.map((row) => (
                                <div className="rounded-lg bg-silver-soft/70 p-3 dark:bg-white/5" key={row.id}>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="text-sm font-bold text-ink dark:text-white">{row.tanggal} | {row.persentase}%</p>
                                        <span className={`rounded-full px-3 py-1 text-[11px] font-extrabold ${badgeClass(row.status)}`}>{row.status_label}</span>
                                    </div>
                                    <p className="mt-1 text-xs font-semibold text-ink-soft">Kontribusi: {row.persentase_total.toFixed(2)}% | Input oleh: {row.input_oleh} | Approve: {row.approved_by} | Lock: {row.record_status}</p>
                                    <p className="mt-2 whitespace-pre-line text-sm font-semibold text-ink dark:text-white">{row.keterangan}</p>
                                    {row.foto_url && (
                                        <a className="mt-2 inline-flex font-bold text-emerald-600 underline decoration-dotted underline-offset-4 dark:text-emerald-300" href={row.foto_url} target="_blank" rel="noreferrer">
                                            Lihat Foto
                                        </a>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        ),
    };

    const logistikAccordion = {
        title: 'Detail Pengambilan Logistik',
        content: (
            <div className="grid gap-5">
                <div>
                    <h4 className="mb-3 text-sm font-extrabold text-ink dark:text-white">Permintaan Material</h4>
                    <div className="grid gap-3">
                        {filteredRequests.length === 0 ? (
                            <p className="text-sm font-bold text-ink-soft">Belum ada permintaan material untuk unit ini.</p>
                        ) : filteredRequests.map((row) => (
                            <div className="rounded-lg border border-silver-deep/50 p-4 dark:border-white/10" key={row.id}>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="text-sm font-extrabold text-ink dark:text-white">{row.kode_request}</p>
                                        <p className="text-xs font-bold text-ink-soft">{row.tanggal} | {row.gudang} | {row.tahapan}</p>
                                    </div>
                                    <span className={`rounded-full px-3 py-1 text-[11px] font-extrabold ${badgeClass(row.status)}`}>{row.status_label}</span>
                                </div>
                                <p className="mt-2 text-sm font-semibold text-ink dark:text-white">{row.items_text}</p>
                                <p className="mt-1 text-sm font-semibold text-ink-soft">{row.keterangan ?? '-'}</p>
                                <p className="mt-1 text-xs font-bold text-ink-soft">Disetujui oleh: {row.approved_by}</p>
                                {row.can_approve && row.status === 'diajukan' && (
                                    <div className="mt-3">
                                        <Button type="button" size="sm" onClick={() => requestApprove(row)}>
                                            <CheckCircle2 size={15} />
                                            Approve Permintaan
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                <div>
                    <h4 className="mb-3 text-sm font-extrabold text-ink dark:text-white">Transaksi Logistik</h4>
                    <div className="grid gap-3">
                        {filteredLogistik.length === 0 ? (
                            <p className="text-sm font-bold text-ink-soft">Belum ada transaksi logistik untuk unit ini.</p>
                        ) : filteredLogistik.map((row) => (
                            <div className="rounded-lg border border-silver-deep/50 p-4 dark:border-white/10" key={row.id}>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <p className="text-sm font-extrabold text-ink dark:text-white">{row.kode_transaksi}</p>
                                        <p className="text-xs font-bold text-ink-soft">{row.tanggal} | {row.gudang} | {row.jenis}</p>
                                    </div>
                                    <span className="rounded-full bg-silver-soft px-3 py-1 text-[11px] font-extrabold text-ink-soft dark:bg-white/10 dark:text-white/70">
                                        <PackageSearch size={13} className="inline-block -translate-y-[1px]" /> Logistik
                                    </span>
                                </div>
                                <p className="mt-2 text-sm font-semibold text-ink dark:text-white">{row.items_text}</p>
                                <p className="mt-1 text-sm font-semibold text-ink-soft">{row.keterangan ?? '-'}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        ),
    };

    const items = [profileAccordion, ...(showHppSection ? [hppAccordion] : []), progressAccordion, logistikAccordion];

    return (
        <>
            <Head title={`${title} ${rumah.kode_nlok ?? ''} ${rumah.nomor_rumah ?? ''}`.trim()} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <Button as={Link} href={baseUrl} variant="ghost" size="sm" className="mb-3">
                                <ChevronRight size={16} className="rotate-180" /> Kembali
                            </Button>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Detail Unit Rumah</p>
                            <h2 className="mt-2 font-display text-3xl font-extrabold">{perumahan.nama_perusahaan}</h2>
                            <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{perumahan.cabang ?? '-'} | {perumahan.alamat}</p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Progress Unit</p>
                                <p className="mt-1 text-xl font-extrabold">{Number(rumah.progress_terakhir ?? 0).toFixed(2)}%</p>
                            </div>
                            <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Harga Jual</p>
                                <p className="mt-1 text-xl font-extrabold">{money(rumah.harga_jual)}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(window.location.pathname, { search }, { preserveScroll: true, preserveState: true, replace: true });
                        }}
                    >
                        <Input
                            className="md:max-w-md"
                            label="Search"
                            value={search}
                            placeholder="Cari tahapan, keterangan, permintaan, atau logistik..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Button type="submit">
                            <Search size={17} />
                            Cari
                        </Button>
                    </form>
                </section>

                {showHppSection && editingHpp && canManageHpp && (
                    <HppFormModal
                        open={Boolean(editingHpp)}
                        title={`Edit HPP ${editingHpp.kelompok_hpp_nama}`}
                        actionUrl={`${hppUrl}/${editingHpp.id ?? `new-${editingHpp.kelompok_hpp_id}`}`}
                        items={[editingHpp]}
                        options={options}
                        onClose={() => setEditingHpp(null)}
                    />
                )}

                <Accordion items={items} defaultOpen={0} />
            </div>
        </>
    );
}

UnitDetail.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Detail Unit Rumah'}>{page}</AdminLayout>;
