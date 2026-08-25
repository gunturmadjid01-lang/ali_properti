import { Head, router } from "@inertiajs/react";
import { Save, Search } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { Button, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { SectionCard, WarehousePage } from "./components/WarehouseShell";

const today = () => new Date().toISOString().slice(0, 10);
const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

function buildDrafts(rows = []) {
    return rows.reduce((accumulator, row) => {
        accumulator[row.id] = String(row.harga_hpp ?? row.harga_terakhir ?? "");
        return accumulator;
    }, {});
}

export default function HargaMaterial({
    title,
    baseUrl,
    syncUrl,
    rows = [],
    filters = {},
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [tanggalBerlaku, setTanggalBerlaku] = useState(
        filters.tanggal_berlaku ?? today(),
    );
    const [drafts, setDrafts] = useState(() => buildDrafts(rows));
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        setDrafts(buildDrafts(rows));
    }, [rows]);

    const rowsWithDraft = useMemo(
        () =>
            rows.map((row) => {
                const nextPrice = Number(drafts[row.id] ?? row.harga_hpp ?? 0);
                const currentPrice = Number(row.harga_hpp ?? 0);

                return {
                    ...row,
                    draftHarga: drafts[row.id] ?? "",
                    nextPrice,
                    changed: nextPrice > 0 && nextPrice !== currentPrice,
                };
            }),
        [drafts, rows],
    );

    const changedCount = rowsWithDraft.filter((row) => row.changed).length;
    const totalValue = rowsWithDraft.reduce(
        (sum, row) => sum + row.nextPrice,
        0,
    );

    const syncAll = () => {
        setProcessing(true);
        router.post(
            syncUrl,
            {
                tanggal_berlaku: tanggalBerlaku,
                items: rowsWithDraft.map((row) => ({
                    barang_material_id: row.id,
                    harga_satuan: row.nextPrice,
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const refreshList = (event) => {
        event.preventDefault();
        router.get(
            baseUrl,
            { search, tanggal_berlaku: tanggalBerlaku },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={title} />
            <WarehousePage>
                <SectionCard
                    title="Harga Dasar Material"
                    description="Pilih tanggal, proses data, ubah harga, lalu simpan sekaligus."
                    actions={
                        <form
                            className="grid w-full gap-3 md:w-auto md:grid-cols-[180px_260px_auto]"
                            onSubmit={refreshList}
                        >
                            <Input
                                label="Tanggal Harga"
                                type="date"
                                value={tanggalBerlaku}
                                onChange={(event) =>
                                    setTanggalBerlaku(event.target.value)
                                }
                            />
                            <Input
                                label="Cari Material"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                            <div className="flex items-end">
                                <Button type="submit" variant="outline">
                                    <Search size={16} /> Proses
                                </Button>
                            </div>
                        </form>
                    }
                >
                    <div className="max-h-[58vh] overflow-auto">
                        <table className="min-w-[1040px] divide-y divide-silver-deep/60 text-xs">
                            <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left text-xs uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-charcoal/95">
                                <tr>
                                    {[
                                        "No",
                                        "Kode Item",
                                        "Material",
                                        "Jenis / Merk",
                                        "Satuan",
                                        "Harga Pada Tanggal",
                                        "Harga Baru",
                                        "Berlaku Sejak",
                                        "Status",
                                    ].map((column) => (
                                        <th
                                            className="px-5 py-4 font-extrabold"
                                            key={column}
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rowsWithDraft.map((row, index) => (
                                    <tr
                                        key={row.id}
                                        className={
                                            row.changed
                                                ? "bg-amber-50/70 dark:bg-amber-400/10"
                                                : ""
                                        }
                                    >
                                        <td className="px-5 py-4 font-bold">
                                            {index + 1}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.kode_barang}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="font-semibold text-ink dark:text-white">
                                                {row.nama_barang}
                                            </div>
                                            {row.keterangan_terakhir && (
                                                <div className="text-xs text-ink-soft dark:text-white/55">
                                                    {row.keterangan_terakhir}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div>
                                                {row.jenis_material ?? "-"}
                                            </div>
                                            <div className="text-xs text-ink-soft dark:text-white/55">
                                                {row.merk_material ?? "-"}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.satuan}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {money(row.harga_hpp)}
                                        </td>
                                        <td className="px-5 py-4">
                                            <Input
                                                type="number"
                                                min="0"
                                                step="1"
                                                value={row.draftHarga}
                                                disabled={
                                                    !permissions.canCreate
                                                }
                                                onChange={(event) =>
                                                    setDrafts((current) => ({
                                                        ...current,
                                                        [row.id]:
                                                            event.target.value,
                                                    }))
                                                }
                                            />
                                        </td>
                                        <td className="px-5 py-4">
                                            <div>
                                                {row.tanggal_terakhir ?? "-"}
                                            </div>
                                        </td>
                                        <td
                                            className={`px-5 py-4 font-bold ${row.changed ? "text-amber-700 dark:text-amber-200" : "text-emerald-700 dark:text-emerald-200"}`}
                                        >
                                            {row.changed ? "Berubah" : "Tetap"}
                                        </td>
                                    </tr>
                                ))}
                                {rowsWithDraft.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center font-bold text-ink-soft"
                                            colSpan={9}
                                        >
                                            Belum ada material.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-silver-deep/60 px-5 py-4">
                        <div className="flex flex-wrap gap-2 text-xs font-extrabold text-ink-soft dark:text-white/60">
                            <span className="rounded-lg border border-silver-deep/60 px-3 py-2 dark:border-white/10">
                                Material: {rows.length}
                            </span>
                            <span className="rounded-lg border border-silver-deep/60 px-3 py-2 dark:border-white/10">
                                Berubah: {changedCount}
                            </span>
                            <span className="rounded-lg border border-silver-deep/60 px-3 py-2 dark:border-white/10">
                                Total: {money(totalValue)}
                            </span>
                        </div>
                        {permissions.canCreate && (
                            <Button
                                type="button"
                                onClick={syncAll}
                                disabled={processing}
                            >
                                <Save size={17} /> Simpan Semua
                            </Button>
                        )}
                    </div>
                </SectionCard>
            </WarehousePage>
        </>
    );
}

HargaMaterial.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Harga Material"}>
        {page}
    </AdminLayout>
);
