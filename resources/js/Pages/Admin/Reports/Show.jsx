import { Head, router } from '@inertiajs/react';
import { ArrowLeft, FileDown, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button, Dropdown, Input } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function queryString(data) {
    const params = new URLSearchParams();
    Object.entries(data).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params.set(key, value);
        }
    });

    return params.toString();
}

export default function Show({
    title,
    description,
    baseUrl,
    printUrl,
    selectedType,
    types = [],
    filters = [],
    filterValues = {},
    options = {},
    columns = [],
    rows = [],
    summary = {},
    permissions = {},
}) {
    const [form, setForm] = useState({
        ...filterValues,
        jenis_laporan: selectedType,
    });

    const selectedTypeLabel = useMemo(
        () => types.find((item) => item.value === form.jenis_laporan)?.label ?? title,
        [form.jenis_laporan, title, types],
    );

    const setValue = (key, value) => setForm((current) => ({ ...current, [key]: value }));

    const submit = (event) => {
        event.preventDefault();
        router.get(baseUrl, form, { preserveScroll: true, preserveState: true, replace: true });
    };

    const print = () => {
        window.open(`${printUrl}?${queryString(form)}`, '_blank', 'noopener,noreferrer');
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Pusat Laporan</p>
                            <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                            <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                        </div>
                        <Button type="button" variant="outline" onClick={() => router.visit('/admin/laporan')}>
                            <ArrowLeft size={16} />
                            Semua Laporan
                        </Button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="grid gap-4 p-5 xl:grid-cols-4" onSubmit={submit}>
                        <div className="grid gap-2 xl:col-span-2">
                            <span className="text-sm font-extrabold">Jenis Laporan</span>
                            <Dropdown
                                value={form.jenis_laporan}
                                label="Pilih Jenis Laporan"
                                options={types}
                                onChange={(value) => setValue('jenis_laporan', value)}
                            />
                        </div>

                        {filters.map((filter) => (
                            <div className="grid gap-2" key={filter.name}>
                                {filter.type === 'select' ? (
                                    <>
                                        <span className="text-sm font-extrabold">{filter.label}</span>
                                        <Dropdown
                                            value={form[filter.name] ?? ''}
                                            label={`Semua ${filter.label}`}
                                            options={[{ value: '', label: `Semua ${filter.label}` }, ...(options[filter.optionsKey] ?? [])]}
                                            onChange={(value) => setValue(filter.name, value)}
                                        />
                                    </>
                                ) : (
                                    <Input
                                        label={filter.label}
                                        type={filter.type}
                                        value={form[filter.name] ?? ''}
                                        onChange={(event) => setValue(filter.name, event.target.value)}
                                    />
                                )}
                            </div>
                        ))}

                        <div className="flex flex-wrap items-end gap-2 xl:col-span-4">
                            <Button type="submit">
                                <Search size={16} />
                                Preview
                            </Button>
                            {permissions.canExport && (
                                <Button type="button" variant="outline" onClick={print}>
                                    <FileDown size={16} />
                                    Cetak PDF
                                </Button>
                            )}
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-3 border-b border-silver-deep/60 px-5 py-4 dark:border-white/10 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-ink-soft">Pratinjau</p>
                            <h3 className="font-display text-xl font-extrabold">{selectedTypeLabel}</h3>
                        </div>
                        <div className="rounded-lg border border-silver-deep/60 px-4 py-2 text-sm font-extrabold dark:border-white/10">
                            Total data: {Number(summary.total_rows ?? rows.length).toLocaleString('id-ID')}
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/55">
                                <tr>
                                    {columns.map((column) => (
                                        <th className="whitespace-nowrap px-4 py-3 font-extrabold" key={column.key}>{column.label}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.map((row, index) => (
                                    <tr key={index}>
                                        {columns.map((column) => (
                                            <td className="whitespace-nowrap px-4 py-3" key={column.key}>{row[column.key] ?? '-'}</td>
                                        ))}
                                    </tr>
                                ))}
                                {rows.length === 0 && (
                                    <tr>
                                        <td className="px-4 py-10 text-center font-bold text-ink-soft dark:text-white/45" colSpan={Math.max(columns.length, 1)}>
                                            Tidak ada data untuk filter ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {rows.length >= 200 && (
                        <div className="border-t border-silver-deep/60 px-5 py-3 text-xs font-bold text-ink-soft dark:border-white/10 dark:text-white/45">
                            Preview dibatasi 200 baris agar halaman tetap ringan. Cetak PDF memuat data lebih banyak.
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

Show.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Laporan'}>{page}</AdminLayout>;
