import { Head, Link, router } from "@inertiajs/react";
import { Check, Lock, Plus, Unlock, X } from "lucide-react";
import { FinanceChart } from "../../../../Components/Finance/FinanceChart";
import { Button, Dropdown, TableActions } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
const money = (v) => `Rp ${Number(v || 0).toLocaleString("id-ID")}`;
const statusLabel = {
    paid: "Lunas",
    unpaid: "Belum Bayar",
    pending_approval: "Menunggu Persetujuan",
    draft: "Draf",
    rejected: "Ditolak",
};
export default function Index({
    title,
    rows,
    summary,
    chart,
    filters,
    housing,
    permissions,
}) {
    const filter = (key, value) =>
        router.get(
            "/admin/pembayaran-air",
            { ...filters, [key]: value },
            { preserveState: true },
        );
    const act = (id, action) =>
        router.post(
            `/admin/pembayaran-air/${id}/${action}`,
            {},
            { preserveScroll: true },
        );
    const review = (id, action) =>
        router.post(
            `/admin/air/payment/${id}/${action}`,
            action === "reject" ? { note: "Perlu diperbaiki" } : {},
            { preserveScroll: true },
        );
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <header className="flex flex-wrap items-end justify-between gap-4 border-b pb-5">
                    <div>
                        <p className="text-xs font-black uppercase tracking-widest text-ink-soft">
                            Keuangan / Air Developer
                        </p>
                        <h1 className="mt-2 text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-ink-soft">
                            Pantau pembayaran air sumur bor berdasarkan
                            perumahan, pemilik unit, periode, dan status.
                        </p>
                    </div>
                    {permissions.create && (
                        <Button as={Link} href="/admin/pembayaran-air/create">
                            <Plus size={17} /> Input Pembayaran
                        </Button>
                    )}
                </header>
                <section className="grid gap-3 md:grid-cols-5">
                    {[
                        ["Total Data", summary.total],
                        ["Lunas", summary.paid],
                        ["Menunggu", summary.pending],
                        ["Belum Bayar", summary.unpaid],
                        ["Dana Diterima", money(summary.amount)],
                    ].map(([l, v]) => (
                        <div
                            className="rounded-xl border bg-white/85 p-4 shadow-soft dark:bg-white/7"
                            key={l}
                        >
                            <p className="text-xs font-black uppercase text-ink-soft">
                                {l}
                            </p>
                            <p className="mt-2 text-2xl font-black">{v}</p>
                        </div>
                    ))}
                </section>
                <section className="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                    <FinanceChart
                        title="Distribusi Status Pembayaran"
                        subtitle="Jumlah transaksi pada filter aktif"
                        items={chart}
                        primaryLabel="Transaksi"
                        valueFormatter={(v) =>
                            Number(v).toLocaleString("id-ID")
                        }
                    />
                    <div className="rounded-xl border bg-white/85 p-5 shadow-soft dark:bg-white/7">
                        <h2 className="font-black">Filter Data</h2>
                        <div className="mt-4 grid gap-4">
                            <label className="grid gap-2 text-sm font-bold">
                                <span>Perumahan</span>
                                <Dropdown
                                    value={filters.perumahan_id}
                                    options={[
                                        { value: "", label: "Semua Perumahan" },
                                        ...housing,
                                    ]}
                                    onChange={(v) => filter("perumahan_id", v)}
                                />
                            </label>
                            <label className="grid gap-2 text-sm font-bold">
                                <span>Status Pembayaran</span>
                                <Dropdown
                                    value={filters.status}
                                    options={[
                                        { value: "", label: "Semua Status" },
                                        { value: "paid", label: "Lunas" },
                                        {
                                            value: "unpaid",
                                            label: "Belum Bayar",
                                        },
                                        {
                                            value: "pending_approval",
                                            label: "Menunggu Persetujuan",
                                        },
                                        { value: "draft", label: "Draf" },
                                        { value: "rejected", label: "Ditolak" },
                                    ]}
                                    onChange={(v) => filter("status", v)}
                                />
                            </label>
                        </div>
                    </div>
                </section>
                <section className="overflow-hidden rounded-xl border bg-white/85 shadow-soft dark:bg-white/7">
                    <div className="overflow-x-auto">
                        <table className="min-w-[1150px] w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase text-ink-soft">
                                <tr>
                                    {[
                                        "Nomor / Tanggal",
                                        "Pemilik / Unit",
                                        "Perumahan",
                                        "Periode",
                                        "Nominal",
                                        "Metode",
                                        "Status",
                                        "Persetujuan",
                                        "Aksi",
                                    ].map((x) => (
                                        <th className="px-4 py-4" key={x}>
                                            {x}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="px-4 py-4">
                                            <b>{r.number}</b>
                                            <p className="text-xs text-ink-soft">
                                                {r.date}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4">
                                            <b>{r.owner}</b>
                                            <p className="text-xs text-ink-soft">
                                                {r.unit}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4">
                                            {r.housing}
                                        </td>
                                        <td className="px-4 py-4">
                                            {r.period}
                                        </td>
                                        <td className="px-4 py-4 font-black">
                                            {money(r.amount)}
                                        </td>
                                        <td className="px-4 py-4">
                                            {r.method}
                                            <p className="text-xs text-ink-soft">
                                                {r.reference || "-"}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 font-bold">
                                            {statusLabel[r.status] || r.status}
                                        </td>
                                        <td className="px-4 py-4">
                                            {r.approval}
                                        </td>
                                        <td className="px-4 py-4">
                                            <TableActions>
                                                {r.can_lock && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            act(r.id, "lock")
                                                        }
                                                    >
                                                        <Lock size={14} />{" "}
                                                        Finalisasi
                                                    </Button>
                                                )}
                                                {r.can_unlock && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            act(r.id, "unlock")
                                                        }
                                                    >
                                                        <Unlock size={14} />{" "}
                                                        Buka
                                                    </Button>
                                                )}
                                                {r.can_review && (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                review(
                                                                    r.id,
                                                                    "approve",
                                                                )
                                                            }
                                                        >
                                                            <Check size={14} />{" "}
                                                            Setujui
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                review(
                                                                    r.id,
                                                                    "reject",
                                                                )
                                                            }
                                                        >
                                                            <X size={14} />{" "}
                                                            Tolak
                                                        </Button>
                                                    </>
                                                )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {!rows.data.length && (
                                    <tr>
                                        <td
                                            className="p-10 text-center font-bold text-ink-soft"
                                            colSpan="9"
                                        >
                                            Belum ada pembayaran air.
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
    <AdminLayout title={page.props.title}>{page}</AdminLayout>
);
