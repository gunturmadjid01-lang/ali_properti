import { Head, Link, router } from "@inertiajs/react";
import { Check, Edit3, Lock, Plus, Search, Unlock, X } from "lucide-react";
import { Button, Dropdown, TableActions } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
const money = (v) => `Rp ${Number(v || 0).toLocaleString("id-ID")}`;
export default function Index({ title, rows, filters, housing, permissions }) {
    const act = (id, action) =>
        router.post(
            `/admin/periode-tagihan-air/${id}/${action}`,
            {},
            { preserveScroll: true },
        );
    const review = (id, action) =>
        router.post(
            `/admin/air/period/${id}/${action}`,
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
                            Pengelolaan Air
                        </p>
                        <h1 className="mt-2 text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-ink-soft">
                            Kelola tarif dan masa berlaku tagihan untuk setiap
                            perumahan.
                        </p>
                    </div>
                    {permissions.create && (
                        <Button
                            as={Link}
                            href="/admin/periode-tagihan-air/create"
                        >
                            <Plus size={17} /> Tambah Periode
                        </Button>
                    )}
                </header>
                <section className="rounded-xl border bg-white/80 p-5 shadow-soft dark:bg-white/5">
                    <div className="flex max-w-xl items-end gap-3">
                        <label className="grid flex-1 gap-2 text-sm font-bold">
                            <span>Perumahan</span>
                            <Dropdown
                                value={filters.perumahan_id}
                                options={[
                                    { value: "", label: "Semua Perumahan" },
                                    ...housing,
                                ]}
                                onChange={(v) =>
                                    router.get("/admin/periode-tagihan-air", {
                                        perumahan_id: v,
                                    })
                                }
                            />
                        </label>
                        <Button variant="outline">
                            <Search size={16} /> Filter
                        </Button>
                    </div>
                </section>
                <section className="overflow-hidden rounded-xl border bg-white/80 shadow-soft dark:bg-white/5">
                    <div className="overflow-x-auto">
                        <table className="min-w-[1050px] w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase text-ink-soft">
                                <tr>
                                    {[
                                        "Kode / Periode",
                                        "Perumahan",
                                        "Rentang / Jatuh Tempo",
                                        "Tarif",
                                        "Pembayaran",
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
                                            <b>{r.name}</b>
                                            <p className="text-xs text-ink-soft">
                                                {r.code}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 font-semibold">
                                            {r.housing}
                                        </td>
                                        <td className="px-4 py-4">
                                            {r.start} – {r.end}
                                            <p className="text-xs text-ink-soft">
                                                Jatuh tempo {r.due}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 font-black">
                                            {money(r.amount)}
                                        </td>
                                        <td className="px-4 py-4">
                                            {r.payments} transaksi
                                            <p className="text-xs text-emerald-700">
                                                {money(r.paid_total)}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4">
                                            <b>
                                                {r.active
                                                    ? "Aktif"
                                                    : "Tidak Aktif"}
                                            </b>
                                            <p className="text-xs text-ink-soft">
                                                {r.record_status === "locked"
                                                    ? "Terkunci"
                                                    : "Draf"}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4">
                                            {r.approval}
                                        </td>
                                        <td className="px-4 py-4">
                                            <TableActions>
                                                {r.can_edit && (
                                                    <Button
                                                        as={Link}
                                                        size="sm"
                                                        variant="outline"
                                                        href={`/admin/periode-tagihan-air/${r.id}/edit`}
                                                    >
                                                        <Edit3 size={14} /> Ubah
                                                    </Button>
                                                )}
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
                                            colSpan="8"
                                        >
                                            Belum ada periode tagihan.
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
    <AdminLayout title={page?.props?.title ?? "Periode Tagihan Air"}>
        {page}
    </AdminLayout>
);
