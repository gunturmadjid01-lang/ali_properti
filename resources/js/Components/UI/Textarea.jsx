import FieldLabel from "./FieldLabel";

export default function Textarea({
    label,
    error,
    className = "",
    textareaClassName = "",
    tone = "neutral",
    required = false,
    ...props
}) {
    const focus =
        tone === "neutral"
            ? "focus:border-ink-soft focus:ring-ink-soft/15"
            : "focus:border-ink-soft focus:ring-ink-soft/15";

    return (
        <label
            className={`grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78 ${className}`}
        >
            <FieldLabel required={required}>{label}</FieldLabel>
            <textarea
                className={`min-h-32 rounded-lg border border-silver-deep/70 bg-white/85 px-4 py-3 font-semibold text-ink outline-none ring-4 ring-transparent transition placeholder:text-ink-soft/60 dark:border-white/10 dark:bg-white/8 dark:text-white dark:placeholder:text-white/35 ${focus} ${textareaClassName}`}
                required={required}
                {...props}
            />
            {error && (
                <span className="text-xs font-bold text-red-600 dark:text-red-300">
                    {error}
                </span>
            )}
        </label>
    );
}
