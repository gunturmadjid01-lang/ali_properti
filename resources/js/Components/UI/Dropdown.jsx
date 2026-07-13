import { ChevronDown, Plus, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

export default function Dropdown({ label = 'Pilih', value, options = [], onChange, onCreate, creatable = false, createLabel = 'Tambah pilihan', className = '', buttonClassName = '', menuClassName = '', searchable = true, disabled = false }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [menuStyle, setMenuStyle] = useState({});
    const wrapperRef = useRef(null);
    const buttonRef = useRef(null);
    const menuRef = useRef(null);
    const selected = options.find((option) => option.value === value);
    const filteredOptions = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        if (!keyword) {
            return options;
        }

        return options.filter((option) => String(option.label ?? '').toLowerCase().includes(keyword));
    }, [options, search]);
    const canCreate = creatable && search.trim() !== '' && !options.some((option) => String(option.label ?? '').trim().toLowerCase() === search.trim().toLowerCase());

    useEffect(() => {
        if (!open) {
            return;
        }

        const updatePosition = () => {
            if (!buttonRef.current) {
                return;
            }

            const rect = buttonRef.current.getBoundingClientRect();
            setMenuStyle({
                position: 'fixed',
                top: `${rect.bottom + 8}px`,
                left: `${rect.left}px`,
                width: `${rect.width}px`,
                zIndex: 9999,
            });
        };

        const handlePointerDown = (event) => {
            const target = event.target;
            const insideButton = wrapperRef.current?.contains(target);
            const insideMenu = menuRef.current?.contains(target);

            if (!insideButton && !insideMenu) {
                setOpen(false);
            }
        };

        updatePosition();
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);
        document.addEventListener('mousedown', handlePointerDown);

        return () => {
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', updatePosition, true);
            document.removeEventListener('mousedown', handlePointerDown);
        };
    }, [open]);

    const menu = open && !disabled ? createPortal(
        <div
            ref={menuRef}
            className={`overflow-hidden rounded-lg border border-white/80 bg-white p-1 shadow-soft dark:border-white/10 dark:bg-graphite ${menuClassName}`}
            style={menuStyle}
        >
            {searchable && (
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
                {canCreate && (
                    <button
                        className="mb-1 flex min-h-11 w-full items-center gap-2 rounded-md bg-amber-50 px-3 text-left text-sm font-extrabold text-amber-800 transition hover:bg-amber-100 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/15"
                        type="button"
                        onClick={() => {
                            const created = search.trim();
                            onCreate?.(created);
                            onChange?.(created, { value: created, label: created, created: true });
                            setOpen(false);
                            setSearch('');
                        }}
                    >
                        <Plus size={16} /> {createLabel}: “{search.trim()}”
                    </button>
                )}
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
                {filteredOptions.length === 0 && !canCreate && (
                    <p className="px-3 py-3 text-sm font-bold text-ink-soft dark:text-white/50">Pilihan tidak ditemukan.</p>
                )}
            </div>
        </div>,
        document.body,
    ) : null;

    return (
        <div ref={wrapperRef} className={`relative ${className}`}>
            <button
                ref={buttonRef}
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
            {menu}
        </div>
    );
}
