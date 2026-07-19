import { Head } from "@inertiajs/react";
import { Printer } from "lucide-react";
import { useEffect } from "react";
import { Button } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
const money = (v) => `Rp ${Number(v || 0).toLocaleString("id-ID")}`;
export default function Preview({ title, row }) {
    useEffect(() => {
        if (new URLSearchParams(window.location.search).get("print") === "1") {
            const timer = setTimeout(() => window.print(), 300);
            return () => clearTimeout(timer);
        }
    }, []);
    return (
        <>
            <Head title={title} />
            <div className="mx-auto max-w-4xl">
                <div className="mb-4 print:hidden">
                    <Button onClick={() => window.print()}>
                        <Printer size={16} /> Cetak Dokumen
                    </Button>
                </div>
                <article className="theme-light-surface relative min-h-[900px] overflow-hidden border bg-white p-10 text-black">
                    <div className="pointer-events-none absolute inset-0 flex -rotate-[28deg] items-center justify-center text-6xl font-black opacity-[.06]">
                        {row.status === "posted"
                            ? "TERPOSTING"
                            : row.status === "reversed"
                              ? "DIREVERSAL"
                              : "BELUM FINAL"}
                    </div>
                    <header className="relative border-b-4 border-black pb-6">
                        <p className="text-sm font-black uppercase">
                            {row.type === "customer_advance"
                                ? "Surat Talangan Customer"
                                : "Tagihan Tambahan Customer"}
                        </p>
                        <h1 className="mt-2 text-3xl font-black">
                            {row.charge_no}
                        </h1>
                    </header>
                    <section className="relative mt-8 grid grid-cols-2 gap-8">
                        <div>
                            <p className="text-xs uppercase">
                                Customer / Transaksi
                            </p>
                            <b>{row.customer}</b>
                            <p>{row.transaction}</p>
                            <p>
                                {row.housing} — {row.unit}
                            </p>
                        </div>
                        <div className="text-right">
                            <p>Tanggal: {row.charge_date}</p>
                            <p>Jatuh tempo: {row.due_date}</p>
                            <p>
                                Invoice: {row.invoice_no || "Belum terbentuk"}
                            </p>
                        </div>
                    </section>
                    <section className="relative mt-10 border-y-2 py-6">
                        <p className="text-sm uppercase">
                            {row.category?.replaceAll("_", " ")}
                        </p>
                        <h2 className="mt-1 text-xl font-black">
                            {row.description}
                        </h2>
                        <p className="mt-5 text-4xl font-black">
                            {money(row.amount)}
                        </p>
                    </section>
                    {row.type === "customer_advance" && (
                        <section className="relative mt-8 grid grid-cols-2 gap-5">
                            <div>
                                <p className="text-xs uppercase">
                                    Dibayarkan kepada
                                </p>
                                <b>{row.paid_to}</b>
                            </div>
                            <div>
                                <p className="text-xs uppercase">
                                    Sumber dana / referensi
                                </p>
                                <b>{row.bank}</b>
                                <p>{row.payment_reference}</p>
                            </div>
                        </section>
                    )}
                    <section className="relative mt-8">
                        <p className="text-xs uppercase">Catatan</p>
                        <p className="mt-2 whitespace-pre-wrap">
                            {row.notes || "-"}
                        </p>
                    </section>
                    <footer className="relative mt-20 border-t pt-4 text-sm">
                        Jurnal: {row.journal_no || "Belum diposting"}. Dokumen
                        resmi hanya berlaku setelah persetujuan final.
                    </footer>
                </article>
            </div>
        </>
    );
}
Preview.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Cetak Tagihan/Talangan"}>
        {page}
    </AdminLayout>
);
