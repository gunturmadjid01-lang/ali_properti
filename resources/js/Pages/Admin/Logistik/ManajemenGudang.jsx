import { Head, router } from "@inertiajs/react";
import { ChevronDown, Edit3, Plus, Search, Trash2 } from "lucide-react";
import { useState } from "react";
import { Button, Input, TableActions } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

export default function ManajemenGudang({
    title,
    baseUrl,
    rows,
    filters = {},
    perumahans = [],
    allUsers = [],
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [perumahanId, setPerumahanId] = useState(filters.perumahan_id ?? "");
    const [expandedRow, setExpandedRow] = useState(null);

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(
            baseUrl,
            {
                search,
                perumahan_id: perumahanId,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const handleAssignUser = (gudangId, userId) => {
        router.post(`/admin/gudang/${gudangId}/assign-user`, {
            user_id: userId,
        });
    };

    const handleRemoveUser = (gudangId, userId) => {
        router.post(`/admin/gudang/${gudangId}/remove-user`, {
            user_id: userId,
        });
    };

    const handleDelete = (row) => {
        if (!window.confirm(`Hapus gudang ${row.nama_gudang}?`)) return;
        router.delete(`/admin/gudang/${row.id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <AdminLayout>
                <div className="px-6 py-4">
                    <div className="mb-6 flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-ink-darkest">
                                Manajemen Gudang
                            </h1>
                            <p className="mt-1 text-sm text-ink-soft">
                                Kelola daftar gudang dan penugasan pekerja per
                                perumahan
                            </p>
                        </div>
                        {permissions.canCreate && (
                            <Button as="a" href="/admin/gudang/create">
                                <Plus size={17} /> Tambah Gudang Baru
                            </Button>
                        )}
                    </div>

                    <div className="mb-6 rounded-lg border border-silver-deep/30 bg-white p-4">
                        <form
                            onSubmit={handleSearch}
                            className="flex flex-col gap-4 md:flex-row md:items-end"
                        >
                            <div className="flex-1">
                                <label className="mb-1 block text-sm font-medium text-ink-darker">
                                    Cari Gudang
                                </label>
                                <Input
                                    placeholder="Kode, nama, atau PIC gudang..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <div className="flex-1">
                                <label className="mb-1 block text-sm font-medium text-ink-darker">
                                    Filter Perumahan
                                </label>
                                <select
                                    className="w-full rounded-lg border border-silver-deep/30 bg-white px-3 py-2 text-sm text-ink-darker placeholder-ink-soft focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                                    value={perumahanId}
                                    onChange={(e) =>
                                        setPerumahanId(e.target.value)
                                    }
                                >
                                    <option value="">Semua Perumahan</option>
                                    {perumahans.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.nama_perusahaan}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <Button type="submit">
                                <Search size={17} /> Cari
                            </Button>
                        </form>
                    </div>

                    <div className="space-y-4">
                        {rows.data && rows.data.length > 0 ? (
                            rows.data.map((row) => (
                                <div
                                    key={row.id}
                                    className="rounded-lg border border-silver-deep/30 bg-white"
                                >
                                    <div
                                        className="flex cursor-pointer items-center justify-between p-4 hover:bg-silver-soft/50"
                                        onClick={() =>
                                            setExpandedRow(
                                                expandedRow === row.id
                                                    ? null
                                                    : row.id,
                                            )
                                        }
                                    >
                                        <div className="flex-1">
                                            <div className="font-bold text-ink-darkest">
                                                {row.nama_gudang}
                                                <span className="ml-2 text-xs font-normal text-ink-soft">
                                                    ({row.kode_gudang})
                                                </span>
                                            </div>
                                            <div className="mt-1 flex gap-4 text-xs text-ink-soft">
                                                <span>
                                                    <strong>Perumahan:</strong>{" "}
                                                    {row.perumahan}
                                                </span>
                                                <span>
                                                    <strong>Cabang:</strong>{" "}
                                                    {row.cabang}
                                                </span>
                                                <span>
                                                    <strong>PIC:</strong>{" "}
                                                    {row.penanggung_jawab ||
                                                        "-"}
                                                </span>
                                                <span>
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs font-bold ${
                                                            row.status ===
                                                            "aktif"
                                                                ? "bg-emerald-100 text-emerald-700"
                                                                : "bg-slate-100 text-slate-600"
                                                        }`}
                                                    >
                                                        {row.status}
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            {(permissions.canUpdate ||
                                                permissions.canDelete) && (
                                                <div
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                >
                                                    <TableActions>
                                                        {permissions.canUpdate && (
                                                            <Button
                                                                as="a"
                                                                href={`/admin/gudang/${row.id}/edit`}
                                                                variant="outline"
                                                                size="sm"
                                                            >
                                                                <Edit3
                                                                    size={15}
                                                                />
                                                                Edit
                                                            </Button>
                                                        )}
                                                        {permissions.canDelete && (
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                                className="text-red-600"
                                                                onClick={() =>
                                                                    handleDelete(
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2
                                                                    size={15}
                                                                />
                                                                Hapus
                                                            </Button>
                                                        )}
                                                    </TableActions>
                                                </div>
                                            )}
                                            <div className="text-right">
                                                <div className="text-xs text-ink-soft">
                                                    Petugas
                                                </div>
                                                <div className="text-sm font-bold text-ink-darker">
                                                    {row.users.length}
                                                </div>
                                            </div>
                                            <ChevronDown
                                                size={20}
                                                className={`transition-transform ${
                                                    expandedRow === row.id
                                                        ? "rotate-180"
                                                        : ""
                                                }`}
                                            />
                                        </div>
                                    </div>

                                    {expandedRow === row.id && (
                                        <div className="border-t border-silver-deep/30 p-4">
                                            <div className="mb-4">
                                                <h4 className="mb-3 font-bold text-ink-darker">
                                                    Petugas Gudang (
                                                    {row.users.length})
                                                </h4>
                                                {row.users.length > 0 ? (
                                                    <div className="mb-4 space-y-2">
                                                        {row.users.map(
                                                            (user) => (
                                                                <div
                                                                    key={
                                                                        user.id
                                                                    }
                                                                    className="flex items-center justify-between rounded bg-silver-soft/50 p-2"
                                                                >
                                                                    <div className="text-sm">
                                                                        <div className="font-medium text-ink-darker">
                                                                            {
                                                                                user.name
                                                                            }
                                                                        </div>
                                                                        <div className="text-xs text-ink-soft">
                                                                            {
                                                                                user.email
                                                                            }
                                                                        </div>
                                                                    </div>
                                                                    {permissions.canUpdate && (
                                                                        <Button
                                                                            variant="outline"
                                                                            size="sm"
                                                                            onClick={() =>
                                                                                handleRemoveUser(
                                                                                    row.id,
                                                                                    user.id,
                                                                                )
                                                                            }
                                                                        >
                                                                            Hapus
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                ) : (
                                                    <div className="rounded bg-silver-soft/50 p-3 text-center text-sm text-ink-soft">
                                                        Belum ada petugas yang
                                                        ditugaskan
                                                    </div>
                                                )}
                                            </div>

                                            {permissions.canUpdate && (
                                                <div>
                                                    <h4 className="mb-3 font-bold text-ink-darker">
                                                        Tambah Petugas
                                                    </h4>
                                                    <select
                                                        className="w-full rounded-lg border border-silver-deep/30 bg-white px-3 py-2 text-sm text-ink-darker"
                                                        onChange={(e) => {
                                                            if (
                                                                e.target.value
                                                            ) {
                                                                handleAssignUser(
                                                                    row.id,
                                                                    parseInt(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                                );
                                                                e.target.value =
                                                                    "";
                                                            }
                                                        }}
                                                        defaultValue=""
                                                    >
                                                        <option value="">
                                                            Pilih Petugas...
                                                        </option>
                                                        {allUsers
                                                            .filter(
                                                                (u) =>
                                                                    !row.users.some(
                                                                        (ru) =>
                                                                            ru.id ===
                                                                            u.id,
                                                                    ),
                                                            )
                                                            .map((user) => (
                                                                <option
                                                                    key={
                                                                        user.id
                                                                    }
                                                                    value={
                                                                        user.id
                                                                    }
                                                                >
                                                                    {user.name}{" "}
                                                                    (
                                                                    {user.email}
                                                                    )
                                                                </option>
                                                            ))}
                                                    </select>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ))
                        ) : (
                            <div className="rounded-lg border border-silver-deep/30 bg-white p-8 text-center">
                                <p className="text-ink-soft">
                                    Belum ada gudang yang sesuai dengan kriteria
                                    pencarian
                                </p>
                            </div>
                        )}
                    </div>

                    {rows.links && rows.last_page > 1 && (
                        <div className="mt-6 flex justify-center gap-2">
                            {rows.links.map((link, idx) => (
                                <a
                                    key={idx}
                                    href={link.url || "#"}
                                    className={`rounded px-3 py-1 text-sm ${
                                        link.active
                                            ? "bg-primary-500 text-white"
                                            : "border border-silver-deep/30 text-ink-darker hover:bg-silver-soft/50"
                                    }`}
                                    onClick={(e) => {
                                        if (!link.url) {
                                            e.preventDefault();
                                            return;
                                        }
                                        e.preventDefault();
                                        router.visit(link.url, {
                                            preserveState: true,
                                        });
                                    }}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </AdminLayout>
        </>
    );
}
