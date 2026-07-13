export default function FieldLabel({
    children,
    required = false,
    className = "",
}) {
    if (!children) return null;

    return (
        <span className={className}>
            {children}
            {required && (
                <span
                    className="ml-1 font-black text-red-600 dark:text-red-400"
                    title="Wajib diisi"
                    aria-label="wajib diisi"
                >
                    *
                </span>
            )}
        </span>
    );
}
