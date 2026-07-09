import { Head, Link, router, useForm } from '@inertiajs/react';
import { Banknote, Edit3, HardHat, LoaderCircle, Plus, Save, Search, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { Button, Dropdown, Form, Input, Textarea } from '../../../Components/UI';
import Pagination from '../../../Components/Pagination';
import AdminLayout from '../../../Layouts/AdminLayout';
import { useResourcePermissions } from '../../../Utils/permissions';

const initialForm = {
    nama: '',
    alamat: '',
    posisi: '',
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

export default function Index({ title, description, baseUrl, rows, filters = {}, positions = [] }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState(null);
    const form = useForm(initialForm);
    const permissions = useResourcePermissions('tukang', baseUrl);

    const resetForm = () => {
        setEditing(null);
        form.setData(initialForm);
        form.clearErrors();
    };

    const editRow = (row) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            nama: row.nama ?? '',
            alamat: row.alamat ?? '',
            posisi: row.posisi ?? '',
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: resetForm };

        if (editing) {
            form.put(`${baseUrl}/${editing.id}`, options);
        } else {
            form.post(baseUrl, options);
        }
    };

    const destroyRow = (row) => {
        if (!window.confirm(`Hapus ${row.nama} dari daftar tukang?`)) return;
        router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    const runSearch = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveScroll: true, preserveState: true, replace: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex items-start gap-4">
                        <div className="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-ink text-white dark:bg-white dark:text-ink">
                            <HardHat size={24} />
                        </div>
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Master Data</p>
                            <h2 className="mt-1 font-display text-3xl font-extrabold">{title}</h2>
                            <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                        </div>
                    </div>
                </section>

                {(permissions.canCreate || (editing && permissions.canUpdate)) && (
                    <Form
                        collapsible
                        title={editing ? `Edit Tukang — ${editing.nama}` : 'Tambah Tukang'}
                        description="Isi data dasar tukang dan nominal gajinya."
                        onSubmit={submit}
                        actions={(
                            <>
                                {editing && <Button type="button" variant="outline" onClick={resetForm}><X size={17} /> Batal Edit</Button>}
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? <LoaderCircle className="animate-spin" size={17} /> : editing ? <Save size={17} /> : <Plus size={17} />}
                                    {editing ? 'Simpan Perubahan' : 'Tambah Tukang'}
                                </Button>
                            </>
                        )}
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <Input
                                label="Nama Tukang"
                                placeholder="Masukkan nama lengkap"
                                value={form.data.nama}
                                error={form.errors.nama}
                                onChange={(event) => form.setData('nama', event.target.value)}
                            />
                            <div className="grid content-start gap-2">
                                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Posisi Tukang</span>
                                <Dropdown
                                    value={form.data.posisi}
                                    label="Pilih posisi tukang"
                                    options={positions}
                                    onChange={(value) => form.setData('posisi', value)}
                                />
                                {form.errors.posisi && <span className="text-xs font-bold text-red-600 dark:text-red-300">{form.errors.posisi}</span>}
                            </div>
                            <Textarea
                                className="md:col-span-2"
                                label="Alamat"
                                placeholder="Masukkan alamat lengkap"
                                value={form.data.alamat}
                                error={form.errors.alamat}
                                onChange={(event) => form.setData('alamat', event.target.value)}
                            />
                        </div>
                    </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between" onSubmit={runSearch}>
                        <Input
                            className="md:max-w-md"
                            label="Cari Tukang"
                            value={search}
                            placeholder="Cari nama, alamat, atau posisi..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Nama', 'Alamat', 'Posisi', 'Gaji', 'Aksi'].map((column) => (
                                        <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                        <td className="px-5 py-4 font-extrabold">{row.nama}</td>
                                        <td className="max-w-md whitespace-normal px-5 py-4">{row.alamat}</td>
                                        <td className="px-5 py-4 font-semibold">{row.posisi_label}</td>
                                        <td className="whitespace-nowrap px-5 py-4">
                                            {row.gaji_aktif ? (
                                                <>
                                                    <p className="font-extrabold">{rupiah.format(Number(row.gaji_aktif.nominal))}</p>
                                                    <p className="mt-1 text-xs font-semibold text-ink-soft">Berlaku {row.gaji_aktif.tanggal_berlaku_label}</p>
                                                </>
                                            ) : (
                                                <span className="font-bold text-amber-700 dark:text-amber-300">Belum diatur</span>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="flex gap-2">
                                                <Button as={Link} href={`${baseUrl}/${row.id}/gaji`} type="button" size="sm">
                                                    <Banknote size={15} /> Gaji
                                                </Button>
                                                {permissions.canUpdate && <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={15} /> Edit</Button>}
                                                {permissions.canDelete && <Button type="button" size="sm" variant="outline" onClick={() => destroyRow(row)}><Trash2 size={15} /> Hapus</Button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={5}>Belum ada data tukang.</td></tr>
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

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Daftar Tukang'}>{page}</AdminLayout>;
