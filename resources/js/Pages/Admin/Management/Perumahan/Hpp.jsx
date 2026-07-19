import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    ChevronDown,
    Edit3,
    LoaderCircle,
    MinusCircle,
    Plus,
    Save,
    Trash2,
    XCircle,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import {
    Button,
    CurrencyInput,
    Input,
    ModalForm,
    TableActions,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { useResourcePermissions } from "../../../../Utils/permissions";

function money(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function calculateAmount(row) {
    const volume = Number(row?.volume || 0);
    const hargaSatuan = Number(row?.harga_satuan || 0);

    return String(row?.satuan ?? "").trim() === "%"
        ? (volume * hargaSatuan) / 100
        : volume * hargaSatuan;
}

function normalizeDraftRow(row, index = 0) {
    const jumlahRab = calculateAmount(row);
    const jumlahRealisasi = Number(row?.jumlah_realisasi ?? 0);

    return {
        ...row,
        draft_id:
            row?.draft_id ??
            (row?.id ? `item-${row.id}` : `new-${Date.now()}-${index}`),
        kelompok_hpp_id: row?.kelompok_hpp_id
            ? String(row.kelompok_hpp_id)
            : "",
        tahapan_pembangunan_id: row?.tahapan_pembangunan_id
            ? String(row.tahapan_pembangunan_id)
            : "",
        nama_pekerjaan: row?.nama_pekerjaan ?? "",
        volume: row?.volume ?? 0,
        satuan: row?.satuan === "-" ? "" : (row?.satuan ?? ""),
        harga_satuan: row?.harga_satuan ?? 0,
        urutan: row?.urutan ?? index + 1,
        jumlah_rab: jumlahRab,
        jumlah_realisasi: jumlahRealisasi,
        sisa_anggaran: jumlahRab - jumlahRealisasi,
    };
}

function isRequiredStage(group) {
    return (
        String(group?.title ?? "")
            .trim()
            .toUpperCase() === "IV RAB BANGUNAN"
    );
}

function isAutomaticStage(group, context) {
    return context === "kawasan" && isRequiredStage(group);
}

function stageItemTemplate() {
    return {
        kelompok_hpp_id: "",
        nama_pekerjaan: "",
        volume: 0,
        satuan: "Ls",
        harga_satuan: 0,
        urutan: 0,
    };
}

function Hpp({
    title = "HPP Perumahan",
    backLabel = "Perumahan",
    metaLine,
    perumahan = {},
    rows = [],
    summary = { jumlah_rab: 0, jumlah_realisasi: 0, sisa_anggaran: 0 },
    options,
    baseUrl,
    detailUrl,
    hppUrl,
    stageUrl,
    stageBaseUrl,
    hppContext = "kawasan",
    hppOwner = {},
}) {
    const [stageOpen, setStageOpen] = useState(false);
    const [editingStage, setEditingStage] = useState(null);
    const [search, setSearch] = useState("");
    const [openStage, setOpenStage] = useState(0);
    const [savingStage, setSavingStage] = useState(null);
    const [draftRows, setDraftRows] = useState(() =>
        rows.map(normalizeDraftRow),
    );
    const permissionKey = hppContext === "unit" ? "rab-unit" : "rab-perumahan";
    const hppPermissions = useResourcePermissions(permissionKey, hppUrl);
    const canUpdateStructure = hppPermissions.canUpdateExact;
    const canCreateStructure = hppPermissions.canCreateExact;
    const canDeleteStructure = hppPermissions.canDeleteExact;
    const canManageValues = hppPermissions.canManage;
    const canManageStages = hppContext !== "unit";
    const canManageStructure = canUpdateStructure || canDeleteStructure;
    const pageTitle = `${title} ${perumahan.nama_perusahaan ?? ""}`.trim();
    const stageForm = useForm({
        konteks: hppContext,
        nama_tahapan: "",
        urutan: (options?.tahapanHpps?.length ?? 0) + 1,
        perumahan_id: hppOwner.perumahan_id ?? "",
        detail_rumah_id: hppOwner.detail_rumah_id ?? "",
        items: [stageItemTemplate()],
    });

    useEffect(() => {
        setDraftRows(rows.map(normalizeDraftRow));
    }, [rows]);

    const pageSummary = useMemo(
        () =>
            draftRows.reduce(
                (carry, row) => {
                    const jumlahRab = calculateAmount(row);
                    const jumlahRealisasi = Number(row.jumlah_realisasi ?? 0);

                    carry.jumlah_rab += jumlahRab;
                    carry.jumlah_realisasi += jumlahRealisasi;
                    carry.sisa_anggaran += jumlahRab - jumlahRealisasi;

                    return carry;
                },
                { jumlah_rab: 0, jumlah_realisasi: 0, sisa_anggaran: 0 },
            ),
        [draftRows],
    );

    const filteredRows = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        if (!keyword) {
            return draftRows;
        }

        return draftRows.filter((row) =>
            [
                row.tanggal,
                row.tahapan_nama,
                row.nama_pekerjaan,
                row.volume,
                row.satuan,
                row.harga_satuan,
                row.jumlah_rab,
                row.jumlah_realisasi,
                row.sisa_anggaran,
            ].some((value) =>
                String(value ?? "")
                    .toLowerCase()
                    .includes(keyword),
            ),
        );
    }, [draftRows, search]);

    const stageGroups = useMemo(() => {
        const grouped = new Map();
        const keyword = search.trim().toLowerCase();

        (options?.tahapanHpps ?? []).forEach((stage) => {
            if (
                keyword &&
                !String(stage.label ?? stage.nama_tahapan ?? "")
                    .toLowerCase()
                    .includes(keyword)
            ) {
                return;
            }

            grouped.set(stage.value, {
                key: stage.value,
                tahapan_pembangunan_id: stage.value,
                title: stage.nama_tahapan ?? stage.label ?? "Tanpa Tahap",
                rows: [],
                jumlah_rab: 0,
                jumlah_realisasi: 0,
                sisa_anggaran: 0,
            });
        });

        filteredRows.forEach((row) => {
            const key =
                row.tahapan_pembangunan_id ||
                `none-${row.tahapan_nama ?? "tanpa-tahap"}`;

            if (!grouped.has(key)) {
                grouped.set(key, {
                    key,
                    tahapan_pembangunan_id: row.tahapan_pembangunan_id ?? "",
                    title: row.tahapan_nama ?? "Tanpa Tahap",
                    rows: [],
                    jumlah_rab: 0,
                    jumlah_realisasi: 0,
                    sisa_anggaran: 0,
                });
            }

            const group = grouped.get(key);
            const jumlahRab = calculateAmount(row);
            const jumlahRealisasi = Number(row.jumlah_realisasi ?? 0);
            group.rows.push({
                ...row,
                jumlah_rab: jumlahRab,
                sisa_anggaran: jumlahRab - jumlahRealisasi,
            });
            group.jumlah_rab += jumlahRab;
            group.jumlah_realisasi += jumlahRealisasi;
            group.sisa_anggaran += jumlahRab - jumlahRealisasi;
        });

        return Array.from(grouped.values());
    }, [filteredRows, options?.tahapanHpps, search]);

    const updateDraftRow = (draftId, key, value) => {
        setDraftRows((currentRows) =>
            currentRows.map((row) =>
                row.draft_id === draftId
                    ? normalizeDraftRow({ ...row, [key]: value })
                    : row,
            ),
        );
    };

    const addItemToStage = (group) => {
        const draftId = `new-${Date.now()}`;
        setDraftRows((currentRows) => {
            const stageRows = currentRows.filter(
                (row) =>
                    String(row.tahapan_pembangunan_id) ===
                    String(group.tahapan_pembangunan_id),
            );
            const nextOrder =
                stageRows.reduce(
                    (highest, row) =>
                        Math.max(highest, Number(row.urutan) || 0),
                    0,
                ) + 1;

            return [
                ...currentRows,
                normalizeDraftRow(
                    {
                        draft_id: draftId,
                        id: null,
                        tahapan_pembangunan_id: group.tahapan_pembangunan_id,
                        tahapan_nama: group.title,
                        kelompok_hpp_id: "",
                        nama_pekerjaan: "",
                        volume: 0,
                        satuan: "Ls",
                        harga_satuan: 0,
                        urutan: nextOrder,
                        jumlah_realisasi: 0,
                    },
                    currentRows.length,
                ),
            ];
        });
    };

    const removeDraftItem = (row) => {
        if (!row.id) {
            setDraftRows((currentRows) =>
                currentRows.filter((item) => item.draft_id !== row.draft_id),
            );
            return;
        }

        deleteItem(row);
    };

    const hppPayload = () =>
        draftRows.map((row, index) => ({
            kelompok_hpp_id: row.kelompok_hpp_id
                ? String(row.kelompok_hpp_id)
                : "",
            tahapan_pembangunan_id: row.tahapan_pembangunan_id
                ? String(row.tahapan_pembangunan_id)
                : "",
            nama_pekerjaan: row.nama_pekerjaan ?? "",
            volume: row.volume ?? 0,
            satuan: row.satuan ?? "",
            harga_satuan: row.harga_satuan ?? 0,
            urutan: row.urutan ?? index + 1,
        }));

    const saveStageItems = (group) => {
        setSavingStage(group.key);
        router.put(
            hppUrl,
            { items: hppPayload() },
            {
                preserveScroll: true,
                onFinish: () => setSavingStage(null),
            },
        );
    };

    const submitStage = (event) => {
        event.preventDefault();
        const requestOptions = {
            preserveScroll: true,
            onSuccess: () => {
                stageForm.reset("nama_tahapan", "urutan");
                stageForm.setData("items", [stageItemTemplate()]);
                setStageOpen(false);
                setEditingStage(null);
            },
        };

        if (editingStage) {
            stageForm.put(
                `${stageBaseUrl}/${editingStage.value}`,
                requestOptions,
            );
            return;
        }

        stageForm.post(stageUrl, requestOptions);
    };

    const openCreateStage = () => {
        setEditingStage(null);
        stageForm.setData({
            konteks: hppContext,
            nama_tahapan: "",
            urutan: (options?.tahapanHpps?.length ?? 0) + 1,
            perumahan_id: hppOwner.perumahan_id ?? "",
            detail_rumah_id: hppOwner.detail_rumah_id ?? "",
            items: [stageItemTemplate()],
        });
        setStageOpen(true);
    };

    const openEditStage = (group) => {
        const stage = (options?.tahapanHpps ?? []).find(
            (item) =>
                String(item.value) === String(group.tahapan_pembangunan_id),
        );
        setEditingStage(stage);
        stageForm.setData({
            konteks: hppContext,
            nama_tahapan: group.title,
            urutan: stage?.urutan ?? 1,
            perumahan_id: hppOwner.perumahan_id ?? "",
            detail_rumah_id: hppOwner.detail_rumah_id ?? "",
            items: [stageItemTemplate()],
        });
        setStageOpen(true);
    };

    const deleteStage = (group) => {
        if (
            !window.confirm(
                `Hapus tahap "${group.title}" beserta ${group.rows.length} uraian pekerjaan di dalamnya?`,
            )
        ) {
            return;
        }

        router.delete(`${stageBaseUrl}/${group.tahapan_pembangunan_id}`, {
            preserveScroll: true,
        });
    };

    const deleteItem = (row) => {
        if (
            !window.confirm(`Hapus uraian pekerjaan "${row.nama_pekerjaan}"?`)
        ) {
            return;
        }

        router.delete(`${hppUrl}/${row.id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title={pageTitle} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <div className="mb-3 flex flex-wrap gap-2">
                                <Button
                                    as={Link}
                                    href={baseUrl}
                                    variant="ghost"
                                    size="sm"
                                >
                                    <ArrowLeft size={16} /> {backLabel}
                                </Button>
                                <Button
                                    as={Link}
                                    href={detailUrl}
                                    variant="outline"
                                    size="sm"
                                >
                                    Detail Rumah
                                </Button>
                            </div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                                {title}
                            </p>
                            <h2 className="mt-2 font-display text-3xl font-extrabold">
                                {perumahan.nama_perusahaan}
                            </h2>
                            <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">
                                {metaLine ??
                                    `${perumahan.cabang ?? "-"} | ${perumahan.alamat ?? "-"}`}
                            </p>
                        </div>
                    </div>

                    <div className="mt-5 grid gap-3 md:grid-cols-3">
                        <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                                Jumlah RAB
                            </p>
                            <p className="mt-1 text-xl font-extrabold">
                                {money(pageSummary.jumlah_rab)}
                            </p>
                        </div>
                        <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                                Jumlah Realisasi
                            </p>
                            <p className="mt-1 text-xl font-extrabold">
                                {money(pageSummary.jumlah_realisasi)}
                            </p>
                        </div>
                        <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                                Sisa Anggaran
                            </p>
                            <p className="mt-1 text-xl font-extrabold">
                                {money(pageSummary.sisa_anggaran)}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-3 border-b border-silver-deep/60 px-5 py-4 dark:border-white/10 md:flex-row md:items-center md:justify-between">
                        <label className="flex items-center gap-2 text-xs font-bold text-ink-soft">
                            Search:
                            <input
                                className="w-full rounded-lg border border-silver-deep bg-white px-3 py-2 text-xs font-semibold text-ink outline-none transition placeholder:text-ink-soft/60 focus:border-ink dark:border-white/10 dark:bg-white/8 dark:text-white md:w-72"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari tahap, pekerjaan, satuan, harga..."
                            />
                        </label>
                        {canCreateStructure && canManageStages && (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    type="button"
                                    onClick={openCreateStage}
                                >
                                    <Plus size={15} /> Tambah Tahap
                                </Button>
                            </div>
                        )}
                    </div>
                    <div className="divide-y divide-silver-deep/60 dark:divide-white/10">
                        {stageGroups.map((group, groupIndex) => (
                            <div key={group.key}>
                                <div className="flex items-center gap-2 px-5 py-4 transition hover:bg-silver/60 dark:hover:bg-white/5">
                                    <button
                                        className="flex min-w-0 flex-1 flex-col gap-3 text-left md:flex-row md:items-center md:justify-between"
                                        type="button"
                                        onClick={() =>
                                            setOpenStage(
                                                openStage === groupIndex
                                                    ? null
                                                    : groupIndex,
                                            )
                                        }
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink text-sm font-extrabold text-white dark:bg-white dark:text-ink">
                                                {groupIndex + 1}
                                            </span>
                                            <div>
                                                <p className="text-sm font-extrabold text-ink dark:text-white">
                                                    {group.title}
                                                </p>
                                                <p className="text-xs font-bold text-ink-soft dark:text-white/50">
                                                    {group.rows.length}{" "}
                                                    pekerjaan
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 text-xs font-extrabold text-ink-soft dark:text-white/60">
                                            <span>
                                                RAB {money(group.jumlah_rab)}
                                            </span>
                                            <span>
                                                Realisasi{" "}
                                                {money(group.jumlah_realisasi)}
                                            </span>
                                            <span>
                                                Sisa{" "}
                                                {money(group.sisa_anggaran)}
                                            </span>
                                            <ChevronDown
                                                className={`transition ${openStage === groupIndex ? "rotate-180" : ""}`}
                                                size={17}
                                            />
                                        </div>
                                    </button>
                                    {canManageStructure &&
                                        canManageStages &&
                                        group.tahapan_pembangunan_id && (
                                            <div className="flex shrink-0 gap-1">
                                                {canUpdateStructure &&
                                                    !isAutomaticStage(
                                                        group,
                                                        hppContext,
                                                    ) && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            type="button"
                                                            onClick={() =>
                                                                openEditStage(
                                                                    group,
                                                                )
                                                            }
                                                            title="Ubah tahap dan urutan"
                                                        >
                                                            <Edit3 size={15} />
                                                        </Button>
                                                    )}
                                                {canDeleteStructure &&
                                                    !isRequiredStage(group) && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            type="button"
                                                            onClick={() =>
                                                                deleteStage(
                                                                    group,
                                                                )
                                                            }
                                                            title="Hapus tahap"
                                                        >
                                                            <Trash2 size={15} />
                                                        </Button>
                                                    )}
                                            </div>
                                        )}
                                </div>

                                {openStage === groupIndex && (
                                    <div className="px-5 pb-5">
                                        {canCreateStructure &&
                                            !isAutomaticStage(
                                                group,
                                                hppContext,
                                            ) && (
                                                <div className="mb-3 flex justify-end">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        type="button"
                                                        onClick={() =>
                                                            addItemToStage(
                                                                group,
                                                            )
                                                        }
                                                    >
                                                        <Plus size={15} />{" "}
                                                        Tambah Item Tahap Ini
                                                    </Button>
                                                </div>
                                            )}
                                        <div className="overflow-x-auto rounded-lg border border-silver-deep/60 dark:border-white/10">
                                            <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                                                <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                                    <tr>
                                                        {[
                                                            "No",
                                                            "Nama Pekerjaan",
                                                            "Volume",
                                                            "Satuan",
                                                            "Harga Satuan",
                                                            "Total",
                                                            "Jumlah Realisasi",
                                                            "Sisa Anggaran",
                                                            ...(canDeleteStructure &&
                                                            !isAutomaticStage(
                                                                group,
                                                                hppContext,
                                                            )
                                                                ? ["Aksi"]
                                                                : []),
                                                        ].map((column) => (
                                                            <th
                                                                className="px-4 py-3 font-extrabold"
                                                                key={column}
                                                            >
                                                                {column}
                                                            </th>
                                                        ))}
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                                    {group.rows.map(
                                                        (row, index) => (
                                                            <tr
                                                                className="transition hover:bg-silver/70 dark:hover:bg-white/5"
                                                                key={
                                                                    row.draft_id ??
                                                                    (row.id
                                                                        ? `hpp-item-${row.id}`
                                                                        : `${group.key}-${index}`)
                                                                }
                                                            >
                                                                <td className="px-4 py-3 font-semibold">
                                                                    {index + 1}
                                                                </td>
                                                                <td className="min-w-72 px-4 py-3 font-semibold text-ink dark:text-white">
                                                                    {canManageValues &&
                                                                    !isAutomaticStage(
                                                                        group,
                                                                        hppContext,
                                                                    ) ? (
                                                                        <Input
                                                                            value={
                                                                                row.nama_pekerjaan
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                updateDraftRow(
                                                                                    row.draft_id,
                                                                                    "nama_pekerjaan",
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            placeholder="Nama pekerjaan"
                                                                        />
                                                                    ) : (
                                                                        (row.nama_pekerjaan ??
                                                                        row.kelompok_hpp_nama ??
                                                                        "-")
                                                                    )}
                                                                </td>
                                                                <td className="min-w-32 px-4 py-3 font-semibold">
                                                                    {canManageValues &&
                                                                    !isAutomaticStage(
                                                                        group,
                                                                        hppContext,
                                                                    ) ? (
                                                                        <Input
                                                                            type="number"
                                                                            min="0"
                                                                            step="0.01"
                                                                            value={
                                                                                row.volume
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                updateDraftRow(
                                                                                    row.draft_id,
                                                                                    "volume",
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                        />
                                                                    ) : (
                                                                        Number(
                                                                            row.volume ??
                                                                                0,
                                                                        ).toLocaleString(
                                                                            "id-ID",
                                                                        )
                                                                    )}
                                                                </td>
                                                                <td className="min-w-28 px-4 py-3 font-semibold">
                                                                    {canManageValues &&
                                                                    !isAutomaticStage(
                                                                        group,
                                                                        hppContext,
                                                                    ) ? (
                                                                        <Input
                                                                            value={
                                                                                row.satuan
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                updateDraftRow(
                                                                                    row.draft_id,
                                                                                    "satuan",
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            placeholder="Ls/M2/Unit"
                                                                        />
                                                                    ) : (
                                                                        row.satuan
                                                                    )}
                                                                </td>
                                                                <td className="min-w-44 px-4 py-3 font-semibold">
                                                                    {canManageValues &&
                                                                    !isAutomaticStage(
                                                                        group,
                                                                        hppContext,
                                                                    ) ? (
                                                                        <CurrencyInput
                                                                            value={
                                                                                row.harga_satuan
                                                                            }
                                                                            onChange={(
                                                                                value,
                                                                            ) =>
                                                                                updateDraftRow(
                                                                                    row.draft_id,
                                                                                    "harga_satuan",
                                                                                    value,
                                                                                )
                                                                            }
                                                                        />
                                                                    ) : (
                                                                        money(
                                                                            row.harga_satuan,
                                                                        )
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-3 font-extrabold">
                                                                    {money(
                                                                        row.jumlah_rab,
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-3 font-extrabold">
                                                                    {money(
                                                                        row.jumlah_realisasi,
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-3 font-extrabold">
                                                                    {money(
                                                                        row.sisa_anggaran,
                                                                    )}
                                                                </td>
                                                                {canDeleteStructure &&
                                                                    !isAutomaticStage(
                                                                        group,
                                                                        hppContext,
                                                                    ) && (
                                                                        <td className="px-4 py-3">
                                                                            <TableActions>
                                                                                <Button
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    type="button"
                                                                                    onClick={() =>
                                                                                        removeDraftItem(
                                                                                            row,
                                                                                        )
                                                                                    }
                                                                                    title="Hapus uraian"
                                                                                >
                                                                                    <Trash2
                                                                                        size={
                                                                                            15
                                                                                        }
                                                                                    />
                                                                                </Button>
                                                                            </TableActions>
                                                                        </td>
                                                                    )}
                                                            </tr>
                                                        ),
                                                    )}
                                                    <tr className="bg-silver-soft/80 font-extrabold dark:bg-white/5">
                                                        <td
                                                            className="px-4 py-3 text-right"
                                                            colSpan={5}
                                                        >
                                                            SUB TOTAL{" "}
                                                            {group.title}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {money(
                                                                group.jumlah_rab,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {money(
                                                                group.jumlah_realisasi,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {money(
                                                                group.sisa_anggaran,
                                                            )}
                                                        </td>
                                                        {canDeleteStructure &&
                                                            !isAutomaticStage(
                                                                group,
                                                                hppContext,
                                                            ) && <td />}
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        {canManageValues &&
                                            !isAutomaticStage(
                                                group,
                                                hppContext,
                                            ) && (
                                                <div className="mt-4 flex justify-end">
                                                    <Button
                                                        type="button"
                                                        onClick={() =>
                                                            saveStageItems(
                                                                group,
                                                            )
                                                        }
                                                        disabled={
                                                            savingStage ===
                                                            group.key
                                                        }
                                                    >
                                                        {savingStage ===
                                                        group.key ? (
                                                            <LoaderCircle
                                                                className="animate-spin"
                                                                size={17}
                                                            />
                                                        ) : (
                                                            <Save size={17} />
                                                        )}
                                                        {savingStage ===
                                                        group.key
                                                            ? "Menyimpan..."
                                                            : `Simpan ${group.title}`}
                                                    </Button>
                                                </div>
                                            )}
                                    </div>
                                )}
                            </div>
                        ))}
                        {stageGroups.length === 0 && (
                            <div className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50">
                                Data HPP tidak ditemukan.
                            </div>
                        )}
                    </div>
                </section>
            </div>

            {stageOpen &&
                (editingStage ? canUpdateStructure : canCreateStructure) && (
                    <ModalForm
                        open={stageOpen}
                        onClose={() => {
                            setStageOpen(false);
                            setEditingStage(null);
                        }}
                        title={`${editingStage ? "Ubah" : "Tambah"} Tahap HPP ${hppContext === "unit" ? "Unit" : "Kawasan"}`}
                        description="Tentukan nama tahap dan posisinya pada urutan accordion HPP."
                        onSubmit={submitStage}
                        actions={
                            <>
                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => setStageOpen(false)}
                                >
                                    <XCircle size={17} /> Batal
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={stageForm.processing}
                                >
                                    <Plus size={17} />{" "}
                                    {stageForm.processing
                                        ? "Menyimpan..."
                                        : editingStage
                                          ? "Simpan Tahap"
                                          : "Tambah Tahap"}
                                </Button>
                            </>
                        }
                    >
                        <div className="grid gap-3 md:grid-cols-2">
                            <Input
                                label="Nama Tahap"
                                value={stageForm.data.nama_tahapan}
                                onChange={(event) =>
                                    stageForm.setData(
                                        "nama_tahapan",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="Urutan"
                                type="number"
                                value={stageForm.data.urutan}
                                onChange={(event) =>
                                    stageForm.setData(
                                        "urutan",
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        {!editingStage && (
                            <div className="grid gap-3 rounded-lg border border-silver-deep/60 p-4 dark:border-white/10">
                                <div className="flex items-center justify-between gap-3">
                                    <p className="text-sm font-extrabold">
                                        Item Pekerjaan Awal
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            stageForm.setData("items", [
                                                ...stageForm.data.items,
                                                stageItemTemplate(),
                                            ])
                                        }
                                    >
                                        <Plus size={14} /> Tambah Item
                                    </Button>
                                </div>
                                {(stageForm.data.items ?? []).map(
                                    (item, index) => (
                                        <div
                                            className="grid gap-3 rounded-lg bg-silver-soft/80 p-3 dark:bg-white/5 md:grid-cols-2 xl:grid-cols-[1.6fr_0.65fr_0.65fr_0.9fr_auto]"
                                            key={index}
                                        >
                                            <Input
                                                label={`Nama Pekerjaan ${index + 1}`}
                                                value={item.nama_pekerjaan}
                                                onChange={(event) =>
                                                    stageForm.setData(
                                                        "items",
                                                        stageForm.data.items.map(
                                                            (row, rowIndex) =>
                                                                rowIndex ===
                                                                index
                                                                    ? {
                                                                          ...row,
                                                                          nama_pekerjaan:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : row,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Volume"
                                                type="number"
                                                value={item.volume}
                                                onChange={(event) =>
                                                    stageForm.setData(
                                                        "items",
                                                        stageForm.data.items.map(
                                                            (row, rowIndex) =>
                                                                rowIndex ===
                                                                index
                                                                    ? {
                                                                          ...row,
                                                                          volume: event
                                                                              .target
                                                                              .value,
                                                                      }
                                                                    : row,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Satuan"
                                                value={item.satuan}
                                                onChange={(event) =>
                                                    stageForm.setData(
                                                        "items",
                                                        stageForm.data.items.map(
                                                            (row, rowIndex) =>
                                                                rowIndex ===
                                                                index
                                                                    ? {
                                                                          ...row,
                                                                          satuan: event
                                                                              .target
                                                                              .value,
                                                                      }
                                                                    : row,
                                                        ),
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Harga Satuan"
                                                type="number"
                                                value={item.harga_satuan}
                                                onChange={(event) =>
                                                    stageForm.setData(
                                                        "items",
                                                        stageForm.data.items.map(
                                                            (row, rowIndex) =>
                                                                rowIndex ===
                                                                index
                                                                    ? {
                                                                          ...row,
                                                                          harga_satuan:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : row,
                                                        ),
                                                    )
                                                }
                                            />
                                            <div className="flex items-end justify-end">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    className="text-red-600"
                                                    disabled={
                                                        stageForm.data.items
                                                            .length === 1
                                                    }
                                                    onClick={() =>
                                                        stageForm.setData(
                                                            "items",
                                                            stageForm.data.items.filter(
                                                                (_, rowIndex) =>
                                                                    rowIndex !==
                                                                    index,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <MinusCircle size={14} />
                                                </Button>
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                        {stageForm.errors.nama_tahapan && (
                            <span className="text-xs font-bold text-red-600">
                                {stageForm.errors.nama_tahapan}
                            </span>
                        )}
                    </ModalForm>
                )}
        </>
    );
}

Hpp.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "HPP Perumahan"}>
        {page}
    </AdminLayout>
);

export default Hpp;
