import { ChevronDown, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

export default function Dropdown({ label = 'Pilih', value, options = [], onChange, className = '', buttonClassName = '', menuClassName = '', searchable = true, disabled = false }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const selected = options.find((option) => option.value === value);
    const filteredOptions = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        if (!keyword) {
            return options;
        }

        return options.filter((option) => String(option.label ?? '').toLowerCase().includes(keyword));
    }, [options, search]);

    return (
        <div className={`relative ${className}`}>
            <button
                className={`flex min-h-11 w-full items-center justify-between gap-3 rounded-lg border border-silver-deep/70 bg-white/80 px-4 text-left text-sm font-extrabold text-ink shadow-[0_10px_28px_rgba(31,37,43,0.06)] transition hover:bg-silver dark:border-white/10 dark:bg-white/8 dark:text-white dark:hover:bg-white/12 ${buttonClassName}`}
                type="button"
                disabled={disabled}
                onClick={() => {
                    if (disabled) {
                        return;
                    }
                    setOpen(!open);
                    setSearch('');
                }}
            >
                <span>{selected?.label ?? label}</span>
                <ChevronDown className={`transition ${open ? 'rotate-180' : ''}`} size={17} />
            </button>
            {open && !disabled && (
                <div className={`absolute left-0 right-0 z-30 mt-2 overflow-hidden rounded-lg border border-white/80 bg-white p-1 shadow-soft dark:border-white/10 dark:bg-graphite ${menuClassName}`}>
                    {searchable && options.length > 0 && (
                        <label className="mb-1 flex min-h-10 items-center gap-2 rounded-md border border-silver-deep/60 bg-silver-soft px-3 text-sm font-bold text-ink-soft dark:border-white/10 dark:bg-white/8 dark:text-white/70">
                            <Search size={15} />
                            <input
                                className="w-full bg-transparent outline-none placeholder:text-ink-soft/50 dark:placeholder:text-white/35"
                                placeholder="Cari pilihan..."
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                            />
                        </label>
                    )}
                    <div className="max-h-72 overflow-y-auto">
                    {filteredOptions.map((option) => (
                        <button
                            className={`flex min-h-10 w-full items-center rounded-md px-3 text-left text-sm font-bold transition ${
                                option.value === value ? 'bg-ink text-white' : 'text-ink-soft hover:bg-silver dark:text-white/70 dark:hover:bg-white/10'
                            }`}
                            type="button"
                            onClick={() => {
                                onChange?.(option.value, option);
                                setOpen(false);
                                setSearch('');
                            }}
                            key={option.value}
                        >
                            {option.label}
                        </button>
                    ))}
                    {filteredOptions.length === 0 && (
                        <p className="px-3 py-3 text-sm font-bold text-ink-soft dark:text-white/50">Pilihan tidak ditemukan.</p>
                    )}
                    </div>
                </div>
            )}
        </div>
    );
}
