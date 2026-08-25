import { Head, router } from "@inertiajs/react";
import { ArrowLeft, CreditCard } from "lucide-react";
import { Button } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;

export default function Show({ title, baseUrl, row }) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-widest text-ink-soft">
                                Marketing / Detail Transaksi Cash
                            </p>
                            <h1 className="mt-2 text-3xl font-extrabold">
                                {title}
                            </h1>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => router.visit(baseUrl)}
                            >
                                <ArrowLeft size={16} /> Kembali
                            </Button>
                            {row.can_update && (
                                <Button
                                    type="button"
                                    onClick={() =>
                                        router.visit(
                                            `${baseUrl}/${row.id}/payments/create`,
                                        )
                                    }
                                >
                                    <CreditCard size={16} /> Tambah Pembayaran
                                </Button>
                            )}
                        </div>
                    </div>
                </section>
                <section className="grid gap-3 rounded-lg border bg-white/80 p-6 shadow-soft md:grid-cols-4 dark:border-white/10 dark:bg-white/8">
                    {[
                        ["Kode", row.kode_cash],
                        ["SPR", row.kode_spr],
                        ["Customer", row.customer],
                        ["Unit", row.unit],
                        ["Perumahan", row.perumahan],
                        ["Harga", money(row.harga_rumah)],
                        ["Dibayar", money(row.total_dibayar)],
                        ["Sisa", money(row.sisa_tagihan)],
                        ["Status", row.status_label],
                        ["Kunci", row.record_status_label],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <p className="text-xs font-bold uppercase text-ink-soft">
                                {label}
                            </p>
                            <p className="mt-1 font-bold">{value}</p>
                        </div>
                    ))}
                </section>
                <section className="overflow-hidden rounded-lg border bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="p-5">
                        <h2 className="text-xl font-extrabold">
                            Riwayat Pembayaran
                        </h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft text-left text-xs uppercase text-ink-soft">
                                <tr>
                                    {[
                                        "Tanggal",
                                        "Nominal",
                                        "Metode",
                                        "Keterangan",
                                        "Input Oleh",
                                        "Bukti",
                                    ].map((label) => (
                                        <th className="px-5 py-4" key={label}>
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {row.payments.map((payment) => (
                                    <tr key={payment.id}>
                                        <td className="px-5 py-4">
                                            {payment.tanggal_pembayaran}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {money(payment.nominal)}
                                        </td>
                                        <td className="px-5 py-4">
                                            {payment.metode_pembayaran}
                                        </td>
                                        <td className="px-5 py-4">
                                            {payment.keterangan || "-"}
                                        </td>
                                        <td className="px-5 py-4">
                                            {payment.created_by}
                                        </td>
                                        <td className="px-5 py-4">
                                            {payment.bukti_url ? (
                                                <a
                                                    className="font-bold text-blue-600"
                                                    href={payment.bukti_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Lihat
                                                </a>
                                            ) : (
                                                "-"
                                            )}
                                        </td>
                                    </tr>
                                ))}
                                {row.payments.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center text-ink-soft"
                                            colSpan={6}
                                        >
                                            Belum ada pembayaran.
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

Show.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail Transaksi Cash"}>
        {page}
    </AdminLayout>
);
