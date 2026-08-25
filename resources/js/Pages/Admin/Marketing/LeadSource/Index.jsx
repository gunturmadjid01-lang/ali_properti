import { Head, router } from "@inertiajs/react";
import {
    Edit3,
    Eye,
    Lock,
    PlusCircle,
    Search,
    Trash2,
    Unlock,
} from "lucide-react";
import { useState } from "react";
import Pagination from "../../../../Components/Pagination";
import { Button, Input, TableActions } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Index({
    title,
    description,
    baseUrl,
    rows = { data: [], links: [] },
    filters = {},
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const filter = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveState: true, replace: true });
    };
    const remove = (row) => {
        if (window.confirm(`Hapus sumber lead ${row.nama_sumber}?`)) {
            router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                        Manager Marketing / Master Data
                    </p>
                    <div className="mt-2 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h1 className="text-3xl font-extrabold">{title}</h1>
                            <p className="mt-2 max-w-3xl text-ink-soft">
                                {description}
                            </p>
                        </div>
                        {permissions.canCreate && (
                            <Button
                                type="button"
                                onClick={() =>
                                    router.visit(`${baseUrl}/create`)
                                }
                            >
                                <PlusCircle size={17} /> Tambah Sumber Lead
                            </Button>
                        )}
                    </div>
                </section>
                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 md:grid-cols-[1fr_auto]"
                        onSubmit={filter}
                    >
                        <Input
                            label="Cari Kode / Nama / Kategori"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
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
                                        "Nama Sumber",
                                        "Kategori",
                                        "Pelanggan",
                                        "Status",
                                        "Audit",
                                        "Kunci",
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
                                            {row.kode_sumber}
                                        </td>
                                        <td className="px-5 py-4 font-extrabold">
                                            {row.nama_sumber}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.kategori}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.jumlah_customer}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.status}
                                        </td>
                                        <td className="min-w-44 px-5 py-4 text-xs">
                                            <b>Dibuat:</b> {row.created_by_name}
                                            <br />
                                            <b>Diubah:</b> {row.updated_by_name}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.record_status}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.visit(
                                                            `${baseUrl}/${row.id}`,
                                                        )
                                                    }
                                                >
                                                    <Eye size={14} /> Detail
                                                </Button>
                                                {row.can_edit && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.visit(
                                                                `${baseUrl}/${row.id}/edit`,
                                                            )
                                                        }
                                                    >
                                                        <Edit3 size={14} /> Ubah
                                                    </Button>
                                                )}
                                                {row.can_delete && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="text-red-600"
                                                        onClick={() =>
                                                            remove(row)
                                                        }
                                                    >
                                                        <Trash2 size={14} />
                                                    </Button>
                                                )}
                                                {row.can_lock && (
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
                                                {row.can_unlock &&
                                                    permissions.canUnlock && (
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
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center font-bold text-ink-soft"
                                            colSpan={8}
                                        >
                                            Belum ada sumber lead.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Sumber Lead"}>
        {page}
    </AdminLayout>
);
