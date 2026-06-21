import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button, Dropdown, Form, Input, Modal, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function emptyForm(fields) {
    return fields.reduce((values, field) => {
        values[field.name] = field.defaultValue ?? '';
        return values;
    }, {});
}

function Field({ field, value, error, onChange }) {
    const common = {
        label: field.label,
        value: value ?? '',
        error,
        placeholder: field.placeholder ?? field.label,
        onChange: (event) => onChange(field.name, event.target.value),
    };

    if (field.type === 'textarea') {
        return <Textarea {...common} />;
    }

    if (field.type === 'select') {
        return (
            <div className="grid gap-2">
                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">{field.label}</span>
                <Dropdown value={value ?? ''} label={field.placeholder ?? field.label} options={field.options ?? []} onChange={(selected) => onChange(field.name, selected)} />
                {error && <span className="text-xs font-bold text-red-600 dark:text-red-300">{error}</span>}
            </div>
        );
    }

    return <Input {...common} type={field.type ?? 'text'} />;
}

function CrudIndex({ title, description, baseUrl, columns, fields, rows }) {
    const [modalMode, setModalMode] = useState(null);
    const [selected, setSelected] = useState(null);
    const formDefaults = useMemo(() => emptyForm(fields), [fields]);
    const form = useForm(formDefaults);

    const openCreate = () => {
        setSelected(null);
        form.clearErrors();
        form.setData(formDefaults);
        setModalMode('create');
    };

    const openEdit = (row) => {
        setSelected(row);
        form.clearErrors();
        form.setData(
            fields.reduce((values, field) => {
                values[field.name] = row[field.name] ?? field.defaultValue ?? '';
                return values;
            }, {}),
        );
        setModalMode('edit');
    };

    const submit = (event) => {
        event.preventDefault();

        if (modalMode === 'create') {
            form.post(baseUrl, {
                preserveScroll: true,
                onSuccess: () => setModalMode(null),
            });
            return;
        }

        form.put(`${baseUrl}/${selected.id}`, {
            preserveScroll: true,
            onSuccess: () => setModalMode(null),
        });
    };

    const destroy = (row) => {
        if (!window.confirm(`Hapus data ${row.name ?? row.nama ?? row.nama_perusahaan ?? row.nama_hpp ?? 'ini'}?`)) {
            return;
        }

        router.delete(`${baseUrl}/${row.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Master Data</p>
                            <h2 className="mt-1 text-xl font-extrabold">{title}</h2>
                            {description && <p className="mt-1 max-w-2xl text-sm leading-6 text-ink-soft dark:text-white/60">{description}</p>}
                        </div>
                        <Button type="button" onClick={openCreate}>
                            <Plus size={18} /> Tambah Data
                        </Button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {columns.map((column) => (
                                        <th className="px-4 py-3 font-extrabold" key={column.key}>{column.label}</th>
                                    ))}
                                    <th className="w-28 px-4 py-3 text-right font-extrabold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                        {columns.map((column) => (
                                            <td className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72" key={column.key}>
                                                {row[column.key] ?? '-'}
                                            </td>
                                        ))}
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button variant="outline" size="sm" type="button" onClick={() => openEdit(row)}>
                                                    <Edit3 size={15} />
                                                </Button>
                                                <Button variant="ghost" size="sm" type="button" className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-300 dark:hover:bg-red-500/10" onClick={() => destroy(row)}>
                                                    <Trash2 size={15} />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={columns.length + 1}>
                                            Belum ada data.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Modal
                open={Boolean(modalMode)}
                onClose={() => setModalMode(null)}
                title={modalMode === 'create' ? `Tambah ${title}` : `Edit ${title}`}
                size="lg"
            >
                <Form
                    className="border-0 bg-transparent p-0 shadow-none dark:bg-transparent"
                    onSubmit={submit}
                    actions={
                        <>
                            <Button variant="outline" type="button" onClick={() => setModalMode(null)}>Batal</Button>
                            <Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan...' : 'Simpan'}</Button>
                        </>
                    }
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        {fields.map((field) => (
                            <div className={field.full ? 'md:col-span-2' : ''} key={field.name}>
                                <Field field={field} value={form.data[field.name]} error={form.errors[field.name]} onChange={form.setData} />
                            </div>
                        ))}
                    </div>
                </Form>
            </Modal>
        </>
    );
}

CrudIndex.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Admin'}>{page}</AdminLayout>;

export default CrudIndex;

