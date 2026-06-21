import { usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import Button from './Button';
import Modal from './Modal';

export default function RequestResponseModal() {
    const { flash } = usePage().props;
    const message = flash?.success ?? flash?.error;
    const type = flash?.success ? 'success' : 'error';
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (message) {
            setOpen(true);
        }
    }, [flash?.id, message]);

    const Icon = type === 'success' ? CheckCircle2 : AlertCircle;
    const tone = useMemo(() => (
        type === 'success'
            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200'
            : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-200'
    ), [type]);

    return (
        <Modal
            open={open && Boolean(message)}
            onClose={() => setOpen(false)}
            title={type === 'success' ? 'Berhasil' : 'Terjadi Kendala'}
            size="sm"
            footer={
                <Button type="button" onClick={() => setOpen(false)}>
                    Tutup
                </Button>
            }
        >
            <div className="flex items-start gap-4">
                <span className={`grid h-11 w-11 shrink-0 place-items-center rounded-lg ${tone}`}>
                    <Icon size={24} />
                </span>
                <p className="pt-2 text-sm font-bold leading-6 text-ink-soft dark:text-white/70">
                    {message}
                </p>
            </div>
        </Modal>
    );
}
