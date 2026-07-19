import { Head, router } from "@inertiajs/react";
import { ArrowDownToLine, CheckCircle2, Trash2 } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { Button, Dropdown, Input, TableActions } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import {
    SectionCard,
    StatGrid,
    WarehousePage,
} from "./components/WarehouseShell";

const today = () => new Date().toISOString().slice(0, 10);
const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

function buildDrafts(rows = []) {
    return rows.reduce((accumulator, row) => {
        accumulator[row.id] = {
            qty: row.qty ? String(row.qty) : "",
            material_unit_id: String(row.material_unit_id ?? ""),
            harga_satuan: row.harga_satuan ? String(row.harga_satuan) : "",
            catatan: row.catatan ?? "",
        };
        return accumulator;
    }, {});
}

export default function SaldoAwalMaterial({
    title,
    baseUrl,
    rows = [],
    filters = {},
    options = {},
    selectedGudang = null,
    assignmentWarning = null,
    permissions = {},
}) {
    const [gudangFilter, setGudangFilter] = useState(
        filters.gudang_id ?? selectedGudang?.id ?? "",
    );
    const [tanggalSaldo, setTanggalSaldo] = useState(
        rows[0]?.tanggal_saldo ?? today(),
    );
    const [drafts, setDrafts] = useState(() => buildDrafts(rows));

    useEffect(() => {
        setDrafts(buildDrafts(rows));
        if (
            rows.length > 0 &&
            !rows.some((row) => row.tanggal_saldo === tanggalSaldo)
        ) {
            setTanggalSaldo(
                rows.find((row) => row.tanggal_saldo)?.tanggal_saldo ?? today(),
            );
        }
    }, [rows]);

    const gudangOptions = useMemo(
        () => [
            { value: "", label: "Pilih Gudang" },
            ...(options.gudangs ?? []),
        ],
        [options.gudangs],
    );
    const rowsWithDraft = useMemo(
        () =>
            rows.map((row) => {
                const draft = drafts[row.id] ?? {};
                const qty = Number(draft.qty ?? row.qty ?? 0);
                const materialUnitId = String(
                    draft.material_unit_id ?? row.material_unit_id ?? "",
                );
                const unitOption =
                    (row.unit_options ?? []).find(
                        (unit) => String(unit.value) === materialUnitId,
                    ) ?? row.unit_options?.[0];
                const hargaSatuan = Number(
                    draft.harga_satuan ??
                        unitOption?.standard_price ??
                        row.harga_satuan ??
                        0,
                );
                const qtyBase = qty / Number(unitOption?.factor_to_base || 1);
                return {
                    ...row,
                    draftQty: draft.qty ?? "",
                    draftUnitId: materialUnitId,
                    draftHarga: draft.harga_satuan ?? "",
                    draftCatatan: draft.catatan ?? "",
                    computedQty: qty,
                    computedQtyBase: qtyBase,
                    computedHarga: hargaSatuan,
                    computedTotal: qty * hargaSatuan,
                };
            }),
        [drafts, rows],
    );

    const stats = useMemo(
        () => [
            {
                label: "Gudang Dipilih",
                value: selectedGudang?.nama_gudang ?? "Belum dipilih",
                hint: "Semua item material ditarik mengikuti gudang ini.",
            },
            {
                label: "Material Aktif",
                value: rows.length,
                hint: "Daftar material aktif yang bisa diisi saldo awalnya.",
            },
            {
                label: "Total Nilai Tersimpan",
                value: money(
                    rowsWithDraft.reduce(
                        (sum, row) =>
                            sum + (row.balance_id ? row.computedTotal : 0),
                        0,
                    ),
                ),
                hint: "Akumulasi saldo awal yang sudah tersimpan.",
            },
        ],
        [rows.length, rowsWithDraft, selectedGudang?.nama_gudang],
    );

    const syncAll = () => {
        if (!gudangFilter) {
            window.alert("Pilih gudang dulu.");
            return;
        }

        router.post(
            `${baseUrl}/sync`,
            {
                gudang_id: gudangFilter,
                tanggal_saldo: tanggalSaldo,
                items: rowsWithDraft.map((row) => ({
                    barang_material_id: row.id,
                    material_unit_id: row.draftUnitId,
                    qty: row.computedQty,
                    harga_satuan: row.computedHarga,
                    catatan: row.draftCatatan ?? "",
                })),
            },
            { preserveScroll: true },
        );
    };

    const deleteRow = (row) => {
        if (!row.balance_id) return;
        if (
            !window.confirm(
                `Kosongkan saldo awal ${row.kode_barang}? Stok awal item ini akan menjadi 0.`,
            )
        )
            return;
        router.delete(`${baseUrl}/${row.balance_id}`, { preserveScroll: true });
    };

    const changeGudang = (value) => {
        setGudangFilter(value);
        router.get(
            baseUrl,
            { gudang_id: value },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={title} />
            <WarehousePage
                eyebrow="Saldo Awal"
                title="Saldo Awal Material"
                description="Input boleh memakai satuan konversi. Stok tetap disimpan otomatis dalam satuan Level 1."
            >
                <StatGrid
                    items={stats.map((item) => ({
                        ...item,
                        icon: CheckCircle2,
                    }))}
                />

                {assignmentWarning && (
                    <section className="rounded-2xl border border-amber-300/70 bg-amber-50 p-5 text-sm font-semibold text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                        {assignmentWarning}
                    </section>
                )}

                <SectionCard
                    title="Daftar Material"
                    description="Setiap baris adalah item material aktif. Isi qty pada kolom jumlah, lalu gunakan tombol Simpan Semua."
                    actions={
                        <div className="grid w-full gap-3 sm:w-auto sm:grid-cols-[240px_220px]">
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold text-ink/75 dark:text-white/78">
                                    Gudang
                                </span>
                                <Dropdown
                                    value={gudangFilter}
                                    label="Pilih Gudang"
                                    options={gudangOptions}
                                    onChange={changeGudang}
                                    disabled={gudangOptions.length <= 1}
                                />
                            </div>
                            <Input
                                label="Tanggal Saldo Awal"
                                type="date"
                                value={tanggalSaldo}
                                onChange={(event) =>
                                    setTanggalSaldo(event.target.value)
                                }
                            />
                        </div>
                    }
                >
                    {!gudangFilter && (
                        <div className="px-5 py-4 text-sm font-semibold text-ink-soft dark:text-white/60">
                            Pilih gudang dulu supaya daftar saldo awal bisa
                            dimuat.
                        </div>
                    )}
                    <div className="max-h-[58vh] overflow-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs">
                            <thead className="sticky top-0 z-10 bg-silver-soft/95 text-left text-xs uppercase tracking-[0.12em] text-ink-soft backdrop-blur dark:bg-charcoal/95">
                                <tr>
                                    {[
                                        "No",
                                        "Kode Item",
                                        "Material",
                                        "Gudang",
                                        "Jumlah",
                                        "Satuan Input",
                                        "Setara Level 1",
                                        "Harga",
                                        "Total",
                                        "Aksi",
                                    ].map((column) => (
                                        <th
                                            key={column}
                                            className="px-5 py-4 font-extrabold"
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rowsWithDraft.map((row, index) => (
                                    <tr key={row.id} className="align-top">
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
                                            <div className="text-xs text-ink-soft dark:text-white/55">
                                                Jenis:{" "}
                                                {row.jenis_material ?? "-"}
                                            </div>
                                            <div className="text-xs text-ink-soft dark:text-white/55">
                                                Merk: {row.merk_material ?? "-"}
                                            </div>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.gudang}
                                        </td>
                                        <td className="px-5 py-4">
                                            <Input
                                                type="number"
                                                step="0.000001"
                                                disabled={!row.can_edit}
                                                min="0"
                                                value={row.draftQty}
                                                onChange={(event) =>
                                                    setDrafts((current) => ({
                                                        ...current,
                                                        [row.id]: {
                                                            ...(current[
                                                                row.id
                                                            ] ?? {}),
                                                            qty: event.target
                                                                .value,
                                                        },
                                                    }))
                                                }
                                            />
                                        </td>
                                        <td className="min-w-40 px-5 py-4">
                                            <Dropdown
                                                disabled={!row.can_edit}
                                                value={row.draftUnitId}
                                                options={row.unit_options ?? []}
                                                onChange={(value) => {
                                                    const unit = (
                                                        row.unit_options ?? []
                                                    ).find(
                                                        (option) =>
                                                            String(
                                                                option.value,
                                                            ) === String(value),
                                                    );
                                                    setDrafts((current) => ({
                                                        ...current,
                                                        [row.id]: {
                                                            ...(current[
                                                                row.id
                                                            ] ?? {}),
                                                            material_unit_id:
                                                                value,
                                                            harga_satuan:
                                                                String(
                                                                    unit?.standard_price ??
                                                                        row.harga_satuan ??
                                                                        0,
                                                                ),
                                                        },
                                                    }));
                                                }}
                                            />
                                        </td>
                                        <td className="px-5 py-4 font-black">
                                            {row.computedQtyBase.toLocaleString(
                                                "id-ID",
                                                { maximumFractionDigits: 6 },
                                            )}{" "}
                                            {row.satuan}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {money(row.computedHarga)}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {money(row.computedTotal)}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                {row.can_delete &&
                                                    permissions.canDelete && (
                                                        <button
                                                            type="button"
                                                            title="Hapus"
                                                            aria-label="Hapus"
                                                            className="inline-grid h-8 w-8 place-items-center rounded-lg border border-silver-deep/70 bg-white/70 text-red-600 transition hover:bg-red-50 hover:text-red-700 dark:border-white/10 dark:bg-white/8 dark:text-red-300 dark:hover:bg-red-400/10"
                                                            onClick={() =>
                                                                deleteRow(row)
                                                            }
                                                        >
                                                            <Trash2 size={15} />
                                                        </button>
                                                    )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-silver-deep/60 px-5 py-4">
                        <div className="text-sm font-semibold text-ink-soft dark:text-white/60">
                            {rowsWithDraft.length} material ditampilkan. Total
                            input:{" "}
                            {money(
                                rowsWithDraft.reduce(
                                    (sum, row) => sum + row.computedTotal,
                                    0,
                                ),
                            )}
                            .
                        </div>
                        {(permissions.canCreate || permissions.canUpdate) && (
                            <Button
                                type="button"
                                onClick={syncAll}
                                disabled={
                                    !gudangFilter || rowsWithDraft.length === 0
                                }
                            >
                                <ArrowDownToLine size={16} /> Simpan Semua
                            </Button>
                        )}
                    </div>
                </SectionCard>
            </WarehousePage>
        </>
    );
}

SaldoAwalMaterial.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Saldo Awal Material"}>
        {page}
    </AdminLayout>
);
