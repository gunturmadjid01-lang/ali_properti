import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button, CurrencyInput, Dropdown, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const normalizeOptions = (source = {}) => Array.isArray(source)
    ? source.map((option) => ({ ...option, value: String(option.value) }))
    : Object.entries(source).map(([value, label]) => ({ value: String(value), label }));

export default function OperationsForm({ title, module, section, sectionTitle, indexUrl, actionUrl, method, fields = [], options = {}, row = null }) {
    const defaults = Object.fromEntries(fields.map((field) => [field.name, row?.[field.name] ?? (field.type === 'boolean' ? true : '')]));
    const form = useForm(defaults);
    const optionList = (key) => normalizeOptions(options[key] ?? {});
    const selectedInventoryItem = optionList('items').find((choice) => choice.value === String(form.data.inventory_item_id ?? ''));
    const isUnitInventory = selectedInventoryItem?.inventory_type === 'unit';

    const relatedChoices = (field, choices) => {
        const currentValue = String(form.data[field.name] ?? '');

        if (module === 'inventory' && section === 'units' && field.name === 'inventory_item_id') {
            return choices.filter((choice) => choice.inventory_type === 'unit' || choice.value === currentValue);
        }

        if (module === 'inventory' && field.name === 'office_asset_id') {
            const itemId = String(form.data.inventory_item_id ?? '');
            if (!itemId) return [];
            return choices.filter((choice) => choice.inventory_item_id === itemId && (choice.status === 'available' || choice.value === currentValue));
        }

        if (module === 'inventory' && field.name === 'inventory_loan_id') {
            return choices.filter((choice) => choice.status === 'borrowed' || choice.value === currentValue);
        }

        if (module === 'inventory' && field.name === 'detail_rumah_id') {
            const projectId = String(form.data.perumahan_id ?? '');
            if (!projectId) return [];
            return choices.filter((choice) => choice.perumahan_id === projectId || choice.value === currentValue);
        }
        if (module === 'sales' && field.name === 'perumahan_id') {
            const branchId = String(form.data.cabang_perusahaan_id ?? '');
            if (!branchId) return choices;
            return choices.filter((choice) => choice.cabang_perusahaan_id === branchId || choice.value === currentValue);
        }

        if (module === 'heavy' && field.name === 'detail_rumah_id') {
            const projectId = String(form.data.perumahan_id ?? '');
            if (!projectId) return [];
            return choices.filter((choice) => choice.perumahan_id === projectId || choice.value === currentValue);
        }

        if (module === 'heavy' && section === 'components' && field.name === 'heavy_equipment_id') {
            const typeId = String(form.data.heavy_equipment_type_id ?? '');
            if (!typeId) return [];
            return choices.filter((choice) => choice.heavy_equipment_type_id === typeId || choice.value === currentValue);
        }

        if (module === 'heavy' && section === 'replacements' && ['old_component_id', 'new_component_id'].includes(field.name)) {
            const equipmentId = String(form.data.heavy_equipment_id ?? '');
            const equipment = optionList('equipment').find((choice) => choice.value === equipmentId);
            if (!equipment) return [];

            if (field.name === 'old_component_id') {
                return choices.filter((choice) => (choice.heavy_equipment_id === equipmentId && choice.status === 'installed') || choice.value === currentValue);
            }

            return choices.filter((choice) => (
                choice.status === 'available'
                && choice.heavy_equipment_type_id === equipment.heavy_equipment_type_id
                && choice.value !== String(form.data.old_component_id ?? '')
            ) || choice.value === currentValue);
        }

        return choices;
    };

    const changeSelect = (field, value, selectedOption) => {
        const next = { ...form.data, [field.name]: value };

        if (module === 'inventory' && field.name === 'inventory_item_id') {
            next.office_asset_id = '';
            next.quantity = selectedOption?.inventory_type === 'unit' ? 1 : (next.quantity || 1);
        }

        if (module === 'inventory' && field.name === 'office_asset_id' && selectedOption) {
            next.inventory_item_id = selectedOption.inventory_item_id ?? next.inventory_item_id;
            if (section === 'loans') next.source_location_id = selectedOption.inventory_location_id ?? '';
            if (section === 'transfers') next.from_location_id = selectedOption.inventory_location_id ?? '';
            if (section === 'damages') next.inventory_location_id = selectedOption.inventory_location_id ?? '';
            if (section === 'losses') next.last_location_id = selectedOption.inventory_location_id ?? '';
        }

        if (module === 'inventory' && field.name === 'inventory_loan_id' && selectedOption) {
            next.return_location_id = selectedOption.source_location_id ?? next.return_location_id;
        }

        if (field.name === 'perumahan_id') {
            next.detail_rumah_id = '';
        }
        if (module === 'sales' && field.name === 'cabang_perusahaan_id') next.perumahan_id = '';

        if (module === 'inventory' && section === 'locations' && field.name === 'owner_type') {
            next.branch_id = '';
            next.perumahan_id = '';
        }

        if (module === 'heavy' && field.name === 'heavy_equipment_type_id') {
            next.heavy_equipment_id = '';
        }

        if (module === 'heavy' && field.name === 'heavy_equipment_id' && selectedOption) {
            if (section === 'components') next.heavy_equipment_type_id = selectedOption.heavy_equipment_type_id ?? next.heavy_equipment_type_id;
            if (section === 'replacements') {
                next.old_component_id = '';
                next.new_component_id = '';
            }
        }

        if (module === 'heavy' && field.name === 'old_component_id') {
            next.new_component_id = '';
        }

        form.setData(next);
    };

    const selectHelp = (field, choices) => {
        if (module === 'inventory' && field.name === 'office_asset_id') {
            if (!form.data.inventory_item_id) return 'Pilih barang terlebih dahulu agar unit aset tersaring.';
            if (!choices.length) return 'Tidak ada unit tersedia untuk barang yang dipilih.';
            return 'Hanya unit tersedia milik barang yang dipilih yang ditampilkan.';
        }
        if (module === 'inventory' && field.name === 'inventory_loan_id') return 'Hanya peminjaman yang belum dikembalikan yang ditampilkan.';
        if (field.name === 'detail_rumah_id') return form.data.perumahan_id ? 'Hanya unit rumah pada perumahan terpilih.' : 'Pilih perumahan terlebih dahulu.';
        if (module === 'heavy' && section === 'components' && field.name === 'heavy_equipment_id') return 'Daftar alat mengikuti jenis alat yang dipilih.';
        if (module === 'heavy' && section === 'replacements' && field.name === 'old_component_id') return 'Hanya komponen yang sedang terpasang pada alat terpilih.';
        if (module === 'heavy' && section === 'replacements' && field.name === 'new_component_id') return 'Hanya komponen tersedia dengan jenis alat yang sesuai.';
        return null;
    };

    const control = (field) => {
        const common = { label: field.label, required: field.required, error: form.errors[field.name] };
        if (field.type === 'auto-code') return <div className="grid gap-2"><Input {...common} readOnly value={form.data[field.name] ?? ''} placeholder="Dibuat otomatis saat disimpan" /><span className="text-xs font-semibold text-ink-soft">Kode dibuat otomatis oleh sistem dan tidak perlu diisi.</span></div>;
        if (field.type === 'asset-status') return <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4"><p className="text-sm font-extrabold">Status Unit Aset</p><p className="mt-1 text-sm text-ink-soft">{row?.status ? String(row.status).replaceAll('_', ' ') : 'Tersedia'}. Status berubah otomatis melalui peminjaman, pengembalian, kerusakan, dan kehilangan.</p></div>;
        if (module === 'inventory' && section === 'items' && ['total_stock', 'available_stock'].includes(field.name) && form.data.inventory_type === 'unit') {
            return <div className="rounded-lg border border-gold/40 bg-gold/10 p-4"><p className="text-sm font-extrabold">{field.label}: otomatis</p><p className="mt-1 text-xs font-semibold text-ink-soft">Stok dihitung dari jumlah Unit Aset dan status masing-masing unit, sehingga tidak dapat diketik manual.</p></div>;
        }
        if (field.type === 'select' || (/_id$/.test(field.name) && (field.options || options[field.optionsKey]))) {
            const choices = relatedChoices(field, normalizeOptions(field.options ?? options[field.optionsKey] ?? {}));
            const disabled = (module === 'inventory' && field.name === 'office_asset_id' && !form.data.inventory_item_id)
                || (field.name === 'detail_rumah_id' && !form.data.perumahan_id)
                || (module === 'heavy' && section === 'components' && field.name === 'heavy_equipment_id' && !form.data.heavy_equipment_type_id)
                || (module === 'heavy' && section === 'replacements' && ['old_component_id', 'new_component_id'].includes(field.name) && !form.data.heavy_equipment_id)
                || (module === 'inventory' && section === 'locations' && field.name === 'branch_id' && form.data.owner_type !== 'branch')
                || (module === 'inventory' && section === 'locations' && field.name === 'perumahan_id' && form.data.owner_type !== 'housing');
            const help = selectHelp(field, choices);
            return <div className="grid gap-2"><span className="text-sm font-extrabold">{field.label}{field.required && <span className="text-red-500"> *</span>}</span><Dropdown disabled={disabled} value={String(form.data[field.name] ?? '')} options={choices} label={disabled ? 'Pilih data induk terlebih dahulu' : `Pilih ${field.label}`} onChange={(value, selectedOption) => changeSelect(field, value, selectedOption)} />{help && <span className="text-xs font-semibold text-ink-soft">{help}</span>}{form.errors[field.name] && <span className="text-xs font-bold text-red-600">{form.errors[field.name]}</span>}</div>;
        }
        if (field.type === 'boolean') return <label className="flex min-h-12 items-center gap-3 rounded-lg border border-silver-deep/60 p-4 font-bold"><input type="checkbox" checked={Boolean(form.data[field.name])} onChange={(event) => form.setData(field.name, event.target.checked)} /> {field.label}</label>;
        if (/notes|description|chronology|purpose|reason|damage/.test(field.name)) return <Textarea {...common} value={form.data[field.name] ?? ''} onChange={(event) => form.setData(field.name, event.target.value)} />;
        if (/cost|price|biaya/.test(field.name)) return <CurrencyInput {...common} value={form.data[field.name] ?? ''} onChange={(value) => form.setData(field.name, value)} />;
        const lockedUnitQuantity = module === 'inventory' && ['loans', 'transfers', 'losses'].includes(section) && field.name === 'quantity' && isUnitInventory;
        return <div className="grid gap-2"><Input {...common} readOnly={lockedUnitQuantity} type={field.type} value={lockedUnitQuantity ? 1 : (form.data[field.name] ?? '')} onChange={(event) => form.setData(field.name, event.target.value)} />{lockedUnitQuantity && <span className="text-xs font-semibold text-ink-soft">Satu kode Unit Aset selalu mewakili satu unit fisik.</span>}</div>;
    };
    const submit = (event) => { event.preventDefault(); form[method](actionUrl); };

    return <><Head title={`${row ? 'Ubah' : 'Tambah'} ${sectionTitle}`} /><div className="mx-auto grid max-w-6xl gap-6">
        <section className="flex flex-col gap-4 rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8 md:flex-row md:items-center md:justify-between"><div><p className="text-xs font-black uppercase tracking-wider text-ink-soft">{module === 'heavy' ? 'Alat Berat' : 'Inventaris Perusahaan'}</p><h1 className="mt-1 text-2xl font-black">{row ? 'Ubah' : 'Tambah'} {sectionTitle}</h1><p className="mt-1 text-sm text-ink-soft">Pilihan pada form saling terhubung sesuai data induknya.</p></div><Button as={Link} href={indexUrl} variant="outline"><ArrowLeft size={16}/> Kembali</Button></section>
        <form className="grid gap-6" onSubmit={submit}><section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8"><div className="grid gap-5 md:grid-cols-2">{fields.filter((field) => !field.createOnly || !row).map((field) => <div className={/notes|description|chronology|purpose|reason|damage/.test(field.name) ? 'md:col-span-2' : ''} key={field.name}>{control(field)}</div>)}</div></section><section className="flex justify-end gap-3 rounded-xl border border-white/80 bg-white/80 p-4 shadow-soft dark:border-white/10 dark:bg-white/8"><Button as={Link} href={indexUrl} variant="outline">Batal</Button><Button type="submit" disabled={form.processing}><Save size={16}/> {form.processing ? 'Menyimpan...' : 'Simpan Data'}</Button></section></form>
    </div></>;
}

OperationsForm.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Form Aset'}>{page}</AdminLayout>;
