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

const money = (value) =>
    new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(
        Number(value ?? 0),
    );

function itemTemplate(material = null) {
    return {
        barang_material_id: material?.value ?? "",
        kode_barang: material?.kode_barang ?? "",
        nama_barang: material?.nama_barang ?? "",
        qty: "",
        material_unit_id: material?.base_unit_id ?? "",
        satuan: material?.satuan ?? "",
        harga_satuan: material?.harga_hpp ?? "",
        diskon: "",
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
    purchase = null,
    selectedRequest = null,
    options = {},
}) {
    const today = new Date().toISOString().slice(0, 10);
    const isEdit = Boolean(purchase?.id);
    const initialItems = purchase?.items?.length
        ? purchase.items
        : selectedRequest?.items?.length
          ? selectedRequest.items.map((item) => ({
                ...item,
                diskon: item.diskon ?? 0,
            }))
          : [itemTemplate()];
    const materialOptions = options.barangMaterials ?? [];

    const form = useForm({
        kode_pembelian: purchase?.kode_pembelian ?? nextCode,
        material_purchase_request_id:
            purchase?.material_purchase_request_id ??
            (selectedRequest?.id ? String(selectedRequest.id) : ""),
        tanggal: purchase?.tanggal ?? today,
        supplier_id: purchase?.supplier_id ?? "",
        supplier: purchase?.supplier ?? "",
        metode_pembayaran: purchase?.metode_pembayaran ?? "hutang",
        planned_master_bank_id: purchase?.planned_master_bank_id ?? "",
        gudang_id: purchase?.gudang_id ?? selectedRequest?.gudang_id ?? "",
        keterangan: purchase?.keterangan ?? selectedRequest?.keterangan ?? "",
        diskon_transaksi: purchase?.diskon_transaksi ?? 0,
        nomor_faktur: purchase?.nomor_faktur ?? "",
        tanggal_faktur: purchase?.tanggal_faktur ?? "",
        nomor_surat_jalan: purchase?.nomor_surat_jalan ?? "",
        nama_ekspedisi: purchase?.nama_ekspedisi ?? "",
        nomor_kendaraan: purchase?.nomor_kendaraan ?? "",
        biaya_ekspedisi: purchase?.biaya_ekspedisi ?? 0,
        upah_buruh_logistik: purchase?.upah_buruh_logistik ?? 0,
        biaya_lain_perolehan: purchase?.biaya_lain_perolehan ?? 0,
        metode_alokasi_biaya: purchase?.metode_alokasi_biaya ?? "nilai",
        items: initialItems.map((item) => ({
            barang_material_id: item.barang_material_id ?? "",
            kode_barang: item.kode_barang ?? "",
            nama_barang: item.nama_barang ?? "",
            qty: item.qty ?? "",
            material_unit_id: item.material_unit_id ?? "",
            satuan: item.satuan ?? "",
            harga_satuan: item.harga_satuan ?? "",
            diskon: item.diskon ?? 0,
        })),
    });

    const [materialPicker, setMaterialPicker] = useState({
        open: false,
        mode: "append",
        index: null,
        search: "",
    });

    const selectedMaterials = useMemo(
        () =>
            new Map(
                (options.barangMaterials ?? []).map((item) => [
                    String(item.value),
                    item,
                ]),
            ),
        [options.barangMaterials],
    );

    const setItem = (index, key, value) => {
        form.setData(
            "items",
            form.data.items.map((item, itemIndex) => {
                if (itemIndex !== index) return item;

                const next = { ...item, [key]: value };

                if (key === "barang_material_id") {
                    const material = selectedMaterials.get(String(value));
                    const [kode, nama] = splitMaterialLabel(material?.label);
                    next.kode_barang =
                        material?.kode_barang ?? kode ?? next.kode_barang ?? "";
                    next.nama_barang =
                        material?.nama_barang ??
                        nama ??
                        material?.label ??
                        next.nama_barang ??
                        "";
                    next.satuan = material?.satuan ?? next.satuan ?? "";
                    next.material_unit_id = material?.base_unit_id ?? "";
                    next.harga_satuan =
                        material?.harga_hpp ?? next.harga_satuan ?? "";
                    if (!next.diskon) {
                        next.diskon = 0;
                    }
                }

                if (key === "material_unit_id") {
                    const material = selectedMaterials.get(
                        String(next.barang_material_id),
                    );
                    const unit = (material?.unit_options ?? []).find(
                        (option) => String(option.value) === String(value),
                    );
                    next.satuan = unit?.symbol ?? next.satuan;
                    next.harga_satuan =
                        unit?.standard_price ?? next.harga_satuan;
                }

                return next;
            }),
        );
    };

    const totalGross = form.data.items.reduce(
        (sum, item) =>
            sum + Number(item.qty || 0) * Number(item.harga_satuan || 0),
        0,
    );
    const totalItemDiscount = form.data.items.reduce(
        (sum, item) => sum + Number(item.diskon || 0),
        0,
    );
    const subtotal = Math.max(0, totalGross - totalItemDiscount);
    const transactionDiscount = Math.min(
        Math.max(0, Number(form.data.diskon_transaksi || 0)),
        subtotal,
    );
    const totalAkhir = Math.max(0, subtotal - transactionDiscount);

    const acquisitionCost =
        Number(form.data.biaya_ekspedisi || 0) +
        Number(form.data.upah_buruh_logistik || 0) +
        Number(form.data.biaya_lain_perolehan || 0);
    const landedTotal = totalAkhir + acquisitionCost;

    const submitForm = () => {
        form.transform((data) => ({
            ...data,
            diskon_transaksi: Number(data.diskon_transaksi || 0),
        }));
        form[isEdit ? "put" : "post"](baseUrl, {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    const handleSubmit = (event) => {
        event.preventDefault();

        submitForm();
    };

    const choosePurchaseRequest = (value) => {
        if (isEdit) return;
        router.visit(
            value
                ? `${indexUrl}/create?purchase_request_id=${value}`
                : `${indexUrl}/create`,
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
                itemTemplate({
                    ...material,
                    label:
                        material.label ??
                        `${material.kode_barang ?? ""} - ${material.nama_barang ?? ""}`,
                }),
            ]);
            return;
        }

        if (materialPicker.index !== null) {
            setItem(materialPicker.index, "barang_material_id", material.value);
        }

        closePicker();
    };

    return (
        <>
            <Head title={title} />
            <form
                className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6"
                onSubmit={handleSubmit}
            >
                <div className="grid gap-3 border-b border-silver-deep/50 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4 lg:grid-cols-[260px_220px_1fr_220px]">
                    <Input
                        label="No Transaksi"
                        value={form.data.kode_pembelian}
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
                        Permintaan Pembelian
                        <Dropdown
                            value={form.data.material_purchase_request_id}
                            label="Tanpa Permintaan"
                            options={[
                                { value: "", label: "Tanpa Permintaan" },
                                ...(options.purchaseRequests ?? []),
                            ]}
                            onChange={choosePurchaseRequest}
                            disabled={isEdit}
                            buttonClassName="min-h-9 text-xs"
                        />
                    </label>
                    <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                        Supplier
                        <Dropdown
                            value={form.data.supplier_id}
                            label="Pilih Supplier"
                            options={[
                                { value: "", label: "Tanpa Supplier" },
                                ...(options.suppliers ?? []),
                            ]}
                            onChange={(value, option) =>
                                form.setData({
                                    ...form.data,
                                    supplier_id: value,
                                    supplier: value ? option.label : "",
                                })
                            }
                            buttonClassName="min-h-9 text-xs"
                        />
                        {(form.errors.supplier_id || form.errors.supplier) && (
                            <span className="text-xs font-bold text-red-600">
                                {form.errors.supplier_id ||
                                    form.errors.supplier}
                            </span>
                        )}
                    </label>
                    <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                        Masuk ke
                        <Dropdown
                            value={form.data.gudang_id}
                            label="Pilih Gudang"
                            options={options.gudangs ?? []}
                            onChange={(value) =>
                                form.setData("gudang_id", value)
                            }
                            disabled={Boolean(selectedRequest) && !isEdit}
                            buttonClassName="min-h-9 text-xs"
                        />
                        {form.errors.gudang_id && (
                            <span className="text-xs font-bold text-red-600">
                                {form.errors.gudang_id}
                            </span>
                        )}
                    </label>
                    <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                        Metode Pembayaran
                        <Dropdown
                            value={form.data.metode_pembayaran}
                            label="Pilih Metode"
                            options={options.metodePembayaran ?? []}
                            onChange={(value) =>
                                form.setData({
                                    ...form.data,
                                    metode_pembayaran: value,
                                    planned_master_bank_id:
                                        value === "hutang"
                                            ? ""
                                            : form.data.planned_master_bank_id,
                                })
                            }
                            searchable={false}
                            buttonClassName="min-h-9 text-xs"
                        />
                    </label>
                    {form.data.metode_pembayaran === "tunai" && (
                        <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78 lg:col-span-2">
                            Rekening Cash / Tunai
                            <Dropdown
                                value={form.data.planned_master_bank_id}
                                label="Pilih Rekening"
                                options={options.masterBanks ?? []}
                                onChange={(value) =>
                                    form.setData(
                                        "planned_master_bank_id",
                                        value,
                                    )
                                }
                                buttonClassName="min-h-9 text-xs"
                            />
                            {form.errors.planned_master_bank_id && (
                                <span className="text-xs font-bold text-red-600">
                                    {form.errors.planned_master_bank_id}
                                </span>
                            )}
                        </label>
                    )}
                    <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78 lg:col-span-2">
                        Keterangan
                        <Input
                            value={form.data.keterangan}
                            onChange={(event) =>
                                form.setData("keterangan", event.target.value)
                            }
                            inputClassName="h-9 min-h-9 text-xs"
                        />
                    </label>
                </div>

                <div className="grid gap-3 border-b border-silver-deep/50 px-4 py-4 dark:border-white/10 md:grid-cols-3 lg:grid-cols-5">
                    <Input
                        label="Nomor Faktur Supplier"
                        value={form.data.nomor_faktur}
                        onChange={(event) =>
                            form.setData("nomor_faktur", event.target.value)
                        }
                    />
                    <Input
                        label="Tanggal Faktur"
                        type="date"
                        value={form.data.tanggal_faktur}
                        onChange={(event) =>
                            form.setData("tanggal_faktur", event.target.value)
                        }
                    />
                    <Input
                        label="Nomor Surat Jalan"
                        value={form.data.nomor_surat_jalan}
                        onChange={(event) =>
                            form.setData(
                                "nomor_surat_jalan",
                                event.target.value,
                            )
                        }
                    />
                    <Input
                        label="Ekspedisi / Pengangkut"
                        value={form.data.nama_ekspedisi}
                        onChange={(event) =>
                            form.setData("nama_ekspedisi", event.target.value)
                        }
                    />
                    <Input
                        label="Nomor Kendaraan"
                        value={form.data.nomor_kendaraan}
                        onChange={(event) =>
                            form.setData("nomor_kendaraan", event.target.value)
                        }
                    />
                    <Input
                        label="Biaya Ekspedisi"
                        type="number"
                        min="0"
                        value={form.data.biaya_ekspedisi}
                        onChange={(event) =>
                            form.setData("biaya_ekspedisi", event.target.value)
                        }
                    />
                    <Input
                        label="Upah Buruh Logistik"
                        type="number"
                        min="0"
                        value={form.data.upah_buruh_logistik}
                        onChange={(event) =>
                            form.setData(
                                "upah_buruh_logistik",
                                event.target.value,
                            )
                        }
                    />
                    <Input
                        label="Biaya Perolehan Lain"
                        type="number"
                        min="0"
                        value={form.data.biaya_lain_perolehan}
                        onChange={(event) =>
                            form.setData(
                                "biaya_lain_perolehan",
                                event.target.value,
                            )
                        }
                    />
                    <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78 md:col-span-2">
                        Dasar Alokasi Biaya
                        <Dropdown
                            value={form.data.metode_alokasi_biaya}
                            options={[
                                {
                                    value: "nilai",
                                    label: "Proporsional Nilai Material",
                                },
                                {
                                    value: "kuantitas",
                                    label: "Proporsional Kuantitas Dasar",
                                },
                            ]}
                            onChange={(value) =>
                                form.setData("metode_alokasi_biaya", value)
                            }
                            searchable={false}
                        />
                    </label>
                </div>

                <div className="grid gap-3 border-b border-silver-deep/50 bg-silver-soft/35 px-4 py-3 dark:border-white/10 dark:bg-white/3 lg:grid-cols-4">
                    <SummaryCard title="Sub Total" value={money(totalGross)} />
                    <SummaryCard
                        title="Potongan Item"
                        value={money(totalItemDiscount)}
                    />
                    <SummaryCard
                        title="Modal Persediaan"
                        value={money(landedTotal)}
                        strong
                    />
                </div>

                <div className="h-[48vh] overflow-auto">
                    <table className="w-full min-w-[1060px] divide-y divide-silver-deep/60 text-xs">
                        <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                            <tr>
                                {[
                                    "No",
                                    "Kode Item",
                                    "Keterangan",
                                    "Jumlah",
                                    "Satuan",
                                    "Setara Level 1",
                                    "Harga",
                                    "Potongan",
                                    "Total",
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
                                const material = selectedMaterials.get(
                                    String(item.barang_material_id),
                                );
                                const lineGross =
                                    Number(item.qty || 0) *
                                    Number(item.harga_satuan || 0);
                                const lineDiscount = Math.min(
                                    Number(item.diskon || 0),
                                    lineGross,
                                );
                                const lineTotal = Math.max(
                                    0,
                                    lineGross - lineDiscount,
                                );
                                const selectedUnit = (
                                    material?.unit_options ?? []
                                ).find(
                                    (option) =>
                                        String(option.value) ===
                                        String(item.material_unit_id),
                                );
                                const normalizedQty =
                                    Number(item.qty || 0) /
                                    Number(selectedUnit?.factor_to_base || 1);
                                const baseUnit = (material?.unit_options ??
                                    [])[0];

                                return (
                                    <tr key={index}>
                                        <td className="px-3 py-2 font-bold">
                                            {index + 1}
                                        </td>
                                        <td className="px-3 py-2 min-w-52">
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
                                                min="0"
                                                step="0.000001"
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
                                            <div className="min-w-32">
                                                <Dropdown
                                                    value={
                                                        item.material_unit_id ??
                                                        ""
                                                    }
                                                    options={
                                                        material?.unit_options ??
                                                        []
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
                                        </td>
                                        <td className="px-3 py-2 text-right font-black">
                                            {Number(
                                                normalizedQty || 0,
                                            ).toLocaleString("id-ID", {
                                                maximumFractionDigits: 6,
                                            })}{" "}
                                            {baseUnit?.symbol ?? ""}
                                        </td>
                                        <td className="px-3 py-2">
                                            <input
                                                className="h-9 w-36 rounded-md border border-silver-deep/70 bg-white/85 px-3 text-right font-bold dark:border-white/10 dark:bg-white/8"
                                                type="number"
                                                min="0"
                                                step="1"
                                                value={item.harga_satuan}
                                                onChange={(event) =>
                                                    setItem(
                                                        index,
                                                        "harga_satuan",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <input
                                                className="h-9 w-32 rounded-md border border-silver-deep/70 bg-white/85 px-3 text-right font-bold dark:border-white/10 dark:bg-white/8"
                                                type="number"
                                                min="0"
                                                step="1"
                                                value={item.diskon}
                                                onChange={(event) =>
                                                    setItem(
                                                        index,
                                                        "diskon",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </td>
                                        <td className="px-3 py-2 text-right font-black">
                                            {money(lineTotal)}
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

                <div className="grid gap-3 border-t border-silver-deep/60 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4 lg:grid-cols-[1fr_440px]">
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
                    <div className="grid gap-2">
                        <FooterTotal label="Sub Total" value={subtotal} />
                        <div className="grid grid-cols-[1fr_180px] items-center gap-3 text-xs font-bold">
                            <span className="text-right text-ink-soft dark:text-white/60">
                                Potongan Transaksi
                            </span>
                            <Input
                                type="number"
                                min="0"
                                step="1"
                                value={form.data.diskon_transaksi}
                                onChange={(event) =>
                                    form.setData(
                                        "diskon_transaksi",
                                        event.target.value,
                                    )
                                }
                                inputClassName="h-9 min-h-9 text-right font-black"
                            />
                        </div>
                        <FooterTotal label="Total Akhir" value={totalAkhir} />
                        <FooterTotal
                            label="Biaya Perolehan"
                            value={acquisitionCost}
                        />
                        <FooterTotal
                            label="Modal Persediaan (Landed Cost)"
                            value={landedTotal}
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

function SummaryCard({ title, value, strong = false }) {
    return (
        <div className="rounded-xl border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
            <p className="text-xs font-extrabold uppercase tracking-[0.2em] text-ink-soft dark:text-white/55">
                {title}
            </p>
            <p
                className={`mt-2 text-lg font-black ${strong ? "text-emerald-600 dark:text-emerald-300" : "text-ink dark:text-white"}`}
            >
                {value}
            </p>
        </div>
    );
}

function FooterTotal({ label, value, strong = false }) {
    return (
        <div
            className={`grid grid-cols-[1fr_180px] items-center gap-3 text-xs ${strong ? "font-black" : "font-bold"}`}
        >
            <span className="text-right text-ink-soft dark:text-white/60">
                {label}
            </span>
            <span className="rounded-md border border-silver-deep/60 bg-white/80 px-3 py-2 text-right dark:border-white/10 dark:bg-white/8">
                {money(value)}
            </span>
        </div>
    );
}

Create.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Transaksi Pembelian Material"}>
        {page}
    </AdminLayout>
);
