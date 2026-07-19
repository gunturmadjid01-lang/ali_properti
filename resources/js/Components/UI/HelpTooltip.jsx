import { CircleHelp } from "lucide-react";
import { useId, useState } from "react";

export default function HelpTooltip({ text, label = "Bantuan pengisian" }) {
    const tooltipId = useId();
    const [open, setOpen] = useState(false);

    if (!text) return null;

    return (
        <span
            className="relative inline-flex shrink-0 align-middle"
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={() => setOpen(false)}
            onFocus={() => setOpen(true)}
            onBlur={() => setOpen(false)}
        >
            <button
                type="button"
                className="grid h-5 w-5 place-items-center rounded-full text-ink-soft transition hover:bg-silver hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-gold dark:text-white/50 dark:hover:bg-white/10 dark:hover:text-white"
                aria-label={label}
                aria-describedby={open ? tooltipId : undefined}
                aria-expanded={open}
                onClick={(event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    setOpen((current) => !current);
                }}
            >
                <CircleHelp size={15} strokeWidth={2.2} />
            </button>
            <span
                id={tooltipId}
                role="tooltip"
                className={`pointer-events-none absolute bottom-full left-1/2 z-[10000] mb-2 w-max max-w-72 -translate-x-1/2 rounded-lg border border-white/10 bg-graphite px-3 py-2 text-left text-xs font-semibold leading-5 text-white shadow-2xl transition duration-150 dark:bg-slate-950 ${open ? "visible translate-y-0 opacity-100" : "invisible translate-y-1 opacity-0"}`}
            >
                {text}
                <span className="absolute left-1/2 top-full -translate-x-1/2 border-4 border-transparent border-t-graphite dark:border-t-slate-950" />
            </span>
        </span>
    );
}

export function fieldHelp({
    label,
    type = "text",
    required = false,
    placeholder,
}) {
    if (!label) return null;

    const name = String(label)
        .replace(/\s*\*\s*$/, "")
        .trim();
    const action =
        type === "date"
            ? `Pilih tanggal ${name.toLowerCase()} yang sesuai.`
            : type === "file"
              ? `Pilih berkas untuk ${name.toLowerCase()} sesuai format yang diminta.`
              : type === "number"
                ? `Masukkan nilai angka untuk ${name.toLowerCase()}.`
                : type === "email"
                  ? `Masukkan alamat email yang aktif dan valid.`
                  : `Isi ${name.toLowerCase()} sesuai data yang benar.`;
    const requirement =
        required || /\*/.test(String(label))
            ? " Field ini wajib diisi."
            : " Field ini dapat dikosongkan jika tidak tersedia.";
    const example = placeholder ? ` Contoh atau petunjuk: ${placeholder}.` : "";

    return `${action}${requirement}${example}`;
}
