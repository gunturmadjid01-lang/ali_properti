import { ImagePlus, Trash2 } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import Button from "./Button";
import FieldLabel from "./FieldLabel";
import { fieldHelp } from "./HelpTooltip";

function imageUrl(value) {
    if (!value || typeof value !== "string") {
        return null;
    }

    if (
        value.startsWith("http://") ||
        value.startsWith("https://") ||
        value.startsWith("/") ||
        value.startsWith("blob:")
    ) {
        return value;
    }

    return `/media/${value}`;
}

export default function ImageUpload({
    label,
    value,
    error,
    previewLabel = "Pratinjau image",
    onChange,
    required = false,
    help,
}) {
    const [objectUrl, setObjectUrl] = useState(null);
    const existingPreview = useMemo(() => imageUrl(value), [value]);
    const preview = objectUrl ?? existingPreview;

    useEffect(() => {
        if (!(value instanceof File)) {
            setObjectUrl(null);
            return undefined;
        }

        const nextUrl = URL.createObjectURL(value);
        setObjectUrl(nextUrl);

        return () => URL.revokeObjectURL(nextUrl);
    }, [value]);

    const chooseFile = (event) => {
        const file = event.target.files?.[0] ?? null;
        onChange(file);
    };

    return (
        <div className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
            <FieldLabel
                required={required}
                help={help ?? fieldHelp({ label, type: "file", required })}
            >
                {label}
            </FieldLabel>
            <label className="group grid min-h-44 cursor-pointer place-items-center overflow-hidden rounded-lg border border-dashed border-silver-deep bg-white/80 text-center transition hover:border-ink-soft hover:bg-silver dark:border-white/15 dark:bg-white/8 dark:hover:bg-white/10">
                {preview ? (
                    <img
                        className="h-44 w-full object-cover"
                        src={preview}
                        alt={previewLabel}
                    />
                ) : (
                    <span className="grid place-items-center gap-3 px-4 text-ink-soft dark:text-white/55">
                        <ImagePlus className="text-ink-soft" size={34} />
                        <span>Pilih gambar</span>
                    </span>
                )}
                <input
                    className="sr-only"
                    accept="image/*"
                    type="file"
                    required={required && !preview}
                    onChange={chooseFile}
                />
            </label>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="text-xs font-bold text-ink-soft dark:text-white/45">
                    JPG, PNG, WEBP maksimal 2MB.
                </span>
                {preview && (
                    <Button
                        size="sm"
                        variant="ghost"
                        type="button"
                        className="text-red-600 dark:text-red-300"
                        onClick={() => onChange("")}
                    >
                        <Trash2 size={15} /> Hapus
                    </Button>
                )}
            </div>
            {error && (
                <span className="text-xs font-bold text-red-600 dark:text-red-300">
                    {error}
                </span>
            )}
        </div>
    );
}
