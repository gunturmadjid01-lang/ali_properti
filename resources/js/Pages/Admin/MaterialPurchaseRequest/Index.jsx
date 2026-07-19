import { Head, router } from "@inertiajs/react";
import {
    ArrowDownAZ,
    ArrowUpAZ,
    CheckCircle2,
    Eye,
    Lock,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    ShoppingCart,
    Trash2,
    Unlock,
    XCircle,
} from "lucide-react";
import { useState } from "react";
import Pagination from "../../../Components/Pagination";
import {
    Button,
    Dropdown,
    Input,
    Modal,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const decimal = (value) => Number(value ?? 0).toLocaleString("id-ID");

export default function Index({
    title,
    baseUrl,
    createUrl,
    purchaseCreateUrl,
    rows = { data: [], links: [] },
    filters = {},
    options = {},
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [gudangId, setGudangId] = useState(filters.gudangId ?? "");
    const [status, setStatus] = useState(filters.status ?? "");
    const [sort, setSort] = useState(filters.sort ?? "tanggal");
    const [direction, setDirection] = useState(filters.direction ?? "desc");
    const [detail, setDetail] = useState(null);

    const filter = (event) => {
        event.preventDefault();
        router.get(
            baseUrl,
            { search, gudang_id: gudangId, status, sort, direction },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const reset = () => {
        setSearch("");
        setGudangId("");
        setStatus("");
        setSort("tanggal");
        setDirection("desc");
        router.get(
            baseUrl,
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    const post = (url) => router.post(url, {}, { preserveScroll: true });
    const destroy = (row) =>
        window.confirm(`Hapus permintaan ${row.kode_request}?`) &&
        router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    const process = (row) =>
        router.visit(`${purchaseCreateUrl}?purchase_request_id=${row.id}`);
    const isLocked = (row) => row.record_status === "locked";

    return (
        <>
            <Head title={title} />
            <section className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6">
                <div className="border-b border-silver-deep/50 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4">
                    <form
                        className="grid gap-2 xl:grid-cols-[1fr_240px_210px_210px_auto]"
                        onSubmit={filter}
                    >
                        <Input
                            label="Kata Kunci"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            inputClassName="h-9 min-h-9 text-xs"
                        />
                        <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                            Dept. / Gudang
                            <Dropdown
                                value={gudangId}
                                label="Semua Gudang"
                                options={[
                                    { value: "", label: "Semua Gudang" },
                                    ...(options.gudangs ?? []),
                                ]}
                                onChange={setGudangId}
                                buttonClassName="min-h-9 text-xs"
                            />
                        </label>
                        <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                            Status
                            <Dropdown
                                value={status}
                                label="Semua Status"
                                options={options.statuses ?? []}
                                onChange={setStatus}
                                searchable={false}
                                buttonClassName="min-h-9 text-xs"
                            />
                        </label>
                        <label className="grid gap-1 text-xs font-extrabold text-ink-soft dark:text-white/60">
                            Urut Berdasar
                            <div className="flex gap-1">
                                <Dropdown
                                    value={sort}
                                    options={[
                                        { value: "tanggal", label: "Tanggal" },
                                        {
                                            value: "kode_request",
                                            label: "No Transaksi",
                                        },
                                        { value: "status", label: "Status" },
                                    ]}
                                    onChange={setSort}
                                    searchable={false}
                                    buttonClassName="min-h-9 text-xs"
                                />
                                <button
                                    type="button"
                                    title="Urut naik"
                                    onClick={() => setDirection("asc")}
                                    className={`grid h-9 w-10 place-items-center rounded-lg border border-silver-deep/70 ${direction === "asc" ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-400/20 dark:text-emerald-200" : "bg-white/80 text-ink-soft dark:bg-white/8 dark:text-white/65"}`}
                                >
                                    <ArrowUpAZ size={16} />
                                </button>
                                <button
                                    type="button"
                                    title="Urut turun"
                                    onClick={() => setDirection("desc")}
                                    className={`grid h-9 w-10 place-items-center rounded-lg border border-silver-deep/70 ${direction === "desc" ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-400/20 dark:text-emerald-200" : "bg-white/80 text-ink-soft dark:bg-white/8 dark:text-white/65"}`}
                                >
                                    <ArrowDownAZ size={16} />
                                </button>
                            </div>
                        </label>
                        <div className="flex items-end justify-end gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                variant="outline"
                                title="Cari"
                            >
                                <Search size={16} />
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={reset}
                                title="Refresh"
                            >
                                <RefreshCw size={16} />
                            </Button>
                            {permissions.canCreate && (
                                <Button as="a" href={createUrl} size="sm">
                                    <Plus size={16} /> Tambah
                                </Button>
                            )}
                        </div>
                    </form>
                    <div className="mt-3 text-right text-xs font-black text-ink dark:text-white">
                        Total data yang ditemukan:{" "}
                        {rows.total ?? rows.data.length}
                    </div>
                </div>

                <div className="max-h-[64vh] overflow-auto">
                    <table className="w-full min-w-[1120px] divide-y divide-silver-deep/60 text-xs">
                        <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                            <tr>
                                {[
                                    "No Transaksi",
                                    "Tanggal",
                                    "Gudang",
                                    "Status",
                                    "Item",
                                    "Keterangan",
                                    "Diminta Oleh",
                                    "Persetujuan",
                                    "Diproses Oleh",
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
                            {rows.data.map((row) => (
                                <tr
                                    key={row.id}
                                    onDoubleClick={() => setDetail(row)}
                                    className="cursor-pointer hover:bg-silver-soft/70 dark:hover:bg-white/8"
                                >
                                    <td className="px-3 py-2 font-black text-ink dark:text-white">
                                        {row.kode_request}
                                    </td>
                                    <td className="px-3 py-2">{row.tanggal}</td>
                                    <td className="px-3 py-2 font-bold">
                                        {row.gudang}
                                    </td>
                                    <td className="px-3 py-2 font-black uppercase">
                                        {row.status_label}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.items_count} item
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.keterangan}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.requested_by_name}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.approved_by_name}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.processed_by_name}
                                    </td>
                                    <td className="px-3 py-2">
                                        <TableActions>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setDetail(row)}
                                            >
                                                <Eye size={15} /> Detail
                                            </Button>
                                            {isLocked(row) ? (
                                                <>
                                                    {permissions.canUnlock &&
                                                        row.can_unlock && (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    post(
                                                                        `${baseUrl}/${row.id}/unlock`,
                                                                    )
                                                                }
                                                            >
                                                                <Unlock
                                                                    size={15}
                                                                />{" "}
                                                                Buka Kunci
                                                            </Button>
                                                        )}
                                                </>
                                            ) : (
                                                <>
                                                    {permissions.canUpdate &&
                                                        row.can_edit && (
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
                                                                <Pencil
                                                                    size={15}
                                                                />{" "}
                                                                Ubah
                                                            </Button>
                                                        )}
                                                    {permissions.canApprove &&
                                                        row.can_approve && (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                onClick={() =>
                                                                    post(
                                                                        `${baseUrl}/${row.id}/approve`,
                                                                    )
                                                                }
                                                            >
                                                                <CheckCircle2
                                                                    size={15}
                                                                />{" "}
                                                                Setujui
                                                            </Button>
                                                        )}
                                                    {permissions.canApprove &&
                                                        row.can_approve && (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="danger"
                                                                onClick={() =>
                                                                    post(
                                                                        `${baseUrl}/${row.id}/reject`,
                                                                    )
                                                                }
                                                            >
                                                                <XCircle
                                                                    size={15}
                                                                />{" "}
                                                                Tolak
                                                            </Button>
                                                        )}
                                                    {permissions.canProcess &&
                                                        row.can_process && (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    process(row)
                                                                }
                                                            >
                                                                <ShoppingCart
                                                                    size={15}
                                                                />{" "}
                                                                Proses ke
                                                                Pembelian
                                                            </Button>
                                                        )}
                                                    {permissions.canDelete &&
                                                        row.can_delete && (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="danger"
                                                                onClick={() =>
                                                                    destroy(row)
                                                                }
                                                            >
                                                                <Trash2
                                                                    size={15}
                                                                />{" "}
                                                                Hapus
                                                            </Button>
                                                        )}
                                                    {permissions.canLock &&
                                                        row.can_lock && (
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    post(
                                                                        `${baseUrl}/${row.id}/lock`,
                                                                    )
                                                                }
                                                            >
                                                                <Lock
                                                                    size={15}
                                                                />{" "}
                                                                Kunci
                                                            </Button>
                                                        )}
                                                </>
                                            )}
                                        </TableActions>
                                    </td>
                                </tr>
                            ))}
                            {rows.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="px-4 py-10 text-center font-semibold text-ink-soft dark:text-white/55"
                                    >
                                        Belum ada permintaan pembelian.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination links={rows.links} />
            </section>

            <Modal
                open={Boolean(detail)}
                onClose={() => setDetail(null)}
                title={
                    detail
                        ? `Detail ${detail.kode_request}`
                        : "Detail Permintaan"
                }
                size="xl"
            >
                {detail && (
                    <div className="grid gap-4">
                        <div className="grid gap-3 rounded-lg border border-silver-deep/60 bg-silver-soft/60 p-4 dark:border-white/10 dark:bg-white/5 md:grid-cols-4">
                            <div>
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    Tanggal
                                </p>
                                <p className="mt-1 font-black">
                                    {detail.tanggal}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    Gudang
                                </p>
                                <p className="mt-1 font-black">
                                    {detail.gudang}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    Status
                                </p>
                                <p className="mt-1 font-black uppercase">
                                    {detail.status}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    Diminta Oleh
                                </p>
                                <p className="mt-1 font-black">
                                    {detail.requested_by_name}
                                </p>
                            </div>
                        </div>
                        <div className="overflow-auto rounded-lg border border-silver-deep/60 dark:border-white/10">
                            <table className="w-full min-w-[760px] divide-y divide-silver-deep/60 text-xs">
                                <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/70">
                                    <tr>
                                        {[
                                            "No",
                                            "Kode Item",
                                            "Material",
                                            "Jumlah",
                                            "Satuan",
                                            "Catatan",
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
                                    {detail.items.map((item, index) => (
                                        <tr key={item.id}>
                                            <td className="px-3 py-2 font-bold">
                                                {index + 1}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.kode_barang}
                                            </td>
                                            <td className="px-3 py-2 font-bold">
                                                {item.barang}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {decimal(item.qty)}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.satuan}
                                            </td>
                                            <td className="px-3 py-2">
                                                {item.catatan ?? "-"}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </Modal>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Permintaan Pembelian Material"}>
        {page}
    </AdminLayout>
);
