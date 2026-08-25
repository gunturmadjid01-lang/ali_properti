import { Head, router } from "@inertiajs/react";
import {
    CalendarDays,
    Edit3,
    Eye,
    Lock,
    MessageSquarePlus,
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
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const filter = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveState: true, replace: true });
    };
    const remove = (row) => {
        if (window.confirm(`Hapus follow-up ${row.customer}?`)) {
            router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">
                                Marketing / Tahap 2
                            </p>
                            <h1 className="mt-1 text-2xl font-extrabold">
                                {title}
                            </h1>
                            <p className="mt-1 max-w-2xl text-sm leading-6 text-ink-soft">
                                {description}
                            </p>
                        </div>
                        <Button
                            type="button"
                            onClick={() => router.visit(`${baseUrl}/create`)}
                        >
                            <MessageSquarePlus size={18} /> Tambah Tindak Lanjut
                        </Button>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-end md:justify-between"
                        onSubmit={filter}
                    >
                        <Input
                            className="w-full md:max-w-md"
                            icon={<Search size={17} />}
                            label="Cari Tindak Lanjut"
                            value={search}
                            placeholder="Nama, identitas, media, atau catatan"
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Button type="submit">
                            <Search size={17} /> Cari
                        </Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5">
                                <tr>
                                    {[
                                        "Tanggal",
                                        "Pelanggan",
                                        "Media",
                                        "Keseriusan",
                                        "Kemajuan",
                                        "Status",
                                        "Kunci",
                                        "Rencana",
                                        "Aksi",
                                    ].map((label) => (
                                        <th
                                            className="px-4 py-3 font-extrabold"
                                            key={label}
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr
                                        className="hover:bg-silver/70 dark:hover:bg-white/5"
                                        key={row.id}
                                    >
                                        <td className="px-4 py-3 font-semibold">
                                            <span className="inline-flex items-center gap-2">
                                                <CalendarDays size={15} />
                                                {row.tanggal_follow_up}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <b>{row.customer}</b>
                                            <br />
                                            <span className="text-ink-soft">
                                                {row.kode_costumer}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.metode_follow_up}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.status_serius}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.progress_kemampuan}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.status_label}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.record_status_label}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.rencana_follow_up_at || "-"}
                                        </td>
                                        <td className="px-4 py-3">
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
                                                    <Eye size={15} /> Detail
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.visit(
                                                            `${baseUrl}/create?costumer_id=${row.costumer_id}`,
                                                        )
                                                    }
                                                >
                                                    <MessageSquarePlus
                                                        size={15}
                                                    />{" "}
                                                    Lagi
                                                </Button>
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
                                                        <Lock size={15} /> Kunci
                                                    </Button>
                                                )}
                                                {row.can_unlock && (
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
                                                        <Unlock size={15} />{" "}
                                                        Unlock
                                                    </Button>
                                                )}
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
                                                        <Edit3 size={15} /> Ubah
                                                    </Button>
                                                )}
                                                {row.can_delete && (
                                                    <Button
                                                        className="text-red-600"
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            remove(row)
                                                        }
                                                    >
                                                        <Trash2 size={15} />
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
                                            colSpan={9}
                                        >
                                            Belum ada data follow-up.
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
    <AdminLayout title={page?.props?.title ?? "Jejak Tindak Lanjut"}>
        {page}
    </AdminLayout>
);
