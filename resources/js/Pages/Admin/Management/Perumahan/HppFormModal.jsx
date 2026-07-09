import { useForm } from '@inertiajs/react';
import { Save, XCircle } from 'lucide-react';
import { Button, Input, ModalForm } from '../../../../Components/UI';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

export default function HppFormModal({ open, title, actionUrl, items = [], onClose }) {
    const initialItems = items.length > 0 ? items : [{
        kelompok_hpp_id: '',
        tahapan_pembangunan_id: '',
        nama_pekerjaan: '',
        volume: 1,
        satuan: 'Ls',
        harga_satuan: 0,
        urutan: 0,
    }];

    const form = useForm({
        items: initialItems.map((item) => ({
            kelompok_hpp_id: item.kelompok_hpp_id ? String(item.kelompok_hpp_id) : '',
            tahapan_pembangunan_id: item.tahapan_pembangunan_id ? String(item.tahapan_pembangunan_id) : '',
            nama_pekerjaan: item.nama_pekerjaan ?? '',
            volume: item.volume ?? 0,
            satuan: item.satuan === '-' ? '' : (item.satuan ?? ''),
            harga_satuan: item.harga_satuan ?? 0,
            urutan: item.urutan ?? 0,
        })),
    });

    const setItem = (index, key, value) => {
        form.setData('items', form.data.items.map((item, itemIndex) => (
            itemIndex === index ? { ...item, [key]: value } : item
        )));
    };

    const submit = (event) => {
        event.preventDefault();
        form.put(actionUrl, {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <ModalForm
            open={open}
            onClose={onClose}
            title={title}
            description="Isi uraian pekerjaan dan nilai rencana biayanya."
            onSubmit={submit}
            size="xl"
            actions={
                <>
                    <Button variant="outline" type="button" onClick={onClose}>
                        <XCircle size={17} /> Batal
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        <Save size={17} /> {form.processing ? 'Menyimpan...' : 'Simpan HPP'}
                    </Button>
                </>
            }
        >
            <div className="grid gap-3">
                <div className="flex items-center justify-between gap-3">
                    <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">Edit nilai HPP</span>
                </div>

                {form.data.items.map((item, index) => (
                    <div className="grid gap-3 rounded-lg border border-silver-deep/70 bg-white/70 p-3 dark:border-white/10 dark:bg-white/8 md:grid-cols-2" key={index}>
                        <Input label="Nama Pekerjaan" value={item.nama_pekerjaan} onChange={(event) => setItem(index, 'nama_pekerjaan', event.target.value)} />
                        <Input label="Volume" type="number" value={item.volume} onChange={(event) => setItem(index, 'volume', event.target.value)} />
                        <Input label="Satuan" value={item.satuan} onChange={(event) => setItem(index, 'satuan', event.target.value)} />
                        <Input label="Harga Satuan" type="number" value={item.harga_satuan} onChange={(event) => setItem(index, 'harga_satuan', event.target.value)} />
                        <div className="rounded-lg bg-silver-soft px-4 py-3 dark:bg-white/8">
                            <p className="text-xs font-extrabold text-ink-soft">Total</p>
                            <p className="mt-1 text-lg font-extrabold">{money(
                                String(item.satuan ?? '').trim() === '%'
                                    ? (Number(item.volume || 0) * Number(item.harga_satuan || 0)) / 100
                                    : Number(item.volume || 0) * Number(item.harga_satuan || 0),
                            )}</p>
                        </div>
                    </div>
                ))}
                {form.errors.items && <span className="text-xs font-bold text-red-600">{form.errors.items}</span>}
            </div>
        </ModalForm>
    );
}
