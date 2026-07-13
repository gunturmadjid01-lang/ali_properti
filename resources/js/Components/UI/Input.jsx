import FieldLabel from "./FieldLabel";

export default function Input({
    label,
    error,
    className = "",
    inputClassName = "",
    tone = "neutral",
    icon = null,
    required = false,
    ...props
}) {
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
            <FieldLabel required={required}>{label}</FieldLabel>
            <div className="relative">
                {icon && (
                    <span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-soft">
                        {icon}
                    </span>
                )}
                <input
                    className={`min-h-11 w-full rounded-lg border border-silver-deep/70 bg-white/85 py-2.5 font-semibold text-ink outline-none ring-4 ring-transparent transition placeholder:text-ink-soft/60 dark:border-white/10 dark:bg-white/8 dark:text-white dark:placeholder:text-white/35 ${icon ? "px-10" : "px-4"} ${focus} ${inputClassName}`}
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
