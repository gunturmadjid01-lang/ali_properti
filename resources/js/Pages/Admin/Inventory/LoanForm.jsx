import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, PackagePlus, Plus, Save, Trash2 } from 'lucide-react';
import { Button, Dropdown, Input, Textarea } from '../../../Components/UI';
import AdminLayout from '../../../Layouts/AdminLayout';

const normalize = (options = []) => options.map((option) => ({ ...option, value: String(option.value) }));
const blankItem = () => ({ key: `${Date.now()}-${Math.random()}`, inventory_item_id: '', office_asset_id: '', quantity: 1, condition_out: 'good', notes: '' });

export default function LoanForm({ title, indexUrl, actionUrl, options = {}, defaults = {} }) {
    const items = normalize(options.items);
    const units = normalize(options.units);
    const locations = normalize(options.locations);
    const projects = normalize(options.perumahans);
    const houseUnits = normalize(options.houseUnits);
    const divisions = normalize(options.divisions);
    const form = useForm({
        transaction_no: defaults.transaction_no ?? '', transaction_type: defaults.transaction_type ?? 'loan', date: defaults.date ?? '',
        borrower: '', taken_by_name: '', taken_by_phone: '', inventory_division_id: '', source_location_id: '', inventory_location_id: '',
        perumahan_id: '', detail_rumah_id: '', planned_return_date: '', purpose: '', notes: '', items: [blankItem()],
    });

    const selectedAssetIds = form.data.items.map((line) => String(line.office_asset_id || '')).filter(Boolean);
    const updateLine = (index, patch) => form.setData('items', form.data.items.map((line, lineIndex) => lineIndex === index ? { ...line, ...patch } : line));
    const removeLine = (index) => form.data.items.length > 1 && form.setData('items', form.data.items.filter((_, lineIndex) => lineIndex !== index));
    const addLine = () => form.setData('items', [...form.data.items, blankItem()]);
    const selectedProjectUnits = houseUnits.filter((unit) => unit.perumahan_id === String(form.data.perumahan_id || ''));

    const selectItem = (index, value, choice) => updateLine(index, {
        inventory_item_id: value,
        office_asset_id: '',
        quantity: choice?.inventory_type === 'unit' ? 1 : Math.max(1, Number(form.data.items[index].quantity || 1)),
    });
    const selectAsset = (index, value, choice) => {
        updateLine(index, { office_asset_id: value, quantity: 1 });
        if (!form.data.source_location_id && choice?.inventory_location_id) form.setData('source_location_id', choice.inventory_location_id);
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(actionUrl);
    };
    const createDivision = async (name) => {
        const response = await fetch('/admin/inventaris-perusahaan/divisi', { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content ?? ''}, body:JSON.stringify({name}) });
        if (!response.ok) throw new Error('Divisi gagal disimpan.');
        return response.json();
    };

    return <><Head title={title} /><div className="mx-auto grid max-w-7xl gap-6">
        <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"><div>
                <p className="text-xs font-black uppercase tracking-[0.16em] text-ink-soft">Inventaris Perusahaan</p>
                <h1 className="mt-2 font-display text-3xl font-black">Pengambilan Multi-Barang</h1>
                <p className="mt-2 text-sm text-ink-soft">Satu nomor transaksi dapat memuat banyak barang. Petugas, pengambil, lokasi, dan Unit Aset tercatat dalam satu jejak audit.</p>
            </div><Button as={Link} href={indexUrl} variant="outline"><ArrowLeft size={16} /> Kembali</Button></div>
        </section>

        <form className="grid gap-6" onSubmit={submit}>
            <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                <div className="mb-5"><h2 className="text-xl font-black">Informasi Transaksi</h2><p className="mt-1 text-sm text-ink-soft">Kode transaksi dan petugas penyerah dibuat otomatis oleh sistem.</p></div>
                <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <Input label="Nomor Transaksi" readOnly value={form.data.transaction_no} placeholder="Dibuat otomatis saat disimpan" />
                    <div className="grid gap-2"><span className="text-sm font-extrabold">Jenis Transaksi *</span><Dropdown value={form.data.transaction_type} options={[{ value: 'loan', label: 'Peminjaman — wajib kembali' }, { value: 'placement', label: 'Penempatan aset' }, { value: 'consumption', label: 'Pemakaian habis' }]} onChange={(value) => form.setData('transaction_type', value)} /></div>
                    <Input label="Tanggal *" type="date" value={form.data.date} error={form.errors.date} onChange={(event) => form.setData('date', event.target.value)} />
                    <Input label="Penanggung Jawab / Peminjam *" value={form.data.borrower} error={form.errors.borrower} onChange={(event) => form.setData('borrower', event.target.value)} />
                    <Input label="Nama yang Mengambil *" value={form.data.taken_by_name} error={form.errors.taken_by_name} onChange={(event) => form.setData('taken_by_name', event.target.value)} />
                    <Input label="Nomor HP Pengambil" value={form.data.taken_by_phone} onChange={(event) => form.setData('taken_by_phone', event.target.value)} />
                    <div className="grid gap-2"><span className="text-sm font-extrabold">Divisi</span><Dropdown creatable createLabel="Simpan divisi baru" value={form.data.inventory_division_id} options={divisions} label="Cari atau tambah divisi" onCreate={createDivision} onChange={(value)=>form.setData('inventory_division_id',value)} /></div>
                    <div className="grid gap-2"><span className="text-sm font-extrabold">Lokasi/Gudang Asal</span><Dropdown value={form.data.source_location_id} options={locations} label="Pilih lokasi asal" onChange={(value) => form.setData('source_location_id', value)} />{form.errors.source_location_id && <p className="text-xs font-bold text-red-600">{form.errors.source_location_id}</p>}</div>
                    <div className="grid gap-2"><span className="text-sm font-extrabold">Lokasi Pemakaian *</span><Dropdown value={form.data.inventory_location_id} options={locations} label="Pilih lokasi pemakaian" onChange={(value) => form.setData('inventory_location_id', value)} />{form.errors.inventory_location_id && <p className="text-xs font-bold text-red-600">{form.errors.inventory_location_id}</p>}</div>
                    <div className="grid gap-2"><span className="text-sm font-extrabold">Perumahan / Proyek</span><Dropdown value={form.data.perumahan_id} options={projects} label="Pilih perumahan" onChange={(value) => form.setData({ ...form.data, perumahan_id: value, detail_rumah_id: '' })} /></div>
                    <div className="grid gap-2"><span className="text-sm font-extrabold">Unit Rumah</span><Dropdown disabled={!form.data.perumahan_id} value={form.data.detail_rumah_id} options={selectedProjectUnits} label={form.data.perumahan_id ? 'Pilih unit rumah' : 'Pilih perumahan dahulu'} onChange={(value) => form.setData('detail_rumah_id', value)} /></div>
                    {form.data.transaction_type !== 'consumption' && <Input label="Rencana Pengembalian *" type="date" value={form.data.planned_return_date} error={form.errors.planned_return_date} onChange={(event) => form.setData('planned_return_date', event.target.value)} />}
                    <div className="md:col-span-2 xl:col-span-3"><Textarea label="Keperluan *" value={form.data.purpose} error={form.errors.purpose} onChange={(event) => form.setData('purpose', event.target.value)} /></div>
                </div>
            </section>

            <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 className="flex items-center gap-2 text-xl font-black"><PackagePlus size={20} /> Daftar Barang</h2><p className="mt-1 text-sm text-ink-soft">Barang jumlah memakai kuantitas; barang Unit Aset wajib memilih unit fisik yang tersedia.</p></div><Button type="button" variant="outline" onClick={addLine}><Plus size={16} /> Tambah Barang</Button></div>
                <div className="grid gap-4">{form.data.items.map((line, index) => {
                    const selectedItem = items.find((item) => item.value === String(line.inventory_item_id));
                    const isUnit = selectedItem?.inventory_type === 'unit';
                    const itemChoices = form.data.transaction_type === 'consumption' ? items.filter((item) => item.inventory_type !== 'unit') : items;
                    const unitChoices = units.filter((unit) => unit.inventory_item_id === String(line.inventory_item_id) && unit.status === 'available' && (!selectedAssetIds.includes(unit.value) || unit.value === String(line.office_asset_id)));
                    return <div className="rounded-xl border border-silver-deep/60 bg-silver-soft/25 p-4" key={line.key}>
                        <div className="mb-4 flex items-center justify-between"><p className="font-black">Barang {index + 1}</p><Button type="button" size="sm" variant="outline" className="text-red-600" disabled={form.data.items.length === 1} onClick={() => removeLine(index)}><Trash2 size={15} /></Button></div>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                            <div className="grid gap-2 xl:col-span-2"><span className="text-sm font-extrabold">Nama Barang *</span><Dropdown value={String(line.inventory_item_id)} options={itemChoices} label="Pilih barang" onChange={(value, choice) => selectItem(index, value, choice)} />{form.errors[`items.${index}.inventory_item_id`] && <p className="text-xs font-bold text-red-600">{form.errors[`items.${index}.inventory_item_id`]}</p>}{selectedItem && <p className="text-xs font-semibold text-ink-soft">Stok tersedia: {selectedItem.available_stock} {selectedItem.inventory_type === 'unit' ? 'unit fisik' : ''}</p>}</div>
                            <div className="grid gap-2 xl:col-span-2"><span className="text-sm font-extrabold">Unit Aset {isUnit && '*'}</span><Dropdown disabled={!isUnit} value={String(line.office_asset_id)} options={unitChoices} label={isUnit ? (unitChoices.length ? 'Pilih unit fisik' : 'Tidak ada unit tersedia') : 'Tidak diperlukan'} onChange={(value, choice) => selectAsset(index, value, choice)} />{form.errors[`items.${index}.office_asset_id`] && <p className="text-xs font-bold text-red-600">{form.errors[`items.${index}.office_asset_id`]}</p>}</div>
                            <Input label="Jumlah *" type="number" min="1" readOnly={isUnit} value={isUnit ? 1 : line.quantity} error={form.errors[`items.${index}.quantity`]} onChange={(event) => updateLine(index, { quantity: event.target.value })} />
                            <div className="grid gap-2"><span className="text-sm font-extrabold">Kondisi Keluar</span><Dropdown value={line.condition_out} options={[{value:'good',label:'Baik'},{value:'fair',label:'Cukup Baik'},{value:'needs_service',label:'Perlu Perawatan'},{value:'damaged',label:'Rusak'}]} onChange={(value)=>updateLine(index,{condition_out:value})} /></div>
                            <div className="md:col-span-2 xl:col-span-4"><Input label="Catatan Item" value={line.notes} onChange={(event) => updateLine(index, { notes: event.target.value })} /></div>
                        </div>
                    </div>;
                })}</div>
                {form.errors.items && <p className="mt-4 text-sm font-bold text-red-600">{form.errors.items}</p>}
            </section>

            <section className="flex flex-col-reverse gap-3 rounded-xl border border-white/80 bg-white/80 p-4 shadow-soft dark:border-white/10 dark:bg-white/8 sm:flex-row sm:justify-end"><Button as={Link} href={indexUrl} variant="outline">Batal</Button><Button type="submit" disabled={form.processing}><Save size={16} /> {form.processing ? 'Memposting...' : 'Posting Pengambilan'}</Button></section>
        </form>
    </div></>;
}

LoanForm.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Pengambilan Barang'}>{page}</AdminLayout>;
