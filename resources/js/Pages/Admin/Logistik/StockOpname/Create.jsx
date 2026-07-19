import { Head, router, useForm } from "@inertiajs/react";
import { RefreshCw, Save, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";
import {
    Button,
    Dropdown,
    Input,
    TableActions,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const decimal = (value) =>
    Number(value ?? 0).toLocaleString("id-ID", { maximumFractionDigits: 6 });

export default function Create({
    title,
    baseUrl,
    storeUrl,
    indexUrl,
    nextCode = "",
    selectedGudang = null,
    rows = [],
    filters = {},
    options = {},
    assignmentWarning = null,
    canCreate = false,
}) {
    const [search, setSearch] = useState("");
    const form = useForm({
        kode_opname: nextCode,
        gudang_id: filters.gudang_id ?? "",
        tanggal: new Date().toISOString().slice(0, 10),
        keterangan: "",
        items: rows.map((row) => ({
            barang_material_id: row.barang_material_id,
            kode_barang: row.kode_barang,
            nama_barang: row.nama_barang,
            jenis_merk: row.jenis_merk,
            satuan: row.satuan,
            stok_sistem: row.stok_sistem,
            fisik: row.fisik,
            masuk: row.masuk,
            keluar: row.keluar,
            selisih: row.selisih,
            unit_options: row.unit_options ?? [],
            unit_counts: (row.unit_options ?? []).map((unit, index) => ({
                unit_id: unit.value,
                qty: index === 0 ? row.stok_sistem : 0,
            })),
        })),
    });

    const filteredItems = useMemo(() => {
        const keyword = search.trim().toLowerCase();
        if (!keyword) return form.data.items;

        return form.data.items.filter((item) => {
            const haystack = [
                item.kode_barang,
                item.nama_barang,
                item.jenis_merk,
                item.satuan,
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();
            return haystack.includes(keyword);
        });
    }, [rows, search, form.data.items]);

    const totalSelisih = form.data.items.reduce(
        (sum, item) =>
            sum + (Number(item.fisik || 0) - Number(item.stok_sistem || 0)),
        0,
    );

    const changeGudang = (value) => {
        router.visit(value ? `${baseUrl}?gudang_id=${value}` : baseUrl, {
            preserveScroll: true,
            preserveState: false,
            replace: true,
        });
    };

    const setItem = (index, key, value) => {
        form.setData(
            "items",
            form.data.items.map((item, itemIndex) => {
                if (itemIndex !== index) return item;
                const next = { ...item, [key]: value };
                if (key === "fisik") {
                    const selisih =
                        Number(value || 0) - Number(item.stok_sistem || 0);
                    next.selisih = selisih;
                    next.masuk = selisih > 0 ? selisih : 0;
                    next.keluar = selisih < 0 ? Math.abs(selisih) : 0;
                }
                return next;
            }),
        );
    };

    const setUnitCount = (index, unitIndex, value) => {
        form.setData(
            "items",
            form.data.items.map((item, itemIndex) => {
                if (itemIndex !== index) return item;
                const unitCounts = item.unit_counts.map((count, countIndex) =>
                    countIndex === unitIndex ? { ...count, qty: value } : count,
                );
                const fisik = unitCounts.reduce((sum, count) => {
                    const unit = item.unit_options.find(
                        (option) =>
                            String(option.value) === String(count.unit_id),
                    );
                    return (
                        sum +
                        Number(count.qty || 0) /
                            Number(unit?.factor_to_base || 1)
                    );
                }, 0);
                const selisih = fisik - Number(item.stok_sistem || 0);
                return {
                    ...item,
                    unit_counts: unitCounts,
                    fisik,
                    selisih,
                    masuk: selisih > 0 ? selisih : 0,
                    keluar: selisih < 0 ? Math.abs(selisih) : 0,
                };
            }),
        );
    };

    const removeItem = (index) => {
        form.setData(
            "items",
            form.data.items.filter((_, itemIndex) => itemIndex !== index),
        );
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(storeUrl);
    };

    return (
        <>
            <Head title={title} />
            <form
                className="overflow-hidden rounded-2xl border border-white/70 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/6"
                onSubmit={submit}
            >
                <div className="grid gap-3 border-b border-silver-deep/50 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4 xl:grid-cols-[240px_180px_260px_1fr_auto]">
                    <Input
                        label="No Opname"
                        value={form.data.kode_opname}
                        readOnly
                        inputClassName="h-9 min-h-9 cursor-not-allowed bg-silver-soft text-xs dark:bg-white/5"
                    />
                    <Input
                        label="Tanggal"
                        type="date"
                        value={form.data.tanggal}
                        onChange={(event) =>
                            form.setData("tanggal", event.target.value)
                        }
                        inputClassName="h-9 min-h-9 text-xs"
                    />
                    <label className="grid gap-2 text-sm font-extrabold text-ink/75 dark:text-white/78">
                        Gudang
                        <Dropdown
                            value={form.data.gudang_id}
                            label="Pilih Gudang"
                            options={[
                                { value: "", label: "Pilih Gudang" },
                                ...(options.gudangs ?? []),
                            ]}
                            onChange={(value) => changeGudang(value)}
                            buttonClassName="min-h-9 text-xs"
                        />
                        {form.errors.gudang_id && (
                            <span className="text-xs font-bold text-red-600">
                                {form.errors.gudang_id}
                            </span>
                        )}
                    </label>
                    <Input
                        label="Keterangan"
                        value={form.data.keterangan}
                        onChange={(event) =>
                            form.setData("keterangan", event.target.value)
                        }
                        inputClassName="h-9 min-h-9 text-xs"
                    />
                    <div className="flex items-end justify-end gap-2">
                        <Button
                            type="submit"
                            size="sm"
                            disabled={
                                form.processing ||
                                !form.data.gudang_id ||
                                form.data.items.length === 0 ||
                                !canCreate
                            }
                        >
                            <Save size={16} /> Simpan
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit(indexUrl)}
                        >
                            <RefreshCw size={16} /> Batal
                        </Button>
                    </div>
                </div>

                <div className="grid gap-3 border-b border-silver-deep/50 bg-silver-soft/35 px-4 py-3 dark:border-white/10 dark:bg-white/3 lg:grid-cols-3">
                    <SummaryCard
                        title="Gudang Dipilih"
                        value={selectedGudang?.nama_gudang ?? "Belum dipilih"}
                        hint="Pilih gudang dulu untuk memuat stok terakhir."
                    />
                    <SummaryCard
                        title="Material Aktif"
                        value={rows.length}
                        hint="Semua material aktif pada gudang ini tampil di tabel."
                    />
                    <SummaryCard
                        title="Total Selisih"
                        value={decimal(totalSelisih)}
                        hint="Selisih dihitung dari fisik dikurangi stok sistem."
                    />
                </div>

                {assignmentWarning && (
                    <div className="border-b border-amber-300/50 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-100">
                        {assignmentWarning}
                    </div>
                )}

                {!form.data.gudang_id ? (
                    <div className="px-4 py-16 text-center text-sm font-semibold text-ink-soft dark:text-white/60">
                        Pilih gudang untuk memuat daftar stok material terbaru.
                    </div>
                ) : (
                    <>
                        <div className="border-b border-silver-deep/50 bg-silver-soft/25 px-4 py-3 dark:border-white/10 dark:bg-white/3">
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                label="Cari Material"
                                inputClassName="h-9 min-h-9 text-xs"
                            />
                        </div>
                        <div className="h-[58vh] overflow-auto">
                            <table className="w-full min-w-[1100px] divide-y divide-silver-deep/60 text-xs">
                                <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-[#232930] dark:text-white">
                                    <tr>
                                        {[
                                            "No",
                                            "Kode Item",
                                            "Material",
                                            "Jenis / Merk",
                                            "Satuan Level 1",
                                            "Buku Level 1",
                                            "Hitung Fisik per Satuan",
                                            "Fisik Level 1",
                                            "Masuk",
                                            "Keluar",
                                            "Selisih",
                                            "Aksi",
                                        ].map((column) => (
                                            <th
                                                key={column}
                                                className="px-3 py-3 font-extrabold"
                                            >
                                                {column}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                    {filteredItems.map((item, index) => {
                                        const originalIndex =
                                            form.data.items.findIndex(
                                                (row) =>
                                                    row.barang_material_id ===
                                                    item.barang_material_id,
                                            );
                                        return (
                                            <tr key={item.barang_material_id}>
                                                <td className="px-3 py-2 font-bold">
                                                    {originalIndex + 1}
                                                </td>
                                                <td className="px-3 py-2 font-black text-ink dark:text-white">
                                                    {item.kode_barang ?? "-"}
                                                </td>
                                                <td className="px-3 py-2 font-bold">
                                                    {item.nama_barang ?? "-"}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.jenis_merk ?? "-"}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {item.satuan ?? "-"}
                                                </td>
                                                <td className="px-3 py-2 text-right font-black">
                                                    {decimal(item.stok_sistem)}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <div className="flex min-w-[320px] flex-wrap gap-2">
                                                        {item.unit_counts.map(
                                                            (
                                                                count,
                                                                unitIndex,
                                                            ) => {
                                                                const unit =
                                                                    item.unit_options.find(
                                                                        (
                                                                            option,
                                                                        ) =>
                                                                            String(
                                                                                option.value,
                                                                            ) ===
                                                                            String(
                                                                                count.unit_id,
                                                                            ),
                                                                    );
                                                                return (
                                                                    <label
                                                                        className="grid gap-1"
                                                                        key={
                                                                            count.unit_id
                                                                        }
                                                                    >
                                                                        <span className="text-[10px] font-black uppercase">
                                                                            {
                                                                                unit?.symbol
                                                                            }
                                                                        </span>
                                                                        <input
                                                                            type="number"
                                                                            min="0"
                                                                            step="0.000001"
                                                                            value={
                                                                                count.qty
                                                                            }
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setUnitCount(
                                                                                    originalIndex,
                                                                                    unitIndex,
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            className="h-9 w-24 rounded-md border border-silver-deep/70 bg-white/85 px-2 text-right font-bold dark:border-white/10 dark:bg-white/8"
                                                                        />
                                                                    </label>
                                                                );
                                                            },
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2 text-right font-black">
                                                    {decimal(item.fisik)}
                                                </td>
                                                <td className="px-3 py-2 text-right font-black text-emerald-600 dark:text-emerald-300">
                                                    {decimal(item.masuk)}
                                                </td>
                                                <td className="px-3 py-2 text-right font-black text-rose-600 dark:text-rose-300">
                                                    {decimal(item.keluar)}
                                                </td>
                                                <td
                                                    className={`px-3 py-2 text-right font-black ${Number(item.selisih || 0) >= 0 ? "text-emerald-600 dark:text-emerald-300" : "text-rose-600 dark:text-rose-300"}`}
                                                >
                                                    {decimal(item.selisih)}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <TableActions>
                                                        <button
                                                            type="button"
                                                            title="Hapus item"
                                                            onClick={() =>
                                                                removeItem(
                                                                    originalIndex,
                                                                )
                                                            }
                                                            className="grid h-8 w-8 place-items-center rounded-md text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-400/10"
                                                        >
                                                            <Trash2 size={16} />
                                                        </button>
                                                    </TableActions>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {filteredItems.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={12}
                                                className="px-4 py-10 text-center font-semibold text-ink-soft dark:text-white/55"
                                            >
                                                Tidak ada material yang cocok
                                                dengan pencarian ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                <div className="grid gap-3 border-t border-silver-deep/60 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/4 lg:grid-cols-[1fr_320px]">
                    <div className="flex items-end gap-2">
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.gudang_id ||
                                form.data.items.length === 0 ||
                                !canCreate
                            }
                        >
                            <Save size={16} /> Simpan
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.visit(indexUrl)}
                        >
                            <RefreshCw size={16} /> Batal
                        </Button>
                    </div>
                    <div className="grid gap-2">
                        <FooterRow
                            label="Total Item"
                            value={form.data.items.length}
                        />
                        <FooterRow
                            label="Total Selisih"
                            value={totalSelisih}
                            strong
                        />
                    </div>
                </div>
            </form>
        </>
    );
}

function SummaryCard({ title, value, hint }) {
    return (
        <div className="rounded-xl border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5">
            <p className="text-xs font-extrabold uppercase tracking-[0.2em] text-ink-soft dark:text-white/55">
                {title}
            </p>
            <p className="mt-2 text-xl font-black text-ink dark:text-white">
                {value}
            </p>
            <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/55">
                {hint}
            </p>
        </div>
    );
}

function FooterRow({ label, value, strong = false }) {
    return (
        <div
            className={`grid grid-cols-[1fr_150px] items-center gap-3 text-xs ${strong ? "font-black" : "font-bold"}`}
        >
            <span className="text-right text-ink-soft dark:text-white/60">
                {label}
            </span>
            <span className="rounded-md border border-silver-deep/60 bg-white/80 px-3 py-2 text-right dark:border-white/10 dark:bg-white/8">
                {Number.isFinite(Number(value)) ? decimal(value) : value}
            </span>
        </div>
    );
}

Create.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Stock Opname Material"}>
        {page}
    </AdminLayout>
);
