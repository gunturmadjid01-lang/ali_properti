import FieldLabel from "./FieldLabel";
import { fieldHelp } from "./HelpTooltip";
import { usePage } from "@inertiajs/react";

export default function Textarea({
    label,
    error: providedError,
    className = "",
    textareaClassName = "",
    tone = "neutral",
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
                        type: "textarea",
                        required,
                        placeholder: props.placeholder,
                    })
                }
            >
                {label}
            </FieldLabel>
            <textarea
                className={`min-h-32 rounded-lg border bg-white/90 px-4 py-3 font-semibold text-ink outline-none transition placeholder:text-ink-soft/60 dark:bg-[#151a20] dark:text-white dark:placeholder:text-white/40 ${error ? "border-red-500 dark:border-red-400" : "border-ink/70 dark:border-white/40"} ${focus} ${textareaClassName}`}
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
