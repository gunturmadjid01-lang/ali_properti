import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Layers3, ListChecks, LoaderCircle, MinusCircle, PlusCircle, Save, WalletCards } from 'lucide-react';
import { useMemo } from 'react';
import { Button, CurrencyInput, Dropdown, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const money = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
}).format(Number(value ?? 0));

const newItem = () => ({ nama_pekerjaan: '', harga_satuan: '' });
const newGroup = () => ({ judul_tahapan: '', items: [newItem()] });
const normalizeGroups = (groups = []) => groups.length ? groups.map((group) => ({
    judul_tahapan: group.judul_tahapan ?? '',
    items: (group.items?.length ? group.items : [newItem()]).map((item) => ({
        nama_pekerjaan: item.nama_pekerjaan ?? '',
        harga_satuan: item.harga_satuan ?? '',
    })),
})) : [newGroup()];

export default function Form({ title, description, context, template, options = {}, indexUrl, storeUrl, updateUrl }) {
    const form = useForm({
        perumahan_id: template?.perumahan_id ?? '',
        nama_template: template?.nama_template ?? '',
        catatan: template?.catatan ?? '',
        work_groups: normalizeGroups(template?.groups),
    });

    const summary = useMemo(() => {
        const groups = form.data.work_groups ?? [];
        return {
            groups: groups.length,
            items: groups.reduce((sum, group) => sum + (group.items?.length ?? 0), 0),
            total: groups.reduce((sum, group) => sum + (group.items ?? []).reduce((itemSum, item) => itemSum + Number(item.harga_satuan || 0), 0), 0),
        };
    }, [form.data.work_groups]);

    const setGroup = (groupIndex, nextGroup) => form.setData('work_groups', form.data.work_groups.map((group, index) => index === groupIndex ? nextGroup : group));
    const addGroup = () => form.setData('work_groups', [...form.data.work_groups, newGroup()]);
    const removeGroup = (groupIndex) => form.setData('work_groups', form.data.work_groups.filter((_, index) => index !== groupIndex));
    const addItem = (groupIndex) => setGroup(groupIndex, { ...form.data.work_groups[groupIndex], items: [...form.data.work_groups[groupIndex].items, newItem()] });
    const removeItem = (groupIndex, itemIndex) => setGroup(groupIndex, { ...form.data.work_groups[groupIndex], items: form.data.work_groups[groupIndex].items.filter((_, index) => index !== itemIndex) });
    const setItem = (groupIndex, itemIndex, key, value) => setGroup(groupIndex, {
        ...form.data.work_groups[groupIndex],
        items: form.data.work_groups[groupIndex].items.map((item, index) => index === itemIndex ? { ...item, [key]: value } : item),
    });

    const submit = (event) => {
        event.preventDefault();
        template ? form.put(updateUrl) : form.post(storeUrl);
    };

    return (
        <>
            <Head title={title} />
            <form className="grid gap-6" onSubmit={submit}>
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Template Pekerjaan SPK</p>
                            <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                            <p className="mt-2 text-ink-soft dark:text-white/60">{description}</p>
                        </div>
                        <Button type="button" variant="outline" onClick={() => router.get(indexUrl)}><ArrowLeft size={17} /> Kembali</Button>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex items-center gap-3"><Layers3 className="text-primary" size={22} /><p className="text-xs font-extrabold uppercase tracking-[0.14em] text-ink-soft">Total Tahap</p></div>
                        <p className="mt-3 text-3xl font-extrabold">{summary.groups}</p>
                    </div>
                    <div className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex items-center gap-3"><ListChecks className="text-primary" size={22} /><p className="text-xs font-extrabold uppercase tracking-[0.14em] text-ink-soft">Total Item</p></div>
                        <p className="mt-3 text-3xl font-extrabold">{summary.items}</p>
                    </div>
                    <div className="rounded-lg border border-primary/25 bg-primary/10 p-5 shadow-soft dark:border-primary/30 dark:bg-primary/10">
                        <div className="flex items-center gap-3"><WalletCards className="text-primary" size={22} /><p className="text-xs font-extrabold uppercase tracking-[0.14em] text-ink-soft">Total Nilai Template</p></div>
                        <p className="mt-3 text-2xl font-extrabold">{money(summary.total)}</p>
                    </div>
                </section>

                <section className="grid gap-5 rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div>
                        <h3 className="text-lg font-extrabold">Informasi Template</h3>
                        <p className="mt-1 text-sm text-ink-soft dark:text-white/60">Tentukan lokasi dan identitas template pekerjaan.</p>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Konteks Template <span className="text-red-500">*</span></span>
                            {template ? (
                                <div className="flex min-h-11 items-center rounded-lg border border-silver-deep/70 bg-silver-soft/70 px-4 font-bold capitalize dark:border-white/10 dark:bg-white/5">{context}</div>
                            ) : (
                                <Dropdown value={context} options={options.contexts ?? []} onChange={(value) => router.get(`/admin/spk-template/create?context=${value}`)} />
                            )}
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Perumahan <span className="text-red-500">*</span></span>
                            <Dropdown value={form.data.perumahan_id} label="Pilih Perumahan" options={options.perumahans ?? []} onChange={(value) => form.setData('perumahan_id', value)} />
                            {form.errors.perumahan_id && <span className="text-xs font-bold text-red-600">{form.errors.perumahan_id}</span>}
                        </div>
                        <Input required label="Nama Template" value={form.data.nama_template} error={form.errors.nama_template} placeholder="Contoh: Upah Borongan Rumah Tipe 36" onChange={(event) => form.setData('nama_template', event.target.value)} />
                    </div>
                    <Textarea label="Catatan" value={form.data.catatan} error={form.errors.catatan} placeholder="Catatan penggunaan template (opsional)" onChange={(event) => form.setData('catatan', event.target.value)} />
                </section>

                <section className="grid gap-5">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div><h3 className="text-xl font-extrabold">Tahapan & Upah Borongan</h3><p className="mt-1 text-sm text-ink-soft dark:text-white/60">Masukkan seluruh item pekerjaan dan nilai borongannya.</p></div>
                        <Button type="button" variant="outline" onClick={addGroup}><PlusCircle size={16} /> Tambah Tahap</Button>
                    </div>

                    {form.data.work_groups.map((group, groupIndex) => {
                        const groupTotal = group.items.reduce((sum, item) => sum + Number(item.harga_satuan || 0), 0);
                        return (
                            <article className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8" key={groupIndex}>
                                <div className="flex flex-col gap-4 border-b border-silver-deep/60 bg-silver-soft/70 p-5 dark:border-white/10 dark:bg-white/5 md:flex-row md:items-end">
                                    <div className="flex-1"><Input required label={`Tahap ${groupIndex + 1}`} value={group.judul_tahapan} placeholder="Contoh: PEKERJAAN PONDASI" onChange={(event) => setGroup(groupIndex, { ...group, judul_tahapan: event.target.value })} /></div>
                                    <div className="min-w-48"><p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Subtotal Tahap</p><p className="mt-2 text-lg font-extrabold">{money(groupTotal)}</p></div>
                                    <Button type="button" variant="ghost" className="text-red-600" disabled={form.data.work_groups.length === 1} onClick={() => removeGroup(groupIndex)}><MinusCircle size={16} /> Hapus Tahap</Button>
                                </div>
                                <div className="grid gap-3 p-5">
                                    {group.items.map((item, itemIndex) => (
                                        <div className="grid gap-3 rounded-lg border border-silver-deep/60 p-4 dark:border-white/10 md:grid-cols-[52px_1fr_260px_auto] md:items-end" key={itemIndex}>
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-ink font-extrabold text-white dark:bg-white dark:text-ink">{itemIndex + 1}</div>
                                            <Input required label="Nama Pekerjaan" value={item.nama_pekerjaan} placeholder="Uraian pekerjaan" onChange={(event) => setItem(groupIndex, itemIndex, 'nama_pekerjaan', event.target.value)} />
                                            <CurrencyInput required label="Upah Borongan" value={item.harga_satuan} onChange={(value) => setItem(groupIndex, itemIndex, 'harga_satuan', value)} />
                                            <Button type="button" variant="ghost" className="text-red-600" disabled={group.items.length === 1} onClick={() => removeItem(groupIndex, itemIndex)}><MinusCircle size={16} /></Button>
                                        </div>
                                    ))}
                                    <div className="flex justify-end"><Button type="button" size="sm" variant="outline" onClick={() => addItem(groupIndex)}><PlusCircle size={15} /> Tambah Item Tahap Ini</Button></div>
                                </div>
                            </article>
                        );
                    })}
                    {form.errors.work_groups && <p className="text-sm font-bold text-red-600">{form.errors.work_groups}</p>}
                </section>

                <section className="sticky bottom-4 z-20 flex flex-col gap-3 rounded-lg border border-silver-deep/70 bg-white/95 p-4 shadow-soft backdrop-blur dark:border-white/10 dark:bg-slate-950/95 md:flex-row md:items-center md:justify-between">
                    <div><p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">Total Template</p><p className="mt-1 text-xl font-extrabold">{money(summary.total)}</p></div>
                    <div className="flex gap-2"><Button type="button" variant="outline" onClick={() => router.get(indexUrl)}>Batal</Button><Button type="submit" disabled={form.processing}>{form.processing ? <LoaderCircle className="animate-spin" size={17} /> : <Save size={17} />}{form.processing ? 'Menyimpan...' : 'Simpan Template'}</Button></div>
                </section>
            </form>
        </>
    );
}

Form.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Template Pekerjaan SPK'}>{page}</AdminLayout>;
