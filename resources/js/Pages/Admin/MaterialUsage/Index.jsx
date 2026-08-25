import { Head, router, useForm } from "@inertiajs/react";
import {
    CheckCircle2,
    Edit3,
    Eye,
    Lock,
    MinusCircle,
    PackageCheck,
    PlusCircle,
    Search,
    Trash2,
    Unlock,
    X,
} from "lucide-react";
import { useMemo, useState } from "react";
import Pagination from "../../../Components/Pagination";
import {
    Button,
    Dropdown,
    Form,
    Input,
    Modal,
    Textarea,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { scopedTahapanOptions } from "../../../Utils/tahapanOptions";

const itemTemplate = () => ({
    site_material_stock_id: "",
    material_unit_id: "",
    qty: "",
    satuan: "",
});

function ErrorSummary({ errors }) {
    const messages = Object.values(errors ?? {}).filter(Boolean);
    if (!messages.length) return null;
    return (
        <div className="rounded-lg border border-red-300 bg-red-50 p-4 text-sm font-bold text-red-700">
            {messages.map((message) => (
                <p key={message}>{message}</p>
            ))}
        </div>
    );
}

export default function Index({
    title,
    baseUrl,
    rows = { data: [], links: [] },
    filters = {},
    options = {},
    siteStockRows = [],
    permissions = {},
    qualityUpgrade = null,
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [filterPerumahan, setFilterPerumahan] = useState(
        filters.perumahan_id ?? "",
    );
    const [filterUnit, setFilterUnit] = useState(filters.detail_rumah_id ?? "");
    const [filterTahapan, setFilterTahapan] = useState(
        filters.tahapan_pembangunan_id ?? "",
    );
    const [editing, setEditing] = useState(null);
    const [detail, setDetail] = useState(null);
    const canCreate = permissions.canCreate ?? false;
    const canUpdate = permissions.canUpdate ?? false;
    const canDelete = permissions.canDelete ?? false;
    const canLock = permissions.canLock ?? false;
    const canUnlock = permissions.canUnlock ?? false;
    const form = useForm({
        tanggal: new Date().toISOString().slice(0, 10),
        perumahan_id: qualityUpgrade?.perumahan_id ?? "",
        detail_rumah_id: qualityUpgrade?.detail_rumah_id ?? "",
        tahapan_pembangunan_id: "",
        progress_pembangunan_id: "",
        quality_upgrade_contract_id: qualityUpgrade?.id ?? "",
        quality_upgrade_contract_item_id: qualityUpgrade?.items?.[0]?.value ?? "",
        keterangan: "",
        foto: null,
        items: [itemTemplate()],
    });

    const tahapanPembangunansUnit =
        options.tahapanPembangunansUnit ?? options.tahapanPembangunans ?? [];
    const tahapanPembangunansKawasan =
        options.tahapanPembangunansKawasan ?? options.tahapanPembangunans ?? [];
    const resolveScopedValue = (selectedValue, fallbackValue) =>
        selectedValue !== undefined &&
        selectedValue !== null &&
        selectedValue !== ""
            ? selectedValue
            : fallbackValue;
    const tahapanPembangunans = useMemo(
        () =>
            scopedTahapanOptions(
                form.data.detail_rumah_id
                    ? tahapanPembangunansUnit
                    : tahapanPembangunansKawasan,
                form.data.perumahan_id,
                form.data.detail_rumah_id,
            ),
        [
            form.data.detail_rumah_id,
            form.data.perumahan_id,
            tahapanPembangunansKawasan,
            tahapanPembangunansUnit,
        ],
    );
    const filterTahapanOptions = useMemo(
        () =>
            scopedTahapanOptions(
                filterUnit
                    ? tahapanPembangunansUnit
                    : tahapanPembangunansKawasan,
                filterPerumahan,
                filterUnit,
            ),
        [
            filterPerumahan,
            filterUnit,
            tahapanPembangunansKawasan,
            tahapanPembangunansUnit,
        ],
    );
    const unitOptions = useMemo(
        () =>
            (options.detailRumahs ?? []).filter(
                (row) =>
                    !form.data.perumahan_id ||
                    row.perumahan_id === String(form.data.perumahan_id),
            ),
        [form.data.perumahan_id, options.detailRumahs],
    );
    const filterUnitOptions = useMemo(
        () =>
            (options.detailRumahs ?? []).filter(
                (row) =>
                    !filterPerumahan ||
                    row.perumahan_id === String(filterPerumahan),
            ),
        [filterPerumahan, options.detailRumahs],
    );
    const progressOptions = useMemo(
        () =>
            (options.progressPembangunans ?? []).filter(
                (row) =>
                    (!form.data.perumahan_id ||
                        row.perumahan_id === String(form.data.perumahan_id)) &&
                    (!form.data.detail_rumah_id ||
                        row.detail_rumah_id ===
                            String(form.data.detail_rumah_id)) &&
                    (!form.data.tahapan_pembangunan_id ||
                        row.tahapan_pembangunan_id ===
                            String(form.data.tahapan_pembangunan_id)),
            ),
        [
            form.data.perumahan_id,
            form.data.detail_rumah_id,
            form.data.tahapan_pembangunan_id,
            options.progressPembangunans,
        ],
    );
    const stockOptions = useMemo(
        () =>
            (options.siteStocks ?? []).filter(
                (row) =>
                    (!form.data.perumahan_id ||
                        row.perumahan_id === String(form.data.perumahan_id)) &&
                    (!form.data.detail_rumah_id ||
                        row.detail_rumah_id ===
                            String(form.data.detail_rumah_id)) &&
                    (!form.data.tahapan_pembangunan_id ||
                        row.tahapan_pembangunan_id ===
                            String(form.data.tahapan_pembangunan_id)),
            ),
        [
            form.data.perumahan_id,
            form.data.detail_rumah_id,
            form.data.tahapan_pembangunan_id,
            options.siteStocks,
        ],
    );

    const setItem = (index, key, value, selected) => {
        form.setData(
            "items",
            form.data.items.map((item, itemIndex) =>
                itemIndex === index
                    ? {
                          ...item,
                          [key]: value,
                          material_unit_id:
                              key === "site_material_stock_id"
                                  ? String(selected?.base_unit_id ?? "")
                                  : item.material_unit_id,
                          satuan:
                              key === "site_material_stock_id"
                                  ? (selected?.unit_options?.find(
                                        (unit) =>
                                            String(unit.value) ===
                                            String(selected?.base_unit_id),
                                    )?.symbol ??
                                    selected?.satuan ??
                                    "")
                                  : key === "material_unit_id"
                                    ? (stockOptions
                                          .find(
                                              (stock) =>
                                                  String(stock.value) ===
                                                  String(
                                                      item.site_material_stock_id,
                                                  ),
                                          )
                                          ?.unit_options?.find(
                                              (unit) =>
                                                  String(unit.value) ===
                                                  String(value),
                                          )?.symbol ?? item.satuan)
                                    : item.satuan,
                      }
                    : item,
            ),
        );
    };

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: resetForm,
        };

        if (editing) {
            form.put(`${baseUrl}/${editing.id}`, requestOptions);
            return;
        }

        form.post(baseUrl, requestOptions);
    };

    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        form.setData("tanggal", new Date().toISOString().slice(0, 10));
    };

    const editRow = (row) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            tanggal: row.tanggal ?? new Date().toISOString().slice(0, 10),
            perumahan_id: row.perumahan_id ?? "",
            detail_rumah_id: row.detail_rumah_id ?? "",
            tahapan_pembangunan_id: row.tahapan_pembangunan_id ?? "",
            progress_pembangunan_id: row.progress_pembangunan_id ?? "",
            quality_upgrade_contract_id:
                row.quality_upgrade_contract_id ?? qualityUpgrade?.id ?? "",
            quality_upgrade_contract_item_id:
                row.quality_upgrade_contract_item_id ??
                qualityUpgrade?.items?.[0]?.value ??
                "",
            keterangan: row.keterangan ?? "",
            foto: null,
            items: row.items?.length ? row.items : [itemTemplate()],
        });
        window.scrollTo({ top: 0, behavior: "smooth" });
    };

    const filterRows = (event) => {
        event.preventDefault();
        router.get(
            baseUrl,
            {
                search,
                perumahan_id: filterPerumahan,
                detail_rumah_id: filterUnit,
                tahapan_pembangunan_id: filterTahapan,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                {canCreate || (editing && canUpdate) ? (
                    <Form
                        collapsible
                        title={
                            editing
                                ? `Edit ${editing.kode_pemakaian}`
                                : "Catat Pemakaian Material"
                        }
                        description="Pemakaian wajib dihubungkan ke progress yang telah disetujui agar konsumsi material dan pekerjaan fisik dapat dibandingkan."
                        onSubmit={submit}
                        actions={
                            <>
                                {editing && canUpdate && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={resetForm}
                                    >
                                        <X size={15} /> Batal
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    <PackageCheck size={17} />{" "}
                                    {editing
                                        ? "Simpan Perubahan"
                                        : "Simpan Pemakaian"}
                                </Button>
                            </>
                        }
                    >
                        <ErrorSummary errors={form.errors} />
                        <div className="grid gap-4 md:grid-cols-4">
                            <Input
                                label="Tanggal"
                                type="date"
                                value={form.data.tanggal}
                                onChange={(event) =>
                                    form.setData("tanggal", event.target.value)
                                }
                            />
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Perumahan
                                </span>
                                <Dropdown
                                    label="Pilih Perumahan"
                                    value={form.data.perumahan_id}
                                    options={options.perumahans}
                                    onChange={(value) =>
                                        form.setData({
                                            ...form.data,
                                            perumahan_id: value,
                                            detail_rumah_id: "",
                                            tahapan_pembangunan_id: "",
                                            progress_pembangunan_id: "",
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Unit
                                </span>
                                <Dropdown
                                    label="Kawasan / Pilih Unit"
                                    value={form.data.detail_rumah_id}
                                    options={unitOptions}
                                    onChange={(value, selected) =>
                                        form.setData({
                                            ...form.data,
                                            detail_rumah_id: value,
                                            perumahan_id: resolveScopedValue(
                                                selected?.perumahan_id,
                                                form.data.perumahan_id,
                                            ),
                                            tahapan_pembangunan_id: "",
                                            progress_pembangunan_id: "",
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Tahapan
                                </span>
                                <Dropdown
                                    label={
                                        form.data.detail_rumah_id
                                            ? "Pilih Tahapan Rumah"
                                            : "Pilih Tahapan Kawasan"
                                    }
                                    value={form.data.tahapan_pembangunan_id}
                                    options={tahapanPembangunans}
                                    onChange={(value) =>
                                        form.setData({
                                            ...form.data,
                                            tahapan_pembangunan_id: value,
                                            progress_pembangunan_id: "",
                                        })
                                    }
                                />
                            </div>
                        </div>
                        {qualityUpgrade && <div className="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900"><strong>Pemakaian untuk {qualityUpgrade.label}</strong><p>Stok yang diposting akan menjadi biaya material aktual Penambahan Mutu.</p><div className="mt-2"><Dropdown label="Pilih item pekerjaan" value={form.data.quality_upgrade_contract_item_id} options={qualityUpgrade.items} onChange={(value) => form.setData("quality_upgrade_contract_item_id", value)}/></div></div>}
                        <div className="grid gap-4 md:grid-cols-2">
                            {!qualityUpgrade && <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Kemajuan Disetujui
                                </span>
                                <Dropdown
                                    label="Pilih Kemajuan"
                                    value={form.data.progress_pembangunan_id}
                                    options={progressOptions}
                                    onChange={(value, selected) =>
                                        form.setData({
                                            ...form.data,
                                            progress_pembangunan_id: value,
                                            perumahan_id: resolveScopedValue(
                                                selected?.perumahan_id,
                                                form.data.perumahan_id,
                                            ),
                                            detail_rumah_id: resolveScopedValue(
                                                selected?.detail_rumah_id,
                                                form.data.detail_rumah_id,
                                            ),
                                            tahapan_pembangunan_id:
                                                resolveScopedValue(
                                                    selected?.tahapan_pembangunan_id,
                                                    form.data
                                                        .tahapan_pembangunan_id,
                                                ),
                                        })
                                    }
                                />
                            </div>}
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Bukti Pemakaian
                                </span>
                                <input
                                    type="file"
                                    accept="image/*"
                                    className="min-h-11 rounded-lg border border-silver-deep/70 p-2"
                                    onChange={(event) =>
                                        form.setData(
                                            "foto",
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-3">
                            <div className="flex items-center justify-between">
                                <h3 className="font-extrabold">
                                    Material Terpakai
                                </h3>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        form.setData("items", [
                                            ...form.data.items,
                                            itemTemplate(),
                                        ])
                                    }
                                >
                                    <PlusCircle size={15} /> Tambah Item
                                </Button>
                            </div>
                            {form.data.items.map((item, index) => (
                                <div
                                    className="grid gap-3 rounded-lg border border-silver-deep/70 p-3 md:grid-cols-[1fr_150px_180px_auto]"
                                    key={index}
                                >
                                    <div className="grid gap-2">
                                        <span className="text-sm font-extrabold">
                                            Stok Lokasi
                                        </span>
                                        <Dropdown
                                            label="Pilih Material"
                                            value={item.site_material_stock_id}
                                            options={stockOptions}
                                            onChange={(value, selected) =>
                                                setItem(
                                                    index,
                                                    "site_material_stock_id",
                                                    value,
                                                    selected,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <span className="text-sm font-extrabold">
                                            Satuan Pemakaian
                                        </span>
                                        <Dropdown
                                            value={item.material_unit_id ?? ""}
                                            options={
                                                stockOptions.find(
                                                    (stock) =>
                                                        String(stock.value) ===
                                                        String(
                                                            item.site_material_stock_id,
                                                        ),
                                                )?.unit_options ?? []
                                            }
                                            onChange={(value) =>
                                                setItem(
                                                    index,
                                                    "material_unit_id",
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                    <Input
                                        label={`Qty (${item.satuan || "-"})`}
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={item.qty}
                                        onChange={(event) =>
                                            setItem(
                                                index,
                                                "qty",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            disabled={
                                                form.data.items.length === 1
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    "items",
                                                    form.data.items.filter(
                                                        (_, itemIndex) =>
                                                            itemIndex !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <MinusCircle size={17} />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <Textarea
                            label="Catatan Pemakaian / Pekerjaan"
                            value={form.data.keterangan}
                            onChange={(event) =>
                                form.setData("keterangan", event.target.value)
                            }
                        />
                    </Form>
                ) : (
                    <section className="rounded-lg border border-dashed border-silver-deep/70 bg-silver-soft/40 p-6 text-sm text-ink-soft dark:border-white/10 dark:bg-white/5">
                        Form pemakaian material disembunyikan karena role aktif
                        tidak memiliki izin create pemakaian material.
                    </section>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b border-silver-deep/60 p-5">
                        <h2 className="text-lg font-extrabold">
                            Saldo Material di Lokasi
                        </h2>
                        <p className="mt-1 text-sm text-ink-soft">
                            Jejak material dari gudang sampai dipakai atau
                            dikembalikan.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                <tr>
                                    {[
                                        "Lokasi",
                                        "Tahapan",
                                        "Material / HPP",
                                        "Diterima",
                                        "Dipakai",
                                        "Menunggu Kembali",
                                        "Dikembalikan",
                                        "Sisa",
                                    ].map((label) => (
                                        <th className="px-5 py-4" key={label}>
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {siteStockRows.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4">
                                            {row.perumahan}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.unit} - {row.gudang}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.tahapan}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.material}
                                            <br />
                                            <span className="text-xs font-normal text-ink-soft">
                                                {row.kelompok_hpp}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.diterima} {row.satuan}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.dipakai} {row.satuan}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.menunggu_pengembalian}{" "}
                                            {row.satuan}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.dikembalikan} {row.satuan}
                                        </td>
                                        <td className="px-5 py-4 font-extrabold">
                                            {row.sisa} {row.satuan}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-4 p-5 md:grid-cols-[1.2fr_1fr_1fr_1fr_auto]"
                        onSubmit={filterRows}
                    >
                        <Input
                            label="Cari Pemakaian"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Filter Perumahan
                            </span>
                            <Dropdown
                                label="Semua Perumahan"
                                value={filterPerumahan}
                                options={options.perumahans}
                                onChange={(value) => {
                                    setFilterPerumahan(value);
                                    setFilterUnit("");
                                }}
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Filter Unit
                            </span>
                            <Dropdown
                                label="Semua Unit"
                                value={filterUnit}
                                options={filterUnitOptions}
                                onChange={setFilterUnit}
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Filter Tahapan
                            </span>
                            <Dropdown
                                label="Semua Tahapan"
                                value={filterTahapan}
                                options={[
                                    { value: "", label: "Semua Tahapan" },
                                    ...filterTahapanOptions,
                                ]}
                                onChange={setFilterTahapan}
                            />
                        </div>
                        <div className="flex items-end">
                            <Button type="submit">
                                <Search size={16} /> Cari
                            </Button>
                        </div>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                <tr>
                                    {[
                                        "Kode",
                                        "Tanggal",
                                        "Lokasi",
                                        "Tahapan / Progress",
                                        "Material",
                                        "Bukti",
                                        "Audit",
                                        "Setting Approval",
                                        "Aksi",
                                    ].map((label) => (
                                        <th className="px-5 py-4" key={label}>
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">
                                            {row.kode_pemakaian}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.tanggal}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.perumahan}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.unit}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.tahapan}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.progress}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.items_text}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.foto_url ? (
                                                <a
                                                    className="font-bold text-emerald-600"
                                                    href={row.foto_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Lihat
                                                </a>
                                            ) : (
                                                "-"
                                            )}
                                        </td>
                                        <td className="min-w-44 px-5 py-4 text-xs">
                                            <span className="font-bold">
                                                Dibuat:
                                            </span>{" "}
                                            {row.created_by_name}
                                            <br />
                                            <span className="font-bold">
                                                Diubah:
                                            </span>{" "}
                                            {row.updated_by_name}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.approval_stage}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                {row.can_review && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() =>
                                                            window.confirm(
                                                                `Setujui tahap aktif ${row.kode_pemakaian}?`,
                                                            ) &&
                                                            router.post(
                                                                `${baseUrl}/${row.id}/approve`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <CheckCircle2
                                                            size={14}
                                                        />{" "}
                                                        Setujui
                                                    </Button>
                                                )}
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setDetail(row)
                                                    }
                                                >
                                                    <Eye size={14} /> Detail
                                                </Button>
                                                {canUpdate && row.can_edit && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            editRow(row)
                                                        }
                                                    >
                                                        <Edit3 size={14} /> Ubah
                                                    </Button>
                                                )}
                                                {canDelete &&
                                                    row.can_delete && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="text-red-600"
                                                            onClick={() =>
                                                                window.confirm(
                                                                    "Hapus catatan pemakaian?",
                                                                ) &&
                                                                router.delete(
                                                                    `${baseUrl}/${row.id}`,
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Trash2 size={14} />
                                                        </Button>
                                                    )}
                                                {canLock && row.can_lock && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `${baseUrl}/${row.id}/lock`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Lock size={14} /> Kunci
                                                    </Button>
                                                )}
                                                {canUnlock &&
                                                    row.can_unlock &&
                                                    row.record_status ===
                                                        "locked" && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/unlock`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Unlock size={14} />{" "}
                                                            Unlock
                                                        </Button>
                                                    )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

            <Modal
                open={Boolean(detail)}
                onClose={() => setDetail(null)}
                title={
                    detail
                        ? `Detail ${detail.kode_pemakaian}`
                        : "Detail Pemakaian Material"
                }
                footer={
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setDetail(null)}
                    >
                        Tutup
                    </Button>
                }
            >
                {detail && (
                    <div className="grid gap-4 text-sm">
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="rounded-lg border border-silver-deep/70 p-4">
                                <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft">
                                    Tanggal
                                </p>
                                <p className="mt-1 font-extrabold">
                                    {detail.tanggal}
                                </p>
                            </div>
                            <div className="rounded-lg border border-silver-deep/70 p-4">
                                <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft">
                                    Kunci
                                </p>
                                <p className="mt-1 font-extrabold">
                                    {detail.record_status}
                                </p>
                            </div>
                            <div className="rounded-lg border border-silver-deep/70 p-4">
                                <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft">
                                    Lokasi
                                </p>
                                <p className="mt-1 font-extrabold">
                                    {detail.perumahan}
                                </p>
                                <p className="text-ink-soft">{detail.unit}</p>
                            </div>
                            <div className="rounded-lg border border-silver-deep/70 p-4">
                                <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft">
                                    Tahapan / Kemajuan
                                </p>
                                <p className="mt-1 font-extrabold">
                                    {detail.tahapan}
                                </p>
                                <p className="text-ink-soft">
                                    {detail.progress}
                                </p>
                            </div>
                        </div>
                        <div className="rounded-lg border border-silver-deep/70 p-4">
                            <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft">
                                Material Terpakai
                            </p>
                            <p className="mt-2 font-bold">
                                {detail.items_text || "-"}
                            </p>
                        </div>
                        <div className="rounded-lg border border-silver-deep/70 p-4">
                            <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft">
                                Catatan
                            </p>
                            <p className="mt-2 whitespace-pre-line">
                                {detail.keterangan || "-"}
                            </p>
                        </div>
                        <div className="rounded-lg border border-silver-deep/70 p-4">
                            <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft">
                                Audit
                            </p>
                            <p className="mt-2">
                                <span className="font-bold">Dibuat:</span>{" "}
                                {detail.created_by_name}
                            </p>
                            <p>
                                <span className="font-bold">Diubah:</span>{" "}
                                {detail.updated_by_name}
                            </p>
                        </div>
                        {detail.foto_url && (
                            <a
                                className="font-extrabold text-emerald-600"
                                href={detail.foto_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Lihat Bukti Foto
                            </a>
                        )}
                    </div>
                )}
            </Modal>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Pemakaian Material"}>
        {page}
    </AdminLayout>
);
