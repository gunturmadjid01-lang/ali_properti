import { Head, Link, router } from "@inertiajs/react";
import {
    Edit3,
    Eye,
    Lock,
    PlusCircle,
    RotateCcw,
    Search,
    Trash2,
    Unlock,
} from "lucide-react";
import { useState } from "react";
import { Button, Dropdown, Input, TableActions } from "../../../Components/UI";
import AuditCell from "../../../Components/UI/AuditCell";
import AdminLayout from "../../../Layouts/AdminLayout";
import { useResourcePermissions } from "../../../Utils/permissions";

const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

function Pagination({ links = [] }) {
    if (links.length <= 3) return null;
    return (
        <div className="flex flex-wrap justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
            {links.map((link, index) => (
                <Button
                    as={Link}
                    className={
                        !link.url ? "pointer-events-none opacity-45" : ""
                    }
                    dangerouslySetInnerHTML={{ __html: link.label }}
                    href={link.url ?? "#"}
                    key={`${link.label}-${index}`}
                    preserveScroll
                    size="sm"
                    variant={link.active ? "dark" : "outline"}
                />
            ))}
        </div>
    );
}

export default function Index({
    title,
    description,
    baseUrl,
    createUrl,
    rows,
    filters = {},
    options,
    permissions = {},
}) {
    const access = useResourcePermissions("detail-rumah", baseUrl);
    const [search, setSearch] = useState(filters.search ?? "");
    const [block, setBlock] = useState(filters.block ?? "");
    const [type, setType] = useState(filters.type ?? "");
    const [perPage, setPerPage] = useState(filters.per_page ?? "10");

    const submitFilters = (event) => {
        event.preventDefault();
        router.get(
            baseUrl,
            { search, block, type, per_page: perPage },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const destroyRow = (row) => {
        if (window.confirm(`Hapus unit ${row.kode_nlok} ${row.nomor_rumah}?`)) {
            router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
        }
    };

    const toggleLock = (row) => {
        const action = row.record_status === "locked" ? "unlock" : "lock";
        const message =
            action === "lock"
                ? "Kunci data unit ini?"
                : "Buka lock data unit ini?";
        if (window.confirm(message)) {
            router.post(
                `${baseUrl}/${row.id}/${action}`,
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="flex flex-col gap-4 rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p className="text-xs font-extrabold uppercase tracking-[0.14em] text-ink-soft">
                            Data Proyek
                        </p>
                        <h2 className="mt-2 font-display text-3xl font-extrabold">
                            {title}
                        </h2>
                        <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">
                            {description}
                        </p>
                    </div>
                    {access.canCreate && (
                        <Button as={Link} href={createUrl}>
                            <PlusCircle size={17} /> Tambah Kapling / Unit
                        </Button>
                    )}
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 lg:grid-cols-[1.4fr_1fr_1fr_0.8fr_auto_auto] lg:items-end"
                        onSubmit={submitFilters}
                    >
                        <Input
                            label="Pencarian"
                            value={search}
                            placeholder="Cari perumahan, blok, nomor, atau tipe..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Filter Blok</span>
                            <Dropdown
                                value={block}
                                options={options.filterBlokOptions}
                                onChange={setBlock}
                            />
                        </label>
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Filter Tipe</span>
                            <Dropdown
                                value={type}
                                options={options.tipeRumahOptions}
                                onChange={setType}
                            />
                        </label>
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Per Halaman</span>
                            <Dropdown
                                value={perPage}
                                options={options.perPageOptions}
                                searchable={false}
                                onChange={setPerPage}
                            />
                        </label>
                        <Button type="submit">
                            <Search size={17} /> Cari
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.get(baseUrl)}
                        >
                            <RotateCcw size={17} /> Atur Ulang
                        </Button>
                    </form>

                    <div className="border-t border-silver-deep/60 px-5 py-3 text-sm font-bold text-ink-soft dark:border-white/10 dark:text-white/55">
                        Menampilkan {rows.from ?? 0} - {rows.to ?? 0} dari{" "}
                        {rows.total ?? 0} unit.
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-[1120px] w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {[
                                        "Perumahan",
                                        "Blok / Nomor",
                                        "Tipe",
                                        "Kemajuan",
                                        "Status Bangun",
                                        "Harga Jual",
                                        "Audit",
                                        "Kunci",
                                        "Status",
                                        "Aksi",
                                    ].map((column) => (
                                        <th
                                            className="px-4 py-4 font-extrabold"
                                            key={column}
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-4 font-bold">
                                            {row.perumahan}
                                        </td>
                                        <td className="px-4 py-4">
                                            <strong>
                                                Blok {row.blok_label}
                                            </strong>
                                            <div className="text-xs text-ink-soft">
                                                No. {row.nomor_rumah}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4">
                                            {row.tipe_rumah || "-"}
                                        </td>
                                        <td className="px-4 py-4 font-bold">
                                            {row.progress_terakhir}%
                                        </td>
                                        <td className="px-4 py-4">
                                            {row.status_pembangunan}
                                        </td>
                                        <td className="px-4 py-4 font-extrabold">
                                            {money(row.harga_jual)}
                                        </td>
                                        <td className="px-4 py-4">
                                            <AuditCell
                                                createdBy={row.created_by}
                                                updatedBy={row.updated_by}
                                            />
                                        </td>
                                        <td className="px-4 py-4">
                                            <span className="rounded-full bg-silver-soft px-3 py-1 text-xs font-extrabold dark:bg-white/10">
                                                {row.record_status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-4">
                                            {row.status}
                                        </td>
                                        <td className="px-4 py-4">
                                            <TableActions>
                                                {access.canUpdate &&
                                                    row.can_edit && (
                                                        <Button
                                                            as={Link}
                                                            href={row.edit_url}
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <Edit3 size={15} />{" "}
                                                            Edit
                                                        </Button>
                                                    )}
                                                <Button
                                                    as={Link}
                                                    href={row.detail_url}
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Eye size={15} /> Detail
                                                </Button>
                                                {access.canDelete &&
                                                    row.can_delete && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                destroyRow(row)
                                                            }
                                                        >
                                                            <Trash2 size={15} />
                                                        </Button>
                                                    )}
                                                {(row.record_status !==
                                                    "locked" ||
                                                    permissions.canManageLocked) && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            toggleLock(row)
                                                        }
                                                    >
                                                        {row.record_status ===
                                                        "locked" ? (
                                                            <Unlock size={15} />
                                                        ) : (
                                                            <Lock size={15} />
                                                        )}
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
                                            colSpan={11}
                                        >
                                            Belum ada kapling atau unit rumah.
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
    <AdminLayout title={page?.props?.title ?? "Kapling / Unit"}>
        {page}
    </AdminLayout>
);
