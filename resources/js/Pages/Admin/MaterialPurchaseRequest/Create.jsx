import { Head, router, useForm } from "@inertiajs/react";
import { MinusCircle, PlusCircle, RefreshCw, Save, Search } from "lucide-react";
import { useState } from "react";
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
        qty: "",
        satuan: "",
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

export default function Create({
    title,
    baseUrl,
    indexUrl,
    nextCode = "",
    requestRow = null,
    options = {},
}) {
    const today = new Date().toISOString().slice(0, 10);
    const isEdit = Boolean(requestRow?.id);
    const [materialPicker, setMaterialPicker] = useState({
        open: false,
        mode: "append",
        index: null,
        search: "",
    });
    const materialOptions = options.barangMaterials ?? [];
    const form = useForm({
        kode_request: requestRow?.kode_request ?? nextCode,
        tanggal: requestRow?.tanggal ?? today,
        gudang_id: requestRow?.gudang_id ?? "",
        keterangan: requestRow?.keterangan ?? "",
        items: requestRow?.items?.length
            ? requestRow.items.map((item) => ({
                  barang_material_id: item.barang_material_id ?? "",
                  kode_barang: item.kode_barang ?? "",
                  nama_barang: item.nama_barang ?? "",
                  qty: item.qty ?? "",
                  satuan: item.satuan ?? "",
                  catatan: item.catatan ?? "",
              }))
            : [itemTemplate()],
    });

    const setItem = (index, key, value) => {
        form.setData(
            "items",
            form.data.items.map((item, itemIndex) => {
                if (itemIndex !== index) return item;
                const next = { ...item, [key]: value };
                if (key === "barang_material_id") {
                    const material = materialOptions.find(
                        (option) => option.value === String(value),
                    );
                    const [kode, nama] = splitMaterialLabel(material?.label);
                    next.kode_barang =
                        material?.kode_barang ?? kode ?? next.kode_barang;
                    next.nama_barang =
                        material?.nama_barang ??
                        nama ??
                        material?.label ??
                        next.nama_barang;
                    next.satuan = material?.satuan ?? "";
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
            setItem(index, "barang_material_id", matches[0].value);
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
                    qty: "",
                    satuan: material.satuan ?? "",
                    catatan: "",
                },
            ]);
            return;
        }

        if (materialPicker.index !== null) {
            setItem(materialPicker.index, "barang_material_id", material.value);
        }

        closePicker();
    };

    const submit = (event) => {
        event.preventDefault();
        if (isEdit) {
            form.put(baseUrl);
            return;
        }
        form.post(baseUrl);
    };

    return (
        <>
            <Head title={title} />
            <form
                className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6"
                onSubmit={submit}
            >
                <div className="grid gap-3 border-b border-silver-deep/50 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4 lg:grid-cols-[260px_220px_260px_1fr]">
                    <Input
                        label="No Permintaan"
                        value={form.data.kode_request}
                        readOnly
                        inputClassName="h-9 min-h-9 cursor-not-allowed bg-silver-soft text-xs dark:bg-white/5"
                    />
                    <Input
                        label="Tanggal"
                        type="date"
                        value={form.data.tanggal}
                        error={form.errors.tanggal}
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
                        {form.errors.gudang_id && (
                            <span className="text-xs font-bold text-red-600">
                                {form.errors.gudang_id}
                            </span>
                        )}
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

                <div className="h-[48vh] overflow-auto">
                    <table className="w-full min-w-[900px] divide-y divide-silver-deep/60 text-xs">
                        <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                            <tr>
                                {[
                                    "No",
                                    "Kode Item",
                                    "Keterangan",
                                    "Jumlah",
                                    "Satuan",
                                    "Catatan",
                                    "",
                                ].map((column) => (
                                    <th
                                        key={column}
                                        className="px-3 py-3 font-extrabold"
                                    >
                                        {column}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                            {form.data.items.map((item, index) => {
                                const material = materialOptions.find(
                                    (option) =>
                                        option.value ===
                                        String(item.barang_material_id),
                                );
                                return (
                                    <tr key={index}>
                                        <td className="px-3 py-2 font-bold">
                                            {index + 1}
                                        </td>
                                        <td className="min-w-56 px-3 py-2">
                                            <div className="flex items-center gap-2">
                                                <input
                                                    value={
                                                        item.kode_barang ?? ""
                                                    }
                                                    onChange={(event) =>
                                                        setItem(
                                                            index,
                                                            "kode_barang",
                                                            event.target.value,
                                                        )
                                                    }
                                                    onKeyDown={(event) => {
                                                        if (
                                                            event.key ===
                                                            "Enter"
                                                        ) {
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
                                                            item.kode_barang ??
                                                                "",
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
                                            {item.nama_barang ||
                                                material?.nama_barang ||
                                                material?.label ||
                                                "-"}
                                        </td>
                                        <td className="px-3 py-2">
                                            <input
                                                className="h-9 w-28 rounded-md border border-silver-deep/70 bg-white/85 px-3 text-right font-bold dark:border-white/10 dark:bg-white/8"
                                                type="number"
                                                value={item.qty}
                                                onChange={(event) =>
                                                    setItem(
                                                        index,
                                                        "qty",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <input
                                                className="h-9 w-24 rounded-md border border-silver-deep/70 bg-white/85 px-3 font-bold dark:border-white/10 dark:bg-white/8"
                                                value={item.satuan}
                                                onChange={(event) =>
                                                    setItem(
                                                        index,
                                                        "satuan",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <input
                                                className="h-9 w-60 rounded-md border border-silver-deep/70 bg-white/85 px-3 font-bold dark:border-white/10 dark:bg-white/8"
                                                value={item.catatan ?? ""}
                                                onChange={(event) =>
                                                    setItem(
                                                        index,
                                                        "catatan",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                title="Hapus detail"
                                                disabled={
                                                    form.data.items.length === 1
                                                }
                                                onClick={() =>
                                                    form.setData(
                                                        "items",
                                                        form.data.items.filter(
                                                            (_, itemIndex) =>
                                                                itemIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                                className="grid h-8 w-8 place-items-center rounded-md text-red-600 transition hover:bg-red-50 disabled:pointer-events-none disabled:opacity-40 dark:text-red-300 dark:hover:bg-red-400/10"
                                            >
                                                <MinusCircle size={16} />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2 border-t border-silver-deep/60 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={openPickerForAppend}
                    >
                        <PlusCircle size={16} /> Show Material
                    </Button>
                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            size="sm"
                            disabled={form.processing}
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

Create.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Permintaan Pembelian Material"}>
        {page}
    </AdminLayout>
);
