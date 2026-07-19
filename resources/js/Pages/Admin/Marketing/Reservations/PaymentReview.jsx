import { Head, Link, useForm } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { Button, Input } from "../../../../Components/UI";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
export default function PaymentReview({
    title,
    row,
    approval,
    proofUrl,
}) {
    const form = useForm({
        fund_received_at: new Date().toISOString().slice(0, 16),
        settlement_proof: null,
        finance_verification_notes: "",
    });
    const reject = () => {
        const note = prompt("Alasan penolakan");
        if (note) {
            form.transform(() => ({ note }));
            form.post(
                `/admin/keuangan/penerimaan-customer/reservasi/${row.id}/verifikasi/reject`,
                { onFinish: () => form.transform((data) => data) },
            );
        }
    };
    const approve = () => {
        form.transform((data) => data);
        form.post(
            `/admin/keuangan/penerimaan-customer/reservasi/${row.id}/verifikasi/approve`,
            { forceFormData: true, onFinish: () => form.transform((data) => data) },
        );
    };
    return (
        <AdminLayout>
            <Head title={title} />
            <div className="mx-auto grid max-w-5xl gap-6">
                <header>
                    <Link
                        href="/admin/keuangan/penerimaan-customer"
                        className="text-sm font-bold text-blue-700"
                    >
                        ← Kembali ke daftar approval
                    </Link>
                    <h1 className="mt-3 text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-ink-soft">
                        Tahap {approval.current_step}/{approval.total_steps}.
                        Pastikan uang benar-benar diterima perusahaan sebelum
                        menyetujui.
                    </p>
                </header>
                <section className="grid gap-4 rounded-2xl border bg-white p-6 md:grid-cols-2">
                    <div>
                        <small>Customer</small>
                        <p className="font-black">{row.customer?.nama}</p>
                    </div>
                    <div>
                        <small>Perumahan / Unit</small>
                        <p className="font-black">
                            {row.unit?.perumahan?.nama_perusahaan} ·{" "}
                            {row.unit?.kode_nlok}/{row.unit?.nomor_rumah}
                        </p>
                    </div>
                    <div>
                        <small>Nominal Booking Fee</small>
                        <p className="text-xl font-black">
                            {money(row.booking_fee)}
                        </p>
                    </div>
                    <div>
                        <small>Metode / Pengirim</small>
                        <p className="font-black">
                            {row.payment_channel} · {row.payment_sender_name}
                        </p>
                        <p>{row.payment_bank_reference || "Tanpa referensi"}</p>
                    </div>
                    <div>
                        <small>Status lokasi uang</small>
                        <p className="font-black text-amber-700">
                            {row.fund_custody_status === "held_by_marketing"
                                ? "Masih dipegang Marketing"
                                : "Menunggu verifikasi rekening"}
                        </p>
                    </div>
                    <div>
                        <small>Bukti dari Marketing</small>
                        <p>
                            <a
                                href={proofUrl}
                                target="_blank"
                                className="font-bold text-blue-700"
                            >
                                Buka bukti pembayaran ↗
                            </a>
                        </p>
                    </div>
                </section>
                <section className="grid gap-4 rounded-2xl border bg-white p-6 md:grid-cols-2">
                    <h2 className="md:col-span-2 text-lg font-black">
                        Konfirmasi Penerimaan oleh Keuangan
                    </h2>
                    <div className="rounded-lg bg-slate-50 p-4 md:col-span-2 dark:bg-white/5">
                        <span className="text-sm font-bold">Lokasi dana yang diverifikasi</span>
                        <p className="mt-1 font-black">
                            {row.payment_channel === "cash"
                                ? `${row.petty_cash_account?.code || "Kas Kecil"} — ${row.petty_cash_account?.name || "Petugas Marketing"}`
                                : `${row.fund_bank?.nama_bank || "Rekening perusahaan"} — ${row.fund_bank?.nomor_rekening || "-"}`}
                        </p>
                    </div>
                    <Input
                        type="datetime-local"
                        label="Waktu Diterima/Disetor *"
                        value={form.data.fund_received_at}
                        error={form.errors.fund_received_at}
                        onChange={(e) =>
                            form.setData("fund_received_at", e.target.value)
                        }
                    />
                    <label className="grid gap-2 text-sm font-bold">
                        <span>
                            Bukti Serah Terima / Slip Setoran{" "}
                            {row.payment_channel === "cash" ? "*" : ""}
                        </span>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            className="rounded-lg border p-3"
                            onChange={(e) =>
                                form.setData(
                                    "settlement_proof",
                                    e.target.files?.[0] || null,
                                )
                            }
                        />
                        {form.errors.settlement_proof && (
                            <span className="text-xs text-red-600">
                                {form.errors.settlement_proof}
                            </span>
                        )}
                    </label>
                    <label className="grid gap-2 md:col-span-2">
                        <span className="text-sm font-bold">
                            Catatan Verifikasi Keuangan *
                        </span>
                        <textarea
                            className="min-h-28 rounded-lg border p-3"
                            value={form.data.finance_verification_notes}
                            onChange={(e) =>
                                form.setData(
                                    "finance_verification_notes",
                                    e.target.value,
                                )
                            }
                        />
                        {form.errors.finance_verification_notes && (
                            <span className="text-xs text-red-600">
                                {form.errors.finance_verification_notes}
                            </span>
                        )}
                    </label>
                    <div className="md:col-span-2 flex justify-end gap-3">
                        <Button variant="danger" onClick={reject}>
                            Tolak
                        </Button>
                        <Button disabled={form.processing} onClick={approve}>
                            Setujui & Posting Keuangan
                        </Button>
                    </div>
                </section>
            </div>
        </AdminLayout>
    );
}
