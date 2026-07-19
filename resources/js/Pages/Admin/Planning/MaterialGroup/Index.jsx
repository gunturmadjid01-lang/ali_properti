import { Head, Link, router } from "@inertiajs/react";
import { Edit3, PackageOpen, Plus, Search, Trash2 } from "lucide-react";
import { useState } from "react";
import Pagination from "../../../../Components/Pagination";
import { Button, Input, TableActions } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const number = (value) =>
    Number(value ?? 0).toLocaleString("id-ID", { maximumFractionDigits: 6 });

export default function Index({
    title,
    baseUrl,
    createUrl,
    rows,
    filters = {},
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const runSearch = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="flex flex-col gap-4 rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p className="text-xs font-black uppercase tracking-wider text-ink-soft">
                            Perencanaan Biaya
                        </p>
                        <h1 className="mt-1 text-2xl font-black">
                            Kelompok Material
                        </h1>
                        <p className="mt-1 text-sm text-ink-soft">
                            Susun resep beberapa material untuk satu item HPP
                            tanpa kehilangan rincian realisasi tiap material.
                        </p>
                    </div>
                    {permissions.canCreate && (
                        <Button as={Link} href={createUrl}>
                            <Plus size={16} /> Tambah Kelompok
                        </Button>
                    )}
                </section>

                <section className="rounded-xl border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 border-b border-silver-deep/50 p-5 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h2 className="font-black">Daftar Kelompok</h2>
                            <p className="text-sm text-ink-soft">
                                Setiap kelompok dapat berisi beberapa material
                                dan satuan konversi berbeda.
                            </p>
                        </div>
                        <form
                            className="flex items-end gap-2"
                            onSubmit={runSearch}
                        >
                            <Input
                                label="Cari kode atau nama"
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
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm">
                            <thead>
                                <tr>
                                    {[
                                        "Kode",
                                        "Nama Kelompok",
                                        "Qty Dasar",
                                        "Isi Material",
                                        "Status",
                                        "Aksi",
                                    ].map((column) => (
                                        <th
                                            className="px-5 py-4 text-left text-xs font-extrabold uppercase tracking-wide"
                                            key={column}
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {(rows.data ?? []).map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">
                                            {row.code}
                                        </td>
                                        <td className="px-5 py-4 font-black">
                                            {row.name}
                                        </td>
                                        <td className="px-5 py-4">
                                            {number(row.base_quantity)}{" "}
                                            {row.base_unit}
                                        </td>
                                        <td className="min-w-72 px-5 py-4">
                                            <div className="grid gap-1">
                                                {row.items_summary.map(
                                                    (item, index) => (
                                                        <span
                                                            key={`${item.material}-${index}`}
                                                        >
                                                            {index + 1}.{" "}
                                                            {item.material} —{" "}
                                                            <b>
                                                                {number(
                                                                    item.quantity,
                                                                )}{" "}
                                                                {item.unit}
                                                            </b>
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 capitalize">
                                            {row.status}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                {permissions.canUpdate && (
                                                    <Button
                                                        as={Link}
                                                        href={`${baseUrl}/${row.id}/edit`}
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Edit3 size={14} />
                                                    </Button>
                                                )}
                                                {permissions.canDelete && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="text-red-600"
                                                        onClick={() =>
                                                            window.confirm(
                                                                `Hapus kelompok ${row.name}?`,
                                                            ) &&
                                                            router.delete(
                                                                `${baseUrl}/${row.id}`,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 size={14} />
                                                    </Button>
                                                )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {!(rows.data ?? []).length && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-5 py-12 text-center text-ink-soft"
                                        >
                                            <PackageOpen className="mx-auto mb-2" />
                                            Belum ada kelompok material.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links ?? []} />
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Kelompok Material"}>
        {page}
    </AdminLayout>
);
