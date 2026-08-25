import { Head, router, useForm } from "@inertiajs/react";
import { ArrowLeft, MapPin, Plus, Save, Trash2 } from "lucide-react";
import {
    Button,
    Dropdown,
    Form,
    Input,
    Textarea,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const selectOptions = {
    visit_type: [
        { value: "customer_location", label: "Lokasi Customer" },
        { value: "office", label: "Kantor" },
        { value: "housing_site", label: "Lokasi Perumahan" },
        { value: "online", label: "Online" },
    ],
    visit_status: [
        { value: "planned", label: "Direncanakan" },
        { value: "in_progress", label: "Berlangsung" },
        { value: "completed", label: "Selesai" },
        { value: "rescheduled", label: "Dijadwalkan Ulang" },
        { value: "cancelled", label: "Dibatalkan" },
    ],
    interest: [
        { value: "cold", label: "Cold" },
        { value: "warm", label: "Warm" },
        { value: "hot", label: "Hot" },
    ],
    priority: [
        { value: "low", label: "Rendah" },
        { value: "medium", label: "Sedang" },
        { value: "high", label: "Tinggi" },
        { value: "urgent", label: "Mendesak" },
    ],
    action_status: [
        { value: "planned", label: "Direncanakan" },
        { value: "in_progress", label: "Dikerjakan" },
        { value: "completed", label: "Selesai" },
        { value: "blocked", label: "Terhambat" },
        { value: "cancelled", label: "Dibatalkan" },
    ],
    process_stage: [
        { value: "qualification", label: "Kualifikasi Lead" },
        { value: "reservation", label: "Reservasi" },
        { value: "spr", label: "SPR" },
        { value: "kpr", label: "KPR" },
        { value: "contract", label: "Kontrak / Akad" },
    ],
};

const defaults = {
    visit_type: "customer_location",
    visit_status: "planned",
    interest: "",
    priority: "medium",
    action_status: "planned",
    process_stage: "qualification",
};

const pageCopy = {
    visits: {
        eyebrow: "Input aktivitas lapangan",
        workflowStep: "Tahap 1 · Pencarian prospek / canvassing",
        formTitle: "Catat Aktivitas Prospek Harian",
        description:
            "Catat aktivitas prospek harian: menawarkan properti ke rumah calon konsumen, canvassing, event, instansi, atau partner. Ambil GPS dan foto bukti sebelum menyimpan.",
        hint: "Kontak yang bersedia ditindaklanjuti dapat dijadikan Lead. Setelah Lead qualified dan dikonversi menjadi Customer, lanjutkan ke survey unit.",
        notThis: "Bukan untuk memilih unit atau mengisi hasil survey rumah.",
    },
    "action-plans": {
        eyebrow: "Aktivitas marketing lain",
        workflowStep: "Tahap 4 · Rencana kerja",
        formTitle: "Rencana Kerja / Aktivitas Marketing",
        description:
            "Dipakai untuk tugas marketing yang bukan follow-up rutin, bukan survey unit, dan bukan kunjungan GPS. Contoh: siapkan materi promo, hubungi komunitas, follow-through hambatan customer, atau target kerja harian.",
        hint: "Marketing dan perumahan akan diisi otomatis untuk akun marketing biasa.",
        notThis:
            "Bukan untuk check-in GPS, jadwal survey unit, atau follow-up rutin.",
    },
    "document-checklists": {
        eyebrow: "Pemeriksaan berkas customer",
        workflowStep: "Tahap 7 · Pemeriksaan berkas",
        formTitle: "Checklist Kelengkapan Berkas",
        description:
            "Daftar dokumen dibuat otomatis dari paket persyaratan yang sudah disetujui sesuai metode pembayaran, bank, produk, dan perumahan customer.",
        hint: "Jika customer belum mempunyai SPR atau belum memilih bank/produk, sistem memakai Master Dokumen Pelanggan sebagai fallback.",
        notThis:
            "Bukan tempat membuat customer baru atau mengatur jadwal kunjungan.",
    },
};

export default function FormPage({
    title,
    resource,
    baseUrl,
    actionUrl,
    method = "post",
    row = {},
    fields = [],
    options = {},
}) {
    const copy = pageCopy[resource] ?? pageCopy["action-plans"];
    const hiddenFields = options.hiddenFields ?? [];
    const descriptors = fields.map((field) => {
        const [name, type, label, source] = field.split(":");
        return { name, type, label, source };
    });
    const initial = Object.fromEntries(
        descriptors.map(({ name, type, source }) => [
            name,
            type === "checklist"
                ? row[name]?.length
                    ? row[name]
                    : (options.documentDefaults ?? [])
                : (row[name] ?? defaults[source] ?? ""),
        ]),
    );
    const form = useForm(initial);
    const isDailyVisit = resource === "visits" && !row?.id;
    const requiredField = (name) => {
        if (!isDailyVisit) return false;

        if (
            [
                "planned_at",
                "visit_type",
                "latitude",
                "evidence_path",
                "objective",
                "result",
                "interest_level",
            ].includes(name)
        ) {
            return true;
        }

        return name === "location" && form.data.visit_type !== "online";
    };
    const submit = (event) => {
        event.preventDefault();
        form[method](actionUrl, {
            forceFormData: true,
            onSuccess: () => router.visit(baseUrl),
        });
    };
    const choices = (descriptor) => {
        if (descriptor.type === "customer") return options.customers ?? [];
        if (descriptor.type === "marketing") return options.marketings ?? [];
        if (descriptor.type === "perumahan") return options.perumahans ?? [];
        if (options[descriptor.source]) return options[descriptor.source] ?? [];
        return selectOptions[descriptor.source] ?? [];
    };
    const updateItem = (index, key, value) =>
        form.setData(
            "items",
            form.data.items.map((item, itemIndex) => {
                if (itemIndex !== index) return item;
                const next = { ...item, [key]: value };
                if (key === "file_upload" && value && next.status === "missing")
                    next.status = "received";
                return next;
            }),
        );
    const removeItem = (index) =>
        form.setData(
            "items",
            form.data.items.filter((_, itemIndex) => itemIndex !== index),
        );

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-widest text-ink-soft">
                        {copy.workflowStep ?? copy.eyebrow} · {copy.eyebrow}
                    </p>
                    <h1 className="mt-2 text-3xl font-extrabold">{title}</h1>
                    <p className="mt-2 max-w-3xl text-sm leading-6 text-ink-soft">
                        {copy.description}
                    </p>
                    {copy.hint && (
                        <p className="mt-3 rounded-lg bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                            {copy.hint}
                        </p>
                    )}
                    {copy.notThis && (
                        <p className="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                            <span className="font-extrabold">Bukan untuk:</span>{" "}
                            {copy.notThis}
                        </p>
                    )}
                    {options.autoIdentity && (
                        <div className="mt-3 grid gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900 md:grid-cols-2">
                            <p>
                                <b>Marketing otomatis:</b>{" "}
                                {options.autoIdentity.marketing || "-"}
                            </p>
                            <p>
                                <b>Perumahan otomatis:</b>{" "}
                                {options.autoIdentity.perumahan || "-"}
                            </p>
                        </div>
                    )}
                </section>
                <Form
                    title={copy.formTitle}
                    description={
                        resource === "visits"
                            ? "Simpan sebagai bukti aktivitas harian. GPS, foto, hasil penawaran, dan minat prospek akan masuk ke monitoring manager/owner."
                            : "Data disimpan sebagai draft. Finalisasi dilakukan melalui Lock dan Setting Approval."
                    }
                    onSubmit={submit}
                    actions={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => router.visit(baseUrl)}
                            >
                                <ArrowLeft size={17} /> Kembali
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                <Save size={17} /> Simpan Draft
                            </Button>
                        </>
                    }
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        {descriptors.map((descriptor) => {
                            const { name, type, label } = descriptor;
                            if (
                                type === "hidden" ||
                                hiddenFields.includes(name)
                            )
                                return null;
                            if (
                                resource === "action-plans" &&
                                ((["actual_result", "completed_at"].includes(
                                    name,
                                ) &&
                                    form.data.status !== "completed") ||
                                    (["blocker"].includes(name) &&
                                        form.data.status !== "blocked"))
                            )
                                return null;
                            if (type === "gps")
                                return (
                                    <div className="grid gap-2" key={name}>
                                        <span className="text-sm font-extrabold">
                                            {label}
                                            {requiredField(name) && (
                                                <span className="ml-1 text-red-600">*</span>
                                            )}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                navigator.geolocation?.getCurrentPosition(
                                                    (position) => {
                                                        form.setData(
                                                            (data) => ({
                                                                ...data,
                                                                latitude:
                                                                    String(
                                                                        position
                                                                            .coords
                                                                            .latitude,
                                                                    ),
                                                                longitude:
                                                                    String(
                                                                        position
                                                                            .coords
                                                                            .longitude,
                                                                    ),
                                                                location_accuracy_m:
                                                                    String(
                                                                        Math.round(
                                                                            position
                                                                                .coords
                                                                                .accuracy,
                                                                        ),
                                                                    ),
                                                            }),
                                                        );
                                                    },
                                                    () =>
                                                        alert(
                                                            "Lokasi tidak dapat diambil. Pastikan izin lokasi browser aktif.",
                                                        ),
                                                    {
                                                        enableHighAccuracy: true,
                                                    },
                                                )
                                            }
                                        >
                                            <MapPin size={16} /> Ambil Lokasi
                                            Saat Ini
                                        </Button>
                                        <span className="text-xs text-ink-soft">
                                            {form.data.latitude &&
                                            form.data.longitude
                                                ? `${form.data.latitude}, ${form.data.longitude} (akurasi ±${form.data.location_accuracy_m || "?"} m)`
                                                : "Koordinat belum diambil."}
                                        </span>
                                        {form.data.latitude &&
                                            form.data.longitude && (
                                                <iframe
                                                    title="Peta lokasi aktivitas"
                                                    className="mt-2 h-52 w-full rounded-xl border border-slate-200"
                                                    loading="lazy"
                                                    src={`https://www.openstreetmap.org/export/embed.html?bbox=${Number(form.data.longitude) - 0.002}%2C${Number(form.data.latitude) - 0.002}%2C${Number(form.data.longitude) + 0.002}%2C${Number(form.data.latitude) + 0.002}&layer=mapnik&marker=${form.data.latitude}%2C${form.data.longitude}`}
                                                />
                                            )}
                                        {form.errors[name] && (
                                            <span className="text-xs font-bold text-red-600">
                                                {form.errors[name]}
                                            </span>
                                        )}
                                    </div>
                                );
                            if (type === "file")
                                return (
                                    <Input
                                        key={name}
                                        type="file"
                                        accept="image/*"
                                        capture="environment"
                                        label={label}
                                        required={requiredField(name)}
                                        error={form.errors[name]}
                                        onChange={(event) =>
                                            form.setData(
                                                name,
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                    />
                                );
                            if (type === "checklist")
                                return (
                                    <div
                                        className="grid gap-3 md:col-span-2"
                                        key={name}
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <span className="text-sm font-extrabold">
                                                    {label}
                                                </span>
                                                <p className="mt-1 text-xs font-semibold text-ink-soft">
                                                    Dokumen wajib berasal dari
                                                    master/paket yang sudah
                                                    di-approve. File yang
                                                    diunggah tersimpan ke
                                                    Repository Dokumen Customer.
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    !form.data.costumer_id
                                                }
                                                onClick={() =>
                                                    form.setData("items", [
                                                        ...form.data.items,
                                                        {
                                                            name: "",
                                                            party_scope:
                                                                "customer",
                                                            source: "Dokumen khusus",
                                                            required: true,
                                                            status: "missing",
                                                            expires_at: "",
                                                            note: "",
                                                        },
                                                    ])
                                                }
                                            >
                                                <Plus size={15} /> Dokumen
                                                Khusus
                                            </Button>
                                        </div>
                                        {options.documentContext && (
                                            <div className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-bold text-blue-900">
                                                {options.documentContext}
                                            </div>
                                        )}
                                        {!form.data.costumer_id && (
                                            <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                                                Pilih customer terlebih dahulu.
                                                Halaman akan memuat ulang daftar
                                                dokumen yang sesuai secara
                                                otomatis.
                                            </div>
                                        )}
                                        {form.data.costumer_id &&
                                            form.data.items.length === 0 && (
                                                <div className="rounded-lg border border-silver-deep/60 px-4 py-3 text-sm font-bold text-ink-soft">
                                                    Belum ada dokumen master
                                                    yang dapat digunakan.
                                                    Periksa status approval
                                                    master dokumen/paket
                                                    persyaratan.
                                                </div>
                                            )}
                                        {form.data.items.map((item, index) => (
                                            <div
                                                className="grid gap-3 rounded-lg border border-silver-deep/60 p-4 md:grid-cols-12"
                                                key={index}
                                            >
                                                <Input
                                                    className="md:col-span-4"
                                                    label={
                                                        item.document_id
                                                            ? "Dokumen Master"
                                                            : "Nama Dokumen Khusus"
                                                    }
                                                    value={item.name}
                                                    disabled={Boolean(
                                                        item.document_id,
                                                    )}
                                                    error={
                                                        form.errors[
                                                            `items.${index}.name`
                                                        ]
                                                    }
                                                    onChange={(event) =>
                                                        updateItem(
                                                            index,
                                                            "name",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <div className="grid gap-2 md:col-span-3">
                                                    <span className="text-sm font-extrabold">
                                                        Status
                                                    </span>
                                                    <Dropdown
                                                        value={item.status}
                                                        options={[
                                                            {
                                                                value: "missing",
                                                                label: "Belum Ada",
                                                            },
                                                            {
                                                                value: "received",
                                                                label: "Diterima",
                                                            },
                                                            {
                                                                value: "valid",
                                                                label: "Valid",
                                                            },
                                                            {
                                                                value: "revision",
                                                                label: "Revisi",
                                                            },
                                                            {
                                                                value: "expired",
                                                                label: "Kedaluwarsa",
                                                            },
                                                            {
                                                                value: "rejected",
                                                                label: "Ditolak",
                                                            },
                                                        ]}
                                                        onChange={(value) =>
                                                            updateItem(
                                                                index,
                                                                "status",
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <Input
                                                    className="md:col-span-3"
                                                    type="date"
                                                    label="Masa Berlaku"
                                                    value={
                                                        item.expires_at ?? ""
                                                    }
                                                    onChange={(event) =>
                                                        updateItem(
                                                            index,
                                                            "expires_at",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                                <label className="flex items-center gap-2 self-end pb-3 text-sm font-bold md:col-span-1">
                                                    <input
                                                        type="checkbox"
                                                        checked={Boolean(
                                                            item.required,
                                                        )}
                                                        disabled={Boolean(
                                                            item.document_id,
                                                        )}
                                                        onChange={(event) =>
                                                            updateItem(
                                                                index,
                                                                "required",
                                                                event.target
                                                                    .checked,
                                                            )
                                                        }
                                                    />{" "}
                                                    Wajib
                                                </label>
                                                <Button
                                                    className="self-end text-red-600 md:col-span-1"
                                                    type="button"
                                                    variant="ghost"
                                                    disabled={Boolean(
                                                        item.document_id,
                                                    )}
                                                    onClick={() =>
                                                        removeItem(index)
                                                    }
                                                >
                                                    <Trash2 size={16} />
                                                </Button>
                                                <div className="rounded-lg bg-blue-50 px-3 py-2 text-xs font-bold text-blue-900 md:col-span-4">
                                                    Pihak:{" "}
                                                    {item.party_scope ===
                                                    "spouse"
                                                        ? "Pasangan"
                                                        : item.party_scope ===
                                                            "both"
                                                          ? "Customer & Pasangan"
                                                          : "Customer"}
                                                </div>
                                                <div className="rounded-lg bg-silver-soft px-3 py-2 text-xs font-bold text-ink-soft md:col-span-8">
                                                    Sumber:{" "}
                                                    {item.source ||
                                                        "Dokumen khusus"}
                                                </div>
                                                <Input
                                                    className="md:col-span-5"
                                                    type="file"
                                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,application/pdf,image/*"
                                                    label="Upload Berkas Customer"
                                                    error={
                                                        form.errors[
                                                            `items.${index}.file_upload`
                                                        ]
                                                    }
                                                    onChange={(event) =>
                                                        updateItem(
                                                            index,
                                                            "file_upload",
                                                            event.target
                                                                .files?.[0] ??
                                                                null,
                                                        )
                                                    }
                                                />
                                                <div className="rounded-lg bg-silver-soft px-3 py-2 text-xs font-bold text-ink-soft md:col-span-7">
                                                    {item.file_upload
                                                        ? `File baru: ${item.file_upload.name}`
                                                        : item.file_name
                                                          ? `Sudah upload: ${item.file_name}`
                                                          : "Belum ada file pada repository customer untuk baris ini."}
                                                </div>
                                                <Textarea
                                                    className="md:col-span-12"
                                                    label="Catatan Dokumen"
                                                    value={item.note ?? ""}
                                                    onChange={(event) =>
                                                        updateItem(
                                                            index,
                                                            "note",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        ))}
                                        {form.errors.items && (
                                            <span className="text-xs font-bold text-red-600">
                                                {form.errors.items}
                                            </span>
                                        )}
                                    </div>
                                );
                            if (
                                [
                                    "select",
                                    "customer",
                                    "marketing",
                                    "perumahan",
                                ].includes(type)
                            )
                                return (
                                    <div className="grid gap-2" key={name}>
                                        <span className="text-sm font-extrabold">
                                            {label}
                                            {requiredField(name) && (
                                                <span className="ml-1 text-red-600">*</span>
                                            )}
                                        </span>
                                        <Dropdown
                                            value={String(
                                                form.data[name] ?? "",
                                            )}
                                            options={choices(descriptor)}
                                            onChange={(value) => {
                                                if (
                                                    resource ===
                                                        "document-checklists" &&
                                                    name === "costumer_id" &&
                                                    !row?.id
                                                ) {
                                                    router.get(
                                                        `${baseUrl}/create`,
                                                        { costumer_id: value },
                                                        { replace: true },
                                                    );
                                                    return;
                                                }
                                                form.setData(name, value);
                                            }}
                                        />
                                        {form.errors[name] && (
                                            <span className="text-xs font-bold text-red-600">
                                                {form.errors[name]}
                                            </span>
                                        )}
                                    </div>
                                );
                            if (type === "textarea")
                                return (
                                    <Textarea
                                        className="md:col-span-2"
                                        key={name}
                                        label={label}
                                        required={requiredField(name)}
                                        value={form.data[name] ?? ""}
                                        error={form.errors[name]}
                                        onChange={(event) =>
                                            form.setData(
                                                name,
                                                event.target.value,
                                            )
                                        }
                                    />
                                );
                            return (
                                <Input
                                    key={name}
                                    type={type}
                                    label={label}
                                    required={requiredField(name)}
                                    readOnly={isDailyVisit && name === "planned_at"}
                                    inputClassName={
                                        isDailyVisit && name === "planned_at"
                                            ? "cursor-not-allowed bg-silver-soft text-ink-soft"
                                            : ""
                                    }
                                    placeholder={
                                        name === "lead_source_note"
                                            ? "Contoh: canvassing door to door, brosur, event, komunitas, atau partner"
                                            : undefined
                                    }
                                    value={form.data[name] ?? ""}
                                    error={form.errors[name]}
                                    onChange={(event) =>
                                        form.setData(name, event.target.value)
                                    }
                                />
                            );
                        })}
                    </div>
                </Form>
            </div>
        </>
    );
}

FormPage.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "CRM"}>{page}</AdminLayout>
);
