import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Calculator, LoaderCircle, PlusCircle, Eye } from 'lucide-react';
import { Button, CurrencyInput, Dropdown, Form, Input, Textarea, Accordion } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function Pagination({ links = [] }) {
    if (links.length <= 3) return null;

    return (
        <div className="flex flex-wrap justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
            {links.map((link, index) => (
                <Button as={Link} className={!link.url ? 'pointer-events-none opacity-45' : ''} href={link.url ?? '#'} key={`${link.label}-${index}`} preserveScroll size="sm" variant={link.active ? 'dark' : 'outline'} dangerouslySetInnerHTML={{ __html: link.label }} />
            ))}
        </div>
    );
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

function Detail({ title = 'Detail Perumahan', perumahan = {}, rows = { data: [], links: [] }, options, baseUrl }) {
    const { auth } = usePage().props;
    const roles = auth?.user?.roles ?? [];
    const canManageUnitAndHpp = !auth?.user || roles.some((role) => ['owner', 'super_admin', 'manajer_pimpro'].includes(role));
    const pageTitle = `${title} ${perumahan.nama_perusahaan ?? ''}`.trim();
    const overviewAccordion = {
        title: 'Detail Perumahan',
        content: (
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {[
                    ['Nama Perumahan', perumahan.nama_perusahaan ?? '-'],
                    ['Cabang', perumahan.cabang ?? '-'],
                    ['Alamat', perumahan.alamat ?? '-'],
                    ['Jumlah Unit', perumahan.jumlah_unit ?? '0'],
                    ['Status', perumahan.status ?? '-'],
                    ['HPP Perumahan', money(perumahan.total_hpp_perumahan)],
                    ['Realisasi Kawasan', money(perumahan.total_realisasi_perumahan)],
                ].map(([label, value]) => (
                    <div className="rounded-lg border border-silver-deep/50 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5" key={label}>
                        <p className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">{label}</p>
                        <p className="mt-1 text-sm font-bold text-ink dark:text-white">{value}</p>
                    </div>
                ))}
            </div>
        ),
    };
    const unitForm = useForm({
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
    });
    const submitUnit = (event) => {
        event.preventDefault();
        unitForm.post(`${baseUrl}/${perumahan.id}/rumah`, {
            preserveScroll: true,
            onSuccess: () => unitForm.reset(),
            onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
        });
    };

    return (
        <>
            <Head title={pageTitle} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <Button as={Link} href={baseUrl} variant="ghost" size="sm" className="mb-3">
                                <ArrowLeft size={16} /> Kembali
                            </Button>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Detail Perumahan</p>
                            <h2 className="mt-2 font-display text-3xl font-extrabold">{perumahan.nama_perusahaan}</h2>
                            <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{perumahan.cabang ?? '-'} | {perumahan.alamat}</p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">HPP Perumahan</p>
                                <p className="mt-1 text-xl font-extrabold">{money(perumahan.total_hpp_perumahan)}</p>
                            </div>
                            <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Realisasi Kawasan</p>
                                <p className="mt-1 text-xl font-extrabold">{money(perumahan.total_realisasi_perumahan)}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <Accordion items={[overviewAccordion]} defaultOpen={0} />

                {canManageUnitAndHpp && (
                    <Form
                        collapsible
                        title="Tambah Kapling / Unit Rumah"
                        description="Khusus owner/manager. Pilih blok, isi nomor mulai dan jumlah unit untuk membuat banyak rumah sekaligus."
                        onSubmit={submitUnit}
                        actions={(
                            <Button type="submit" disabled={unitForm.processing}>
                                {unitForm.processing ? <LoaderCircle className="animate-spin" size={17} /> : <PlusCircle size={17} />}
                                {unitForm.processing ? 'Menyimpan...' : 'Tambah Unit'}
                            </Button>
                        )}
                    >
                        <FormErrorSummary errors={unitForm.errors} />
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-ink-soft">Identitas Unit</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-5">
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Blok</span><Dropdown value={unitForm.data.kode_nlok} label="Pilih Blok" options={options.blokOptions} onChange={(value) => unitForm.setData('kode_nlok', value)} />{unitForm.errors.kode_nlok && <span className="text-xs font-bold text-red-600">{unitForm.errors.kode_nlok}</span>}</div>
                            <Input label="Nomor Mulai" value={unitForm.data.nomor_rumah} error={unitForm.errors.nomor_rumah} onChange={(event) => unitForm.setData('nomor_rumah', event.target.value)} />
                            <Input label="Jumlah Unit Dibuat" type="number" value={unitForm.data.jumlah_unit} error={unitForm.errors.jumlah_unit} onChange={(event) => unitForm.setData('jumlah_unit', event.target.value)} />
                            <Input label="Luas Tanah" value={unitForm.data.luas_tanah} error={unitForm.errors.luas_tanah} onChange={(event) => unitForm.setData('luas_tanah', event.target.value)} />
                        </div>
                        {unitForm.data.status_pembangunan !== 'kapling' && (
                            <>
                                <div>
                                    <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-ink-soft">Spesifikasi Rumah</p>
                                </div>
                                <div className="grid gap-4 md:grid-cols-4">
                                    <Input label="Tipe Rumah" value={unitForm.data.tipe_rumah} error={unitForm.errors.tipe_rumah} onChange={(event) => unitForm.setData('tipe_rumah', event.target.value)} />
                                    <Input label="Model Unit" value={unitForm.data.model_unit} error={unitForm.errors.model_unit} onChange={(event) => unitForm.setData('model_unit', event.target.value)} />
                                    <Input label="Luas Bangunan" value={unitForm.data.luas_bangunan} error={unitForm.errors.luas_bangunan} onChange={(event) => unitForm.setData('luas_bangunan', event.target.value)} />
                                    <Input label="Jumlah Lantai" type="number" value={unitForm.data.jumlah_lantai} error={unitForm.errors.jumlah_lantai} onChange={(event) => unitForm.setData('jumlah_lantai', event.target.value)} />
                                    <Input label="Kamar Tidur" type="number" value={unitForm.data.kamar_tidur} error={unitForm.errors.kamar_tidur} onChange={(event) => unitForm.setData('kamar_tidur', event.target.value)} />
                                    <Input label="Kamar Mandi" type="number" value={unitForm.data.kamar_mandi} error={unitForm.errors.kamar_mandi} onChange={(event) => unitForm.setData('kamar_mandi', event.target.value)} />
                                    <Input label="Daya Listrik" value={unitForm.data.daya_listrik} error={unitForm.errors.daya_listrik} onChange={(event) => unitForm.setData('daya_listrik', event.target.value)} />
                                    <Input label="Sumber Air" value={unitForm.data.sumber_air} error={unitForm.errors.sumber_air} onChange={(event) => unitForm.setData('sumber_air', event.target.value)} />
                                    <Input label="Carport" value={unitForm.data.carport} error={unitForm.errors.carport} onChange={(event) => unitForm.setData('carport', event.target.value)} />
                                    <div className="grid gap-2"><span className="text-sm font-extrabold">Arah Hadap</span><Dropdown value={unitForm.data.arah_hadap} label="Pilih Arah" options={options.arahHadap} onChange={(value) => unitForm.setData('arah_hadap', value)} /></div>
                                    <div className="grid gap-2"><span className="text-sm font-extrabold">Posisi Unit</span><Dropdown value={unitForm.data.posisi_unit} label="Pilih Posisi" options={options.posisiUnit} onChange={(value) => unitForm.setData('posisi_unit', value)} /></div>
                                </div>
                                <Textarea label="Spesifikasi Bangunan" value={unitForm.data.spesifikasi} error={unitForm.errors.spesifikasi} onChange={(event) => unitForm.setData('spesifikasi', event.target.value)} />
                            </>
                        )}
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-ink-soft">Harga & Status Jual</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-4">
                            <CurrencyInput label="Harga Jual Dasar" value={unitForm.data.harga_jual} error={unitForm.errors.harga_jual} onChange={(value) => unitForm.setData('harga_jual', value)} />
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Status Penjualan</span><Dropdown value={unitForm.data.status_penjualan} options={options.statusPenjualan} onChange={(value) => unitForm.setData('status_penjualan', value)} /></div>
                        </div>
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-ink-soft">Pembangunan & Catatan</p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Status Pembangunan</span><Dropdown value={unitForm.data.status_pembangunan} options={options.statusPembangunan} onChange={(value) => unitForm.setData('status_pembangunan', value)} /></div>
                            <Input label="Progress Awal %" type="number" value={unitForm.data.progress_terakhir} error={unitForm.errors.progress_terakhir} onChange={(event) => unitForm.setData('progress_terakhir', event.target.value)} />
                            <Input label="Tanggal Mulai" type="date" value={unitForm.data.tanggal_mulai_bangun} onChange={(event) => unitForm.setData('tanggal_mulai_bangun', event.target.value)} />
                            <Input label="Tanggal Selesai" type="date" value={unitForm.data.tanggal_selesai_bangun} onChange={(event) => unitForm.setData('tanggal_selesai_bangun', event.target.value)} />
                        </div>
                        <Textarea label="Catatan Unit" value={unitForm.data.catatan} error={unitForm.errors.catatan} onChange={(event) => unitForm.setData('catatan', event.target.value)} />
                    </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                        <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Daftar Rumah</p>
                        <h3 className="mt-0.5 text-base font-extrabold">Kapling / Unit Rumah</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>{['Blok', 'Nomor', 'Tipe', 'Progress', 'Status Bangun', 'Harga Jual', 'Dibuat Oleh', 'Diupdate Oleh', 'RAB HPP', 'Realisasi', 'Aksi'].map((column) => <th className="px-4 py-3 font-extrabold" key={column}>{column}</th>)}</tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                        <td className="px-4 py-3 font-semibold">{row.blok_label}</td>
                                        <td className="px-4 py-3 font-semibold">{row.nomor_rumah}</td>
                                        <td className="px-4 py-3 font-semibold">{row.tipe_rumah ?? '-'}</td>
                                        <td className="px-4 py-3 font-semibold">{row.progress_terakhir}%</td>
                                        <td className="px-4 py-3 font-semibold">{row.status_pembangunan}</td>
                                        <td className="px-4 py-3 font-semibold">{money(row.harga_jual)}</td>
                                        <td className="px-4 py-3 font-semibold">{row.created_by}</td>
                                        <td className="px-4 py-3 font-semibold">{row.updated_by}</td>
                                        <td className="px-4 py-3 font-extrabold">{money(row.total_rab)}</td>
                                        <td className="px-4 py-3 font-extrabold">{money(row.total_realisasi)}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-wrap gap-2">
                                                <Button as={Link} href={row.detail_url} variant="outline" size="sm">
                                                    <Eye size={15} /> Detail Unit
                                                </Button>
                                                {canManageUnitAndHpp && (
                                                    <Button as={Link} href={`${baseUrl}/${perumahan.id}/rumah/${row.id}/hpp`} variant="outline" size="sm">
                                                        <Calculator size={15} /> HPP Unit
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={11}>Belum ada data rumah/unit untuk perumahan ini.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

        </>
    );
}

Detail.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Detail Perumahan'}>{page}</AdminLayout>;

export default Detail;
