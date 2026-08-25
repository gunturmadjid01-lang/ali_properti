import { Head, router } from "@inertiajs/react";
import { Edit3, Lock, Plus, Search, Trash2, Unlock } from "lucide-react";
import { useState } from "react";
import { Button, Dropdown, Input, TableActions } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { SectionCard, WarehousePage } from "./components/WarehouseShell";

const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

export default function MasterMaterial({
    title,
    baseUrl,
    createUrl,
    rows,
    filters = {},
    options,
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [gudangId, setGudangId] = useState(filters.gudang_id ?? "");
    const gudangOptions = [
        { value: "", label: "Semua Gudang" },
        ...(options?.gudangs ?? []),
    ];
    const lock = (row, action) =>
        window.confirm(
            `${action === "lock" ? "Kunci" : "Buka lock"} material ${row.nama_barang}?`,
        ) &&
        router.post(
            `${baseUrl}/${row.id}/${action}`,
            {},
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={title} />
            <WarehousePage
                eyebrow="Master Gudang"
                title="Kelola Item Material"
                description="Stok seluruh sistem ditampilkan dalam satuan level 1. Satuan pembelian lainnya otomatis dikonversi."
            >
                <SectionCard
                    title="Daftar material"
                    description="Pilih gudang untuk melihat stok level 1 per gudang."
                    actions={
                        <div className="flex flex-col gap-3 md:flex-row md:items-end">
                            {permissions.canCreate && (
                                <Button as="a" href={createUrl}>
                                    <Plus size={16} /> Tambah Material
                                </Button>
                            )}
                            <form
                                className="flex flex-col gap-3 md:flex-row md:items-end"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    router.get(
                                        baseUrl,
                                        { search, gudang_id: gudangId },
                                        { preserveState: true, replace: true },
                                    );
                                }}
                            >
                                <div className="grid gap-2 md:w-56">
                                    <span className="text-sm font-extrabold">
                                        Gudang
                                    </span>
                                    <Dropdown
                                        value={gudangId}
                                        options={gudangOptions}
                                        onChange={setGudangId}
                                    />
                                </div>
                                <Input
                                    label="Cari material"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                                <Button type="submit">
                                    <Search size={16} /> Cari
                                </Button>
                            </form>
                        </div>
                    }
                >
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs">
                            <thead>
                                <tr>
                                    {[
                                        "Kode",
                                        "Material",
                                        "Jenis",
                                        "Merk",
                                        "Stok Level 1",
                                        "Harga Level 1",
                                        "Minimum",
                                        "Status",
                                        "Aksi",
                                    ].map((column) => (
                                        <th
                                            className="px-4 py-3 text-left font-extrabold"
                                            key={column}
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-3 font-bold">
                                            {row.kode_barang}
                                        </td>
                                        <td className="px-4 py-3 font-extrabold">
                                            {row.nama_barang}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.jenis_material ?? "-"}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.merk_material ?? "-"}
                                        </td>
                                        <td className="px-4 py-3 font-black">
                                            {Number(
                                                row.stok_tersedia ?? 0,
                                            ).toLocaleString("id-ID")}{" "}
                                            {row.satuan}
                                        </td>
                                        <td className="px-4 py-3 font-bold">
                                            {money(row.harga_hpp)} /{" "}
                                            {row.satuan}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.stok_minimum}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.status}
                                        </td>
                                        <td className="px-4 py-3">
                                            <TableActions>
                                                {row.record_status ===
                                                "locked" ? (
                                                    permissions.canUnlock && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                lock(
                                                                    row,
                                                                    "unlock",
                                                                )
                                                            }
                                                        >
                                                            <Unlock size={14} />
                                                        </Button>
                                                    )
                                                ) : (
                                                    <>
                                                        {permissions.canLock && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    lock(
                                                                        row,
                                                                        "lock",
                                                                    )
                                                                }
                                                            >
                                                                <Lock
                                                                    size={14}
                                                                />
                                                            </Button>
                                                        )}
                                                        {permissions.canUpdate && (
                                                            <Button
                                                                as="a"
                                                                href={`${baseUrl}/${row.id}/edit`}
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                <Edit3
                                                                    size={14}
                                                                />
                                                            </Button>
                                                        )}
                                                        {permissions.canDelete && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="text-red-600"
                                                                onClick={() =>
                                                                    window.confirm(
                                                                        `Hapus ${row.nama_barang}?`,
                                                                    ) &&
                                                                    router.delete(
                                                                        `${baseUrl}/${row.id}`,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2
                                                                    size={14}
                                                                />
                                                            </Button>
                                                        )}
                                                    </>
                                                )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </SectionCard>
            </WarehousePage>
        </>
    );
}

MasterMaterial.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Kelola Item Material"}>
        {page}
    </AdminLayout>
);
