import { Head, router, useForm } from '@inertiajs/react';
import { Edit3, LoaderCircle, PlusCircle, Search, Save, Trash2, MinusCircle, GripVertical, ChevronDown, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Button, CurrencyInput, Dropdown, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function workItemTemplate() {
    return {
        nama_pekerjaan: '',
        harga_satuan: '',
    };
}

function workGroupTemplate() {
    return {
        ui_id: typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `group-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        judul_tahapan: '',
        items: [workItemTemplate()],
    };
}

function normalizeGroups(groups = []) {
    return groups.length > 0
        ? groups.map((group) => ({
            ui_id: group.ui_id ?? (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `group-${Date.now()}-${Math.random().toString(36).slice(2)}`),
            judul_tahapan: group.judul_tahapan ?? '',
            items: (group.items ?? []).map((item) => ({
                nama_pekerjaan: item.nama_pekerjaan ?? '',
                harga_satuan: item.harga_satuan ?? '',
            })),
        }))
        : [workGroupTemplate()];
}

export default function Index({ title, description, context, baseUrl, rows, filters = {}, options = {}, permissions = {} }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedContext, setSelectedContext] = useState(context ?? 'perumahan');
    const [editing, setEditing] = useState(null);
    const [draggedGroupIndex, setDraggedGroupIndex] = useState(null);
    const [activeGroupIndex, setActiveGroupIndex] = useState(0);
    const [collapsedGroupIds, setCollapsedGroupIds] = useState(new Set());
    const defaults = useMemo(() => ({
        perumahan_id: '',
        nama_template: '',
        catatan: '',
        work_groups: [workGroupTemplate()],
    }), []);
    const form = useForm(defaults);

    useEffect(() => {
        if (editing) {
            return;
        }

        form.setData(defaults);
    }, [context]);

    useEffect(() => {
        setSelectedContext(context ?? 'perumahan');
    }, [context]);

    const templateUrl = (path = '') => `${baseUrl}${path}?context=${selectedContext}`;

    const resetForm = () => {
        setEditing(null);
        setDraggedGroupIndex(null);
        setActiveGroupIndex(0);
        setCollapsedGroupIds(new Set());
        form.reset();
        form.clearErrors();
    };

    const setGroups = (groups) => {
        form.setData('work_groups', groups.map((group) => ({
            ...group,
            ui_id: group.ui_id ?? (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `group-${Date.now()}-${Math.random().toString(36).slice(2)}`),
        })));
    };

    const setWorkGroup = (groupIndex, key, value) => {
        form.setData('work_groups', form.data.work_groups.map((group, index) => (
            index === groupIndex ? { ...group, [key]: value } : group
        )));
    };

    const setWorkGroupItem = (groupIndex, itemIndex, key, value) => {
        form.setData('work_groups', form.data.work_groups.map((group, index) => {
            if (index !== groupIndex) {
                return group;
            }

            return {
                ...group,
                items: group.items.map((item, innerIndex) => (
                    innerIndex === itemIndex ? { ...item, [key]: value } : item
                )),
            };
        }));
    };

    const itemTotal = (item) => Number(item?.harga_satuan || 0);

    const addGroup = () => {
        const nextGroups = [...(form.data.work_groups ?? []), workGroupTemplate()];
        setGroups(nextGroups);
        setActiveGroupIndex(nextGroups.length - 1);
    };

    const addItemToGroup = (groupIndex = activeGroupIndex) => {
        const nextGroups = (form.data.work_groups ?? []).map((group, index) => (
            index === groupIndex
                ? { ...group, items: [...(group.items ?? []), workItemTemplate()] }
                : group
        ));

        setGroups(nextGroups);
        setActiveGroupIndex(groupIndex);
        const groupId = nextGroups[groupIndex]?.ui_id;
        if (groupId) {
            setCollapsedGroupIds((current) => {
                const next = new Set(current);
                next.delete(groupId);
                return next;
            });
        }
    };

    const removeGroup = (groupIndex) => {
        const nextGroups = (form.data.work_groups ?? []).filter((_, index) => index !== groupIndex);
        setGroups(nextGroups);
        setActiveGroupIndex((current) => Math.max(0, Math.min(current, nextGroups.length - 1)));
    };

    const toggleGroupCollapsed = (groupId) => {
        setCollapsedGroupIds((current) => {
            const next = new Set(current);
            if (next.has(groupId)) {
                next.delete(groupId);
            } else {
                next.add(groupId);
            }
            return next;
        });
    };

    const reorderGroups = (fromIndex, toIndex) => {
        if (fromIndex === null || toIndex === null || fromIndex === toIndex) {
            return;
        }

        const nextGroups = [...form.data.work_groups];
        const [moved] = nextGroups.splice(fromIndex, 1);
        nextGroups.splice(toIndex, 0, moved);
        setGroups(nextGroups);
        setActiveGroupIndex(toIndex);
    };

    const editRow = (row) => {
        setEditing(row);
        setDraggedGroupIndex(null);
        setActiveGroupIndex(0);
        setCollapsedGroupIds(new Set());
        form.setData({
            perumahan_id: row.perumahan_id ?? '',
            nama_template: row.nama_template ?? '',
            catatan: row.catatan ?? '',
            work_groups: normalizeGroups(row.groups ?? []),
        });
    };

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true, onSuccess: resetForm };
        editing ? form.put(templateUrl(`/${editing.id}`), requestOptions) : form.post(templateUrl(), requestOptions);
    };

    const destroyRow = (row) => {
        if (!window.confirm(`Hapus template ${row.nama_template}?`)) {
            return;
        }

        router.delete(templateUrl(`/${row.id}`), { preserveScroll: true });
    };

    const filteredRows = useMemo(() => {
        const keyword = search.trim().toLowerCase();
        if (!keyword) {
            return rows.data ?? [];
        }

        return (rows.data ?? []).filter((row) => [
            row.perumahan,
            row.nama_template,
            row.groups_text,
            row.catatan,
        ].some((value) => String(value ?? '').toLowerCase().includes(keyword)));
    }, [rows.data, search]);

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Manajemen Proyek</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                {permissions.canCreate && (
                    <section className="grid gap-4 rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-sm font-extrabold">{editing ? 'Edit Template' : 'Tambah Template'}</p>
                                <p className="text-xs text-ink-soft dark:text-white/60">Satu tahapan bisa punya banyak item. Judul tahapan tidak boleh dobel dalam satu template.</p>
                            </div>
                            {editing && <Button type="button" variant="outline" onClick={resetForm}><MinusCircle size={16} /> Batal</Button>}
                        </div>

                        <form className="grid gap-4" onSubmit={submit}>
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="grid gap-2">
                                    <span className="text-sm font-extrabold">Konteks Template</span>
                                    <Dropdown value={selectedContext} label="Pilih Konteks" options={options.contexts ?? []} onChange={(value) => {
                                        setSelectedContext(value);
                                        setEditing(null);
                                        form.reset();
                                        form.setData(defaults);
                                        router.get(baseUrl, { context: value, search }, { preserveScroll: true, preserveState: true, replace: true });
                                    }} />
                                </div>
                                <div className="grid gap-2">
                                    <span className="text-sm font-extrabold">Perumahan</span>
                                    <Dropdown value={form.data.perumahan_id} label="Pilih Perumahan" options={options.perumahans ?? []} onChange={(value) => form.setData('perumahan_id', value)} />
                                    {form.errors.perumahan_id && <span className="text-xs font-bold text-red-600">{form.errors.perumahan_id}</span>}
                                </div>
                                <Input label="Nama Template" value={form.data.nama_template} error={form.errors.nama_template} onChange={(event) => form.setData('nama_template', event.target.value)} />
                                <Textarea label="Catatan" value={form.data.catatan} error={form.errors.catatan} onChange={(event) => form.setData('catatan', event.target.value)} />
                            </div>

                            <div className="grid gap-4 rounded-lg border border-silver-deep/60 p-4 dark:border-white/10">
                                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <p className="text-sm font-extrabold">Judul Tahapan & Item Pekerjaan</p>
                                        <p className="text-xs text-ink-soft dark:text-white/60">Formatnya mirip lembar progres: satu tahap bisa banyak item pekerjaan.</p>
                                    </div>
                                </div>

                                {(form.data.work_groups ?? []).map((group, groupIndex) => (
                                    <div
                                        className={`grid gap-4 rounded-lg bg-silver-soft/80 p-4 dark:bg-white/5 ${draggedGroupIndex === groupIndex ? 'ring-2 ring-primary/60' : ''}`}
                                        key={group.ui_id ?? groupIndex}
                                        draggable
                                        onDragStart={(event) => {
                                            setDraggedGroupIndex(groupIndex);
                                            event.dataTransfer.effectAllowed = 'move';
                                            event.dataTransfer.setData('text/plain', String(groupIndex));
                                        }}
                                        onDragOver={(event) => {
                                            event.preventDefault();
                                            event.dataTransfer.dropEffect = 'move';
                                        }}
                                        onDrop={(event) => {
                                            event.preventDefault();
                                            reorderGroups(Number(event.dataTransfer.getData('text/plain')), groupIndex);
                                            setDraggedGroupIndex(null);
                                        }}
                                        onDragEnd={() => setDraggedGroupIndex(null)}
                                    >
                                        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                            <div className="grid gap-2 md:flex-1">
                                                <button
                                                    type="button"
                                                    className="flex items-center gap-2 text-left"
                                                    onClick={() => setActiveGroupIndex(groupIndex)}
                                                >
                                                    <GripVertical size={16} className="shrink-0 text-ink-soft dark:text-white/50" />
                                                    <span className="text-sm font-extrabold">Judul Tahapan</span>
                                                </button>
                                                <Input
                                                    value={group.judul_tahapan}
                                                    placeholder="Contoh: PEK. PERSIAPAN & PONDASI"
                                                    onChange={(event) => setWorkGroup(groupIndex, 'judul_tahapan', event.target.value)}
                                                />
                                                <span className="text-xs text-ink-soft dark:text-white/60">
                                                    {group.items?.length ?? 0} item - {money((group.items ?? []).reduce((sum, item) => sum + itemTotal(item), 0))}
                                                </span>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <Button type="button" variant="outline" onClick={() => toggleGroupCollapsed(group.ui_id)}>
                                                    {collapsedGroupIds.has(group.ui_id) ? <ChevronRight size={16} /> : <ChevronDown size={16} />}
                                                    {collapsedGroupIds.has(group.ui_id) ? 'Buka' : 'Tutup'}
                                                </Button>
                                                <Button type="button" variant="outline" onClick={() => addItemToGroup(groupIndex)}>
                                                    <PlusCircle size={16} /> Tambah Item
                                                </Button>
                                                <Button type="button" variant="ghost" className="text-red-600" disabled={form.data.work_groups.length === 1} onClick={() => removeGroup(groupIndex)}>
                                                    <MinusCircle size={16} />
                                                </Button>
                                            </div>
                                        </div>

                                        {!collapsedGroupIds.has(group.ui_id) && (
                                            <div className="grid gap-3">
                                                {(group.items ?? []).map((item, itemIndex) => (
                                                    <div className="grid gap-3 rounded-lg border border-white/50 bg-white/70 p-3 dark:border-white/10 dark:bg-black/10 md:grid-cols-[1.7fr_0.9fr_auto]" key={itemIndex}>
                                                        <Input label={`Item Pekerjaan ${itemIndex + 1}`} value={item.nama_pekerjaan} placeholder="Contoh: Pasang pondasi batu gunung" onChange={(event) => setWorkGroupItem(groupIndex, itemIndex, 'nama_pekerjaan', event.target.value)} />
                                                        <CurrencyInput label="Harga Satuan" value={item.harga_satuan} onChange={(value) => setWorkGroupItem(groupIndex, itemIndex, 'harga_satuan', value)} />
                                                        <div className="flex items-end justify-end">
                                                            <Button type="button" variant="ghost" size="sm" className="text-red-600" disabled={group.items.length === 1} onClick={() => setWorkGroup(groupIndex, 'items', group.items.filter((_, index) => index !== itemIndex))}>
                                                                <MinusCircle size={16} />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        {!collapsedGroupIds.has(group.ui_id) && (
                                            <div className="text-right text-sm font-extrabold text-ink-soft dark:text-white/60">
                                                Total tahapan: {money((group.items ?? []).reduce((sum, item) => sum + itemTotal(item), 0))}
                                            </div>
                                        )}
                                    </div>
                                ))}

                                <div className="sticky bottom-3 z-10 -mx-4 rounded-lg border border-silver-deep/60 bg-white/95 p-3 shadow-soft backdrop-blur dark:border-white/10 dark:bg-slate-950/95">
                                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                        <div className="text-sm font-bold text-ink-soft dark:text-white/60">
                                            Tahap aktif: {form.data.work_groups[activeGroupIndex]?.judul_tahapan?.trim() || `Tahap ${activeGroupIndex + 1}`}
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button type="button" variant="outline" onClick={addGroup}>
                                                <PlusCircle size={16} /> Tambah Tahap
                                            </Button>
                                            <Button type="button" variant="outline" onClick={() => addItemToGroup(activeGroupIndex)}>
                                                <PlusCircle size={16} /> Tambah Item
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                {form.errors.work_groups && <p className="text-sm font-bold text-red-600">{form.errors.work_groups}</p>}
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? <LoaderCircle className="animate-spin" size={16} /> : <Save size={16} />}
                                    {editing ? 'Simpan Perubahan' : 'Simpan Template'}
                                </Button>
                                {editing && <Button type="button" variant="outline" onClick={resetForm}><MinusCircle size={16} /> Reset</Button>}
                            </div>
                        </form>
                    </section>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between" onSubmit={(event) => { event.preventDefault(); router.get(baseUrl, { context: selectedContext, search }, { preserveScroll: true, preserveState: true, replace: true }); }}>
                        <Input className="md:max-w-md" label="Search" value={search} placeholder="Cari template, perumahan, atau tahap..." onChange={(event) => setSearch(event.target.value)} />
                        <Button type="submit"><Search size={17} /> Cari</Button>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {['Perumahan', 'Nama Template', 'Tahap', 'Item', 'Nilai Template', 'Ringkasan', 'Aksi'].map((column) => (
                                        <th className="px-5 py-4 font-extrabold" key={column}>{column}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {filteredRows.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-semibold">{row.perumahan}</td>
                                        <td className="px-5 py-4 font-extrabold">{row.nama_template}</td>
                                        <td className="px-5 py-4 font-bold">{row.group_count}</td>
                                        <td className="px-5 py-4 font-bold">{row.item_count}</td>
                                        <td className="px-5 py-4 font-extrabold">{money(row.total_nilai)}</td>
                                        <td className="px-5 py-4 text-xs text-ink-soft">{row.groups_text || row.catatan || '-'}</td>
                                        <td className="px-5 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                {row.can_edit && <Button type="button" size="sm" variant="outline" onClick={() => editRow(row)}><Edit3 size={15} /> Edit</Button>}
                                                {row.can_delete && <Button type="button" size="sm" variant="outline" className="text-red-600" onClick={() => destroyRow(row)}><Trash2 size={15} /> Hapus</Button>}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {filteredRows.length === 0 && <tr><td className="px-5 py-10 text-center font-bold text-ink-soft" colSpan={7}>Belum ada template pekerjaan.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Admin'}>{page}</AdminLayout>;
