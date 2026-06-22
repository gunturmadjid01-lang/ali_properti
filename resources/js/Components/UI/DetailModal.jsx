import Button from './Button';
import Modal from './Modal';

function formatValue(value) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (Array.isArray(value)) {
        return value.length ? `${value.length} data` : '-';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

export default function DetailModal({ open, onClose, title = 'Detail Data', row, columns = [] }) {
    const visibleColumns = columns.length
        ? columns
        : Object.keys(row ?? {}).map((key) => ({ key, label: key.replaceAll('_', ' ') }));

    return (
        <Modal open={open} onClose={onClose} title={title} size="xl" footer={<Button type="button" variant="outline" onClick={onClose}>Tutup</Button>}>
            {row && (
                <div className="grid gap-3 md:grid-cols-2">
                    {visibleColumns.map((column) => (
                        <div className={column.full ? 'md:col-span-2' : ''} key={column.key}>
                            <p className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">{column.label}</p>
                            <p className="mt-1 whitespace-pre-line break-words text-sm font-bold text-ink dark:text-white">
                                {formatValue(column.render ? column.render(row) : row[column.key])}
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </Modal>
    );
}
