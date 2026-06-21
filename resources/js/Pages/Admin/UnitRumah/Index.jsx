import { Head, Link, router, useForm } from '@inertiajs/react';
import { Edit3, Eye, LoaderCircle, Lock, PlusCircle, RotateCcw, Search, Trash2, Unlock, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import Accordion from '../../../Components/UI/Accordion';
import { Button, CurrencyInput, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

function statusBadge(status) {
    return status === 'locked'
        ? 'bg-ink text-white dark:bg-white dark:text-graphite'
        : 'bg-silver-soft text-ink-soft dark:bg-white/10 dark:text-white/70';
}

function FormErrorSummary({ errors }) {
    const messages = Object.values(errors ?? {}).filter(Boolean);

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            <p>Data belum bisa disimpan. Periksa bagian berikut:</p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {messages.map((message, index) => <li key={`${message}-${index}`}>{message}</li>)}
            </ul>
        </div>
    );
}

function buildHppItems(groups = [], source = []) {
    return groups.map((group) => {
        const item = source.find((row) => String(row.kelompok_hpp_id) === String(group.value));

        return {
            kelompok_hpp_id: String(group.value ?? ''),
            kelompok_hpp_nama: group.label ?? '-',
            kategori: group.kategori ?? '-',
            volume: item?.volume ?? '0',
            satuan: item?.satuan ?? '',
            harga_satuan: item?.harga_satuan ?? '0',
            jumlah_rab: item?.jumlah_rab ?? 0,
        };
    });
}

function Pagination({ links = [] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
            {links.map((link, index) => (
                <Button
                    as={Link}
                    className={!link.url ? 'pointer-events-none opacity-45' : ''}
                    href={link.url ?? '#'}
                    key={`${link.label}-${index}`}
                    preserveScroll
                    size="sm"
                    variant={link.active ? 'dark' : 'outline'}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

export default function Index({ title, description, baseUrl, rows, filters = {}, options, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [block, setBlock] = useState(filters.block ?? '');
    const [type, setType] = useState(filters.type ?? '');
    const [perPage, setPerPage] = useState(filters.per_page ?? '10');
    const [editing, setEditing] = useState(null);
    const hppGroups = options.kelompokHpps ?? [];
    const form = useForm({
        perumahan_id: '',
        kode_nlok: '',
        nomor_rumah: '',
        jumlah_unit: '1',
        tipe_rumah: '',
        model_unit: '',
        luas_bangunan: '',
        luas_tanah: '',
        jumlah_lantai: '1',
        kamar_tidur: '0',
        kamar_mandi: '0',
        daya_listrik: '',
        sumber_air: '',
        carport: '',
        arah_hadap: '',
        posisi_unit: 'standar',
        harga_jual: '',
        status_penjualan: 'tersedia',
        status_pembangunan: 'kapling',
        progress_terakhir: '0',
        tanggal_mulai_bangun: '',
        tanggal_selesai_bangun: '',
        spesifikasi: '',
        catatan: '',
        status: 'aktif',
        hpp_items: buildHppItems(hppGroups),
    });
    const hppTotal = useMemo(
        () => form.data.hpp_items.reduce((sum, item) => sum + Number(item.volume || 0) * Number(item.harga_satuan || 0), 0),
        [form.data.hpp_items],
    );
    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };

    const editRow = (row) => {
        setEditing(row);
        form.setData({
            perumahan_id: String(row.perumahan_id ?? ''),
            kode_nlok: row.kode_nlok ?? '',
            nomor_rumah: row.nomor_rumah ?? '',
            jumlah_unit: '1',
            tipe_rumah: row.tipe_rumah ?? '',
            model_unit: row.model_unit ?? '',
            luas_bangunan: row.luas_bangunan ?? '',
            luas_tanah: row.luas_tanah ?? '',
            jumlah_lantai: String(row.jumlah_lantai ?? 1),
            kamar_tidur: String(row.kamar_tidur ?? 0),
            kamar_mandi: String(row.kamar_mandi ?? 0),
            daya_listrik: row.daya_listrik ?? '',
            sumber_air: row.sumber_air ?? '',
            carport: row.carport ?? '',
            arah_hadap: row.arah_hadap ?? '',
            posisi_unit: row.posisi_unit ?? 'standar',
            harga_jual: row.harga_jual ?? '',
            status_penjualan: row.status_penjualan ?? 'tersedia',
            status_pembangunan: row.status_pembangunan ?? 'kapling',
            progress_terakhir: String(row.progress_terakhir ?? 0),
            tanggal_mulai_bangun: row.tanggal_mulai_bangun ?? '',
            tanggal_selesai_bangun: row.tanggal_selesai_bangun ?? '',
            spesifikasi: row.spesifikasi ?? '',
            catatan: row.catatan ?? '',
            status: row.status ?? 'aktif',
            hpp_items: buildHppItems(hppGroups, row.hpp_items ?? []),
        });
    };

    const submit = (event) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: resetForm,
            onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
        };

        editing ? form.put(`${baseUrl}/${editing.id}`, options) : form.post(baseUrl, options);
    };

    const submitFilters = (event) => {
        event.preventDefault();

        router.get(baseUrl, {
            search,
            block,
            type,
            per_page: perPage,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        setSearch('');
        setBlock('');
        setType('');
        setPerPage('10');

        router.get(baseUrl, {}, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const destroyRow = (row) => {
        if (!window.confirm(`Hapus unit ${row.kode_nlok} ${row.nomor_rumah}?`)) {
            return;
        }

        router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    const lockRow = (row) => {
        if (!window.confirm('Lock data unit ini? Setelah locked, admin tidak bisa edit atau hapus data ini.')) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/lock`, {}, { preserveScroll: true });
    };

    const unlockRow = (row) => {
        if (!window.confirm('Buka lock data unit ini? Data akan kembali bisa diedit oleh admin.')) {
            return;
        }

        router.post(`${baseUrl}/${row.id}/unlock`, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Management Proyek</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <Form
                    collapsible
                    title={editing ? 'Edit Kapling / Unit Rumah' : 'Tambah Kapling / Unit Rumah'}
                    description={editing ? 'Edit data satu unit rumah. Jika sudah locked, admin tidak bisa mengubah data.' : 'Pilih blok, isi nomor mulai dan jumlah unit. Sistem akan membuat unit berurutan otomatis.'}
                    onSubmit={submit}
                    actions={(
                        <>
                            {editing && <Button type="button" variant="outline" onClick={resetForm}><X size={17} /> Batal Edit</Button>}
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <PlusCircle size={17} />}
                                {form.processing ? 'Menyimpan...' : (editing ? 'Simpan Perubahan' : 'Tambah Unit')}
                            </Button>
                        </>
                    )}
                >
                    <FormErrorSummary errors={form.errors} />
                    <Accordion
                        defaultOpen={0}
                        items={[
                            {
                                title: 'Identitas Unit',
                                content: (
                                    <div className="grid gap-4 md:grid-cols-5">
                                        <div className="grid gap-2">
                                            <span className="text-sm font-extrabold">Perumahan</span>
                                            <Dropdown value={form.data.perumahan_id} label="Pilih Perumahan" options={options.perumahans} onChange={(value) => form.setData('perumahan_id', value)} />
                                            {form.errors.perumahan_id && <span className="text-xs font-bold text-red-600">{form.errors.perumahan_id}</span>}
                                        </div>
                                        <div className="grid gap-2">
                                            <span className="text-sm font-extrabold">Blok</span>
                                            <Dropdown value={form.data.kode_nlok} label="Pilih Blok" options={options.blokOptions} onChange={(value) => form.setData('kode_nlok', value)} />
                                            {form.errors.kode_nlok && <span className="text-xs font-bold text-red-600">{form.errors.kode_nlok}</span>}
                                        </div>
                                        <Input label={editing ? 'Nomor Rumah' : 'Nomor Mulai'} value={form.data.nomor_rumah} error={form.errors.nomor_rumah} onChange={(event) => form.setData('nomor_rumah', event.target.value)} />
                                        {!editing && <Input label="Jumlah Unit Dibuat" type="number" value={form.data.jumlah_unit} error={form.errors.jumlah_unit} onChange={(event) => form.setData('jumlah_unit', event.target.value)} />}
                                        <Input label="Luas Tanah" value={form.data.luas_tanah} error={form.errors.luas_tanah} onChange={(event) => form.setData('luas_tanah', event.target.value)} />
                                    </div>
                                ),
                            },
                            {
                                title: 'Spesifikasi Rumah',
                                content: form.data.status_pembangunan === 'kapling' ? (
                                    <p className="text-sm font-bold text-ink-soft">Spesifikasi rumah akan muncul setelah status pembangunan bukan Kapling.</p>
                                ) : (
                                    <div className="grid gap-4 md:grid-cols-4">
                                        <Input label="Tipe Rumah" value={form.data.tipe_rumah} error={form.errors.tipe_rumah} onChange={(event) => form.setData('tipe_rumah', event.target.value)} />
                                        <Input label="Model Unit" value={form.data.model_unit} error={form.errors.model_unit} onChange={(event) => form.setData('model_unit', event.target.value)} />
                                        <Input label="Luas Bangunan" value={form.data.luas_bangunan} error={form.errors.luas_bangunan} onChange={(event) => form.setData('luas_bangunan', event.target.value)} />
                                        <Input label="Jumlah Lantai" type="number" value={form.data.jumlah_lantai} error={form.errors.jumlah_lantai} onChange={(event) => form.setData('jumlah_lantai', event.target.value)} />
                                        <Input label="Kamar Tidur" type="number" value={form.data.kamar_tidur} error={form.errors.kamar_tidur} onChange={(event) => form.setData('kamar_tidur', event.target.value)} />
                                        <Input label="Kamar Mandi" type="number" value={form.data.kamar_mandi} error={form.errors.kamar_mandi} onChange={(event) => form.setData('kamar_mandi', event.target.value)} />
                                        <Input label="Daya Listrik" value={form.data.daya_listrik} error={form.errors.daya_listrik} onChange={(event) => form.setData('daya_listrik', event.target.value)} />
                                        <Input label="Sumber Air" value={form.data.sumber_air} error={form.errors.sumber_air} onChange={(event) => form.setData('sumber_air', event.target.value)} />
                                        <Input label="Carport" value={form.data.carport} error={form.errors.carport} onChange={(event) => form.setData('carport', event.target.value)} />
                                        <div className="grid gap-2">
                                            <span className="text-sm font-extrabold">Arah Hadap</span>
                                            <Dropdown value={form.data.arah_hadap} label="Pilih Arah" options={options.arahHadap} onChange={(value) => form.setData('arah_hadap', value)} />
                                        </div>
                                        <div className="grid gap-2">
                                            <span className="text-sm font-extrabold">Posisi Unit</span>
                                            <Dropdown value={form.data.posisi_unit} label="Pilih Posisi" options={options.posisiUnit} onChange={(value) => form.setData('posisi_unit', value)} />
                                        </div>
                                        <div className="md:col-span-4">
                                            <Textarea label="Spesifikasi Bangunan" value={form.data.spesifikasi} error={form.errors.spesifikasi} onChange={(event) => form.setData('spesifikasi', event.target.value)} />
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                title: 'Harga & Status Jual',
                                content: (
                                    <div className="grid gap-4 md:grid-cols-4">
                                        <CurrencyInput label="Harga Jual Dasar" value={form.data.harga_jual} error={form.errors.harga_jual} onChange={(value) => form.setData('harga_jual', value)} />
                                        <div className="grid gap-2">
                                            <span className="text-sm font-extrabold">Status Penjualan</span>
                                            <Dropdown value={form.data.status_penjualan} options={options.statusPenjualan} onChange={(value) => form.setData('status_penjualan', value)} />
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                title: 'Pembangunan & Catatan',
                                content: (
                                    <div className="grid gap-4 md:grid-cols-4">
                                        <div className="grid gap-2">
                                            <span className="text-sm font-extrabold">Status Pembangunan</span>
                                            <Dropdown value={form.data.status_pembangunan} options={options.statusPembangunan} onChange={(value) => form.setData('status_pembangunan', value)} />
                                        </div>
                                        <Input label="Progress Awal %" type="number" value={form.data.progress_terakhir} error={form.errors.progress_terakhir} onChange={(event) => form.setData('progress_terakhir', event.target.value)} />
                                        <Input label="Tanggal Mulai" type="date" value={form.data.tanggal_mulai_bangun} onChange={(event) => form.setData('tanggal_mulai_bangun', event.target.value)} />
                                        <Input label="Tanggal Selesai" type="date" value={form.data.tanggal_selesai_bangun} onChange={(event) => form.setData('tanggal_selesai_bangun', event.target.value)} />
                                        <div className="md:col-span-4">
                                            <Textarea label="Catatan Unit" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                title: 'HPP Unit Rumah',
                                content: (
                                    <div className="grid gap-4">
                                        <div className="grid gap-3 md:grid-cols-3">
                                            <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                                                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Total HPP</p>
                                                <p className="mt-1 text-xl font-extrabold">{money(hppTotal)}</p>
                                            </div>
                                            <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8 md:col-span-2">
                                                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Petunjuk</p>
                                                <p className="mt-1 text-sm font-semibold text-ink-soft">Isi volume dan harga satuan untuk tiap kelompok HPP. Data ini akan tersimpan bersama unit rumah, termasuk saat membuat banyak unit sekaligus.</p>
                                            </div>
                                        </div>
                                        <div className="grid gap-3">
                                            {form.data.hpp_items.map((item, index) => (
                                                <div className="grid gap-3 rounded-lg border border-silver-deep/50 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5 lg:grid-cols-[1.2fr_0.5fr_0.5fr_0.7fr]" key={item.kelompok_hpp_id || index}>
                                                    <div className="grid gap-1">
                                                        <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Kelompok HPP</p>
                                                        <div className="rounded-lg border border-silver-deep/60 bg-white px-4 py-3 text-sm font-extrabold text-ink dark:border-white/10 dark:bg-white/8 dark:text-white">
                                                            <p>{item.kelompok_hpp_nama}</p>
                                                            <p className="mt-1 text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">{item.kategori}</p>
                                                        </div>
                                                    </div>
                                                    <Input
                                                        label="Volume"
                                                        type="number"
                                                        value={item.volume}
                                                        error={form.errors[`hpp_items.${index}.volume`]}
                                                        onChange={(event) => form.setData('hpp_items', form.data.hpp_items.map((row, rowIndex) => (rowIndex === index ? { ...row, volume: event.target.value } : row)))}
                                                    />
                                                    <Input
                                                        label="Satuan"
                                                        value={item.satuan}
                                                        error={form.errors[`hpp_items.${index}.satuan`]}
                                                        onChange={(event) => form.setData('hpp_items', form.data.hpp_items.map((row, rowIndex) => (rowIndex === index ? { ...row, satuan: event.target.value } : row)))}
                                                    />
                                                    <CurrencyInput
                                                        label="Harga Satuan"
                                                        value={item.harga_satuan}
                                                        error={form.errors[`hpp_items.${index}.harga_satuan`]}
                                                        onChange={(value) => form.setData('hpp_items', form.data.hpp_items.map((row, rowIndex) => (rowIndex === index ? { ...row, harga_satuan: value } : row)))}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ),
                            },
                        ]}
                    />
                </Form>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="grid gap-3 p-5 lg:grid-cols-[1.4fr_1fr_1fr_0.8fr_auto_auto] lg:items-end" onSubmit={submitFilters}>
                        <Input label="Search" value={search} placeholder="Cari perumahan, blok, nomor, tipe..." onChange={(event) => setSearch(event.target.value)} />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Filter Blok</span>
                            <Dropdown value={block} options={options.filterBlokOptions} onChange={(value) => setBlock(value)} />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Filter Tipe</span>
                            <Dropdown value={type} options={options.tipeRumahOptions} onChange={(value) => setType(value)} />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Show Page</span>
                            <Dropdown value={perPage} options={options.perPageOptions} searchable={false} onChange={(value) => setPerPage(value)} />
                        </div>
                        <Button type="submit"><Search size={17} /> Cari</Button>
                        <Button type="button" variant="outline" onClick={resetFilters}><RotateCcw size={17} /> Reset</Button>
                    </form>
                    <div className="border-t border-silver-deep/60 px-5 py-3 text-sm font-bold text-ink-soft dark:border-white/10 dark:text-white/55">
                        Menampilkan {rows.from ?? 0} - {rows.to ?? 0} dari {rows.total ?? 0} unit.
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>{['Perumahan', 'Blok', 'Nomor', 'Tipe', 'Progress', 'Status Bangun', 'Harga Jual', 'Dibuat Oleh', 'Diupdate Oleh', 'Lock', 'Status', 'Aksi'].map((column) => <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-semibold">{row.perumahan}</td>
                                        <td className="px-5 py-4 font-semibold">{row.blok_label}</td>
                                        <td className="px-5 py-4 font-semibold">{row.nomor_rumah}</td>
                                        <td className="px-5 py-4 font-semibold">{row.tipe_rumah ?? '-'}</td>
                                        <td className="px-5 py-4 font-semibold">{row.progress_terakhir}%</td>
                                        <td className="px-5 py-4 font-semibold">{row.status_pembangunan}</td>
                                        <td className="px-5 py-4 font-extrabold">{money(row.harga_jual)}</td>
                                        <td className="px-5 py-4 font-semibold">{row.created_by}</td>
                                        <td className="px-5 py-4 font-semibold">{row.updated_by}</td>
                                        <td className="px-5 py-4">
                                            <span className={`rounded-full px-3 py-1 text-xs font-extrabold ${statusBadge(row.record_status)}`}>{row.record_status_label}</span>
                                        </td>
                                        <td className="px-5 py-4 font-semibold">{row.status}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                        {row.can_edit && <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={15} /> Edit</Button>}
                                        <Button as={Link} href={row.detail_url} size="sm" variant="outline">
                                            <Eye size={15} /> Detail
                                        </Button>
                                                {row.can_delete && <Button type="button" size="sm" variant="outline" onClick={() => destroyRow(row)}><Trash2 size={15} /> Hapus</Button>}
                                                {row.record_status === 'locked' ? (
                                                    permissions.canManageLocked && <Button type="button" size="sm" variant="outline" onClick={() => unlockRow(row)}><Unlock size={15} /> Unlock</Button>
                                                ) : (
                                                    <Button type="button" size="sm" variant="outline" onClick={() => lockRow(row)}><Lock size={15} /> Lock</Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={12}>Belum ada unit rumah.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Management Proyek'}>{page}</AdminLayout>;
