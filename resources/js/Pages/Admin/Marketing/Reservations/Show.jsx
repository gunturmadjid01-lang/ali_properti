import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    CalendarClock,
    CircleDollarSign,
    FileText,
    Home,
    LockKeyhole,
    Printer,
    UserRound,
} from "lucide-react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { Button } from "../../../../Components/UI";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
const dateTime = (value) => value ? new Date(value).toLocaleString("id-ID", { dateStyle: "long", timeStyle: "short" }) : "-";
const labels = {
    draft: "Draft Privat", active: "Booking Fee Diterima", spr_created: "SPR Dibuat",
    sales_process: "Proses Penjualan", handover: "Serah Terima",
    occupied: "Sudah Dihuni", completed: "Selesai",
    customer_cancelled: "Dibatalkan Customer", cancelled: "Dibatalkan Internal",
    expired: "Kedaluwarsa", unpaid: "Belum Dibayar", received_pending_approval: "Dana Diterima, Menunggu Approval", partial: "Dibayar Sebagian",
    paid: "Lunas", refunded: "Dikembalikan", pending: "Menunggu Persetujuan",
    approved: "Disetujui", rejected: "Ditolak",
};

function Info({ label, value, note }) {
    return <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <p className="text-xs font-bold uppercase tracking-wide text-ink-soft">{label}</p>
        <p className="mt-1 font-bold text-slate-900">{value || "-"}</p>
        {note && <p className="mt-1 text-xs text-ink-soft">{note}</p>}
    </div>;
}

function SectionTitle({ icon: Icon, title, description }) {
    return <div className="mb-4 flex items-start gap-3">
        <span className="rounded-xl bg-slate-100 p-2 text-slate-700"><Icon size={19}/></span>
        <div><h2 className="font-black">{title}</h2><p className="text-sm text-ink-soft">{description}</p></div>
    </div>;
}

export default function Show({ title, row, approval, invoiceUrl }) {
    const schedule = row.payment_schedule;
    const remaining = Math.max(0, Number(row.booking_fee || 0) - Number(row.paid_amount || 0));
    return <AdminLayout>
        <Head title={title}/>
        <div className="mx-auto grid max-w-6xl gap-6">
            <header className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link href="/admin/marketing/reservasi-perumahan" className="inline-flex items-center gap-2 text-sm font-bold text-blue-700"><ArrowLeft size={16}/> Kembali ke daftar</Link>
                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <h1 className="text-3xl font-black">{row.reservation_no}</h1>
                        <span className={`rounded-full px-3 py-1 text-xs font-black ${row.record_status === "locked" ? "bg-slate-900 text-white" : "bg-amber-100 text-amber-800"}`}>
                            {row.record_status === "locked" ? "LOCKED" : "DRAFT PRIVAT"}
                        </span>
                    </div>
                    <p className="mt-1 text-ink-soft">Invoice Booking Fee: <b>{row.invoice_no}</b></p>
                </div>
                {invoiceUrl && <Button as={Link} href={invoiceUrl}><Printer size={17}/> Buka Invoice</Button>}
            </header>

            <section className="grid gap-3 md:grid-cols-4">
                <Info label="Status Reservasi" value={labels[row.status] || row.status}/>
                <Info label="Status Pembayaran" value={labels[row.payment_status] || row.payment_status}/>
                <Info label="Booking Fee" value={money(row.booking_fee)} note={`Sisa ${money(remaining)}`}/>
                <Info label="Tanggal Dana Diterima" value={dateTime(row.payment_submitted_at)}/>
            </section>

            <div className="grid gap-6 lg:grid-cols-2">
                <section className="rounded-2xl border bg-white p-6">
                    <SectionTitle icon={UserRound} title="Data Customer" description="Identitas pihak yang melakukan reservasi."/>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Info label="Nama" value={row.customer?.nama}/>
                        <Info label="Kode Customer" value={row.customer?.kode_costumer}/>
                        <Info label="Telepon" value={row.customer?.telepon}/>
                        <Info label="Email" value={row.customer?.email}/>
                        <div className="sm:col-span-2"><Info label="Alamat" value={row.customer?.alamat}/></div>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-6">
                    <SectionTitle icon={Home} title="Unit yang Direservasi" description="Objek dan harga unit pada reservasi ini."/>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Info label="Perumahan" value={row.unit?.perumahan?.nama_perusahaan}/>
                        <Info label="Blok / Nomor" value={`${row.unit?.kode_nlok || "-"} / ${row.unit?.nomor_rumah || "-"}`}/>
                        <Info label="Tipe Unit" value={row.unit?.tipe_rumah}/>
                        <Info label="Harga Jual" value={money(row.unit?.harga_jual)}/>
                        <Info label="Luas" value={`LT ${row.unit?.luas_tanah || 0} m² · LB ${row.unit?.luas_bangunan || 0} m²`}/>
                        <Info label="Status Unit" value={row.unit?.status_penjualan}/>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-6">
                    <SectionTitle icon={CircleDollarSign} title="Informasi Tagihan" description="Nilai dan status invoice Booking Fee."/>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Info label="Nomor Invoice" value={row.invoice_no}/>
                        <Info label="Metode Penjualan" value={(row.payment_method || "-").replaceAll("_", " ").toUpperCase()}/>
                        <Info label="Nominal" value={money(schedule?.amount ?? row.booking_fee)}/>
                        <Info label="Sudah Dibayar" value={money(schedule?.paid_amount ?? row.paid_amount)}/>
                        <Info label="Terbit" value={schedule?.issued_at ? new Date(schedule.issued_at).toLocaleDateString("id-ID") : "Belum diterbitkan"}/>
                        <Info label="Status Invoice" value={labels[schedule?.status] || schedule?.status || "Belum diterbitkan"}/>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-6">
                    <SectionTitle icon={LockKeyhole} title="Approval dan Audit" description="Jejak penguncian serta tahap persetujuan."/>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Info label="Status Approval" value={labels[approval?.status] || approval?.status || (row.record_status === "draft" ? "Belum diajukan" : "Disetujui otomatis")}/>
                        <Info label="Tahap Approval" value={approval ? `${approval.current_step || 0} dari ${approval.total_steps || 0}` : "-"}/>
                        <Info label="Dibuat Oleh" value={row.creator?.name} note={dateTime(row.created_at)}/>
                        <Info label="Dikunci Pada" value={dateTime(row.locked_at)}/>
                        <Info label="SPR Terkait" value={row.spr?.kode_spr || "Belum dibuat"} note={row.process_stage || undefined}/>
                        <Info label="Pembatalan" value={row.cancellation_reason || "Tidak ada"} note={row.canceller?.name ? `Oleh ${row.canceller.name}` : undefined}/>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>;
}
