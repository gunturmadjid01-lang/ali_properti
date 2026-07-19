import FieldLabel from "./FieldLabel";
import { fieldHelp } from "./HelpTooltip";
import { usePage } from "@inertiajs/react";

function formatRupiah(value) {
    const raw = String(value ?? "").replace(/[^\d]/g, "");

    if (raw === "") {
        return "";
    }

    return new Intl.NumberFormat("id-ID").format(Number(raw));
}

function normalizeRupiah(value) {
    return String(value ?? "").replace(/[^\d]/g, "");
}

export default function CurrencyInput({
    label,
    error: providedError,
    className = "",
    inputClassName = "",
    tone = "neutral",
    value,
    onChange,
    prefix = "Rp",
    placeholder = "0",
    required = false,
    help,
    ...props
}) {
    const sharedErrors = usePage().props.errors ?? {};
    const error =
        providedError ?? sharedErrors[props.name] ?? sharedErrors[props.id];
    const focus =
        tone === "neutral"
            ? "focus:border-ink-soft focus:ring-ink-soft/15"
            : "focus:border-ink-soft focus:ring-ink-soft/15";
    const displayValue = formatRupiah(value);

    const handleChange = (event) => {
        const normalized = normalizeRupiah(event.target.value);

        if (typeof onChange === "function") {
            onChange(normalized, event);
        }
    };

    return (
        <label
            className={`grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78 ${className}`}
        >
            <FieldLabel
                required={required}
                help={
                    help ??
                    fieldHelp({ label, type: "number", required, placeholder })
                }
            >
                {label}
            </FieldLabel>
            <div className="relative">
                <span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-extrabold text-ink-soft dark:text-white/45">
                    {prefix}
                </span>
                <input
                    className={`min-h-11 w-full rounded-lg border bg-white/90 py-2.5 pl-12 pr-4 font-semibold text-ink outline-none transition placeholder:text-ink-soft/60 dark:bg-[#151a20] dark:text-white dark:placeholder:text-white/40 ${error ? "border-red-500 dark:border-red-400" : "border-ink/70 dark:border-white/40"} ${focus} ${inputClassName}`}
                    inputMode="numeric"
                    placeholder={placeholder}
                    value={displayValue}
                    onChange={handleChange}
                    required={required}
                    {...props}
                />
            </div>
            {error && (
                <span className="text-xs font-bold text-red-600 dark:text-red-300">
                    {error}
                </span>
            )}
        </label>
    );
}
