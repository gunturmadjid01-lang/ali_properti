import { Head, router, useForm } from "@inertiajs/react";
import {
    CheckCircle2,
    Edit3,
    Eye,
    LoaderCircle,
    Lock,
    MinusCircle,
    PlusCircle,
    Save,
    Search,
    Trash2,
    Unlock,
    X,
    XCircle,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import {
    Button,
    CurrencyInput,
    Dropdown,
    Form,
    Input,
    Modal,
    Textarea,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

function money(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function workItemTemplate() {
    return {
        nama_pekerjaan: "",
        harga_satuan: "",
    };
}

function workGroupTemplate() {
    return {
        judul_tahapan: "",
        items: [workItemTemplate()],
    };
}

function paymentTemplate() {
    return { tanggal_jatuh_tempo: "", nominal: "", keterangan: "" };
}

function FormErrorSummary({ errors }) {
    const messages = Object.values(errors ?? {}).filter(Boolean);
    if (messages.length === 0) return null;

    return (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
            <p>Data belum bisa disimpan. Periksa bagian berikut:</p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
                {messages.map((message, index) => (
                    <li key={`${message}-${index}`}>{message}</li>
                ))}
            </ul>
        </div>
    );
}

export default function Index({
    title,
    description,
    baseUrl,
    pageUrl = baseUrl,
    rows,
    filters = {},
    options,
    permissions = {},
    approvalOnly = false,
    paymentOnly = false,
    disbursementOnly = false,
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [editing, setEditing] = useState(null);
    const [showUnitModal, setShowUnitModal] = useState(false);
    const [unitSearch, setUnitSearch] = useState("");
    const [selectedPerumahanTemplateId, setSelectedPerumahanTemplateId] =
        useState("");
    const [selectedUnitTemplateId, setSelectedUnitTemplateId] = useState("");
    const showPaymentSchedule = approvalOnly || paymentOnly || disbursementOnly;
    const form = useForm({
        sumber_tenaga_kerja: "tukang_owner",
        kontraktor_id: "",
        perumahan_id: "",
        detail_rumah_id: "",
        detail_rumah_ids: [],
        judul_pekerjaan: "",
        jenis_pekerjaan: "rumah",
        tanggal_spk: new Date().toISOString().slice(0, 10),
        tanggal_mulai: "",
        tanggal_selesai: "",
        nilai_kontrak: "",
        metode_pembayaran: "cash",
        lingkup_pekerjaan: "",
        catatan: "",
        status: "draft",
        work_groups: [workGroupTemplate()],
        payments: [paymentTemplate()],
    });

    const selectedDetailRumahIds = form.data.detail_rumah_ids ?? [];
    const occupiedDetailRumahIds = useMemo(
        () =>
            new Set(
                (options.spkKontraktors ?? [])
                    .filter(
                        (spk) =>
                            String(spk.status ?? "") !== "batal" &&
                            String(spk.detail_rumah_id ?? "") !== "",
                    )
                    .map((spk) => String(spk.detail_rumah_id)),
            ),
        [options.spkKontraktors],
    );
    const detailRumahOptions = useMemo(() => {
        if (!form.data.perumahan_id) return options.detailRumahs;
        return options.detailRumahs.filter(
            (item) => item.perumahan_id === String(form.data.perumahan_id),
        );
    }, [form.data.perumahan_id, options.detailRumahs]);
    const filteredDetailRumahOptions = useMemo(() => {
        const query = unitSearch.trim().toLowerCase();
        return detailRumahOptions.filter((item) => {
            const itemId = String(item.value);
            const isSelected = selectedDetailRumahIds.includes(itemId);
            const isOccupied =
                occupiedDetailRumahIds.has(itemId) &&
                !isSelected &&
                String(form.data.detail_rumah_id ?? "") !== itemId;
            if (isOccupied) {
                return false;
            }

            if (!query) {
                return true;
            }

            return String(item.label ?? "")
                .toLowerCase()
                .includes(query);
        });
    }, [
        detailRumahOptions,
        occupiedDetailRumahIds,
        selectedDetailRumahIds,
        form.data.detail_rumah_id,
        unitSearch,
    ]);

    const perumahanTemplateOptions = useMemo(
        () =>
            (options.spkTemplatePerumahans ?? []).filter(
                (template) =>
                    !form.data.perumahan_id ||
                    String(template.perumahan_id) ===
                        String(form.data.perumahan_id),
            ),
        [form.data.perumahan_id, options.spkTemplatePerumahans],
    );
    const unitTemplateOptions = useMemo(
        () =>
            (options.spkTemplateUnits ?? []).filter(
                (template) =>
                    !form.data.perumahan_id ||
                    String(template.perumahan_id) ===
                        String(form.data.perumahan_id),
            ),
        [form.data.perumahan_id, options.spkTemplateUnits],
    );

    const templateToWorkGroups = (template) =>
        (template?.groups ?? []).map((group) => ({
            judul_tahapan: group.judul_tahapan ?? "",
            items: (group.items ?? []).map((item) => ({
                nama_pekerjaan: item.nama_pekerjaan ?? "",
                harga_satuan: item.harga_satuan ?? "",
            })),
        }));

    const itemsTotal = useMemo(
        () =>
            (form.data.work_groups ?? []).reduce(
                (sumGroup, group) =>
                    sumGroup +
                    (group.items ?? []).reduce(
                        (sumItem, item) =>
                            sumItem + Number(item.harga_satuan || 0),
                        0,
                    ),
                0,
            ),
        [form.data.work_groups],
    );
    const nilaiDasarKontrak = itemsTotal;
    const totalKontrak = nilaiDasarKontrak;
    const totalPayment = form.data.payments.reduce(
        (sum, item) => sum + Number(item.nominal || 0),
        0,
    );
    const paymentDifference = totalKontrak - totalPayment;
    const paymentIsBalanced =
        form.data.metode_pembayaran === "cash" ||
        Math.round(paymentDifference) === 0;

    useEffect(() => {
        if (Number(form.data.nilai_kontrak || 0) !== totalKontrak) {
            form.setData("nilai_kontrak", totalKontrak);
        }
        if (form.data.metode_pembayaran === "cash") {
            const currentPayment = form.data.payments[0] ?? paymentTemplate();
            const nextNominal = totalKontrak;
            if (
                Number(currentPayment.nominal || 0) !== nextNominal ||
                currentPayment.tanggal_jatuh_tempo !== form.data.tanggal_spk
            ) {
                form.setData("payments", [
                    {
                        ...currentPayment,
                        tanggal_jatuh_tempo: form.data.tanggal_spk,
                        nominal: nextNominal,
                        keterangan:
                            currentPayment.keterangan ||
                            "Pembayaran cash / sekaligus.",
                    },
                ]);
            }
        }
    }, [totalKontrak, form.data.metode_pembayaran, form.data.tanggal_spk]);

    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setShowUnitModal(false);
        setUnitSearch("");
        setSelectedPerumahanTemplateId("");
        setSelectedUnitTemplateId("");
    };
    const setSelectedUnits = (unitIds) => {
        const uniqueIds = Array.from(new Set(unitIds.map((id) => String(id))));
        form.setData({
            ...form.data,
            detail_rumah_ids: uniqueIds,
            detail_rumah_id: uniqueIds[0] ?? "",
        });
    };
    const toggleDetailRumah = (unitId) => {
        const stringId = String(unitId);
        const current = selectedDetailRumahIds.map((id) => String(id));
        const exists = current.includes(stringId);
        const next = exists
            ? current.filter((id) => id !== stringId)
            : [...current, stringId];
        setSelectedUnits(next);
    };

    const setMetodePembayaran = (value) => {
        form.setData({
            ...form.data,
            metode_pembayaran: value,
            payments:
                value === "cash"
                    ? [
                          {
                              ...paymentTemplate(),
                              tanggal_jatuh_tempo: form.data.tanggal_spk,
                              nominal: totalKontrak,
                              keterangan: "Pembayaran cash / sekaligus.",
                          },
                      ]
                    : form.data.payments.length
                      ? form.data.payments
                      : [
                            {
                                ...paymentTemplate(),
                                tanggal_jatuh_tempo: form.data.tanggal_spk,
                            },
                        ],
        });
    };

    const setPayment = (index, key, value) => {
        form.setData(
            "payments",
            form.data.payments.map((item, paymentIndex) =>
                paymentIndex === index ? { ...item, [key]: value } : item,
            ),
        );
    };

    const applyTemplateById = (templateId, templateOptions) => {
        const template = templateOptions.find(
            (row) => String(row.value) === String(templateId),
        );

        if (!template) {
            return;
        }

        form.setData("work_groups", templateToWorkGroups(template));
    };

    const editRow = (row) => {
        setEditing(row);
        setSelectedPerumahanTemplateId("");
        setSelectedUnitTemplateId("");
        const groupedWorkGroups = Object.values(
            (row.items ?? []).reduce((acc, item) => {
                const judulTahapan = item.nama_tahap_pekerjaan || "Tahap";
                if (!acc[judulTahapan]) {
                    acc[judulTahapan] = {
                        judul_tahapan: judulTahapan,
                        items: [],
                    };
                }

                acc[judulTahapan].items.push({
                    nama_pekerjaan: item.nama_pekerjaan ?? "",
                    harga_satuan: item.harga_satuan ?? "",
                });
                return acc;
            }, {}),
        );

        form.setData({
            sumber_tenaga_kerja: row.sumber_tenaga_kerja ?? "tukang_owner",
            kontraktor_id: row.kontraktor_id ?? "",
            perumahan_id: row.perumahan_id ?? "",
            detail_rumah_id: row.detail_rumah_id ?? "",
            detail_rumah_ids: row.detail_rumah_id
                ? [String(row.detail_rumah_id)]
                : [],
            judul_pekerjaan: row.judul_pekerjaan ?? "",
            jenis_pekerjaan: row.jenis_pekerjaan ?? "rumah",
            tanggal_spk:
                row.tanggal_spk ?? new Date().toISOString().slice(0, 10),
            tanggal_mulai: row.tanggal_mulai ?? "",
            tanggal_selesai: row.tanggal_selesai ?? "",
            nilai_kontrak: row.nilai_kontrak ?? "",
            metode_pembayaran: row.metode_pembayaran ?? "cash",
            lingkup_pekerjaan: row.lingkup_pekerjaan ?? "",
            catatan: row.catatan ?? "",
            status: row.status ?? "draft",
            work_groups: groupedWorkGroups.length
                ? groupedWorkGroups
                : [workGroupTemplate()],
            payments: row.payments?.length
                ? row.payments.map((payment) => {
                      return {
                          tanggal_jatuh_tempo:
                              payment.tanggal_jatuh_tempo ?? "",
                          nominal: payment.nominal ?? "",
                          keterangan: payment.keterangan ?? "",
                      };
                  })
                : [paymentTemplate()],
        });
    };

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true, onSuccess: resetForm };
        editing
            ? form.put(`${baseUrl}/${editing.id}`, requestOptions)
            : form.post(baseUrl, requestOptions);
    };

    const setWorkGroup = (groupIndex, key, value) => {
        form.setData(
            "work_groups",
            form.data.work_groups.map((group, index) => {
                if (index !== groupIndex) return group;
                return { ...group, [key]: value };
            }),
        );
    };

    const setWorkGroupItem = (groupIndex, itemIndex, key, value) => {
        form.setData(
            "work_groups",
            form.data.work_groups.map((group, index) => {
                if (index !== groupIndex) return group;

                return {
                    ...group,
                    items: group.items.map((item, innerIndex) => {
                        if (innerIndex !== itemIndex) return item;
                        return { ...item, [key]: value };
                    }),
                };
            }),
        );
    };

    const destroyRow = (row) => {
        if (!window.confirm(`Hapus SPK ${row.nomor_spk}?`)) return;
        router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    const cancelRow = (row) => {
        if (!window.confirm(`Batalkan SPK ${row.nomor_spk}?`)) return;
        router.post(
            `${baseUrl}/${row.id}/cancel`,
            {},
            { preserveScroll: true },
        );
    };

    const postPaymentAction = (row, payment, action) => {
        let payload = {};

        if (action === "request" && !row.hpp_plan_exists) {
            const confirmed = window.confirm(
                `Rencana HPP ${row.hpp_plan_label} belum diisi. Pembayaran tetap akan dicatat sebagai realisasi dan dapat membuat anggaran terlihat terlampaui. Tetap ajukan pembayaran?`,
            );
            if (!confirmed) return;
            payload = { confirm_without_hpp: true };
        }

        router.post(
            `${baseUrl}/${row.id}/payments/${payment.id}/${action}`,
            payload,
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                        Manajemen Proyek
                    </p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">
                        {title}
                    </h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">
                        {description}
                    </p>
                </section>

                {!approvalOnly &&
                    !paymentOnly &&
                    !disbursementOnly &&
                    permissions.canManageSpk && (
                        <Form
                            collapsible
                            title={
                                editing
                                    ? "Ubah SPK Kontraktor"
                                    : "Tambah SPK Kontraktor"
                            }
                            description="SPK digunakan sebagai surat perjanjian pekerjaan, item tahap, dan termin pembayaran kontraktor."
                            onSubmit={submit}
                            actions={
                                <>
                                    {editing && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={resetForm}
                                        >
                                            <X size={17} /> Batal Ubah
                                        </Button>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            !paymentIsBalanced
                                        }
                                    >
                                        {form.processing ? (
                                            <LoaderCircle
                                                className="animate-spin"
                                                size={17}
                                            />
                                        ) : (
                                            <Save size={17} />
                                        )}
                                        {editing
                                            ? "Simpan Perubahan"
                                            : "Buat SPK"}
                                    </Button>
                                </>
                            }
                        >
                            <FormErrorSummary errors={form.errors} />
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="rounded-lg border border-silver-deep/70 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                                        Total Item SPK
                                    </p>
                                    <p className="mt-2 text-2xl font-extrabold">
                                        {money(itemsTotal)}
                                    </p>
                                    <p className="mt-1 text-sm text-ink-soft dark:text-white/60">
                                        Nilai SPK mengikuti jumlah harga item
                                        pada tiap tahap.
                                    </p>
                                </div>
                                <div className="rounded-lg border border-silver-deep/70 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                                        Total Pengajuan Kredit
                                    </p>
                                    <p className="mt-2 text-2xl font-extrabold">
                                        {money(totalKontrak)}
                                    </p>
                                    <p className="mt-1 text-sm text-ink-soft dark:text-white/60">
                                        Nominal ini akan dipakai saat SPK
                                        diajukan.
                                    </p>
                                </div>
                            </div>
                            <div className="grid gap-4 md:grid-cols-4">
                                <div className="grid gap-2">
                                    <span className="text-sm font-extrabold">
                                        Sumber Tenaga Kerja
                                    </span>
                                    <Dropdown
                                        value={form.data.sumber_tenaga_kerja}
                                        options={options.sumberTenagaKerjas}
                                        onChange={(value) =>
                                            form.setData({
                                                ...form.data,
                                                sumber_tenaga_kerja: value,
                                                kontraktor_id:
                                                    value === "kontraktor"
                                                        ? form.data
                                                              .kontraktor_id
                                                        : "",
                                            })
                                        }
                                    />
                                </div>
                                {form.data.sumber_tenaga_kerja ===
                                "kontraktor" ? (
                                    <div className="grid gap-2">
                                        <span className="text-sm font-extrabold">
                                            Kontraktor
                                        </span>
                                        <Dropdown
                                            value={form.data.kontraktor_id}
                                            label="Pilih Kontraktor"
                                            options={options.kontraktors}
                                            onChange={(value) =>
                                                form.setData(
                                                    "kontraktor_id",
                                                    value,
                                                )
                                            }
                                        />
                                        {form.errors.kontraktor_id && (
                                            <span className="text-xs font-bold text-red-600">
                                                {form.errors.kontraktor_id}
                                            </span>
                                        )}
                                    </div>
                                ) : null}
                                <Input
                                    label="Judul Pekerjaan"
                                    value={form.data.judul_pekerjaan}
                                    error={form.errors.judul_pekerjaan}
                                    onChange={(event) =>
                                        form.setData(
                                            "judul_pekerjaan",
                                            event.target.value,
                                        )
                                    }
                                />
                                <div className="grid gap-2">
                                    <span className="text-sm font-extrabold">
                                        Jenis Pekerjaan
                                    </span>
                                    <Dropdown
                                        value={form.data.jenis_pekerjaan}
                                        options={options.jenisPekerjaan}
                                        onChange={(value) =>
                                            form.setData(
                                                "jenis_pekerjaan",
                                                value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid gap-4 md:grid-cols-4">
                                <Input
                                    label="Tanggal SPK"
                                    type="date"
                                    value={form.data.tanggal_spk}
                                    error={form.errors.tanggal_spk}
                                    onChange={(event) =>
                                        form.setData(
                                            "tanggal_spk",
                                            event.target.value,
                                        )
                                    }
                                />
                                <Input
                                    label="Tanggal Mulai"
                                    type="date"
                                    value={form.data.tanggal_mulai}
                                    error={form.errors.tanggal_mulai}
                                    onChange={(event) =>
                                        form.setData(
                                            "tanggal_mulai",
                                            event.target.value,
                                        )
                                    }
                                />
                                <Input
                                    label="Tanggal Selesai"
                                    type="date"
                                    value={form.data.tanggal_selesai}
                                    error={form.errors.tanggal_selesai}
                                    onChange={(event) =>
                                        form.setData(
                                            "tanggal_selesai",
                                            event.target.value,
                                        )
                                    }
                                />
                                <div className="grid gap-2">
                                    <span className="text-sm font-extrabold">
                                        Status SPK
                                    </span>
                                    <Dropdown
                                        value={form.data.status}
                                        options={options.status}
                                        onChange={(value) =>
                                            form.setData("status", value)
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="grid gap-2">
                                    <span className="text-sm font-extrabold">
                                        Perumahan
                                    </span>
                                    <Dropdown
                                        value={form.data.perumahan_id}
                                        label="Pilih Perumahan"
                                        options={options.perumahans}
                                        onChange={(value) => {
                                            setSelectedPerumahanTemplateId("");
                                            setSelectedUnitTemplateId("");
                                            setShowUnitModal(false);
                                            setUnitSearch("");
                                            form.setData({
                                                ...form.data,
                                                perumahan_id: value,
                                                detail_rumah_id: "",
                                                detail_rumah_ids: [],
                                            });
                                        }}
                                    />
                                    {form.errors.perumahan_id && (
                                        <span className="text-xs font-bold text-red-600">
                                            {form.errors.perumahan_id}
                                        </span>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <span className="text-sm font-extrabold">
                                        Unit Rumah
                                    </span>
                                    <div className="flex items-center gap-3 rounded-lg border border-silver-deep/60 bg-white/60 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setShowUnitModal(true)
                                            }
                                            disabled={!form.data.perumahan_id}
                                        >
                                            Tampilkan Data Rumah
                                        </Button>
                                        <span className="text-xs font-semibold text-ink-soft dark:text-white/60">
                                            {selectedDetailRumahIds.length > 0
                                                ? `${selectedDetailRumahIds.length} dipilih`
                                                : "Belum ada pilihan"}
                                        </span>
                                    </div>
                                    {form.errors.detail_rumah_ids && (
                                        <span className="text-xs font-bold text-red-600">
                                            {form.errors.detail_rumah_ids}
                                        </span>
                                    )}
                                </div>
                            </div>
                            <Textarea
                                label="Lingkup Pekerjaan"
                                value={form.data.lingkup_pekerjaan}
                                error={form.errors.lingkup_pekerjaan}
                                onChange={(event) =>
                                    form.setData(
                                        "lingkup_pekerjaan",
                                        event.target.value,
                                    )
                                }
                            />

                            <div className="grid gap-4 rounded-lg border border-silver-deep/70 p-4 dark:border-white/10">
                                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <div>
                                        <p className="text-sm font-extrabold">
                                            Judul Tahapan & Item Pekerjaan
                                        </p>
                                        <p className="text-xs text-ink-soft dark:text-white/60">
                                            Satu judul tahapan bisa punya banyak
                                            item pekerjaan, persis seperti
                                            lembar progres yang kamu kirim.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Dropdown
                                            value={
                                                form.data.detail_rumah_id
                                                    ? selectedUnitTemplateId
                                                    : selectedPerumahanTemplateId
                                            }
                                            label={
                                                form.data.detail_rumah_id
                                                    ? "Template Unit"
                                                    : "Template Perumahan"
                                            }
                                            options={(form.data.detail_rumah_id
                                                ? unitTemplateOptions
                                                : perumahanTemplateOptions
                                            ).map((template) => ({
                                                value: template.value,
                                                label: `${template.label} (${template.group_count} tahap)`,
                                            }))}
                                            onChange={(value) => {
                                                if (form.data.detail_rumah_id) {
                                                    setSelectedUnitTemplateId(
                                                        value,
                                                    );
                                                    applyTemplateById(
                                                        value,
                                                        unitTemplateOptions,
                                                    );
                                                    return;
                                                }

                                                setSelectedPerumahanTemplateId(
                                                    value,
                                                );
                                                applyTemplateById(
                                                    value,
                                                    perumahanTemplateOptions,
                                                );
                                            }}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                form.setData("work_groups", [
                                                    ...form.data.work_groups,
                                                    workGroupTemplate(),
                                                ])
                                            }
                                        >
                                            <PlusCircle size={16} /> Tambah
                                            Tahap
                                        </Button>
                                    </div>
                                </div>

                                {(form.data.work_groups ?? []).map(
                                    (group, groupIndex) => (
                                        <div
                                            className="grid gap-4 rounded-lg bg-silver-soft/80 p-4 dark:bg-white/5"
                                            key={groupIndex}
                                        >
                                            <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                                                <div className="grid gap-2 md:flex-1">
                                                    <span className="text-sm font-extrabold">
                                                        Judul Tahapan
                                                    </span>
                                                    <Input
                                                        value={
                                                            group.judul_tahapan
                                                        }
                                                        placeholder="Contoh: PEK. PERSIAPAN & PONDASI"
                                                        onChange={(event) =>
                                                            setWorkGroup(
                                                                groupIndex,
                                                                "judul_tahapan",
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setWorkGroup(
                                                                groupIndex,
                                                                "items",
                                                                [
                                                                    ...group.items,
                                                                    workItemTemplate(),
                                                                ],
                                                            )
                                                        }
                                                    >
                                                        <PlusCircle size={16} />{" "}
                                                        Tambah Item
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        className="text-red-600"
                                                        disabled={
                                                            form.data
                                                                .work_groups
                                                                .length === 1
                                                        }
                                                        onClick={() =>
                                                            form.setData(
                                                                "work_groups",
                                                                form.data.work_groups.filter(
                                                                    (
                                                                        _,
                                                                        index,
                                                                    ) =>
                                                                        index !==
                                                                        groupIndex,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <MinusCircle
                                                            size={16}
                                                        />
                                                    </Button>
                                                </div>
                                            </div>

                                            <div className="grid gap-3">
                                                {(group.items ?? []).map(
                                                    (item, itemIndex) => (
                                                        <div
                                                            className="grid gap-3 rounded-lg border border-white/50 bg-white/70 p-3 dark:border-white/10 dark:bg-black/10 md:grid-cols-[1.7fr_0.9fr_auto]"
                                                            key={itemIndex}
                                                        >
                                                            <Input
                                                                label={`Item Pekerjaan ${itemIndex + 1}`}
                                                                value={
                                                                    item.nama_pekerjaan
                                                                }
                                                                placeholder="Contoh: Pasang pondasi batu gunung"
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setWorkGroupItem(
                                                                        groupIndex,
                                                                        itemIndex,
                                                                        "nama_pekerjaan",
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                            <CurrencyInput
                                                                label="Harga Satuan"
                                                                value={
                                                                    item.harga_satuan
                                                                }
                                                                onChange={(
                                                                    value,
                                                                ) =>
                                                                    setWorkGroupItem(
                                                                        groupIndex,
                                                                        itemIndex,
                                                                        "harga_satuan",
                                                                        value,
                                                                    )
                                                                }
                                                            />
                                                            <div className="flex items-end justify-end">
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-red-600"
                                                                    disabled={
                                                                        group
                                                                            .items
                                                                            .length ===
                                                                        1
                                                                    }
                                                                    onClick={() =>
                                                                        setWorkGroup(
                                                                            groupIndex,
                                                                            "items",
                                                                            group.items.filter(
                                                                                (
                                                                                    _,
                                                                                    index,
                                                                                ) =>
                                                                                    index !==
                                                                                    itemIndex,
                                                                            ),
                                                                        )
                                                                    }
                                                                >
                                                                    <MinusCircle
                                                                        size={
                                                                            16
                                                                        }
                                                                    />
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    ),
                                                )}
                                            </div>

                                            <div className="text-right text-sm font-extrabold text-ink-soft dark:text-white/60">
                                                Total tahapan:{" "}
                                                {money(
                                                    (group.items ?? []).reduce(
                                                        (sum, item) =>
                                                            sum +
                                                            Number(
                                                                item.harga_satuan ||
                                                                    0,
                                                            ),
                                                        0,
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>

                            <div className="grid gap-3 rounded-lg border border-silver-deep/70 p-4 dark:border-white/10">
                                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                                    <div className="grid gap-2 md:w-64">
                                        <span className="text-sm font-extrabold">
                                            Metode Pembayaran
                                        </span>
                                        <Dropdown
                                            value={form.data.metode_pembayaran}
                                            options={options.metodePembayaran}
                                            onChange={setMetodePembayaran}
                                        />
                                        <p className="text-xs text-ink-soft dark:text-white/60">
                                            Cash / Sekaligus = pembayaran 1
                                            kali. Cicil / Termin = pembayaran
                                            bertahap dengan jatuh tempo tiap
                                            termin.
                                        </p>
                                    </div>
                                    {form.data.metode_pembayaran ===
                                        "cicil" && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                form.setData("payments", [
                                                    ...form.data.payments,
                                                    {
                                                        ...paymentTemplate(),
                                                        tanggal_jatuh_tempo:
                                                            form.data
                                                                .tanggal_spk,
                                                    },
                                                ])
                                            }
                                        >
                                            <PlusCircle size={16} /> Tambah
                                            Termin
                                        </Button>
                                    )}
                                </div>

                                {form.data.payments.map((payment, index) => (
                                    <div
                                        className="grid gap-3 rounded-lg bg-silver-soft/80 p-3 dark:bg-white/5 md:grid-cols-2 xl:grid-cols-[0.35fr_0.95fr_1fr_auto]"
                                        key={index}
                                    >
                                        <Input
                                            label="Termin"
                                            value={index + 1}
                                            readOnly
                                        />
                                        <Input
                                            label="Jatuh Tempo"
                                            type="date"
                                            value={payment.tanggal_jatuh_tempo}
                                            onChange={(event) =>
                                                setPayment(
                                                    index,
                                                    "tanggal_jatuh_tempo",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <CurrencyInput
                                            label="Nominal"
                                            value={payment.nominal}
                                            onChange={(value) =>
                                                setPayment(
                                                    index,
                                                    "nominal",
                                                    value,
                                                )
                                            }
                                            readOnly={
                                                form.data.metode_pembayaran ===
                                                "cash"
                                            }
                                        />
                                        <Input
                                            label="Keterangan"
                                            value={payment.keterangan}
                                            onChange={(event) =>
                                                form.setData(
                                                    "payments",
                                                    form.data.payments.map(
                                                        (item, paymentIndex) =>
                                                            paymentIndex ===
                                                            index
                                                                ? {
                                                                      ...item,
                                                                      keterangan:
                                                                          event
                                                                              .target
                                                                              .value,
                                                                  }
                                                                : item,
                                                    ),
                                                )
                                            }
                                        />
                                        <div className="flex items-end justify-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600"
                                                disabled={
                                                    form.data
                                                        .metode_pembayaran ===
                                                        "cash" ||
                                                    form.data.payments
                                                        .length === 1
                                                }
                                                onClick={() =>
                                                    form.setData(
                                                        "payments",
                                                        form.data.payments.filter(
                                                            (_, paymentIndex) =>
                                                                paymentIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <MinusCircle size={16} />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                                <div className="grid gap-1 text-right text-sm font-extrabold">
                                    <span className="text-ink-soft">
                                        Total jadwal pembayaran:{" "}
                                        {money(
                                            form.data.metode_pembayaran ===
                                                "cash"
                                                ? totalKontrak
                                                : totalPayment,
                                        )}
                                    </span>
                                    {form.data.metode_pembayaran ===
                                        "cicil" && (
                                        <span
                                            className={
                                                paymentIsBalanced
                                                    ? "text-emerald-600 dark:text-emerald-300"
                                                    : "text-red-600 dark:text-red-300"
                                            }
                                        >
                                            {paymentIsBalanced
                                                ? "Total termin sudah sesuai total pengajuan kredit."
                                                : `Selisih termin: ${money(Math.abs(paymentDifference))} ${paymentDifference > 0 ? "kurang" : "lebih"}.`}
                                        </span>
                                    )}
                                    {form.errors.payments && (
                                        <span className="text-red-600 dark:text-red-300">
                                            {form.errors.payments}
                                        </span>
                                    )}
                                </div>
                            </div>

                            <Textarea
                                label="Catatan SPK"
                                value={form.data.catatan}
                                error={form.errors.catatan}
                                onChange={(event) =>
                                    form.setData("catatan", event.target.value)
                                }
                            />
                        </Form>
                    )}

                <Modal
                    open={showUnitModal}
                    onClose={() => setShowUnitModal(false)}
                    title="Data Rumah"
                    size="xl"
                    footer={
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowUnitModal(false)}
                            >
                                Tutup
                            </Button>
                        </div>
                    }
                >
                    <div className="grid gap-4">
                        <Input
                            label="Cari Data Rumah"
                            value={unitSearch}
                            placeholder="Cari nomor rumah, kode, atau nama blok..."
                            onChange={(event) =>
                                setUnitSearch(event.target.value)
                            }
                        />

                        <div className="grid gap-4 lg:grid-cols-[1.25fr_0.75fr]">
                            {filteredDetailRumahOptions.length > 0 ? (
                                <div className="grid max-h-[58vh] grid-cols-2 gap-2 overflow-y-auto rounded-lg border border-silver-deep/60 bg-white/60 p-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 dark:border-white/10 dark:bg-white/5">
                                    {filteredDetailRumahOptions.map((unit) => (
                                        <button
                                            className={`flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-left text-[13px] font-bold whitespace-nowrap transition ${
                                                selectedDetailRumahIds.includes(
                                                    String(unit.value),
                                                )
                                                    ? "border-emerald-500 bg-emerald-500/10 text-white dark:text-white"
                                                    : "border-silver-deep/50 bg-white/70 text-white dark:border-white/10 dark:bg-white/5 dark:text-white"
                                            }`}
                                            key={unit.value}
                                            type="button"
                                            onClick={() =>
                                                toggleDetailRumah(unit.value)
                                            }
                                        >
                                            <input
                                                checked={selectedDetailRumahIds.includes(
                                                    String(unit.value),
                                                )}
                                                readOnly
                                                type="checkbox"
                                            />
                                            <span>{unit.label}</span>
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-lg border border-dashed border-silver-deep/60 p-4 text-sm text-ink-soft dark:border-white/10 dark:text-white/60">
                                    {form.data.perumahan_id
                                        ? "Tidak ada data rumah yang cocok."
                                        : "Pilih perumahan dulu supaya data rumah muncul."}
                                </div>
                            )}

                            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setSelectedUnits(
                                                filteredDetailRumahOptions.map(
                                                    (unit) => unit.value,
                                                ),
                                            )
                                        }
                                        disabled={
                                            filteredDetailRumahOptions.length ===
                                            0
                                        }
                                    >
                                        Pilih hasil cari
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            setSelectedUnits(
                                                detailRumahOptions.map(
                                                    (unit) => unit.value,
                                                ),
                                            )
                                        }
                                        disabled={
                                            detailRumahOptions.length === 0
                                        }
                                    >
                                        Pilih semua
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setSelectedUnits([])}
                                        disabled={
                                            detailRumahOptions.length === 0
                                        }
                                    >
                                        Kosongkan
                                    </Button>
                                </div>
                                <div className="mt-4 grid max-h-[34vh] grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3 lg:grid-cols-4">
                                    {selectedDetailRumahIds.length > 0 ? (
                                        selectedDetailRumahIds.map((unitId) => {
                                            const unit =
                                                detailRumahOptions.find(
                                                    (item) =>
                                                        String(item.value) ===
                                                        String(unitId),
                                                );
                                            if (!unit) return null;
                                            return (
                                                <div
                                                    className="rounded-lg border border-silver-deep/50 bg-white px-3 py-2 text-sm font-bold whitespace-nowrap dark:border-white/10 dark:bg-black/10"
                                                    key={unitId}
                                                >
                                                    {unit.label}
                                                </div>
                                            );
                                        })
                                    ) : (
                                        <p className="text-sm text-ink-soft">
                                            Belum ada unit yang dipilih.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </Modal>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                pageUrl,
                                { search },
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                    replace: true,
                                },
                            );
                        }}
                    >
                        <Input
                            className="md:max-w-md"
                            label="Pencarian"
                            value={search}
                            placeholder="Cari nomor SPK, pekerjaan, kontraktor..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Button type="submit">
                            <Search size={17} /> Cari
                        </Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {[
                                        "Nomor SPK",
                                        "Kontraktor",
                                        "Pekerjaan",
                                        "Lokasi",
                                        "Nilai Dasar",
                                        "Total",
                                        "Metode",
                                        "Persetujuan",
                                        ...(showPaymentSchedule
                                            ? ["Termin Pembayaran"]
                                            : []),
                                        "Status SPK",
                                        "Aksi",
                                    ].map((column) => (
                                        <th
                                            className="px-5 py-4 font-extrabold"
                                            key={column}
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-extrabold">
                                            {row.nomor_spk}
                                        </td>
                                        <td className="px-5 py-4 font-semibold">
                                            {row.kontraktor}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.judul_pekerjaan}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.perumahan} / {row.unit}
                                        </td>
                                        <td className="px-5 py-4 font-extrabold">
                                            {money(row.nilai_kontrak_dasar)}
                                        </td>
                                        <td className="px-5 py-4 font-extrabold">
                                            {money(row.nilai_kontrak)}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.metode_pembayaran === "cash"
                                                ? "Cash / Sekaligus"
                                                : "Cicil / Termin"}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.approval_role === "admin"
                                                ? "Admin"
                                                : "Manajer"}
                                        </td>
                                        {showPaymentSchedule && (
                                            <td className="px-5 py-4">
                                                <TableActions>
                                                    {row.payments.map(
                                                        (payment) => (
                                                            <div
                                                                className="rounded-lg border border-silver-deep/60 p-2 dark:border-white/10"
                                                                key={`${row.id}-${payment.termin_ke}`}
                                                            >
                                                                <p className="font-extrabold">
                                                                    Termin{" "}
                                                                    {
                                                                        payment.termin_ke
                                                                    }{" "}
                                                                    -{" "}
                                                                    {money(
                                                                        payment.nominal,
                                                                    )}
                                                                </p>
                                                                <p className="text-xs font-bold text-ink-soft">
                                                                    Jatuh tempo:{" "}
                                                                    {payment.tanggal_jatuh_tempo ??
                                                                        "-"}{" "}
                                                                    | Bayar:{" "}
                                                                    {payment.tanggal_pembayaran ??
                                                                        "-"}{" "}
                                                                    |{" "}
                                                                    {
                                                                        payment.status_label
                                                                    }
                                                                </p>
                                                                {(approvalOnly ||
                                                                    paymentOnly ||
                                                                    disbursementOnly) && (
                                                                    <p
                                                                        className={`text-xs font-bold ${row.hpp_plan_exists ? "text-emerald-600 dark:text-emerald-300" : "text-amber-600 dark:text-amber-300"}`}
                                                                    >
                                                                        {row.hpp_plan_exists
                                                                            ? `Rencana HPP ${row.hpp_plan_label}: ${money(row.hpp_plan_total)}`
                                                                            : `Peringatan: rencana HPP ${row.hpp_plan_label} belum diisi.`}
                                                                    </p>
                                                                )}
                                                                {payment.opname && (
                                                                    <p className="text-xs font-bold text-ink-soft">
                                                                        Opname:{" "}
                                                                        {
                                                                            payment.opname
                                                                        }
                                                                    </p>
                                                                )}
                                                                {(approvalOnly ||
                                                                    paymentOnly ||
                                                                    disbursementOnly) && (
                                                                    <div className="mt-2 flex flex-wrap gap-2">
                                                                        {paymentOnly &&
                                                                            permissions.canRequestPayment &&
                                                                            payment.status ===
                                                                                "menunggu_pengajuan" && (
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    variant="outline"
                                                                                    onClick={() =>
                                                                                        postPaymentAction(
                                                                                            row,
                                                                                            payment,
                                                                                            "request",
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Ajukan
                                                                                    Pembayaran
                                                                                </Button>
                                                                            )}
                                                                        {(approvalOnly ||
                                                                            disbursementOnly) &&
                                                                            permissions.canApprovePayment &&
                                                                            [
                                                                                "menunggu_approval_manager",
                                                                                "menunggu_approval_manajer",
                                                                            ].includes(
                                                                                payment.status,
                                                                            ) && (
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    variant="outline"
                                                                                    onClick={() =>
                                                                                        postPaymentAction(
                                                                                            row,
                                                                                            payment,
                                                                                            "approve",
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Setujui
                                                                                    Pencairan
                                                                                </Button>
                                                                            )}
                                                                        {paymentOnly &&
                                                                            permissions.canReleasePayment &&
                                                                            payment.status ===
                                                                                "menunggu_pembayaran_keuangan" && (
                                                                                <Button
                                                                                    type="button"
                                                                                    size="sm"
                                                                                    onClick={() =>
                                                                                        postPaymentAction(
                                                                                            row,
                                                                                            payment,
                                                                                            "release",
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    Bayar
                                                                                    oleh
                                                                                    Keuangan
                                                                                </Button>
                                                                            )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ),
                                                    )}
                                                </TableActions>
                                            </td>
                                        )}
                                        <td className="px-5 py-4 font-bold">
                                            {row.status}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.record_status_label}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    title="Detail SPK"
                                                    aria-label="Detail SPK"
                                                    onClick={() =>
                                                        router.get(
                                                            `${baseUrl}/${row.id}`,
                                                        )
                                                    }
                                                    className="w-9 gap-0 px-0"
                                                >
                                                    <Eye size={15} />
                                                </Button>
                                                {!approvalOnly &&
                                                    !paymentOnly &&
                                                    !disbursementOnly &&
                                                    permissions.canApproveSpk &&
                                                    row.can_approve && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            title="Setujui SPK"
                                                            aria-label="Setujui SPK"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/approve`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                            className="w-9 gap-0 px-0"
                                                        >
                                                            <CheckCircle2
                                                                size={15}
                                                            />
                                                        </Button>
                                                    )}
                                                {!approvalOnly &&
                                                    !paymentOnly &&
                                                    !disbursementOnly &&
                                                    permissions.canManageSpk && (
                                                        <>
                                                            {row.can_edit && (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    title="Ubah"
                                                                    aria-label="Ubah"
                                                                    onClick={() =>
                                                                        editRow(
                                                                            row,
                                                                        )
                                                                    }
                                                                    className="w-9 gap-0 px-0"
                                                                >
                                                                    <Edit3
                                                                        size={
                                                                            15
                                                                        }
                                                                    />
                                                                </Button>
                                                            )}
                                                            {row.can_lock && (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    title="Kunci"
                                                                    aria-label="Kunci"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            `${baseUrl}/${row.id}/lock`,
                                                                            {},
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        )
                                                                    }
                                                                    className="w-9 gap-0 px-0"
                                                                >
                                                                    <Lock
                                                                        size={
                                                                            15
                                                                        }
                                                                    />
                                                                </Button>
                                                            )}
                                                            {row.can_unlock && (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    title="Buka Kunci"
                                                                    aria-label="Buka Kunci"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            `${baseUrl}/${row.id}/unlock`,
                                                                            {},
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        )
                                                                    }
                                                                    className="w-9 gap-0 px-0"
                                                                >
                                                                    <Unlock
                                                                        size={
                                                                            15
                                                                        }
                                                                    />
                                                                </Button>
                                                            )}
                                                            {row.can_cancel && (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    title="Cancel SPK"
                                                                    aria-label="Cancel SPK"
                                                                    onClick={() =>
                                                                        cancelRow(
                                                                            row,
                                                                        )
                                                                    }
                                                                    className="w-9 gap-0 px-0 text-rose-600"
                                                                >
                                                                    <XCircle
                                                                        size={
                                                                            15
                                                                        }
                                                                    />
                                                                </Button>
                                                            )}
                                                            {row.can_delete && (
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    title="Hapus"
                                                                    aria-label="Hapus"
                                                                    onClick={() =>
                                                                        destroyRow(
                                                                            row,
                                                                        )
                                                                    }
                                                                    className="w-9 gap-0 px-0"
                                                                >
                                                                    <Trash2
                                                                        size={
                                                                            15
                                                                        }
                                                                    />
                                                                </Button>
                                                            )}
                                                        </>
                                                    )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center font-bold text-ink-soft"
                                            colSpan={
                                                showPaymentSchedule ? 11 : 10
                                            }
                                        >
                                            Belum ada SPK kontraktor.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "SPK Kontraktor"}>
        {page}
    </AdminLayout>
);
