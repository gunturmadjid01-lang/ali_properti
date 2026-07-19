import { Head, router, useForm } from "@inertiajs/react";
import {
    CheckCircle2,
    Lock,
    MinusCircle,
    PlusCircle,
    RotateCcw,
    Search,
    Unlock,
    XCircle,
} from "lucide-react";
import { useMemo, useState } from "react";
import {
    Button,
    Dropdown,
    Form,
    Input,
    Textarea,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const itemTemplate = () => ({ site_material_stock_id: "", qty: "" });

export default function Index({
    title,
    baseUrl,
    rows = { data: [], links: [] },
    filters = {},
    siteStocks = [],
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const canCreate = permissions.canCreate ?? false;
    const canReceive = permissions.canReceive ?? false;
    const canLock = permissions.canLock ?? false;
    const canUnlock = permissions.canUnlock ?? false;
    const form = useForm({
        tanggal: new Date().toISOString().slice(0, 10),
        gudang_id: "",
        perumahan_id: "",
        detail_rumah_id: "",
        tahapan_pembangunan_id: "",
        keterangan: "",
        items: [itemTemplate()],
    });

    const availableStocks = useMemo(
        () =>
            siteStocks.filter(
                (row) =>
                    (!form.data.gudang_id ||
                        row.gudang_id === String(form.data.gudang_id)) &&
                    (!form.data.perumahan_id ||
                        row.perumahan_id === String(form.data.perumahan_id)) &&
                    (!form.data.detail_rumah_id ||
                        row.detail_rumah_id ===
                            String(form.data.detail_rumah_id)) &&
                    (!form.data.tahapan_pembangunan_id ||
                        row.tahapan_pembangunan_id ===
                            String(form.data.tahapan_pembangunan_id)),
            ),
        [
            siteStocks,
            form.data.gudang_id,
            form.data.perumahan_id,
            form.data.detail_rumah_id,
            form.data.tahapan_pembangunan_id,
        ],
    );

    const setItem = (index, value, selected) => {
        const nextItems = form.data.items.map((item, itemIndex) =>
            itemIndex === index
                ? { ...item, site_material_stock_id: value }
                : item,
        );
        form.setData({
            ...form.data,
            gudang_id: selected?.gudang_id ?? form.data.gudang_id,
            perumahan_id: selected?.perumahan_id ?? form.data.perumahan_id,
            detail_rumah_id:
                selected?.detail_rumah_id ?? form.data.detail_rumah_id,
            tahapan_pembangunan_id:
                selected?.tahapan_pembangunan_id ??
                form.data.tahapan_pembangunan_id,
            items: nextItems,
        });
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(baseUrl, {
            preserveScroll: true,
            onSuccess: () =>
                form.reset(
                    "gudang_id",
                    "perumahan_id",
                    "detail_rumah_id",
                    "tahapan_pembangunan_id",
                    "keterangan",
                    "items",
                ),
        });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                {canCreate && (
                    <Form
                        collapsible
                        title="Ajukan Pengembalian Stok"
                        description="Pilih material sisa di lokasi. Barang baru menambah stok gudang setelah diperiksa dan diterima petugas gudang."
                        onSubmit={submit}
                        actions={
                            <Button type="submit" disabled={form.processing}>
                                <RotateCcw size={17} /> Ajukan Pengembalian
                            </Button>
                        }
                    >
                        {Object.keys(form.errors).length > 0 && (
                            <div className="rounded-lg bg-red-50 p-4 text-sm font-bold text-red-700">
                                {Object.values(form.errors).map((error) => (
                                    <p key={error}>{error}</p>
                                ))}
                            </div>
                        )}
                        <Input
                            label="Tanggal Pengembalian"
                            type="date"
                            value={form.data.tanggal}
                            onChange={(event) =>
                                form.setData("tanggal", event.target.value)
                            }
                        />
                        <div className="rounded-lg bg-silver-soft p-4 text-sm font-bold dark:bg-white/8">
                            Tujuan:{" "}
                            {siteStocks.find(
                                (row) => row.gudang_id === form.data.gudang_id,
                            )?.gudang ?? "-"}{" "}
                            · Lokasi:{" "}
                            {siteStocks.find(
                                (row) =>
                                    row.perumahan_id === form.data.perumahan_id,
                            )?.perumahan ?? "-"}{" "}
                            /{" "}
                            {siteStocks.find(
                                (row) =>
                                    row.detail_rumah_id ===
                                    form.data.detail_rumah_id,
                            )?.unit ?? "Kawasan"}
                        </div>
                        <div className="grid gap-3">
                            <div className="flex items-center justify-between">
                                <h3 className="font-extrabold">
                                    Material Dikembalikan
                                </h3>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        form.setData("items", [
                                            ...form.data.items,
                                            itemTemplate(),
                                        ])
                                    }
                                >
                                    <PlusCircle size={15} /> Tambah
                                </Button>
                            </div>
                            {form.data.items.map((item, index) => (
                                <div
                                    className="grid gap-3 rounded-lg border border-silver-deep/70 p-3 md:grid-cols-[1fr_180px_auto]"
                                    key={index}
                                >
                                    <div className="grid gap-2">
                                        <span className="text-sm font-extrabold">
                                            Stok Sisa Lokasi
                                        </span>
                                        <Dropdown
                                            label="Pilih Material"
                                            value={item.site_material_stock_id}
                                            options={availableStocks}
                                            onChange={(value, selected) =>
                                                setItem(index, value, selected)
                                            }
                                        />
                                    </div>
                                    <Input
                                        label="Qty Dikembalikan"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        value={item.qty}
                                        onChange={(event) =>
                                            form.setData(
                                                "items",
                                                form.data.items.map(
                                                    (row, rowIndex) =>
                                                        rowIndex === index
                                                            ? {
                                                                  ...row,
                                                                  qty: event
                                                                      .target
                                                                      .value,
                                                              }
                                                            : row,
                                                ),
                                            )
                                        }
                                    />
                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            disabled={
                                                form.data.items.length === 1
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    "items",
                                                    form.data.items.filter(
                                                        (_, rowIndex) =>
                                                            rowIndex !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <MinusCircle size={17} />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <Textarea
                            label="Alasan / Kondisi Material"
                            value={form.data.keterangan}
                            onChange={(event) =>
                                form.setData("keterangan", event.target.value)
                            }
                        />
                    </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="flex gap-3 p-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                baseUrl,
                                { search },
                                { preserveState: true, replace: true },
                            );
                        }}
                    >
                        <Input
                            className="flex-1"
                            label="Cari Pengembalian"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="flex items-end">
                            <Button>
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
                                        "Tanggal",
                                        "Asal / Gudang",
                                        "Material",
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
                                            {row.kode_pengembalian}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.tanggal}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.perumahan} / {row.unit}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                Ke {row.gudang}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.items_text}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.status}
                                        </td>
                                        <td className="min-w-44 px-5 py-4 text-xs">
                                            <span className="font-bold">
                                                Dibuat:
                                            </span>{" "}
                                            {row.created_by_name}
                                            <br />
                                            <span className="font-bold">
                                                Diubah:
                                            </span>{" "}
                                            {row.updated_by_name}
                                            <br />
                                            <span className="font-bold">
                                                Diterima:
                                            </span>{" "}
                                            {row.received_by_name}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.record_status}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                {canReceive &&
                                                    row.can_receive &&
                                                    row.status ===
                                                        "diajukan" && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/receive`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <CheckCircle2
                                                                size={14}
                                                            />{" "}
                                                            Terima Gudang
                                                        </Button>
                                                    )}
                                                {canReceive &&
                                                    row.can_receive &&
                                                    row.status ===
                                                        "diajukan" && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="text-red-600"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/reject`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <XCircle
                                                                size={14}
                                                            />{" "}
                                                            Tolak
                                                        </Button>
                                                    )}
                                                {canUnlock &&
                                                row.record_status ===
                                                    "locked" ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `${baseUrl}/${row.id}/unlock`,
                                                            )
                                                        }
                                                    >
                                                        <Unlock size={14} />
                                                    </Button>
                                                ) : (
                                                    canLock && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/lock`,
                                                                )
                                                            }
                                                        >
                                                            <Lock size={14} />
                                                        </Button>
                                                    )
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
                                            Belum ada pengembalian material dari
                                            lokasi.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Pengembalian Stok"}>
        {page}
    </AdminLayout>
);
