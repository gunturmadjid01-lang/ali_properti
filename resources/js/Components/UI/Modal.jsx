import { X } from 'lucide-react';
import Button from './Button';

export default function Modal({ open, onClose, title, children, footer, size = 'md' }) {
    const sizes = {
        sm: 'max-w-md',
        md: 'max-w-xl',
        lg: 'max-w-3xl',
        xl: 'max-w-5xl',
        full: 'h-[100vh] max-w-none w-[100vw] rounded-none',
    };

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-[80] grid place-items-center bg-graphite/55 p-4 backdrop-blur-sm">
            <div className={`max-h-[92vh] w-full ${sizes[size] ?? sizes.md} overflow-hidden border border-white/80 bg-white shadow-soft dark:border-white/10 dark:bg-graphite`}>
                <div className="flex min-h-14 items-center justify-between border-b border-silver-deep/60 px-5 dark:border-white/10">
                    <h2 className="text-lg font-extrabold text-ink dark:text-white">{title}</h2>
                    <Button variant="ghost" size="sm" type="button" onClick={onClose} aria-label="Tutup modal">
                        <X size={18} />
                    </Button>
                </div>
                <div className="max-h-[calc(92vh-8rem)] overflow-y-auto p-5 text-ink dark:text-white">{children}</div>
                {footer && <div className="flex justify-end gap-3 border-t border-silver-deep/60 p-5 dark:border-white/10">{footer}</div>}
            </div>
        </div>
    );
}
