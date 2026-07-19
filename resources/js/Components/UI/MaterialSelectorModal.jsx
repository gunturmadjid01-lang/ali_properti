import { TableActions } from "./";
import { PlusCircle, Search, X } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import Button from "./Button";
import Input from "./Input";
import Modal from "./Modal";

const money = (value) =>
    new Intl.NumberFormat("id-ID", { maximumFractionDigits: 0 }).format(
        Number(value ?? 0),
    );

function splitMaterialLabel(label) {
    if (typeof label !== "string") {
        return ["", ""];
    }

    const [kode = "", ...rest] = label.split(" - ");

    return [kode, rest.join(" - ")];
}

export default function MaterialSelectorModal({
    open,
    onClose,
    onPick,
    materials = [],
    title = "Pilih Material",
    description = "Cari material lalu tekan Add untuk memasukkan item ke transaksi.",
    initialSearch = "",
}) {
    const [search, setSearch] = useState(() => String(initialSearch ?? ""));

    useEffect(() => {
        if (open) {
            setSearch(String(initialSearch ?? ""));
        }
    }, [open, initialSearch]);

    const filteredMaterials = useMemo(() => {
        const keyword = String(search ?? "")
            .trim()
            .toLowerCase();

        if (!keyword) {
            return materials;
        }

        return materials.filter((material) => {
            const haystack = [
                material.kode_barang,
                material.nama_barang,
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
    }, [materials, search]);

    return (
        <Modal open={open} onClose={onClose} title={title} size="xl">
            <div className="grid gap-3">
                <p className="text-sm font-semibold text-ink-soft dark:text-white/65">
                    {description}
                </p>
                <Input
                    label="Cari Material"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    inputClassName="h-10 min-h-10 text-sm"
                />

                <div className="max-h-[58vh] overflow-auto rounded-xl border border-silver-deep/60 dark:border-white/10">
                    <table className="w-full min-w-[860px] divide-y divide-silver-deep/60 text-xs">
                        <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                            <tr>
                                {[
                                    "Kode",
                                    "Material",
                                    "Jenis / Merk",
                                    "Satuan",
                                    "HPP",
                                    "Aksi",
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
                            {filteredMaterials.map((material) => (
                                <tr
                                    key={material.value}
                                    className="hover:bg-silver-soft/60 dark:hover:bg-white/8"
                                >
                                    {(() => {
                                        const [kode, nama] = splitMaterialLabel(
                                            material.label,
                                        );

                                        return (
                                            <>
                                                <td className="px-3 py-2 font-black text-ink dark:text-white">
                                                    {material.kode_barang ??
                                                        kode ??
                                                        "-"}
                                                </td>
                                                <td className="px-3 py-2 font-bold">
                                                    {material.nama_barang ??
                                                        nama ??
                                                        material.label ??
                                                        "-"}
                                                </td>
                                            </>
                                        );
                                    })()}
                                    <td className="px-3 py-2">
                                        {trimLabel(material)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {material.satuan ?? "-"}
                                    </td>
                                    <td className="px-3 py-2 text-right font-black">
                                        {money(material.harga_hpp)}
                                    </td>
                                    <td className="px-3 py-2">
                                        <TableActions>
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() =>
                                                    onPick?.(material)
                                                }
                                            >
                                                <PlusCircle size={15} /> Add
                                            </Button>
                                        </TableActions>
                                    </td>
                                </tr>
                            ))}
                            {filteredMaterials.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center font-semibold text-ink-soft dark:text-white/55"
                                    >
                                        Material tidak ditemukan.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-end">
                    <Button type="button" variant="outline" onClick={onClose}>
                        <X size={15} /> Tutup
                    </Button>
                </div>
            </div>
        </Modal>
    );
}

function trimLabel(material) {
    const jenis = material.jenis_material ?? material.kategori_material ?? "-";
    const merk = material.merk_material ?? "-";

    return `${jenis} / ${merk}`;
}
