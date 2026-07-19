import HelpTooltip, { fieldHelp } from "./HelpTooltip";

export default function FieldLabel({
    children,
    required = false,
    help = null,
    className = "",
}) {
    if (!children) return null;

    return (
        <span className={`inline-flex items-center gap-1.5 ${className}`}>
            <span>{children}</span>
            {required && (
                <span
                    className="ml-1 font-black text-red-600 dark:text-red-400"
                    title="Wajib diisi"
                    aria-label="wajib diisi"
                >
                    *
                </span>
            )}
            <HelpTooltip
                text={
                    help ??
                    fieldHelp({
                        label:
                            typeof children === "string"
                                ? children
                                : "field ini",
                        required,
                    })
                }
                label={`Bantuan untuk ${typeof children === "string" ? children : "field"}`}
            />
        </span>
    );
}
