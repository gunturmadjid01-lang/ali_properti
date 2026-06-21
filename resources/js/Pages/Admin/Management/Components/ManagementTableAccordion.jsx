import { Link } from '@inertiajs/react';
import { ChevronDown, Edit3, Lock, Search, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';
import { Button, Input } from '../../../../Components/UI';

function Pagination({ links = [] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
            {links.map((link, index) => (
                <Button
                    as={Link}
                    className={!link.url ? 'pointer-events-none opacity-45' : ''}
                    href={link.url ?? '#'}
                    key={`${link.label}-${index}`}
                    preserveScroll
                    size="sm"
                    variant={link.active ? 'dark' : 'outline'}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

export default function ManagementTableAccordion({ title, columns, rows, filters, onEdit, onDelete, onSearch, onLock, onUnlock, extraActions, defaultOpen = true }) {
    const [open, setOpen] = useState(defaultOpen);
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (event) => {
        event.preventDefault();
        onSearch(search);
    };

    return (
        <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
            <button
                className="flex min-h-14 w-full items-center justify-between gap-4 border-b border-silver-deep/60 px-5 text-left dark:border-white/10"
                type="button"
                onClick={() => setOpen(!open)}
            >
                <div>
                    <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">Tabel Data</p>
                    <h2 className="mt-0.5 text-base font-extrabold">{title}</h2>
                </div>
                <ChevronDown className={`shrink-0 text-ink-soft transition ${open ? 'rotate-180' : ''}`} size={20} />
            </button>

            {open && (
                <>
                    <form className="flex flex-col gap-3 px-5 py-3 md:flex-row md:items-end md:justify-between" onSubmit={submitSearch}>
                        <Input
                            className="w-full md:max-w-md"
                            label="Search"
                            value={search}
                            placeholder="Cari data..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Button type="submit">
                            <Search size={17} /> Cari
                        </Button>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {columns.map((column) => (
                                        <th className="px-4 py-3 font-extrabold" key={column.key}>{column.label}</th>
                                    ))}
                                    <th className="w-28 px-4 py-3 text-right font-extrabold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                        {columns.map((column) => (
                                            <td className="max-w-[280px] px-4 py-3 font-semibold text-ink/80 dark:text-white/72" key={column.key}>
                                                <span className="line-clamp-2">{row[column.key] ?? '-'}</span>
                                            </td>
                                        ))}
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                {extraActions?.(row)}
                                                {row.record_status === 'locked' ? (
                                                    <Button variant="outline" size="sm" type="button" title="Buka Lock" onClick={() => onUnlock?.(row)}>
                                                        <Unlock size={15} />
                                                    </Button>
                                                ) : (
                                                    <>
                                                        <Button variant="outline" size="sm" type="button" title="Lock Data" onClick={() => onLock?.(row)}>
                                                            <Lock size={15} />
                                                        </Button>
                                                        <Button variant="outline" size="sm" type="button" onClick={() => onEdit(row)}>
                                                            <Edit3 size={15} />
                                                        </Button>
                                                        <Button variant="ghost" size="sm" type="button" className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-300 dark:hover:bg-red-500/10" onClick={() => onDelete(row)}>
                                                            <Trash2 size={15} />
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={columns.length + 1}>
                                            Belum ada data.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination links={rows.links} />
                </>
            )}
        </section>
    );
}

