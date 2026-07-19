import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-react';
import { Button, Dropdown, Input, Textarea } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';

const number = (value) => Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 6 });
const blankItem = () => ({ barang_material_id: '', material_unit_id: '', quantity: '' });

export default function Form({ title, indexUrl, actionUrl, method, group = null, materials = [], statusOptions = [] }) {
    const form = useForm({
        name: group?.name ?? '', base_quantity: group?.base_quantity ?? 1, base_unit: group?.base_unit ?? 'item', notes: group?.notes ?? '',
        status: group?.status ?? 'aktif', items: group?.items?.length ? group.items : [blankItem()], submit_action: 'save',
    });
    const materialById = (id) => materials.find((material) => String(material.value) === String(id));
    const unitFor = (row) => materialById(row.barang_material_id)?.unit_options?.find((unit) => String(unit.value) === String(row.material_unit_id));
    const updateItem = (index, key, value) => form.setData('items', form.data.items.map((row, rowIndex) => {
        if (rowIndex !== index) return row;
        if (key === 'barang_material_id') {
            const selected = materialById(value);
            return { ...row, barang_material_id: value, material_unit_id: selected?.unit_options?.[0]?.value ?? '' };
        }
        return { ...row, [key]: value };
    }));
    const addItem = () => form.setData('items', [...form.data.items, blankItem()]);
    const removeItem = (index) => form.setData('items', form.data.items.filter((_, rowIndex) => rowIndex !== index));
    const submit = (action) => {
        form.transform((data) => ({ ...data, submit_action: action }));
        form[method](actionUrl, { onFinish: () => form.transform((data) => data) });
    };

    return <>
        <Head title={title} />
        <div className="grid gap-6">
            <section className="flex flex-col gap-4 rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8 md:flex-row md:items-center md:justify-between">
                <div><p className="text-xs font-black uppercase tracking-wider text-ink-soft">Perencanaan Biaya</p><h1 className="mt-1 text-2xl font-black">{title}</h1><p className="mt-1 text-sm text-ink-soft">Qty material berlaku untuk satu qty dasar kelompok. Satuan pembentuk boleh memakai seluruh level konversi material.</p></div>
                <Button as={Link} href={indexUrl} variant="outline"><ArrowLeft size={16} /> Kembali</Button>
            </section>

            <form className="grid gap-6" onSubmit={(event) => { event.preventDefault(); submit('save'); }}>
                <section className="rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <h2 className="font-black">Informasi Kelompok</h2>
                    <div className="mt-4 grid gap-4 md:grid-cols-4">
                        <div className="md:col-span-2"><Input label="Nama Kelompok" value={form.data.name} error={form.errors.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Contoh: Baut, paku, dan kawat" /></div>
                        <Input label="Qty Dasar" type="number" min="0.000001" step="0.000001" value={form.data.base_quantity} error={form.errors.base_quantity} onChange={(event) => form.setData('base_quantity', event.target.value)} />
                        <Input label="Satuan Qty Dasar" value={form.data.base_unit} error={form.errors.base_unit} onChange={(event) => form.setData('base_unit', event.target.value)} placeholder="item" />
                        <div className="grid gap-2"><span className="text-sm font-extrabold">Status</span><Dropdown value={form.data.status} options={statusOptions} onChange={(value) => form.setData('status', value)} /></div>
                        <div className="md:col-span-3"><Textarea label="Catatan" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} /></div>
                    </div>
                </section>

                <section className="rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="font-black">Item Material</h2><p className="text-sm text-ink-soft">Pilih beberapa material. Ekuivalen Level 1 dihitung otomatis untuk persiapan realisasi HPP.</p></div><Button type="button" variant="outline" onClick={addItem}><Plus size={16} /> Tambah Material</Button></div>
                    <div className="mt-4 grid gap-3">
                        {form.data.items.map((row, index) => {
                            const selectedMaterial = materialById(row.barang_material_id);
                            const selectedUnit = unitFor(row);
                            const equivalent = Number(row.quantity || 0) / Number(selectedUnit?.factor_to_base || 1);
                            const usedMaterialIds = form.data.items.filter((_, rowIndex) => rowIndex !== index).map((item) => String(item.barang_material_id));
                            return <div className="grid gap-3 rounded-lg border border-silver-deep/70 p-4 md:grid-cols-[50px_2fr_1fr_1fr_1fr_auto] md:items-end" key={index}>
                                <div className="pb-3 text-center text-lg font-black">{index + 1}</div>
                                <div className="grid gap-2"><span className="text-sm font-extrabold">Material</span><Dropdown value={row.barang_material_id} options={materials.filter((material) => !usedMaterialIds.includes(String(material.value)))} onChange={(value) => updateItem(index, 'barang_material_id', value)} />{form.errors[`items.${index}.barang_material_id`] && <span className="text-xs font-bold text-red-600">{form.errors[`items.${index}.barang_material_id`]}</span>}</div>
                                <Input label="Qty" type="number" min="0.000001" step="0.000001" value={row.quantity} error={form.errors[`items.${index}.quantity`]} onChange={(event) => updateItem(index, 'quantity', event.target.value)} />
                                <div className="grid gap-2"><span className="text-sm font-extrabold">Satuan</span><Dropdown value={row.material_unit_id} options={selectedMaterial?.unit_options ?? []} onChange={(value) => updateItem(index, 'material_unit_id', value)} />{form.errors[`items.${index}.material_unit_id`] && <span className="text-xs font-bold text-red-600">{form.errors[`items.${index}.material_unit_id`]}</span>}</div>
                                <div className="pb-2"><p className="text-xs font-bold uppercase text-ink-soft">Setara Level 1</p><p className="mt-1 font-black">{number(equivalent)} {selectedMaterial?.base_unit ?? '-'}</p></div>
                                <Button type="button" variant="outline" className="text-red-600" disabled={form.data.items.length === 1} onClick={() => removeItem(index)}><Trash2 size={15} /></Button>
                            </div>;
                        })}
                    </div>
                    {form.errors.items && <p className="mt-3 text-sm font-bold text-red-600">{form.errors.items}</p>}
                </section>

                <section className="flex flex-col justify-end gap-3 rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8 sm:flex-row">
                    {!group && <Button type="button" variant="outline" disabled={form.processing} onClick={() => submit('add_another')}><Plus size={16} /> Tambah Item Baru</Button>}
                    <Button type="submit" disabled={form.processing}><Save size={16} /> Simpan</Button>
                </section>
            </form>
        </div>
    </>;
}

Form.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Kelompok Material'}>{page}</AdminLayout>;
