import { CurrencyInput, Dropdown, ImageUpload, Input, Textarea } from '../../../../Components/UI';

function Checkboxes({ field, value = [], error, options = [], onChange }) {
    const values = Array.isArray(value) ? value.map(String) : [];

    const toggle = (optionValue) => {
        const normalized = String(optionValue);
        const next = values.includes(normalized)
            ? values.filter((item) => item !== normalized)
            : [...values, normalized];

        onChange(field.name, next);
    };

    return (
        <div className="grid gap-2">
            <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">{field.label}</span>
            <div className="grid gap-2 rounded-lg border border-silver-deep/70 bg-white/70 p-3 dark:border-white/10 dark:bg-white/8 md:grid-cols-2">
                {options.map((option) => (
                    <label className="flex min-h-10 items-center gap-3 rounded-md px-2 text-sm font-bold text-ink-soft hover:bg-silver dark:text-white/70 dark:hover:bg-white/10" key={option.value}>
                        <input
                            checked={values.includes(String(option.value))}
                            className="h-4 w-4 rounded border-silver-deep text-ink-soft"
                            type="checkbox"
                            onChange={() => toggle(option.value)}
                        />
                        <span>{option.label}</span>
                    </label>
                ))}
                {options.length === 0 && <p className="text-sm font-bold text-ink-soft dark:text-white/50">Belum ada pilihan.</p>}
            </div>
            {error && <span className="text-xs font-bold text-red-600 dark:text-red-300">{error}</span>}
        </div>
    );
}

export default function FieldRenderer({ field, value, error, options, onChange }) {
    const fieldOptions = options[field.optionsKey] ?? field.options ?? [];

    if (field.type === 'textarea') {
        return (
            <Textarea
                label={field.label}
                value={value ?? ''}
                error={error}
                placeholder={field.placeholder ?? field.label}
                onChange={(event) => onChange(field.name, event.target.value)}
            />
        );
    }

    if (field.type === 'select') {
        return (
            <div className="grid gap-2">
                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">{field.label}</span>
                <Dropdown
                    value={value === null || value === undefined ? '' : String(value)}
                    label={field.placeholder ?? `Pilih ${field.label}`}
                    options={fieldOptions}
                    onChange={(selected) => onChange(field.name, selected)}
                />
                {error && <span className="text-xs font-bold text-red-600 dark:text-red-300">{error}</span>}
            </div>
        );
    }

    if (field.type === 'checkboxes') {
        return <Checkboxes field={field} value={value} error={error} options={fieldOptions} onChange={onChange} />;
    }

    if (field.type === 'image') {
        return (
            <ImageUpload
                label={field.label}
                value={value}
                error={error}
                previewLabel={field.previewLabel ?? field.label}
                onChange={(file) => onChange(field.name, file)}
            />
        );
    }

    if (field.type === 'currency') {
        return (
            <CurrencyInput
                label={field.label}
                value={value ?? ''}
                error={error}
                placeholder={field.placeholder ?? '0'}
                onChange={(nextValue) => onChange(field.name, nextValue)}
            />
        );
    }

    return (
        <Input
            label={field.label}
            type={field.type ?? 'text'}
            value={value ?? ''}
            error={error}
            placeholder={field.placeholder ?? field.label}
            onChange={(event) => onChange(field.name, event.target.value)}
        />
    );
}

