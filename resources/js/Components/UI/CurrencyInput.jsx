function formatRupiah(value) {
    const raw = String(value ?? '').replace(/[^\d]/g, '');

    if (raw === '') {
        return '';
    }

    return new Intl.NumberFormat('id-ID').format(Number(raw));
}

function normalizeRupiah(value) {
    return String(value ?? '').replace(/[^\d]/g, '');
}

export default function CurrencyInput({
    label,
    error,
    className = '',
    inputClassName = '',
    tone = 'neutral',
    value,
    onChange,
    prefix = 'Rp',
    placeholder = '0',
    ...props
}) {
    const focus = tone === 'neutral' ? 'focus:border-ink-soft focus:ring-ink-soft/15' : 'focus:border-ink-soft focus:ring-ink-soft/15';
    const displayValue = formatRupiah(value);

    const handleChange = (event) => {
        const normalized = normalizeRupiah(event.target.value);

        if (typeof onChange === 'function') {
            onChange(normalized, event);
        }
    };

    return (
        <label className={`grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78 ${className}`}>
            {label && <span>{label}</span>}
            <div className="relative">
                <span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-extrabold text-ink-soft dark:text-white/45">
                    {prefix}
                </span>
                <input
                    className={`min-h-11 w-full rounded-lg border border-silver-deep/70 bg-white/85 py-2.5 pl-12 pr-4 font-semibold text-ink outline-none ring-4 ring-transparent transition placeholder:text-ink-soft/60 dark:border-white/10 dark:bg-white/8 dark:text-white dark:placeholder:text-white/35 ${focus} ${inputClassName}`}
                    inputMode="numeric"
                    placeholder={placeholder}
                    value={displayValue}
                    onChange={handleChange}
                    {...props}
                />
            </div>
            {error && <span className="text-xs font-bold text-red-600 dark:text-red-300">{error}</span>}
        </label>
    );
}
