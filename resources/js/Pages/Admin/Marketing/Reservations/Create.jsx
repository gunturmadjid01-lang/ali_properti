import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, CircleDollarSign, Home, Landmark, Save, UserRound, WalletCards } from "lucide-react";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { Button, CurrencyInput, Dropdown } from "../../../../Components/UI";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
const methods = [
    { value: "cash", label: "Cash" },
    { value: "cash_bertahap", label: "Cash Bertahap" },
    { value: "kpr_bank", label: "KPR Bank" },
    { value: "kpr_developer", label: "KPR Developer" },
];

function StepTitle({ number, icon: Icon, title, description }) {
    return <div className="mb-5 flex items-start gap-3 border-b pb-4">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 font-black text-white">{number}</span>
        <div className="flex-1"><div className="flex items-center gap-2"><Icon size={18}/><h2 className="font-black">{title}</h2></div><p className="mt-1 text-sm text-ink-soft">{description}</p></div>
    </div>;
}

export default function Create({ title, customers, units, bookingSources, bankAccounts, pettyCashAccounts, row }) {
    const form = useForm({
        costumer_id: row?.costumer_id ? String(row.costumer_id) : "",
        detail_rumah_id: row?.detail_rumah_id ? String(row.detail_rumah_id) : "",
        payment_method: row?.payment_method || "cash_bertahap",
        booking_fee_source_id: row?.booking_fee_source_id ? String(row.booking_fee_source_id) : "",
        booking_fee: row?.booking_fee ? String(row.booking_fee) : "",
        payment_submitted_at: row?.payment_submitted_at ? String(row.payment_submitted_at).slice(0, 10) : new Date().toISOString().slice(0, 10),
        payment_channel: row?.payment_channel || "transfer",
        payment_sender_name: row?.payment_sender_name || "",
        payment_bank_reference: row?.payment_bank_reference || "",
        fund_master_bank_id: row?.fund_master_bank_id ? String(row.fund_master_bank_id) : "",
        petty_cash_account_id: row?.petty_cash_account_id ? String(row.petty_cash_account_id) : (pettyCashAccounts?.[0]?.value || ""),
        payment_notes: row?.payment_notes || "",
        proof: null,
    });
    const sources = bookingSources?.[form.data.payment_method] ?? [];
    const customer = customers.find((item) => String(item.id) === String(form.data.costumer_id));
    const unit = units.find((item) => String(item.id) === String(form.data.detail_rumah_id));
    const availableBanks = (bankAccounts ?? []).filter((bank) => !unit || String(bank.perumahan_id) === String(unit.perumahan_id));
    const customerOptions = customers.map((item) => ({ value: String(item.id), label: `${item.nama} — ${item.kode_costumer}${item.telepon ? ` — ${item.telepon}` : ""}` }));
    const unitOptions = units.map((item) => ({ value: String(item.id), label: `${item.perumahan?.nama_perusahaan} — Blok ${item.kode_nlok || "-"} / ${item.nomor_rumah || "-"} — Tipe ${item.tipe_rumah || "-"} — ${money(item.harga_jual)}` }));
    const sourceOptions = sources.map((item) => ({ ...item, value: String(item.id), label: `${item.label}${item.booking_fee ? ` — ${money(item.booking_fee)}` : ""}` }));

    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => row ? { ...data, _method: "put" } : data);
        form.post(row ? `/admin/marketing/reservasi-perumahan/${row.id}` : "/admin/marketing/reservasi-perumahan", {
            forceFormData: true,
            onFinish: () => form.transform((data) => data),
        });
    };

    return <AdminLayout>
        <Head title={title}/>
        <div className="mx-auto grid max-w-7xl gap-6">
            <header className="rounded-2xl bg-slate-900 p-6 text-white md:p-8">
                <Link href="/admin/marketing/reservasi-perumahan" className="inline-flex items-center gap-2 text-sm font-bold text-slate-200"><ArrowLeft size={16}/> Kembali ke daftar reservasi</Link>
                <div className="mt-4 flex flex-wrap items-end justify-between gap-4">
                    <div><p className="text-xs font-black uppercase tracking-[0.2em] text-slate-300">Form Transaksi Reservasi</p><h1 className="mt-1 text-3xl font-black">{title}</h1><p className="mt-2 max-w-2xl text-sm text-slate-300">Data disimpan sebagai draft privat. Unit belum ditahan dan invoice belum diterbitkan sampai reservasi dikunci.</p></div>
                    <span className="rounded-full bg-amber-300 px-4 py-2 text-xs font-black text-slate-900">DRAFT PRIVAT</span>
                </div>
            </header>

            <form onSubmit={submit} className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div className="grid gap-6">
                    <section className="rounded-2xl border bg-white p-6">
                        <StepTitle number="1" icon={UserRound} title="Pilih Customer" description="Tentukan calon pembeli yang melakukan reservasi."/>
                        <Dropdown label="Cari nama, kode, atau nomor telepon customer" value={form.data.costumer_id} options={customerOptions} error={form.errors.costumer_id} onChange={(value) => form.setData("costumer_id", value)}/>
                        {customer && <div className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-3"><div><small>Nama Customer</small><p className="font-black">{customer.nama}</p></div><div><small>Kode</small><p className="font-bold">{customer.kode_costumer || "-"}</p></div><div><small>Telepon</small><p className="font-bold">{customer.telepon || "-"}</p></div></div>}
                    </section>

                    <section className="order-4 rounded-2xl border bg-white p-6">
                        <StepTitle number="4" icon={WalletCards} title="Penerimaan Booking Fee" description="Marketing mencatat dana yang benar-benar diterima. Data ini ikut diajukan bersama reservasi untuk diverifikasi Keuangan."/>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div><span className="mb-2 block text-sm font-bold">Cara Dana Diterima *</span><Dropdown searchable={false} value={form.data.payment_channel} options={[{ value: "transfer", label: "Transfer ke Rekening Perumahan" }, { value: "cash", label: "Tunai ke Kas Kecil Marketing" }]} onChange={(value) => form.setData({ ...form.data, payment_channel: value, fund_master_bank_id: "", petty_cash_account_id: value === "cash" ? (pettyCashAccounts?.[0]?.value || "") : "", payment_bank_reference: "" })}/></div>
                            <label><span className="mb-2 block text-sm font-bold">Tanggal Dana Diterima *</span><input required type="date" className="w-full rounded-lg border p-3" value={form.data.payment_submitted_at} onChange={(event) => form.setData("payment_submitted_at", event.target.value)}/>{form.errors.payment_submitted_at && <small className="text-red-600">{form.errors.payment_submitted_at}</small>}</label>
                            {form.data.payment_channel === "transfer" ? <>
                                <div className="md:col-span-2"><span className="mb-2 block text-sm font-bold">Rekening Tujuan Perumahan *</span><Dropdown label={unit ? (availableBanks.length ? "Pilih rekening perusahaan untuk perumahan ini" : "Belum ada rekening aktif dan final untuk perumahan ini") : "Pilih unit terlebih dahulu"} disabled={!unit || !availableBanks.length} value={form.data.fund_master_bank_id} options={availableBanks} error={form.errors.fund_master_bank_id} onChange={(value) => form.setData("fund_master_bank_id", value)}/><p className="mt-1 text-xs text-ink-soft">Sumber rekening berasal dari Master Bank modul Akuntansi dan otomatis difilter sesuai perumahan unit.</p></div>
                                <label><span className="mb-2 block text-sm font-bold">Nama Pengirim *</span><input className="w-full rounded-lg border p-3" value={form.data.payment_sender_name} onChange={(event) => form.setData("payment_sender_name", event.target.value)}/>{form.errors.payment_sender_name && <small className="text-red-600">{form.errors.payment_sender_name}</small>}</label>
                                <label><span className="mb-2 block text-sm font-bold">Referensi Transfer *</span><input className="w-full rounded-lg border p-3" value={form.data.payment_bank_reference} onChange={(event) => form.setData("payment_bank_reference", event.target.value)}/>{form.errors.payment_bank_reference && <small className="text-red-600">{form.errors.payment_bank_reference}</small>}</label>
                            </> : <>
                                <div className="md:col-span-2"><span className="mb-2 block text-sm font-bold">Kas Kecil Marketing *</span><Dropdown label={pettyCashAccounts?.length ? "Pilih Kas Kecil penerima" : "Marketing belum memiliki Kas Kecil aktif"} disabled={!pettyCashAccounts?.length} value={form.data.petty_cash_account_id} options={pettyCashAccounts ?? []} error={form.errors.petty_cash_account_id} onChange={(value) => form.setData("petty_cash_account_id", value)}/><p className="mt-1 text-xs text-ink-soft">Setelah approval Keuangan, saldo dan buku mutasi Kas Kecil Marketing bertambah otomatis.</p></div>
                                <label className="md:col-span-2"><span className="mb-2 block text-sm font-bold">Diterima Dari *</span><input className="w-full rounded-lg border p-3" value={form.data.payment_sender_name} onChange={(event) => form.setData("payment_sender_name", event.target.value)}/>{form.errors.payment_sender_name && <small className="text-red-600">{form.errors.payment_sender_name}</small>}</label>
                            </>}
                            <label className="md:col-span-2"><span className="mb-2 block text-sm font-bold">Bukti Penerimaan {row?.payment_proof_path ? "(kosongkan bila tidak diganti)" : "*"}</span><input type="file" accept=".jpg,.jpeg,.png,.pdf" className="w-full rounded-lg border p-3" onChange={(event) => form.setData("proof", event.target.files?.[0] || null)}/>{form.errors.proof && <small className="text-red-600">{form.errors.proof}</small>}</label>
                            <label className="md:col-span-2"><span className="mb-2 block text-sm font-bold">Catatan Penerimaan</span><textarea rows="3" className="w-full rounded-lg border p-3" value={form.data.payment_notes} onChange={(event) => form.setData("payment_notes", event.target.value)}/></label>
                        </div>
                    </section>

                    <section className="rounded-2xl border bg-white p-6">
                        <StepTitle number="2" icon={Home} title="Pilih Unit" description="Hanya unit tersedia yang dapat dipilih dan ditahan saat lock."/>
                        <Dropdown label="Cari perumahan, blok, nomor, atau tipe unit" value={form.data.detail_rumah_id} options={unitOptions} error={form.errors.detail_rumah_id} onChange={(value) => form.setData("detail_rumah_id", value)}/>
                        {unit && <div className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4"><div className="sm:col-span-2"><small>Perumahan</small><p className="font-black">{unit.perumahan?.nama_perusahaan}</p></div><div><small>Blok / Nomor</small><p className="font-bold">{unit.kode_nlok || "-"} / {unit.nomor_rumah || "-"}</p></div><div><small>Tipe</small><p className="font-bold">{unit.tipe_rumah || "-"}</p></div><div><small>Luas</small><p className="font-bold">LT {unit.luas_tanah || 0} m² · LB {unit.luas_bangunan || 0} m²</p></div><div><small>Harga Jual</small><p className="font-black text-emerald-700">{money(unit.harga_jual)}</p></div></div>}
                    </section>

                    <section className="rounded-2xl border bg-white p-6">
                        <StepTitle number="3" icon={CircleDollarSign} title="Data Booking Fee yang Diterima" description="Pilih metode penjualan dan catat nominal Booking Fee yang sudah diterima dari customer."/>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div><span className="mb-2 block text-sm font-bold">Metode Penjualan *</span><Dropdown searchable={false} value={form.data.payment_method} options={methods} onChange={(value) => form.setData({ ...form.data, payment_method: value, booking_fee_source_id: "", booking_fee: "" })}/></div>
                            <div><span className="mb-2 block text-sm font-bold">Produk / Skema</span><Dropdown label={sourceOptions.length ? "Pilih produk atau skema" : "Tidak ada master untuk metode ini"} disabled={!sourceOptions.length} value={form.data.booking_fee_source_id} options={sourceOptions} onChange={(value, source) => form.setData({ ...form.data, booking_fee_source_id: value, booking_fee: source?.booking_fee ? String(source.booking_fee) : form.data.booking_fee })}/></div>
                            <CurrencyInput label="Nominal Booking Fee *" value={form.data.booking_fee} error={form.errors.booking_fee} onChange={(value) => form.setData("booking_fee", value)}/>
                        </div>
                    </section>
                </div>

                <aside className="grid gap-4 lg:sticky lg:top-5">
                    <section className="rounded-2xl border bg-white p-5 shadow-sm">
                        <h2 className="font-black">Ringkasan Reservasi</h2>
                        <div className="mt-4 grid gap-4 text-sm">
                            <div><p className="text-ink-soft">Customer</p><p className="font-bold">{customer?.nama || "Belum dipilih"}</p></div>
                            <div><p className="text-ink-soft">Unit</p><p className="font-bold">{unit ? `${unit.perumahan?.nama_perusahaan} · ${unit.kode_nlok || "-"}/${unit.nomor_rumah || "-"}` : "Belum dipilih"}</p></div>
                            <div><p className="text-ink-soft">Metode</p><p className="font-bold">{methods.find((item) => item.value === form.data.payment_method)?.label}</p></div>
                            <div className="rounded-xl bg-slate-900 p-4 text-white"><p className="text-xs text-slate-300">Booking Fee</p><p className="mt-1 text-xl font-black">{money(form.data.booking_fee)}</p></div>
                            <div className="rounded-xl border p-3"><div className="flex items-center gap-2 text-xs font-bold uppercase text-ink-soft"><Landmark size={14}/> Lokasi Dana</div><p className="mt-1 font-bold">{form.data.payment_channel === "cash" ? (pettyCashAccounts?.find((item) => item.value === form.data.petty_cash_account_id)?.label || "Kas Kecil belum dipilih") : (availableBanks.find((item) => item.value === form.data.fund_master_bank_id)?.label || "Rekening belum dipilih")}</p></div>
                            <p className="rounded-xl bg-emerald-50 p-3 text-xs font-bold text-emerald-800">Reservasi dicatat setelah dana diterima. Tidak ada jatuh tempo Booking Fee.</p>
                        </div>
                    </section>
                    <div className="grid gap-2"><Button disabled={form.processing || !form.data.costumer_id || !form.data.detail_rumah_id}><Save size={17}/>{row ? "Simpan Perubahan" : "Simpan Draft Reservasi"}</Button><Button as={Link} href="/admin/marketing/reservasi-perumahan" type="button" variant="secondary">Batal</Button></div>
                </aside>
            </form>
        </div>
    </AdminLayout>;
}
