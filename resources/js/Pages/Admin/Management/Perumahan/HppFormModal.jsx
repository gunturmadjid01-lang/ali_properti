import { useForm } from '@inertiajs/react';
import { Save, XCircle } from 'lucide-react';
import { useMemo } from 'react';
import { Button, Input, ModalForm } from '../../../../Components/UI';

function money(value) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

export default function HppFormModal({ open, title, actionUrl, items = [], onClose }) {
    const form = useForm({
        items: items.map((item) => ({
            kelompok_hpp_id: item.kelompok_hpp_id ? String(item.kelompok_hpp_id) : '',
            kelompok_hpp_nama: item.kelompok_hpp_nama ?? '-',
            volume: item.volume ?? 0,
            satuan: item.satuan === '-' ? '' : (item.satuan ?? ''),
            harga_satuan: item.harga_satuan ?? 0,
        })),
    });

    const total = useMemo(() => form.data.items.reduce((sum, item) => {
        return sum + (Number(item.volume || 0) * Number(item.harga_satuan || 0));
    }, 0), [form.data.items]);

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
            description="Atur rencana biaya HPP berdasarkan kelompok biaya, volume, satuan, dan harga satuan."
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
                    <div className="grid gap-3 rounded-lg border border-silver-deep/70 bg-white/70 p-3 dark:border-white/10 dark:bg-white/8 md:grid-cols-[1.4fr_0.6fr_0.6fr_0.8fr]" key={item.kelompok_hpp_id || index}>
                        <div className="grid gap-2">
                            <span className="text-xs font-extrabold text-ink-soft">Kelompok HPP</span>
                            <div className="flex min-h-11 items-center rounded-lg border border-silver-deep/70 bg-silver-soft px-4 text-sm font-extrabold text-ink dark:border-white/10 dark:bg-white/8 dark:text-white">
                                {item.kelompok_hpp_nama}
                            </div>
                        </div>
                        <Input label="Volume" type="number" value={item.volume} onChange={(event) => setItem(index, 'volume', event.target.value)} />
                        <Input label="Satuan" value={item.satuan} onChange={(event) => setItem(index, 'satuan', event.target.value)} />
                        <Input label="Harga" type="number" value={item.harga_satuan} onChange={(event) => setItem(index, 'harga_satuan', event.target.value)} />
                    </div>
                ))}
                {form.errors.items && <span className="text-xs font-bold text-red-600">{form.errors.items}</span>}
                <div className="flex justify-end rounded-lg bg-silver-soft px-4 py-3 text-lg font-extrabold dark:bg-white/8">
                    Total RAB: {money(total)}
                </div>
            </div>
        </ModalForm>
    );
}
