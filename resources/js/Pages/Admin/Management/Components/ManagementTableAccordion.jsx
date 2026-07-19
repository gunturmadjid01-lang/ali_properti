import { Link } from '@inertiajs/react';
import { ChevronDown, Edit3, Eye, Lock, Search, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';
import { Button, Input, TableActions } from '../../../../Components/UI';
import AuditCell from '../../../../Components/UI/AuditCell';
import DetailModal from '../../../../Components/UI/DetailModal';

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

export default function ManagementTableAccordion({ title, columns, rows, filters, permissions = {}, onEdit, onDetail, onDelete, onSearch, onLock, onUnlock, extraActions, defaultOpen = true, showDetailAction = true }) {
    const [open, setOpen] = useState(defaultOpen);
    const [search, setSearch] = useState(filters.search ?? '');
    const [detail, setDetail] = useState(null);
    const submitSearch = (event) => {
        event.preventDefault();
        onSearch(search);
    };
    const displayColumns = columns.reduce((carry, column, index) => {
        if (column.key === 'updated_by' && columns[index - 1]?.key === 'created_by') {
            return carry;
        }

        if (column.key === 'created_by' && columns[index + 1]?.key === 'updated_by') {
            return [...carry, { key: 'audit', label: 'Audit', type: 'audit' }];
        }

        return [...carry, column];
    }, []);

    return (
        <>
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
                            label="Pencarian"
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
                                    {displayColumns.map((column) => (
                                        <th className="px-4 py-3 font-extrabold" key={column.key}>{column.label}</th>
                                    ))}
                                    <th className="w-28 px-4 py-3 text-right font-extrabold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr className="transition hover:bg-silver/70 dark:hover:bg-white/5" key={row.id}>
                                        {displayColumns.map((column) => (
                                            <td className="max-w-[280px] px-4 py-3 font-semibold text-ink/80 dark:text-white/72" key={column.key}>
                                                {column.type === 'audit' ? (
                                                    <AuditCell createdBy={row.created_by} updatedBy={row.updated_by} />
                                                ) : (
                                                    <span className="line-clamp-2">{row[column.key] ?? '-'}</span>
                                                )}
                                            </td>
                                        ))}
                                        <td className="px-4 py-3">
                                            <TableActions>
                                                {showDetailAction && (
                                                    <Button variant="outline" size="sm" type="button" title="Detail Data" onClick={() => onDetail ? onDetail(row) : setDetail(row)}>
                                                        <Eye size={15} />
                                                    </Button>
                                                )}
                                                {extraActions?.(row)}
                                                {row.record_status === 'locked' ? (
                                                    permissions.canUnlock && (
                                                        <Button variant="outline" size="sm" type="button" title="Buka Kunci" onClick={() => onUnlock?.(row)}>
                                                            <Unlock size={15} />
                                                        </Button>
                                                    )
                                                ) : (
                                                    <>
                                                        {permissions.canUnlock && (
                                                            <Button variant="outline" size="sm" type="button" title="Kunci Data" onClick={() => onLock?.(row)}>
                                                                <Lock size={15} />
                                                            </Button>
                                                        )}
                                                        {permissions.canUpdate && (
                                                            <Button variant="outline" size="sm" type="button" onClick={() => onEdit(row)}>
                                                                <Edit3 size={15} />
                                                            </Button>
                                                        )}
                                                        {permissions.canDelete && (
                                                            <Button variant="ghost" size="sm" type="button" className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-300 dark:hover:bg-red-500/10" onClick={() => onDelete(row)}>
                                                                <Trash2 size={15} />
                                                            </Button>
                                                        )}
                                                    </>
                                                )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50" colSpan={displayColumns.length + 1}>
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
        {!onDetail && <DetailModal
            open={Boolean(detail)}
            onClose={() => setDetail(null)}
            row={detail}
            title={detail ? `Detail ${title}` : 'Detail Data'}
            columns={columns}
        />}
        </>
    );
}
