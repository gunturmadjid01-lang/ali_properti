import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, Printer } from "lucide-react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { Button } from "../../../../Components/UI";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
const date = (value) => value ? new Date(value).toLocaleDateString("id-ID", { day: "2-digit", month: "long", year: "numeric" }) : "-";

export default function Invoice({ title, row }) {
    const schedule = row.payment_schedule;
    const amount = Number(schedule?.amount ?? row.booking_fee ?? 0);
    const paid = Number(schedule?.paid_amount ?? row.paid_amount ?? 0);
    const remaining = Math.max(0, amount - paid);
    const isPaid = remaining <= 0;

    return <AdminLayout>
        <Head title={title}/>
        <div className="mx-auto max-w-5xl">
            <div className="mb-4 flex items-center justify-between print:hidden">
                <Link href={`/admin/marketing/reservasi-perumahan/${row.id}`} className="inline-flex items-center gap-2 text-sm font-bold text-blue-700"><ArrowLeft size={16}/> Detail reservasi</Link>
                <Button onClick={() => window.print()}><Printer size={17}/> Cetak Invoice</Button>
            </div>
            <article className="relative overflow-hidden rounded-2xl border bg-white p-8 shadow-sm print:border-0 print:p-0 print:shadow-none md:p-12">
                <div className={`pointer-events-none absolute right-[-55px] top-8 rotate-45 px-16 py-2 text-sm font-black tracking-[0.25em] ${isPaid ? "bg-emerald-600 text-white" : "bg-amber-400 text-slate-900"}`}>
                    {isPaid ? "LUNAS" : "BELUM LUNAS"}
                </div>
                <header className="grid gap-6 border-b-2 border-slate-900 pb-7 md:grid-cols-2">
                    <div>
                        <p className="text-sm font-black uppercase tracking-[0.2em] text-ink-soft">Invoice Booking Fee</p>
                        <h1 className="mt-2 text-3xl font-black">{row.invoice_no}</h1>
                        <p className="mt-1 text-sm">Referensi reservasi: <b>{row.reservation_no}</b></p>
                    </div>
                    <div className="md:text-right">
                        <h2 className="text-xl font-black">{row.unit?.perumahan?.nama_perusahaan || "Perumahan"}</h2>
                        <p className="mt-2">Tanggal terbit: <b>{date(schedule?.issued_at ?? row.locked_at)}</b></p>
                        <p>Tanggal diterima: <b>{date(row.payment_submitted_at)}</b></p>
                    </div>
                </header>

                <section className="grid gap-5 border-b py-7 md:grid-cols-2">
                    <div>
                        <p className="text-xs font-black uppercase tracking-wider text-ink-soft">Ditagihkan Kepada</p>
                        <h3 className="mt-2 text-xl font-black">{row.customer?.nama || "-"}</h3>
                        <p>{row.customer?.kode_costumer || "-"}</p>
                        <p>{row.customer?.telepon || "-"}</p>
                        <p className="mt-1 max-w-md text-sm text-ink-soft">{row.customer?.alamat || "Alamat customer belum dicatat."}</p>
                    </div>
                    <div className="rounded-xl bg-slate-50 p-5">
                        <p className="text-xs font-black uppercase tracking-wider text-ink-soft">Objek Reservasi</p>
                        <h3 className="mt-2 text-lg font-black">{row.unit?.perumahan?.nama_perusahaan}</h3>
                        <p>Blok/Unit <b>{row.unit?.kode_nlok || "-"} / {row.unit?.nomor_rumah || "-"}</b></p>
                        <p>Tipe {row.unit?.tipe_rumah || "-"} · LT {row.unit?.luas_tanah || 0} m² · LB {row.unit?.luas_bangunan || 0} m²</p>
                        <p className="mt-2 text-sm">Metode: <b>{(row.payment_method || "-").replaceAll("_", " ").toUpperCase()}</b></p>
                    </div>
                </section>

                <div className="mt-7 overflow-hidden rounded-xl border">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-900 text-left text-white"><tr><th className="p-4">Keterangan</th><th className="p-4">Referensi</th><th className="p-4 text-right">Nominal</th></tr></thead>
                        <tbody><tr className="border-b"><td className="p-4"><b>Booking Fee Reservasi Unit</b><p className="text-xs text-ink-soft">Dana telah diterima Marketing dan diverifikasi melalui approval Keuangan.</p></td><td className="p-4">{row.reservation_no}</td><td className="p-4 text-right text-lg font-black">{money(amount)}</td></tr></tbody>
                    </table>
                </div>

                <section className="ml-auto mt-6 grid max-w-md gap-2 text-sm">
                    <div className="flex justify-between"><span>Total Tagihan</span><b>{money(amount)}</b></div>
                    <div className="flex justify-between text-emerald-700"><span>Sudah Dibayar</span><b>{money(paid)}</b></div>
                    <div className="flex justify-between border-t-2 border-slate-900 pt-3 text-xl"><span className="font-black">Sisa Tagihan</span><b>{money(remaining)}</b></div>
                </section>

                <footer className="mt-10 grid gap-4 border-t pt-5 text-xs text-ink-soft md:grid-cols-2">
                    <p>Invoice ini sah diterbitkan setelah reservasi dikunci. Simpan nomor invoice sebagai referensi pembayaran Booking Fee.</p>
                    <p className="md:text-right">Dibuat oleh {row.creator?.name || "Sistem"}{row.spr?.kode_spr ? ` · SPR ${row.spr.kode_spr}` : ""}</p>
                </footer>
            </article>
        </div>
    </AdminLayout>;
}
