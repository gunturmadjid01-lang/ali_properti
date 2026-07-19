import { Head } from "@inertiajs/react";
import { Printer } from "lucide-react";
import { Button } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
const money = (v) => `Rp ${Number(v || 0).toLocaleString("id-ID")}`;
export default function Invoice({ title, invoice }) {
    const mark = invoice.remaining <= 0 ? "LUNAS" : "BELUM LUNAS";
    return (
        <>
            <Head title={title} />
            <div className="mx-auto max-w-4xl">
                <div className="mb-4 print:hidden">
                    <Button onClick={() => window.print()}>
                        <Printer size={16} />
                        Cetak Invoice
                    </Button>
                </div>
                <article className="theme-light-surface relative min-h-[900px] overflow-hidden border bg-white p-10 text-black">
                    <div
                        className={`pointer-events-none absolute inset-0 flex rotate-[-28deg] items-center justify-center text-8xl font-black opacity-[0.08] ${invoice.remaining <= 0 ? "text-emerald-700" : "text-red-700"}`}
                    >
                        {mark}
                    </div>
                    <header className="relative flex justify-between border-b-4 border-black pb-6">
                        <div>
                            <p className="text-sm font-black uppercase">
                                Invoice Penjualan
                            </p>
                            <h1 className="mt-2 text-3xl font-black">
                                {invoice.invoice_no}
                            </h1>
                        </div>
                        <div className="text-right">
                            <b>{invoice.housing}</b>
                            <p>{invoice.unit}</p>
                        </div>
                    </header>
                    <section className="relative mt-8 grid grid-cols-2 gap-6">
                        <div>
                            <p className="text-xs uppercase">
                                Ditagihkan kepada
                            </p>
                            <p className="text-xl font-black">
                                {invoice.customer}
                            </p>
                            <p>{invoice.transaction}</p>
                        </div>
                        <div className="text-right">
                            <p>Terbit: {invoice.issued_at}</p>
                            <p>
                                Jatuh tempo: <b>{invoice.due_date}</b>
                            </p>
                        </div>
                    </section>
                    <table className="relative mt-10 w-full">
                        <thead>
                            <tr className="border-y-2 border-black text-left">
                                <th className="py-4">Uraian</th>
                                <th>Nilai</th>
                                <th>Dibayar</th>
                                <th>Sisa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td className="py-6 font-bold">
                                    {invoice.description}
                                </td>
                                <td>{money(invoice.bill)}</td>
                                <td>{money(invoice.paid)}</td>
                                <td className="font-black">
                                    {money(invoice.remaining)}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <section className="relative mt-10 border-t-2 border-black pt-6">
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <p className="text-xs font-black uppercase">
                                    Riwayat Pembayaran Invoice
                                </p>
                                <h2 className="mt-1 text-xl font-black">
                                    {invoice.payments?.length || 0} kali
                                    pembayaran
                                </h2>
                            </div>
                            <p className="text-right text-sm">
                                Akumulasi terposting
                                <br />
                                <b>{money(invoice.paid)}</b>
                            </p>
                        </div>
                        <div className="mt-5 grid gap-3">
                            {(invoice.payments ?? []).map((payment, index) => (
                                <div
                                    className="flex items-center justify-between gap-5 border-b pb-3"
                                    key={`${payment.receipt_no}-${index}`}
                                >
                                    <div>
                                        <b>{payment.receipt_no}</b>
                                        <p className="text-sm">
                                            {payment.date} ·{" "}
                                            {payment.method?.replaceAll(
                                                "_",
                                                " ",
                                            )}{" "}
                                            · {payment.status}
                                        </p>
                                    </div>
                                    <strong>{money(payment.amount)}</strong>
                                </div>
                            ))}
                            {!invoice.payments?.length && (
                                <p className="border border-dashed p-5 text-center text-sm">
                                    Belum ada pembayaran untuk invoice ini.
                                </p>
                            )}
                        </div>
                    </section>
                    <footer className="relative mt-20 border-t pt-5 text-sm">
                        Invoice dihasilkan dari jadwal transaksi yang telah
                        difinalisasi. Pembayaran hanya sah setelah persetujuan
                        akhir dan posting akuntansi.
                    </footer>
                </article>
            </div>
        </>
    );
}
Invoice.layout = (p) => (
    <AdminLayout title={p?.props?.title ?? "Invoice Piutang"}>{p}</AdminLayout>
);
