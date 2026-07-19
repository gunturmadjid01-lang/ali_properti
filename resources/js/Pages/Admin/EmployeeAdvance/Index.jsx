import { Head, Link, router } from "@inertiajs/react";
import { Banknote, PlusCircle, Search, Users } from "lucide-react";
import { useState } from "react";
import { Button, Input, TableActions } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
const rp = (v) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(v || 0));
export default function Index({
    title,
    baseUrl,
    createUrl,
    filters,
    statistics,
    trend,
    rows,
}) {
    const [q, setQ] = useState(filters.search);
    const [f, setF] = useState(filters.from_period);
    const [t, setT] = useState(filters.to_period);
    const apply = (e) => {
        e.preventDefault();
        router.get(
            baseUrl,
            { search: q, from_period: f, to_period: t },
            { preserveState: true },
        );
    };
    const act = (id, a) => router.post(`${baseUrl}/${id}/${a}`);
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <section className="flex items-center justify-between rounded-2xl bg-ink p-6 text-white">
                    <div>
                        <p className="text-xs font-black uppercase text-champagne">
                            Akuntansi
                        </p>
                        <h1 className="mt-2 text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-sm text-white/60">
                            Panjar disetujui otomatis dipotong pada periode
                            penggajian tujuan.
                        </p>
                    </div>
                    <Button as={Link} href={createUrl}>
                        <PlusCircle size={16} /> Ambil Panjar
                    </Button>
                </section>
                <section className="grid gap-3 md:grid-cols-4">
                    {[
                        ["Total Panjar", statistics.total],
                        ["Sudah Dipotong", statistics.deducted],
                        ["Belum Dipotong", statistics.pending],
                        ["Pegawai", statistics.employees],
                    ].map(([l, v]) => (
                        <div className="rounded-xl border bg-white p-4" key={l}>
                            <Banknote size={18} />
                            <p className="mt-2 text-xs font-bold">{l}</p>
                            <p className="text-xl font-black">
                                {l === "Pegawai" ? v : rp(v)}
                            </p>
                        </div>
                    ))}
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <form
                        onSubmit={apply}
                        className="grid gap-3 md:grid-cols-[1fr_180px_180px_auto] md:items-end"
                    >
                        <Input
                            label="Cari"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                        <Input
                            type="month"
                            label="Dari"
                            value={f}
                            onChange={(e) => setF(e.target.value)}
                        />
                        <Input
                            type="month"
                            label="Sampai"
                            value={t}
                            onChange={(e) => setT(e.target.value)}
                        />
                        <Button>
                            <Search size={15} /> Terapkan
                        </Button>
                    </form>
                    <h2 className="mt-6 font-black">Tren Panjar Bulanan</h2>
                    <div className="mt-4 flex min-h-56 items-end gap-2 overflow-x-auto border-b">
                        {trend.map((x) => {
                            const max = Math.max(
                                ...trend.map((y) => y.total),
                                1,
                            );
                            return (
                                <div
                                    key={x.period}
                                    className="flex min-w-16 flex-1 flex-col items-center"
                                >
                                    <span className="text-[10px]">
                                        {rp(x.total)}
                                    </span>
                                    <div
                                        className="mt-auto w-full rounded-t bg-amber-500"
                                        style={{
                                            height: `${Math.max(x.total ? 8 : 2, (x.total / max) * 150)}px`,
                                        }}
                                    />
                                    <span className="py-2 text-[10px]">
                                        {x.label}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </section>
                <section className="overflow-x-auto rounded-2xl border bg-white">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="bg-silver-soft text-left">
                                <th className="p-4">Panjar</th>
                                <th className="p-4">Pegawai</th>
                                <th className="p-4">Periode Potong</th>
                                <th className="p-4">Nominal</th>
                                <th className="p-4">Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((x) => (
                                <tr className="border-t" key={x.id}>
                                    <td className="p-4">
                                        <b>{x.number}</b>
                                        <p className="text-xs">
                                            {x.advance_date}
                                        </p>
                                    </td>
                                    <td className="p-4">
                                        {x.employee}
                                        <p className="text-xs">{x.position}</p>
                                    </td>
                                    <td className="p-4">
                                        {x.deduction_period}
                                    </td>
                                    <td className="p-4 font-bold">
                                        {rp(x.amount)}
                                    </td>
                                    <td className="p-4">
                                        {x.deducted
                                            ? "Sudah dipotong"
                                            : x.status}
                                    </td>
                                    <td className="p-4">
                                        <TableActions>
                                            {x.record_status === "draft" && (
                                                <>
                                                    <Button
                                                        as={Link}
                                                        href={x.edit_url}
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            act(x.id, "lock")
                                                        }
                                                    >
                                                        Finalisasi
                                                    </Button>
                                                </>
                                            )}
                                            {x.status ===
                                                "pending_approval" && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        act(x.id, "unlock")
                                                    }
                                                >
                                                    Unlock
                                                </Button>
                                            )}
                                            {x.can_review && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            act(
                                                                x.id,
                                                                "review/approve",
                                                            )
                                                        }
                                                    >
                                                        Approve
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="danger"
                                                        onClick={() =>
                                                            act(
                                                                x.id,
                                                                "review/reject",
                                                            )
                                                        }
                                                    >
                                                        Reject
                                                    </Button>
                                                </>
                                            )}
                                        </TableActions>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}
Index.layout = (p) => <AdminLayout title={p?.props?.title}>{p}</AdminLayout>;
