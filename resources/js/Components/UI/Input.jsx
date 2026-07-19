import FieldLabel from "./FieldLabel";
import { fieldHelp } from "./HelpTooltip";
import { usePage } from "@inertiajs/react";

export default function Input({
    label,
    error: providedError,
    className = "",
    inputClassName = "",
    tone = "neutral",
    icon = null,
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
    const isNumberInput = props.type === "number";
    const step = props.step ?? (isNumberInput ? 1 : undefined);

    return (
        <label
            className={`grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78 ${className}`}
        >
            <FieldLabel
                required={required}
                help={
                    help ??
                    fieldHelp({
                        label,
                        type: props.type,
                        required,
                        placeholder: props.placeholder,
                    })
                }
            >
                {label}
            </FieldLabel>
            <div className="relative">
                {icon && (
                    <span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-soft">
                        {icon}
                    </span>
                )}
                <input
                    className={`min-h-11 w-full rounded-lg border bg-white/90 py-2.5 font-semibold text-ink outline-none transition placeholder:text-ink-soft/60 dark:bg-[#151a20] dark:text-white dark:placeholder:text-white/40 ${error ? "border-red-500 dark:border-red-400" : "border-ink/70 dark:border-white/40"} ${icon ? "px-10" : "px-4"} ${focus} ${inputClassName}`}
                    step={step}
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
