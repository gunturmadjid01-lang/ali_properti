import { router, usePage } from "@inertiajs/react";
import { AlertCircle, AlertTriangle, CheckCircle2 } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";
import Button from "./Button";
import Modal from "./Modal";

export default function RequestResponseModal() {
    const { flash, errors = {} } = usePage().props;
    const [localResponse, setLocalResponse] = useState(null);
    const pendingMutation = useRef(false);
    const message = flash?.success ?? flash?.error ?? localResponse?.message;
    const type =
        flash?.success || localResponse?.type === "success"
            ? "success"
            : "error";
    const validationErrors = useMemo(() => flattenErrors(errors), [errors]);
    const validationSignature = useMemo(
        () => JSON.stringify(validationErrors),
        [validationErrors],
    );
    const [responseOpen, setResponseOpen] = useState(false);
    const [validationOpen, setValidationOpen] = useState(false);

    useEffect(() => {
        const stopStart = router.on("start", (event) => {
            const method = String(
                event.detail.visit.method ?? "get",
            ).toLowerCase();
            pendingMutation.current = method !== "get";
            if (pendingMutation.current) setLocalResponse(null);
        });
        const stopSuccess = router.on("success", (event) => {
            if (!pendingMutation.current) return;
            pendingMutation.current = false;
            const props = event.detail.page.props ?? {};
            if (
                Object.keys(props.errors ?? {}).length > 0 ||
                props.flash?.success ||
                props.flash?.error
            )
                return;
            setLocalResponse({
                type: "success",
                message: "Data berhasil diproses dan disimpan.",
            });
            setResponseOpen(true);
        });
        const stopError = router.on("error", (event) => {
            if (!pendingMutation.current) return;
            pendingMutation.current = false;
            if (Object.keys(event.detail.errors ?? {}).length > 0) return;
            setLocalResponse({
                type: "error",
                message:
                    "Permintaan gagal diproses. Periksa data atau koneksi lalu coba kembali.",
            });
            setResponseOpen(true);
        });
        return () => {
            stopStart();
            stopSuccess();
            stopError();
        };
    }, []);

    useEffect(() => {
        if (message) {
            setResponseOpen(true);
        }
    }, [flash?.id, localResponse, message]);

    useEffect(() => {
        if (validationErrors.length > 0) {
            setValidationOpen(true);
        }
    }, [flash?.validation_id, validationSignature, validationErrors.length]);

    const Icon = type === "success" ? CheckCircle2 : AlertCircle;
    const tone = useMemo(
        () =>
            type === "success"
                ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200"
                : "bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-200",
        [type],
    );

    return (
        <>
            <Modal
                open={responseOpen && Boolean(message)}
                onClose={() => setResponseOpen(false)}
                title={type === "success" ? "Berhasil" : "Terjadi Kendala"}
                size="sm"
                footer={
                    <Button
                        type="button"
                        onClick={() => setResponseOpen(false)}
                    >
                        Tutup
                    </Button>
                }
            >
                <div className="flex items-start gap-4">
                    <span
                        className={`grid h-11 w-11 shrink-0 place-items-center rounded-lg ${tone}`}
                    >
                        <Icon size={24} />
                    </span>
                    <p className="pt-2 text-sm font-bold leading-6 text-ink-soft dark:text-white/70">
                        {message}
                    </p>
                </div>
            </Modal>
            <Modal
                open={validationOpen && validationErrors.length > 0}
                onClose={() => setValidationOpen(false)}
                title="Form Belum Dapat Disimpan"
                size="md"
                footer={
                    <Button
                        type="button"
                        onClick={() => setValidationOpen(false)}
                    >
                        Tutup dan Perbaiki
                    </Button>
                }
            >
                <div className="grid gap-4">
                    <div className="flex items-start gap-4 rounded-lg bg-amber-50 p-4 text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                        <AlertTriangle className="mt-0.5 shrink-0" size={22} />
                        <div>
                            <p className="font-extrabold">
                                Periksa kembali data yang diisi.
                            </p>
                            <p className="mt-1 text-sm leading-6">
                                Ada {validationErrors.length} validasi yang
                                perlu diperbaiki sebelum data dapat disimpan.
                            </p>
                        </div>
                    </div>
                    <ul className="max-h-72 space-y-2 overflow-y-auto pr-1">
                        {validationErrors.map((error, index) => (
                            <li
                                className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/8 dark:text-red-200"
                                key={`${error.field}-${index}`}
                            >
                                <button
                                    className="w-full text-left"
                                    type="button"
                                    onClick={() => focusField(error.field)}
                                >
                                    <span className="font-extrabold">
                                        {error.label}:
                                    </span>{" "}
                                    <span className="font-semibold">
                                        {error.message}
                                    </span>
                                    <span className="mt-1 block text-xs font-bold opacity-70">
                                        Klik untuk menuju field
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            </Modal>
        </>
    );
}

function focusField(field) {
    const candidates = [
        field,
        field.replaceAll(".", "-"),
        `field-${field.replaceAll(".", "-")}`,
        `spr-field-${field.replaceAll(".", "-")}`,
    ];
    const element = candidates
        .map((value) =>
            document.querySelector(
                `[name="${CSS.escape(value)}"], #${CSS.escape(value)}`,
            ),
        )
        .find(Boolean);
    if (!element) return;
    element.scrollIntoView({ behavior: "smooth", block: "center" });
    window.setTimeout(
        () =>
            element
                .querySelector?.("input,select,textarea,button")
                ?.focus?.() ?? element.focus?.(),
        350,
    );
}

function flattenErrors(errors, prefix = "") {
    return Object.entries(errors ?? {}).flatMap(([field, value]) => {
        const path = prefix ? `${prefix}.${field}` : field;

        if (Array.isArray(value)) {
            return value.flatMap((item, index) =>
                typeof item === "object" && item !== null
                    ? flattenErrors(item, `${path}.${index}`)
                    : [
                          {
                              field: `${path}.${index}`,
                              label: fieldLabel(path),
                              message: String(item),
                          },
                      ],
            );
        }

        if (typeof value === "object" && value !== null) {
            return flattenErrors(value, path);
        }

        if (!value) return [];

        return [
            { field: path, label: fieldLabel(path), message: String(value) },
        ];
    });
}

function fieldLabel(field) {
    const meaningfulParts = field
        .split(".")
        .filter((part) => !/^\d+$/.test(part));
    const name = meaningfulParts.at(-1) ?? field;

    return name
        .replace(/[_-]+/g, " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
