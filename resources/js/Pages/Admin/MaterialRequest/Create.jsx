import { Head, router, useForm } from "@inertiajs/react";
import { MinusCircle, PlusCircle, RefreshCw, Save, Search } from "lucide-react";
import { useMemo, useState } from "react";
import {
    Button,
    Dropdown,
    Input,
    MaterialSelectorModal,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

function itemTemplate() {
    return {
        barang_material_id: "",
        kode_barang: "",
        nama_barang: "",
        material_unit_id: "",
        satuan: "",
        qty: "",
        catatan: "",
    };
}

function splitMaterialLabel(label) {
    if (typeof label !== "string") {
        return ["", ""];
    }

    const [kode = "", ...rest] = label.split(" - ");
    return [kode, rest.join(" - ")];
}

const decimal = (value) =>
    Number(value ?? 0).toLocaleString("id-ID", { maximumFractionDigits: 3 });

export default function Create({
    title,
    baseUrl,
    indexUrl,
    nextCode = "",
    request = null,
    options = {},
    canCreate = false,
    qualityUpgrade = null,
}) {
    const [search, setSearch] = useState("");
    const [materialPicker, setMaterialPicker] = useState({
        open: false,
        mode: "append",
        index: null,
        search: "",
    });
    const isEdit = Boolean(request?.id);
    const materialOptions = options.barangMaterials ?? [];

    const initialItems = request?.items?.length
        ? request.items
        : [itemTemplate()];

    const form = useForm({
        kode_request: nextCode,
        tanggal: request?.tanggal ?? new Date().toISOString().slice(0, 10),
        gudang_id: request?.gudang_id ?? "",
        perumahan_id: request?.perumahan_id ?? qualityUpgrade?.perumahan_id ?? "",
        detail_rumah_id: request?.detail_rumah_id ?? qualityUpgrade?.detail_rumah_id ?? "",
        quality_upgrade_contract_id:
            request?.quality_upgrade_contract_id ?? qualityUpgrade?.id ?? "",
        quality_upgrade_contract_item_id:
            request?.quality_upgrade_contract_item_id ??
            qualityUpgrade?.items?.[0]?.value ??
            "",
        keterangan: request?.keterangan ?? "",
        items: initialItems,
    });

    const detailRumahOptions = useMemo(() => {
        if (!form.data.perumahan_id) return options.detailRumahs ?? [];
        return (options.detailRumahs ?? []).filter(
            (item) => item.perumahan_id === String(form.data.perumahan_id),
        );
    }, [form.data.perumahan_id, options.detailRumahs]);

    const visibleItems = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        return form.data.items
            .map((item, index) => ({ item, index }))
            .filter(({ item }) =>
                [item.kode_barang, item.nama_barang, item.satuan, item.catatan]
                    .filter(Boolean)
                    .join(" ")
                    .toLowerCase()
                    .includes(keyword),
            );
    }, [form.data.items, search]);

    const setItem = (index, key, value, selected) => {
        form.setData(
            "items",
            form.data.items.map((item, itemIndex) => {
                if (itemIndex !== index) return item;
                const next = { ...item, [key]: value };
                if (key === "barang_material_id") {
                    const [kode, nama] = splitMaterialLabel(selected?.label);
                    next.kode_barang =
                        selected?.kode_barang ?? kode ?? next.kode_barang;
                    next.nama_barang =
                        selected?.nama_barang ??
                        nama ??
                        selected?.label ??
                        next.nama_barang;
                    next.material_unit_id = String(
                        selected?.base_unit_id ?? "",
                    );
                    next.satuan =
                        selected?.unit_options?.find(
                            (unit) =>
                                String(unit.value) ===
                                String(next.material_unit_id),
                        )?.symbol ??
                        selected?.satuan ??
                        "";
                }
                if (key === "material_unit_id") {
                    const material = materialOptions.find(
                        (option) =>
                            String(option.value) ===
                            String(next.barang_material_id),
                    );
                    next.satuan =
                        material?.unit_options?.find(
                            (unit) => String(unit.value) === String(value),
                        )?.symbol ?? next.satuan;
                }
                return next;
            }),
        );
    };

    const findMaterialMatches = (query) => {
        const keyword = query.trim().toLowerCase();

        if (!keyword) {
            return [];
        }

        return materialOptions.filter((material) => {
            const haystack = [
                material.kode_barang,
                material.nama_barang,
                material.label,
                material.jenis_material,
                material.merk_material,
                material.kategori_material,
                material.satuan,
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();

            return haystack.includes(keyword);
        });
    };

    const openPickerForAppend = () =>
        setMaterialPicker({
            open: true,
            mode: "append",
            index: null,
            search: "",
        });
    const openPickerForRow = (index, searchValue = "") =>
        setMaterialPicker({
            open: true,
            mode: "update",
            index,
            search: searchValue,
        });
    const closePicker = () =>
        setMaterialPicker({
            open: false,
            mode: "append",
            index: null,
            search: "",
        });
    const resolveMaterial = (index, query) => {
        const matches = findMaterialMatches(query);

        if (matches.length === 1) {
            setItem(index, "barang_material_id", matches[0].value, matches[0]);
            return true;
        }

        openPickerForRow(index, query);
        return false;
    };
    const pickMaterial = (material) => {
        if (materialPicker.mode === "append") {
            form.setData("items", [
                ...form.data.items,
                {
                    barang_material_id: material.value,
                    kode_barang:
                        material.kode_barang ??
                        splitMaterialLabel(material.label)[0] ??
                        "",
                    nama_barang:
                        material.nama_barang ??
                        splitMaterialLabel(material.label)[1] ??
                        material.label ??
                        "",
                    material_unit_id: String(material.base_unit_id ?? ""),
                    satuan:
                        material.unit_options?.find(
                            (unit) =>
                                String(unit.value) ===
                                String(material.base_unit_id),
                        )?.symbol ??
                        material.satuan ??
                        "",
                    qty: "",
                    catatan: "",
                },
            ]);
            return;
        }

        if (materialPicker.index !== null) {
            setItem(
                materialPicker.index,
                "barang_material_id",
                material.value,
                material,
            );
        }

        closePicker();
    };

    const totalQty = form.data.items.reduce(
        (sum, item) => sum + Number(item.qty || 0),
        0,
    );
    const canSubmit = isEdit || canCreate;

    const submit = (event) => {
        event.preventDefault();
        if (isEdit) {
            form.put(baseUrl, { preserveScroll: true });
            return;
        }
        form.post(baseUrl, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <form
                className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6"
                onSubmit={submit}
            >
                {qualityUpgrade && <div className="border-b border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><strong>Permintaan untuk {qualityUpgrade.label}</strong><div className="mt-2 max-w-md"><Dropdown label="Pilih item pekerjaan" value={form.data.quality_upgrade_contract_item_id} options={qualityUpgrade.items} onChange={(value) => form.setData("quality_upgrade_contract_item_id", value)}/></div></div>}
                <div className="grid gap-3 border-b border-silver-deep/50 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4 xl:grid-cols-[260px_180px_1fr_220px]">
                    <Input
                        label="No Transaksi"
                        value={form.data.kode_request}
                        readOnly
                        inputClassName="h-9 min-h-9 cursor-not-allowed bg-silver-soft text-xs dark:bg-white/5"
                    />
                    <Input
                        label="Tanggal"
                        type="date"
                        value={form.data.tanggal}
                        onChange={(event) =>
                            form.setData("tanggal", event.target.value)
                        }
                        inputClassName="h-9 min-h-9 text-xs"
                    />
                    <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                        Gudang
                        <Dropdown
                            value={form.data.gudang_id}
                            label="Pilih Gudang"
                            options={options.gudangs ?? []}
                            onChange={(value) =>
                                form.setData("gudang_id", value)
                            }
                            buttonClassName="min-h-9 text-xs"
                        />
                    </label>
                    <Input
                        label="Keterangan"
                        value={form.data.keterangan}
                        onChange={(event) =>
                            form.setData("keterangan", event.target.value)
                        }
                        inputClassName="h-9 min-h-9 text-xs"
                    />
                </div>

                <div className="grid gap-3 border-b border-silver-deep/50 bg-silver-soft/35 px-4 py-3 dark:border-white/10 dark:bg-white/3 lg:grid-cols-3">
                    <SummaryCard
                        title="Perumahan"
                        value={
                            options.perumahans?.find(
                                (row) =>
                                    row.value ===
                                    String(form.data.perumahan_id),
                            )?.label ?? "Opsional"
                        }
                    />
                    <SummaryCard
                        title="Unit Rumah"
                        value={
                            detailRumahOptions.find(
                                (row) =>
                                    row.value ===
                                    String(form.data.detail_rumah_id),
                            )?.label ?? "Opsional"
                        }
                    />
                    <SummaryCard title="Total Qty" value={decimal(totalQty)} />
                </div>

                <div className="border-b border-silver-deep/50 bg-silver-soft/25 px-4 py-3 dark:border-white/10 dark:bg-white/3">
                    <div className="grid gap-3 md:grid-cols-3">
                        <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                            Perumahan
                            <Dropdown
                                value={form.data.perumahan_id}
                                label="Pilih Perumahan"
                                options={options.perumahans ?? []}
                                onChange={(value) =>
                                    form.setData({
                                        ...form.data,
                                        perumahan_id: value,
                                        detail_rumah_id: "",
                                    })
                                }
                                buttonClassName="min-h-9 text-xs"
                            />
                        </label>
                        <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                            Unit Rumah
                            <Dropdown
                                value={form.data.detail_rumah_id}
                                label="Pilih Unit"
                                options={detailRumahOptions}
                                onChange={(value, selected) =>
                                    form.setData({
                                        ...form.data,
                                        detail_rumah_id: value,
                                        perumahan_id:
                                            selected?.perumahan_id ??
                                            form.data.perumahan_id,
                                    })
                                }
                                buttonClassName="min-h-9 text-xs"
                            />
                        </label>
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            label="Cari Item"
                            inputClassName="h-9 min-h-9 text-xs"
                        />
                    </div>
                </div>

                <div className="h-[58vh] overflow-auto">
                    <table className="w-full min-w-[1050px] divide-y divide-silver-deep/60 text-xs">
                        <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                            <tr>
                                {[
                                    "No",
                                    "Kode Item",
                                    "Material",
                                    "Qty",
                                    "Satuan",
                                    "Catatan",
                                    "",
                                ].map((column) => (
                                    <th
                                        className="px-3 py-3 font-extrabold"
                                        key={column}
                                    >
                                        {column}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {visibleItems.map(({ item, index }) => (
                                <tr
                                    key={`${item.barang_material_id || "new"}-${index}`}
                                >
                                    <td className="px-3 py-2 font-bold">
                                        {index + 1}
                                    </td>
                                    <td className="px-3 py-2 min-w-52">
                                        <div className="flex items-center gap-2">
                                            <input
                                                value={item.kode_barang ?? ""}
                                                onChange={(event) =>
                                                    setItem(
                                                        index,
                                                        "kode_barang",
                                                        event.target.value,
                                                    )
                                                }
                                                onKeyDown={(event) => {
                                                    if (event.key === "Enter") {
                                                        event.preventDefault();
                                                        resolveMaterial(
                                                            index,
                                                            item.kode_barang ??
                                                                "",
                                                        );
                                                    }
                                                }}
                                                placeholder="Kode / nama material"
                                                className="h-9 min-w-0 flex-1 rounded-md border border-silver-deep/70 bg-white/85 px-3 text-xs font-bold text-ink outline-none transition placeholder:text-ink-soft/55 focus:border-ink-soft focus:ring-4 focus:ring-ink-soft/10 dark:border-white/10 dark:bg-white/8 dark:text-white dark:placeholder:text-white/35"
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    resolveMaterial(
                                                        index,
                                                        item.kode_barang ?? "",
                                                    )
                                                }
                                                className="grid h-9 w-9 shrink-0 place-items-center rounded-md border border-silver-deep/70 bg-white/85 text-ink transition hover:bg-silver-soft dark:border-white/10 dark:bg-white/8 dark:text-white"
                                                title="Cari material"
                                            >
                                                <Search
                                                    size={15}
                                                    className="shrink-0 opacity-70"
                                                />
                                            </button>
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 font-bold">
                                        {item.nama_barang || "-"}
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            value={item.qty}
                                            onChange={(event) =>
                                                setItem(
                                                    index,
                                                    "qty",
                                                    event.target.value,
                                                )
                                            }
                                            className="h-9 w-28 rounded-md border border-silver-deep/70 bg-white/85 px-3 text-right font-bold dark:border-white/10 dark:bg-white/8"
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <Dropdown
                                            value={item.material_unit_id ?? ""}
                                            options={
                                                materialOptions.find(
                                                    (material) =>
                                                        String(
                                                            material.value,
                                                        ) ===
                                                        String(
                                                            item.barang_material_id,
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
                                            buttonClassName="h-9 min-h-9 w-28 text-xs"
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <input
                                            value={item.catatan}
                                            onChange={(event) =>
                                                setItem(
                                                    index,
                                                    "catatan",
                                                    event.target.value,
                                                )
                                            }
                                            className="h-9 w-full rounded-md border border-silver-deep/70 bg-white/85 px-3 font-bold dark:border-white/10 dark:bg-white/8"
                                        />
                                    </td>
                                    <td className="px-3 py-2">
                                        <button
                                            type="button"
                                            title="Hapus item"
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
                                            className="grid h-8 w-8 place-items-center rounded-md text-red-600 transition hover:bg-red-50 disabled:pointer-events-none disabled:opacity-40 dark:text-red-300 dark:hover:bg-red-400/10"
                                        >
                                            <MinusCircle size={16} />
                                        </button>
                                    </td>
                                </tr>
                            ))}
                            {visibleItems.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center font-semibold text-ink-soft dark:text-white/55"
                                    >
                                        Tidak ada item material yang cocok
                                        dengan pencarian ini.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="grid gap-3 border-t border-silver-deep/60 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4 lg:grid-cols-[1fr_360px]">
                    <div className="flex flex-wrap items-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={openPickerForAppend}
                        >
                            <PlusCircle size={16} /> Show Material
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing || !canSubmit}
                        >
                            <Save size={16} /> Simpan
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit(indexUrl)}
                        >
                            <RefreshCw size={16} /> Batal
                        </Button>
                    </div>
                    <div className="grid gap-2">
                        <FooterTotal
                            label="Total Item"
                            value={form.data.items.length}
                        />
                        <FooterTotal
                            label="Total Qty"
                            value={totalQty}
                            strong
                        />
                    </div>
                </div>
            </form>

            <MaterialSelectorModal
                open={materialPicker.open}
                onClose={closePicker}
                materials={materialOptions}
                onPick={pickMaterial}
                title="Show Material"
                initialSearch={materialPicker.search}
            />
        </>
    );
}

function SummaryCard({ title, value }) {
    return (
        <div className="rounded-xl border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
            <p className="text-xs font-extrabold uppercase tracking-[0.2em] text-ink-soft dark:text-white/55">
                {title}
            </p>
            <p className="mt-2 text-lg font-black text-ink dark:text-white">
                {value}
            </p>
        </div>
    );
}

function FooterTotal({ label, value, strong = false }) {
    return (
        <div
            className={`grid grid-cols-[1fr_160px] items-center gap-3 text-xs ${strong ? "font-black" : "font-bold"}`}
        >
            <span className="text-right text-ink-soft dark:text-white/60">
                {label}
            </span>
            <span className="rounded-md border border-silver-deep/60 bg-white/80 px-3 py-2 text-right dark:border-white/10 dark:bg-white/8">
                {Number.isFinite(Number(value)) ? decimal(value) : value}
            </span>
        </div>
    );
}

Create.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Permintaan Barang"}>
        {page}
    </AdminLayout>
);
