import { Head, router } from "@inertiajs/react";
import {
    Boxes,
    ClipboardCheck,
    PackageCheck,
    RotateCcw,
    Search,
    Warehouse,
    Wrench,
} from "lucide-react";
import { useMemo, useState } from "react";
import { Button, Input, TableActions } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import {
    ActionGrid,
    SectionCard,
    StatGrid,
    WarehousePage,
} from "./components/WarehouseShell";

export default function Gudang({
    title,
    baseUrl,
    rows,
    filters = {},
    options,
    stats = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const quickActions = useMemo(
        () => [
            {
                title: "Permintaan Material",
                description: "Tinjau permintaan material dari proyek dan unit.",
                href: "/admin/permintaan-barang",
                icon: ClipboardCheck,
            },
            {
                title: "Stok Material",
                description: "Pantau stok gudang dan material yang menipis.",
                href: "/admin/stok-material",
                icon: Boxes,
            },
            {
                title: "Pembelian Material",
                description:
                    "Lihat dan input transaksi pembelian barang masuk.",
                href: "/admin/pembelian-material",
                icon: Warehouse,
            },
            {
                title: "Pemakaian Material",
                description: "Lihat material yang sudah keluar ke lapangan.",
                href: "/admin/pemakaian-material",
                icon: Wrench,
            },
            {
                title: "Pengembalian Material",
                description: "Cek material sisa yang kembali ke gudang.",
                href: "/admin/pengembalian-material",
                icon: RotateCcw,
            },
            {
                title: "Riwayat Mutasi",
                description: "Buka histori masuk dan keluar material.",
                href: "/admin/logistik",
                icon: PackageCheck,
            },
        ],
        [],
    );

    return (
        <>
            <Head title={title} />
            <WarehousePage
                eyebrow="Peran Gudang"
                title="Dasbor Gudang"
                description="Pusat kendali operasional untuk permintaan material, mutasi stok, pembelian, pengembalian, dan saldo gudang."
                actions={
                    <Button
                        as="a"
                        href="/admin/permintaan-barang"
                        variant="outline"
                        size="sm"
                    >
                        <ClipboardCheck size={15} /> Buka Permintaan
                    </Button>
                }
            >
                <StatGrid
                    items={[
                        {
                            label: "Gudang Aktif",
                            value: stats.gudang_aktif ?? 0,
                            hint: `${stats.total_gudang ?? 0} gudang terdaftar`,
                            icon: Warehouse,
                        },
                        {
                            label: "Stok Kosong",
                            value: stats.stok_kosong ?? 0,
                            hint: "Material yang perlu segera ditinjau",
                            icon: Boxes,
                            tone: "text-red-600 dark:text-red-300",
                        },
                        {
                            label: "Permintaan Material",
                            value: stats.permintaan_material ?? 0,
                            hint: "Menunggu proses gudang / owner",
                            icon: ClipboardCheck,
                            tone: "text-amber-600 dark:text-amber-300",
                        },
                        {
                            label: "Pemakaian Hari Ini",
                            value: stats.pemakaian_hari_ini ?? 0,
                            hint: "Transaksi pemakaian yang dicatat hari ini",
                            icon: PackageCheck,
                            tone: "text-emerald-600 dark:text-emerald-300",
                        },
                    ]}
                />

                <section className="grid gap-4 xl:grid-cols-3">
                    <SectionCard
                        className="xl:col-span-2"
                        title="Alur kerja gudang"
                        description="Semua tindakan penting dikumpulkan dalam satu tempat supaya petugas tidak bolak-balik ke banyak menu."
                    >
                        <div className="px-5 pb-5">
                            <ActionGrid
                                items={quickActions}
                                onAction={(href) => router.visit(href)}
                            />
                        </div>
                    </SectionCard>

                    <SectionCard
                        title="Ringkasan operasional"
                        description="Status singkat pergerakan gudang hari ini."
                    >
                        <div className="grid gap-3 px-5 pb-5">
                            <MetricRow
                                label="Pengembalian Diajukan"
                                value={stats.pengembalian_diajukan ?? 0}
                            />
                            <MetricRow
                                label="Gudang Aktif"
                                value={stats.gudang_aktif ?? 0}
                            />
                        </div>
                    </SectionCard>
                </section>

                <SectionCard
                    title="Daftar gudang"
                    description="Gudang yang terdaftar dan bisa dipakai dalam transaksi logistik."
                    actions={
                        <form
                            className="flex w-full flex-col gap-3 md:w-auto md:flex-row md:items-end"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(
                                    baseUrl,
                                    { search },
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                        replace: true,
                                    },
                                );
                            }}
                        >
                            <Input
                                className="md:w-80"
                                label="Cari gudang"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                            <Button type="submit">
                                <Search size={17} /> Cari
                            </Button>
                        </form>
                    }
                >
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/50 text-xs">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft">
                                <tr>
                                    {[
                                        "Kode",
                                        "Gudang",
                                        "Cabang",
                                        "Perumahan",
                                        "PIC",
                                        "Status",
                                        "Aksi",
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
                            <tbody className="divide-y divide-silver-deep/40">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">
                                            {row.kode_gudang}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.nama_gudang}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.cabang}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.perumahan}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.penanggung_jawab ?? "-"}
                                        </td>
                                        <td className="px-5 py-4">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-black ${row.status === "aktif" ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-600"}`}
                                            >
                                                {row.status}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                <Button
                                                    as="a"
                                                    href="/admin/logistik"
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    Buka
                                                </Button>
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center font-semibold text-ink-soft"
                                            colSpan={7}
                                        >
                                            Belum ada gudang yang cocok dengan
                                            pencarian ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </SectionCard>
            </WarehousePage>
        </>
    );
}

Gudang.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Dasbor Gudang"}>
        {page}
    </AdminLayout>
);

function MetricRow({ label, value }) {
    return (
        <div className="flex items-center justify-between rounded-xl border border-silver-deep/60 bg-silver-soft px-4 py-3 dark:border-white/10 dark:bg-white/8">
            <span className="text-sm font-semibold text-ink-soft dark:text-white/55">
                {label}
            </span>
            <span className="text-lg font-black">{value}</span>
        </div>
    );
}
