import { Head } from "@inertiajs/react";
import { Printer } from "lucide-react";
import { Button } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { useEffect } from "react";
const money = (v) => `Rp ${Number(v || 0).toLocaleString("id-ID")}`;
export default function Preview({ title, receipt }) {
    useEffect(() => {
        if (new URLSearchParams(window.location.search).get("print") === "1") {
            const timer = window.setTimeout(() => window.print(), 350);
            return () => window.clearTimeout(timer);
        }
    }, []);
    const final = receipt.status === "posted";
    const watermark = final
        ? "DISETUJUI FINAL"
        : receipt.status === "rejected"
          ? "DITOLAK"
          : receipt.record_status === "draft"
            ? "DRAF — BELUM DISETUJUI"
            : `MENUNGGU PERSETUJUAN ${receipt.approval_step || 1}/${receipt.approval_total || 1}`;
    return (
        <>
            <Head title={title} />
            <div className="mx-auto max-w-4xl">
                <div className="mb-4 print:hidden">
                    <Button onClick={() => window.print()}>
                        <Printer size={16} />
                        Cetak
                    </Button>
                </div>
                <article className="theme-light-surface relative min-h-[900px] overflow-hidden border bg-white p-10 text-black">
                    <div
                        className={`pointer-events-none absolute inset-0 flex rotate-[-28deg] items-center justify-center whitespace-nowrap text-6xl font-black opacity-[0.09] ${final ? "text-emerald-700" : "text-blue-700"}`}
                    >
                        {watermark}
                    </div>
                    <header className="relative border-b-4 border-black pb-6">
                        <p className="text-sm font-black uppercase">
                            {final
                                ? "Kuitansi Penerimaan"
                                : "Pratinjau Penerimaan"}
                        </p>
                        <h1 className="mt-2 text-3xl font-black">
                            {receipt.receipt_no}
                        </h1>
                    </header>
                    <section className="relative mt-8 grid grid-cols-2 gap-4">
                        <div>
                            <p className="text-xs uppercase">Pelanggan</p>
                            <b>{receipt.customer}</b>
                            <p>{receipt.transaction}</p>
                            <p>
                                {receipt.housing} — {receipt.unit}
                            </p>
                        </div>
                        <div className="text-right">
                            <p>Tanggal: {receipt.date}</p>
                            <p>Metode: {receipt.method}</p>
                            <p>Rekening: {receipt.bank}</p>
                        </div>
                    </section>
                    <p className="relative my-10 border-y-2 py-6 text-center text-4xl font-black">
                        {money(receipt.amount)}
                    </p>
                    <table className="relative w-full">
                        <thead>
                            <tr className="border-b-2 text-left">
                                <th className="py-3">Alokasi</th>
                                <th className="text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {receipt.allocations.map((a, i) => (
                                <tr className="border-b" key={i}>
                                    <td className="py-4">{a.label}</td>
                                    <td className="text-right font-bold">
                                        {money(a.amount)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <footer className="relative mt-20 border-t pt-4 text-sm">
                        {final
                            ? "Dokumen sah setelah persetujuan akhir dan posting jurnal."
                            : "Dokumen ini belum merupakan bukti penerimaan final."}
                    </footer>
                </article>
            </div>
        </>
    );
}
Preview.layout = (p) => (
    <AdminLayout title={p?.props?.title ?? "Pratinjau Penerimaan"}>
        {p}
    </AdminLayout>
);
