import { Head, Link, router, useForm } from "@inertiajs/react";
import { AlertCircle, ArrowLeft, CheckCircle2, FileText, Printer } from "lucide-react";
import { useState } from "react";
import { Button, CurrencyInput, HelpTooltip, Modal } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

export default function ProcessForm({ title, backUrl, transaction, step }) {
    const [finalizeResponse, setFinalizeResponse] = useState(null);
    const fieldHelp = {
        grace_days:
            "Jumlah hari setelah jatuh tempo sebelum denda mulai dihitung. Nilainya berasal dari master skema.",
        penalty_terms:
            "Ringkasan metode dan nilai denda dari master skema; bukan diisi ulang pada transaksi.",
        early_settlement_terms:
            "Aturan jika pelanggan ingin melunasi sebelum seluruh termin jatuh tempo.",
        cancellation_terms:
            "Aturan pembatalan kontrak, penalti, refund, dan pelepasan unit.",
        received_date:
            "Tanggal ketika seluruh atau sebagian berkas persyaratan diterima untuk diperiksa pada tahap ini.",
        validator:
            "Petugas yang bertanggung jawab memeriksa kelengkapan dan kesesuaian dokumen pada tahap ini.",
        validation_result:
            "Kesimpulan pemeriksaan dokumen. Pilih Perlu Revisi bila ada kekurangan yang masih dapat diperbaiki.",
        revision_deadline:
            "Batas waktu customer atau marketing menyerahkan perbaikan dokumen. Diisi jika hasil validasi memerlukan revisi.",
        validation_notes:
            "Tuliskan dokumen yang kurang, tidak sesuai, kedaluwarsa, atau tindakan lanjutan yang harus dilakukan.",
    };
    const helpFor = (field) =>
        fieldHelp[field.name] ??
        {
            date: "Pilih tanggal kejadian sesuai bukti atau catatan operasional.",
            datetime: "Isi tanggal dan waktu aktual kejadian.",
            currency:
                "Isi nilai Rupiah sesuai dokumen sumber; jangan memasukkan tanda baca manual.",
            number: "Isi angka sesuai hasil pemeriksaan atau dokumen sumber.",
            select: "Pilih status yang paling sesuai dengan kondisi aktual.",
            boolean: "Pilih Ya atau Tidak berdasarkan hasil pemeriksaan.",
            textarea:
                "Tuliskan uraian yang cukup agar dapat dipahami reviewer tanpa penjelasan tambahan.",
        }[field.type] ??
        `Isi ${field.label.toLowerCase()} sesuai kondisi dan bukti pada tahap ini.`;
    const HelpToggle = ({ text }) => <HelpTooltip text={text} />;
    const checklist = Object.fromEntries(
        (step.checklist ?? []).map((item) => [item.key, item.completed]),
    );
    const form = useForm({
        assigned_to: step.assigned_to || "",
        planned_date: step.planned_date || "",
        actual_date: step.actual_date || "",
        notes: step.notes || "",
        metadata: step.metadata || {},
    });
    const checklistForm = useForm({ checklist });
    const document = useForm({
        document_type: step.document_types?.[0]?.type || "",
        document_number: "",
        document_date: "",
        expires_at: "",
        notes: "",
        file: null,
    });
    const setMeta = (name, value) =>
        form.setData("metadata", { ...form.data.metadata, [name]: value });
    const displayValue = (field, value) => {
        if (value === null || value === undefined || value === "")
            return "Belum diisi";
        if (field.type === "currency")
            return `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
        if (field.type === "boolean")
            return String(value) === "1" || value === true ? "Ya" : "Tidak";
        if (field.type === "select") return field.options?.[value] ?? value;
        if (field.type === "date")
            return new Date(`${value}T00:00:00`).toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "long",
                year: "numeric",
            });
        if (field.type === "datetime")
            return new Date(value).toLocaleString("id-ID", {
                day: "2-digit",
                month: "long",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
            });
        return String(value);
    };
    const save = (event) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: "put" }));
        form.post(`/admin/penjualan-terintegrasi/tahapan/${step.id}`, {
            forceFormData: true,
            onFinish: () => form.transform((data) => data),
        });
    };
    const finalize = () => {
        setFinalizeResponse(null);
        const payload = {
            ...form.data,
            checklist: checklistForm.data.checklist,
            _method: "put",
            finalize: true,
        };
        router.post(`/admin/penjualan-terintegrasi/tahapan/${step.id}`, payload, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                setFinalizeResponse({
                    type: "validation",
                    title: "Tahap Belum Dapat Difinalisasi",
                    message: "Lengkapi bagian berikut sebelum mengajukan finalisasi.",
                    errors: Object.values(errors ?? {}),
                });
            },
            onSuccess: (page) => {
                const error = page?.props?.flash?.error;
                const successMessage = page?.props?.flash?.success ?? "Tahap berhasil difinalisasi dan diajukan ke approval.";
                if (error) {
                    setFinalizeResponse({ type: "error", title: "Finalisasi Gagal", message: error, errors: [] });
                    return;
                }

                router.reload({
                    only: ["step"],
                    preserveScroll: true,
                    onSuccess: (refreshedPage) => {
                        const refreshedStep = refreshedPage?.props?.step;
                        const isLocked = refreshedStep?.record_status === "locked" || refreshedStep?.status === "completed";
                        setFinalizeResponse({
                            type: isLocked ? "success" : "error",
                            title: isLocked ? "Finalisasi Berhasil" : "Finalisasi Belum Tersimpan",
                            message: isLocked
                                ? successMessage
                                : `Status tahap masih ${refreshedStep?.record_status ?? "tidak diketahui"}. Data belum dikunci dan masih dapat diedit.`,
                            errors: [],
                        });
                    },
                });
            },
        });
    };
    const saveChecklist = (event) => {
        event.preventDefault();
        checklistForm.put(
            `/admin/penjualan-terintegrasi/tahapan/${step.id}/checklist`,
            { preserveScroll: true },
        );
    };
    const upload = (event) => {
        event.preventDefault();
        document.clearErrors();
        document.post(
            `/admin/penjualan-terintegrasi/tahapan/${step.id}/dokumen`,
            {
                forceFormData: true,
                preserveScroll: true,
                showProgress: false,
                onSuccess: () =>
                    document.reset(
                        "document_number",
                        "document_date",
                        "expires_at",
                        "notes",
                        "file",
                    ),
            },
        );
    };
    const input = (field) => {
        const rawValue = form.data.metadata[field.name] ?? "";
        const value = field.type === "datetime"
            ? String(rawValue).replace(" ", "T").slice(0, 16)
            : field.type === "date"
              ? String(rawValue).slice(0, 10)
              : rawValue;
        const automatic = !!step.sources?.[field.name];
        if (field.type === "currency")
            return (
                <CurrencyInput
                    label=""
                    disabled={automatic}
                    inputClassName="disabled:bg-slate-100"
                    value={value}
                    onChange={(next) => setMeta(field.name, next)}
                />
            );
        if (field.type === "textarea")
            return (
                <textarea
                    disabled={automatic}
                    className="mt-1 min-h-24 w-full rounded-lg border p-3 disabled:bg-slate-100"
                    value={value}
                    onChange={(e) => setMeta(field.name, e.target.value)}
                />
            );
        if (field.type === "select")
            return (
                <select
                    disabled={automatic}
                    className="mt-1 w-full rounded-lg border p-3 disabled:bg-slate-100"
                    value={value}
                    onChange={(e) => setMeta(field.name, e.target.value)}
                >
                    <option value="">Pilih</option>
                    {Object.entries(field.options ?? {}).map(([v, l]) => (
                        <option value={v} key={v}>
                            {l}
                        </option>
                    ))}
                </select>
            );
        if (field.type === "boolean")
            return (
                <select
                    disabled={automatic}
                    className="mt-1 w-full rounded-lg border p-3 disabled:bg-slate-100"
                    value={value}
                    onChange={(e) => setMeta(field.name, e.target.value)}
                >
                    <option value="">Pilih</option>
                    <option value="1">Ya</option>
                    <option value="0">Tidak</option>
                </select>
            );
        return (
            <input
                disabled={automatic}
                className="mt-1 w-full rounded-lg border p-3 disabled:bg-slate-100"
                type={field.type === "datetime" ? "datetime-local" : field.type === "number" ? "number" : field.type}
                value={value}
                onChange={(e) => setMeta(field.name, e.target.value)}
            />
        );
    };
    return (
        <>
            <Head title={title} />
            <Modal
                open={Boolean(finalizeResponse)}
                onClose={() => setFinalizeResponse(null)}
                title={finalizeResponse?.title}
                size="sm"
                footer={<Button type="button" onClick={() => setFinalizeResponse(null)}>{finalizeResponse?.type === "success" ? "Tutup" : "Tutup dan Perbaiki"}</Button>}
            >
                <div className="grid gap-4">
                    <div className={`flex items-start gap-3 rounded-lg p-4 ${finalizeResponse?.type === "success" ? "bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200" : "bg-red-50 text-red-800 dark:bg-red-500/10 dark:text-red-200"}`}>
                        {finalizeResponse?.type === "success" ? <CheckCircle2 className="mt-0.5 shrink-0" size={22} /> : <AlertCircle className="mt-0.5 shrink-0" size={22} />}
                        <p className="text-sm font-bold leading-6">{finalizeResponse?.message}</p>
                    </div>
                    {(finalizeResponse?.errors ?? []).length > 0 && (
                        <div className="grid gap-2">
                            {finalizeResponse.errors.map((error, index) => (
                                <div className="rounded-lg border border-red-200 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-500/20 dark:text-red-300" key={`${error}-${index}`}>{error}</div>
                            ))}
                        </div>
                    )}
                </div>
            </Modal>
            <div className="grid gap-6">
                <section className="rounded-xl border bg-white/80 p-6 shadow-soft">
                    <Button as={Link} href={backUrl} variant="outline">
                        <ArrowLeft size={16} /> Kembali ke Detail Transaksi
                    </Button>
                    <p className="mt-5 text-xs font-black uppercase tracking-wider text-ink-soft">
                        Tahap {step.sequence} · {step.category}
                    </p>
                    <h1 className="mt-2 text-3xl font-black">{step.label}</h1>
                    <p className="mt-1 text-ink-soft">
                        {transaction.number} · {transaction.customer} ·{" "}
                        {transaction.housing} / {transaction.unit}
                    </p>
                    <p className="mt-3">{step.description}</p>
                    <div className="mt-5 grid gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <span className="text-[11px] font-black uppercase tracking-wider text-sky-700">
                                Metode Pembayaran
                            </span>
                            <p className="mt-1 font-black">
                                {transaction.method || "-"}
                            </p>
                        </div>
                        <div>
                            <span className="text-[11px] font-black uppercase tracking-wider text-sky-700">
                                Bank / Skema / Produk
                            </span>
                            <p className="mt-1 font-black">
                                {transaction.method_summary?.product ||
                                    transaction.method_summary
                                        ?.developer_product ||
                                    transaction.method_summary?.cash_scheme ||
                                    transaction.method_summary?.bank ||
                                    "-"}
                            </p>
                            {transaction.method_summary?.branch && (
                                <small className="text-ink-soft">
                                    {transaction.method_summary.branch}
                                </small>
                            )}
                        </div>
                        <div>
                            <span className="text-[11px] font-black uppercase tracking-wider text-sky-700">
                                Tenor / Jumlah Termin
                            </span>
                            <p className="mt-1 font-black">
                                {transaction.method_summary?.tenor
                                    ? `${transaction.method_summary.tenor} bulan/termin`
                                    : "-"}
                            </p>
                        </div>
                        <div>
                            <span className="text-[11px] font-black uppercase tracking-wider text-sky-700">
                                Nilai Pembiayaan / Kontrak
                            </span>
                            <p className="mt-1 font-black">
                                {transaction.method_summary?.financing ||
                                    transaction.price}
                            </p>
                        </div>
                    </div>
                    <div className="mt-4 flex gap-3">
                        <b>{step.status_label}</b>
                        {step.approval_stage && (
                            <span className="text-blue-700">
                                {step.approval_stage}
                            </span>
                        )}
                    </div>
                </section>
                {step.status === "waiting" && (
                    <section className="rounded-xl border border-dashed bg-white/80 p-8 text-center">
                        <h2 className="font-black">
                            Tahap belum dapat dikerjakan
                        </h2>
                        <p className="mt-2 text-ink-soft">
                            Selesaikan dan setujui tahap sebelumnya terlebih
                            dahulu. Persyaratan tahap ini tetap dapat ditinjau
                            di bawah.
                        </p>
                    </section>
                )}
                <section className="rounded-xl border bg-white/80 p-6 shadow-soft">
                    <h2 className="text-xl font-black">
                        Data Pelanggan dan Unit
                    </h2>
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            ["SPR", transaction.spr],
                            ["Pelanggan", transaction.customer],
                            ["No. Identitas", transaction.identity],
                            ["Kontak", transaction.phone],
                            ["Perumahan", transaction.housing],
                            ["Unit Rumah", transaction.unit],
                            ["Metode Pembayaran", transaction.method],
                            ["Harga Transaksi", transaction.price],
                        ].map(([label, value]) => (
                            <div className="rounded-lg border p-3" key={label}>
                                <span className="text-xs font-bold uppercase text-ink-soft">
                                    {label}
                                </span>
                                <p className="mt-1 font-bold">{value || "-"}</p>
                            </div>
                        ))}
                    </div>
                    {!!transaction.existing_documents?.length && (
                        <div className="mt-5">
                            <h3 className="font-black">
                                Dokumen Pelanggan yang Sudah Ada di SPR
                            </h3>
                            <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                {transaction.existing_documents.map(
                                    (doc, index) => (
                                        <div
                                            className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-400/30 dark:bg-emerald-400/10"
                                            key={`${doc.label}-${index}`}
                                        >
                                            <b>{doc.label}</b>
                                            <p className="text-xs text-ink-soft">
                                                {doc.name}
                                            </p>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                    {!!transaction.document_requirements?.length && (
                        <div className="mt-5">
                            <h3 className="font-black">
                                Persyaratan Dokumen Tahap Ini
                            </h3>
                            <p className="mt-1 text-sm text-ink-soft">
                                Checklist ini digunakan marketing untuk
                                memastikan berkas persyaratan sudah tersedia.
                                Tidak ada kewajiban upload pada tahap ini.
                            </p>
                            <div className="mt-3 grid gap-2 md:grid-cols-2">
                                {transaction.document_requirements.map(
                                    (doc) => (
                                        <div
                                            className={`rounded-lg border p-3 ${doc.uploaded ? "border-emerald-300 bg-emerald-50 dark:border-emerald-400/30 dark:bg-emerald-400/10" : doc.required ? "border-red-300 bg-red-50 dark:border-red-400/30 dark:bg-red-400/10" : "bg-slate-50 dark:border-white/10 dark:bg-white/5"}`}
                                            key={`${doc.document_id}-${doc.party_scope}`}
                                        >
                                            <div className="flex justify-between gap-3">
                                                <b>{doc.label}</b>
                                                <span className="text-xs font-black">
                                                    {doc.complete
                                                        ? "LENGKAP"
                                                        : doc.required
                                                          ? "WAJIB - BELUM LENGKAP"
                                                          : "OPSIONAL"}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-xs text-ink-soft">
                                                Untuk: {doc.party_scope} ·
                                                Sumber: {doc.source}
                                            </p>
                                            {step.can_edit && (
                                                <label className="mt-3 flex cursor-pointer items-center gap-2 rounded-lg border bg-white p-3 font-bold">
                                                    <input
                                                        type="checkbox"
                                                        checked={Boolean(
                                                            doc.complete,
                                                        )}
                                                        onChange={(event) =>
                                                            router.post(
                                                                `/admin/penjualan-terintegrasi/tahapan/${step.id}/checklist-dokumen`,
                                                                {
                                                                    requirement_item_id:
                                                                        doc.requirement_item_id,
                                                                    is_complete:
                                                                        event
                                                                            .target
                                                                            .checked,
                                                                    notes:
                                                                        doc.check_notes ??
                                                                        null,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    />{" "}
                                                    Berkas sudah tersedia /
                                                    lengkap
                                                </label>
                                            )}
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                </section>
                <section className="rounded-xl border bg-white/80 p-6 shadow-soft">
                    <h2 className="text-xl font-black">Data Pelaksanaan</h2>
                    <p className="mt-2 text-sm text-ink-soft">
                        Nilai bertanda <b>Otomatis</b> diambil dari SPR,
                        transaksi, atau master metode pembayaran. Anda cukup
                        memeriksa dan melengkapi data operasional yang belum
                        tersedia.
                    </p>
                    {step.can_edit ? (
                        <form
                            onSubmit={save}
                            className="mt-5 grid gap-4 md:grid-cols-2"
                        >
                            <label>
                                <span className="flex items-start">
                                    PIC / Penanggung Jawab Tahap{" "}
                                    <HelpToggle
                                        id="assigned_to"
                                        text="Pilih petugas yang bertanggung jawab mengerjakan dan menindaklanjuti tahap ini."
                                    />
                                </span>
                                <select
                                    className="mt-1 w-full rounded-lg border p-3"
                                    value={form.data.assigned_to}
                                    onChange={(e) =>
                                        form.setData(
                                            "assigned_to",
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Pilih PIC</option>
                                    {(step.assignees ?? []).map((x) => (
                                        <option value={x.value} key={x.value}>
                                            {x.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                <span className="flex items-start">
                                    Tanggal Rencana Penyelesaian Tahap{" "}
                                    <HelpToggle
                                        id="planned_date"
                                        text="Target tanggal tahap ini direncanakan selesai. Digunakan untuk pemantauan keterlambatan."
                                    />
                                </span>
                                <input
                                    className="mt-1 w-full rounded-lg border p-3"
                                    type="date"
                                    value={form.data.planned_date}
                                    onChange={(e) =>
                                        form.setData(
                                            "planned_date",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label>
                                <span className="flex items-start">
                                    Tanggal Realisasi Tahap{" "}
                                    <HelpToggle
                                        id="actual_date"
                                        text="Tanggal tahap benar-benar dilaksanakan atau diselesaikan berdasarkan kondisi aktual."
                                    />
                                </span>
                                <input
                                    className="mt-1 w-full rounded-lg border p-3"
                                    type="date"
                                    value={form.data.actual_date}
                                    onChange={(e) =>
                                        form.setData(
                                            "actual_date",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            {(step.fields ?? []).map((field) => (
                                <label
                                    className={
                                        field.type === "textarea"
                                            ? "md:col-span-2"
                                            : ""
                                    }
                                    key={field.name}
                                >
                                    <span className="flex items-center gap-2">
                                        {field.label}
                                        {field.required && (
                                            <b className="text-red-600"> *</b>
                                        )}
                                        {step.sources?.[field.name] && (
                                            <small className="rounded-full bg-blue-100 px-2 py-0.5 font-bold text-blue-700">
                                                Otomatis
                                            </small>
                                        )}
                                        <HelpToggle
                                            id={`field-${field.name}`}
                                            text={helpFor(field)}
                                        />
                                    </span>
                                    {input(field)}
                                    {fieldHelp[field.name] && (
                                        <small className="block text-ink-soft">
                                            {fieldHelp[field.name]}
                                        </small>
                                    )}
                                    {step.sources?.[field.name] && (
                                        <small className="text-ink-soft">
                                            {step.sources[field.name]}
                                        </small>
                                    )}
                                </label>
                            ))}
                            <label className="md:col-span-2">
                                <span className="flex items-start">
                                    Catatan Pelaksanaan Tahap{" "}
                                    <b className="ml-1 text-red-600">*</b>
                                    <HelpToggle
                                        id="notes"
                                        text="Tuliskan ringkasan pekerjaan, hasil pemeriksaan, kendala, dan tindak lanjut tahap. Catatan akan dibaca reviewer saat approval."
                                    />
                                </span>
                                <textarea
                                    className="mt-1 min-h-24 w-full rounded-lg border p-3"
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData("notes", e.target.value)
                                    }
                                />
                            </label>
                            {Object.values(form.errors).map((error, i) => (
                                <p
                                    className="text-sm text-red-600 md:col-span-2"
                                    key={i}
                                >
                                    {error}
                                </p>
                            ))}
                            <div className="md:col-span-2">
                                <Button disabled={form.processing}>
                                    Simpan Data Pelaksanaan
                                </Button>
                            </div>
                        </form>
                    ) : (
                        <div className="mt-5 grid gap-6">
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {[
                                    ["PIC / Penanggung Jawab", step.assignee],
                                    [
                                        "Tanggal Rencana Penyelesaian",
                                        step.planned_date,
                                    ],
                                    [
                                        "Tanggal Realisasi Tahap",
                                        step.actual_date,
                                    ],
                                ].map(([label, value]) => (
                                    <div
                                        className="rounded-xl border bg-slate-50 p-4"
                                        key={label}
                                    >
                                        <span className="text-xs font-black uppercase tracking-wide text-ink-soft">
                                            {label}
                                        </span>
                                        <p className="mt-2 text-base font-black">
                                            {value || "Belum ditentukan"}
                                        </p>
                                    </div>
                                ))}
                            </div>

                            <div>
                                <h3 className="font-black">
                                    Rincian Hasil Pelaksanaan
                                </h3>
                                <dl className="mt-4 grid gap-x-10 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                                    {(step.fields ?? []).map((field) => (
                                        <div
                                            className={`${field.type === "textarea" ? "sm:col-span-2 lg:col-span-3" : ""}`}
                                            key={field.name}
                                        >
                                            <dt className="flex flex-wrap items-center gap-2 text-[11px] font-black uppercase tracking-wider text-ink-soft">
                                                {field.label}
                                                {step.sources?.[field.name] && (
                                                    <small className="rounded-full bg-blue-100 px-2 py-0.5 font-bold text-blue-700">
                                                        Otomatis
                                                    </small>
                                                )}
                                            </dt>
                                            <dd className="mt-1 min-w-0 whitespace-pre-wrap text-base font-black leading-6">
                                                {displayValue(
                                                    field,
                                                    step.metadata?.[field.name],
                                                )}
                                                {step.sources?.[field.name] && (
                                                    <small className="mt-0.5 block text-xs font-semibold text-ink-soft">
                                                        {
                                                            step.sources[
                                                                field.name
                                                            ]
                                                        }
                                                    </small>
                                                )}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>

                            <div>
                                <h3 className="font-black">
                                    Checklist Kelengkapan Dokumen & Prasyarat
                                </h3>
                                <div className="mt-3 grid gap-2 md:grid-cols-2">
                                    {(step.checklist ?? []).map((item) => (
                                        <div
                                            className={`flex items-start justify-between gap-3 rounded-xl border p-4 ${item.completed ? "border-emerald-200 bg-emerald-50" : item.required ? "border-red-200 bg-red-50" : "bg-slate-50"}`}
                                            key={item.key}
                                        >
                                            <span className="font-bold">
                                                {item.label}
                                                {item.required && (
                                                    <b className="text-red-600">
                                                        {" "}
                                                        *
                                                    </b>
                                                )}
                                            </span>
                                            <span
                                                className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-black ${item.completed ? "bg-emerald-600 text-white" : "bg-slate-200 text-slate-700"}`}
                                            >
                                                {item.completed
                                                    ? "LENGKAP"
                                                    : "BELUM"}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="rounded-xl border bg-slate-50 p-5">
                                <span className="text-xs font-black uppercase tracking-wide text-ink-soft">
                                    Catatan Pelaksanaan Tahap
                                </span>
                                <p className="mt-2 whitespace-pre-wrap font-semibold leading-7">
                                    {step.notes || "Tidak ada catatan."}
                                </p>
                            </div>
                        </div>
                    )}
                </section>
                {step.can_edit && (
                    <section className="rounded-xl border bg-white/80 p-6 shadow-soft">
                        <h2 className="text-xl font-black">
                            Checklist Kelengkapan Dokumen & Prasyarat
                        </h2>
                        <p className="mt-2 text-sm text-ink-soft">
                            Checklist dapat disimpan terpisah. Item wajib harus
                            lengkap hanya ketika tahap difinalisasi.
                        </p>
                        <form
                            className="mt-5 grid gap-3 md:grid-cols-2"
                            onSubmit={saveChecklist}
                        >
                            {(step.checklist ?? []).map((item) => (
                                <label
                                    className="flex items-start gap-3 rounded-lg border p-3"
                                    key={item.key}
                                >
                                    <input
                                        className="mt-1"
                                        type="checkbox"
                                        checked={
                                            !!checklistForm.data.checklist[
                                                item.key
                                            ]
                                        }
                                        onChange={(event) =>
                                            checklistForm.setData(
                                                "checklist",
                                                {
                                                    ...checklistForm.data
                                                        .checklist,
                                                    [item.key]:
                                                        event.target.checked,
                                                },
                                            )
                                        }
                                    />
                                    <span>
                                        {item.label}
                                        {item.required && (
                                            <b className="text-red-600"> *</b>
                                        )}
                                    </span>
                                </label>
                            ))}
                            {Object.values(checklistForm.errors).map(
                                (error, index) => (
                                    <p
                                        className="text-sm text-red-600 md:col-span-2"
                                        key={index}
                                    >
                                        {error}
                                    </p>
                                ),
                            )}
                            <div className="md:col-span-2">
                                <Button
                                    disabled={checklistForm.processing}
                                    variant="outline"
                                >
                                    Simpan Checklist
                                </Button>
                            </div>
                        </form>
                    </section>
                )}
                <section className="rounded-xl border bg-white/80 p-6 shadow-soft">
                    <h2 className="text-xl font-black">Hasil & Bukti Proses</h2>
                    <p className="mt-2 text-sm text-ink-soft">
                        Bagian ini bukan berkas persyaratan customer. Simpan
                        hanya dokumen resmi yang dihasilkan pada tahap ini,
                        seperti hasil SLIK, appraisal, SP3K, kontrak bertanda
                        tangan, atau BAST.
                    </p>
                    {!!step.printable_documents?.length && (
                        <div className="mt-4 grid gap-3 md:grid-cols-2">
                            {step.printable_documents.map((doc) => (
                                <a
                                    className="flex items-center justify-between rounded-xl border border-sky-200 bg-sky-50 p-4 hover:border-sky-500"
                                    href={doc.url}
                                    key={doc.id}
                                >
                                    <span className="flex items-center gap-3">
                                        <FileText className="text-sky-700" />
                                        <span>
                                            <b className="block">{doc.name}</b>
                                            <small className="text-ink-soft">
                                                {doc.description}
                                            </small>
                                        </span>
                                    </span>
                                    <Printer size={18} />
                                </a>
                            ))}
                        </div>
                    )}
                    {step.can_edit && !!step.document_types?.length && (
                        <form
                            onSubmit={upload}
                            className="mt-5 grid gap-3 md:grid-cols-3"
                        >
                            <label>
                                <span className="flex items-start">
                                    Jenis Hasil/Bukti{" "}
                                    <HelpToggle
                                        id="process_document_type"
                                        text="Pilih jenis dokumen resmi yang dihasilkan oleh tahap ini. Daftar mengikuti definisi proses."
                                    />
                                </span>
                                <select
                                    className="mt-1 w-full rounded-lg border p-3"
                                    value={document.data.document_type}
                                    onChange={(e) =>
                                        document.setData(
                                            "document_type",
                                            e.target.value,
                                        )
                                    }
                                >
                                    {step.document_types.map((x) => (
                                        <option value={x.type} key={x.type}>
                                            {x.label}
                                            {x.required ? " *" : ""}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                <span className="flex items-start">
                                    Nomor Dokumen Hasil{" "}
                                    <HelpToggle
                                        id="process_document_number"
                                        text="Isi nomor yang tercantum pada dokumen. Boleh kosong jika bukti memang tidak memiliki nomor."
                                    />
                                </span>
                                <input
                                    className="mt-1 w-full rounded-lg border p-3"
                                    value={document.data.document_number}
                                    onChange={(e) =>
                                        document.setData(
                                            "document_number",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label>
                                <span className="flex items-start">
                                    Tanggal Terbit Dokumen{" "}
                                    <HelpToggle
                                        id="process_document_date"
                                        text="Tanggal yang tercantum pada dokumen hasil, bukan tanggal file diunggah."
                                    />
                                </span>
                                <input
                                    className="mt-1 w-full rounded-lg border p-3"
                                    type="date"
                                    value={document.data.document_date}
                                    onChange={(e) =>
                                        document.setData(
                                            "document_date",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label>
                                <span className="flex items-start">
                                    Masa Berlaku Sampai{" "}
                                    <HelpToggle
                                        id="process_document_expiry"
                                        text="Isi jika dokumen memiliki masa berlaku, misalnya SP3K. Kosongkan jika tidak ada kedaluwarsa."
                                    />
                                </span>
                                <input
                                    className="mt-1 w-full rounded-lg border p-3"
                                    type="date"
                                    value={document.data.expires_at}
                                    onChange={(e) =>
                                        document.setData(
                                            "expires_at",
                                            e.target.value,
                                        )
                                    }
                                />
                            </label>
                            <label className="md:col-span-2">
                                <span className="flex items-start">
                                    File Hasil/Bukti Proses{" "}
                                    <HelpToggle
                                        id="process_document_file"
                                        text="Unggah scan atau file resmi hasil tahap. Jangan mengunggah ulang KTP, KK, atau persyaratan customer di bagian ini."
                                    />
                                </span>
                                <input
                                    className="mt-2 block w-full"
                                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx"
                                    type="file"
                                    onChange={(e) => {
                                        document.clearErrors("file");
                                        document.setData(
                                            "file",
                                            e.target.files[0],
                                        );
                                    }}
                                />
                            </label>
                            {document.progress && (
                                <div className="md:col-span-3">
                                    <div className="mb-1 flex justify-between text-xs font-bold text-ink-soft">
                                        <span>Mengunggah dokumen</span>
                                        <span>{document.progress.percentage ?? 0}%</span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                                        <div
                                            className="h-full rounded-full bg-emerald-500 transition-[width] duration-150"
                                            style={{ width: `${document.progress.percentage ?? 0}%` }}
                                        />
                                    </div>
                                </div>
                            )}
                            {Object.values(document.errors).map((error, i) => (
                                <p
                                    className="text-sm text-red-600 md:col-span-3"
                                    key={i}
                                >
                                    {error}
                                </p>
                            ))}
                            <div className="md:col-span-3">
                                <Button
                                    disabled={document.processing}
                                    variant="outline"
                                >
                                    Unggah Dokumen
                                </Button>
                            </div>
                        </form>
                    )}
                    <div className="mt-5 grid gap-2">
                        {(step.documents ?? []).map((doc) => (
                            <div
                                className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
                                key={doc.id}
                            >
                                <div>
                                    <b>{doc.label}</b>
                                    <p className="text-xs text-ink-soft">
                                        {doc.number || "Tanpa nomor"} ·{" "}
                                        {doc.date || "-"} · {doc.name}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        as={Link}
                                        href={doc.url}
                                        size="sm"
                                        variant="outline"
                                    >
                                        Lihat
                                    </Button>
                                    {step.can_edit && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="danger"
                                            onClick={() =>
                                                router.delete(doc.delete_url)
                                            }
                                        >
                                            Hapus
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                        {!step.documents?.length && (
                            <p className="rounded-lg border border-dashed p-6 text-center text-ink-soft">
                                Belum ada dokumen.
                            </p>
                        )}
                    </div>
                </section>
                <section className="rounded-xl border bg-white/80 p-6 shadow-soft">
                    <h2 className="text-xl font-black">
                        Finalisasi dan Approval
                    </h2>
                    <p className="mt-2 text-sm text-ink-soft">
                        Finalisasi hanya dapat dilakukan setelah data,
                        checklist, tanggal aktual, catatan, dan dokumen wajib
                        lengkap. Untuk tahap pembangunan dan QC, tahap bisa
                        dilewati jika status unit atau inspeksi sudah
                        tersinkron.
                    </p>
                    <div className="mt-4 flex flex-wrap gap-2">
                        {step.can_lock && (
                            <Button
                                type="button"
                                disabled={form.processing}
                                onClick={finalize}
                            >
                                {form.processing
                                    ? "Menyimpan & Memfinalisasi..."
                                : "Simpan, Finalisasi & Ajukan"}
                            </Button>
                        )}
                        {step.can_skip && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/admin/penjualan-terintegrasi/tahapan/${step.id}/skip`,
                                    )
                                }
                            >
                                Lewati Tahap & Buka Berikutnya
                            </Button>
                        )}
                        {step.can_unlock && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/admin/penjualan-terintegrasi/tahapan/${step.id}/unlock`,
                                    )
                                }
                            >
                                Buka Kembali
                            </Button>
                        )}
                        {step.can_review && (
                            <>
                                <Button
                                    type="button"
                                    onClick={() =>
                                        router.post(
                                            `/admin/penjualan-terintegrasi/tahapan/${step.id}/review/approve`,
                                        )
                                    }
                                >
                                    Approve Tahap
                                </Button>
                                <Button
                                    type="button"
                                    variant="danger"
                                    onClick={() => {
                                        const note =
                                            window.prompt("Alasan penolakan");
                                        if (note)
                                            router.post(
                                                `/admin/penjualan-terintegrasi/tahapan/${step.id}/review/reject`,
                                                { note },
                                            );
                                    }}
                                >
                                    Tolak
                                </Button>
                            </>
                        )}
                    </div>
                    {!step.can_edit && (
                        <div className="mt-5 grid gap-3 border-t pt-5 sm:grid-cols-2 lg:grid-cols-4">
                            {[
                                [
                                    "Status Data",
                                    step.record_status === "locked"
                                        ? "Sudah Difinalisasi"
                                        : step.record_status,
                                ],
                                ["Difinalisasi Oleh", step.locked_by],
                                ["Waktu Finalisasi", step.locked_at],
                                [
                                    "Status Approval",
                                    step.approval?.status || step.status_label,
                                ],
                                ["Diajukan Oleh", step.approval?.requested_by],
                                [
                                    "Tahap Persetujuan",
                                    step.approval
                                        ? `${step.approval.current_step}/${step.approval.total_steps}`
                                        : null,
                                ],
                                [
                                    "Direview Oleh",
                                    step.approval?.reviewed_by ||
                                        step.completed_by,
                                ],
                                ["Waktu Review", step.approval?.reviewed_at],
                            ].map(([label, value]) => (
                                <div
                                    className="rounded-xl border p-4"
                                    key={label}
                                >
                                    <span className="text-xs font-black uppercase tracking-wide text-ink-soft">
                                        {label}
                                    </span>
                                    <p className="mt-2 font-black">
                                        {value || "-"}
                                    </p>
                                </div>
                            ))}
                            {step.approval?.note && (
                                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 sm:col-span-2 lg:col-span-4">
                                    <span className="text-xs font-black uppercase tracking-wide text-amber-800">
                                        Catatan Approval
                                    </span>
                                    <p className="mt-2 whitespace-pre-wrap font-semibold">
                                        {step.approval.note}
                                    </p>
                                </div>
                            )}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
ProcessForm.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Proses Penjualan"}>
        {page}
    </AdminLayout>
);
