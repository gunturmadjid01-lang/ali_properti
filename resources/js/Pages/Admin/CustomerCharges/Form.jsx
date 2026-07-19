import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    BadgeDollarSign,
    Landmark,
    Save,
    ShieldAlert,
} from "lucide-react";
import { Button, CurrencyInput, Input, Textarea } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const Select = ({ label, error, children, ...props }) => (
    <label className="grid gap-2 text-sm font-extrabold">
        <span>{label}</span>
        <select
            className={`min-h-11 rounded-lg border bg-white/85 px-4 dark:bg-white/8 ${error ? "border-red-500" : "border-silver-deep/70 dark:border-white/10"}`}
            {...props}
        >
            {children}
        </select>
        {error && <span className="text-xs text-red-600">{error}</span>}
    </label>
);

export default function Form({
    title,
    row = {},
    method,
    actionUrl,
    transactions = [],
    banks = [],
}) {
    const form = useForm({
        sales_transaction_id: row.sales_transaction_id ?? "",
        charge_type: row.charge_type ?? "additional_charge",
        category: row.category ?? "biaya_akad",
        description: row.description ?? "",
        amount: row.amount ?? "",
        charge_date:
            row.charge_date?.slice?.(0, 10) ??
            new Date().toISOString().slice(0, 10),
        due_date: row.due_date?.slice?.(0, 10) ?? "",
        master_bank_id: row.master_bank_id ?? "",
        paid_to: row.paid_to ?? "",
        payment_reference: row.payment_reference ?? "",
        proof: null,
        notes: row.notes ?? "",
    });
    const advance = form.data.charge_type === "customer_advance";
    const submit = (event) => {
        event.preventDefault();
        if (method === "put")
            form.transform((data) => ({ ...data, _method: "put" })).post(
                actionUrl,
                { forceFormData: true },
            );
        else form.post(actionUrl, { forceFormData: true });
    };
    return (
        <>
            <Head title={title} />
            <form className="grid gap-5" onSubmit={submit}>
                <header className="relative overflow-hidden rounded-2xl border border-slate-950 bg-gradient-to-br from-[#20262d] via-[#29313a] to-[#101419] p-7 text-white">
                    <div className="absolute -right-12 -top-20 h-64 w-64 rounded-full bg-amber-400/15 blur-3xl" />
                    <div className="relative flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-xs font-black uppercase tracking-[.2em] text-amber-300">
                                Keuangan / Piutang Pelanggan
                            </p>
                            <h1 className="mt-2 text-3xl font-black">
                                {title}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm text-white/65">
                                Membuat kewajiban baru tanpa mengubah SPR atau
                                kontrak yang sudah final.
                            </p>
                        </div>
                        <Button
                            as={Link}
                            href="/admin/keuangan/tagihan-talangan-customer"
                            variant="outline"
                            className="border-white/20 bg-white/10 text-white"
                        >
                            <ArrowLeft size={16} /> Kembali
                        </Button>
                    </div>
                </header>
                <section className="grid gap-5 rounded-xl border border-white/80 bg-white/85 p-6 shadow-soft md:grid-cols-2 dark:border-white/10 dark:bg-white/7">
                    <div className="md:col-span-2">
                        <h2 className="flex items-center gap-2 text-xl font-black">
                            <BadgeDollarSign /> Identitas Kewajiban
                        </h2>
                        <p className="text-sm text-ink-soft">
                            Pilih transaksi penjualan agar invoice otomatis
                            masuk ke piutang customer yang benar.
                        </p>
                    </div>
                    <div className="md:col-span-2">
                        <Select
                            label="Transaksi Penjualan *"
                            value={form.data.sales_transaction_id}
                            error={form.errors.sales_transaction_id}
                            onChange={(e) =>
                                form.setData(
                                    "sales_transaction_id",
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">
                                Pilih transaksi dan customer
                            </option>
                            {transactions.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <Select
                        label="Jenis Transaksi *"
                        value={form.data.charge_type}
                        error={form.errors.charge_type}
                        onChange={(e) =>
                            form.setData("charge_type", e.target.value)
                        }
                    >
                        <option value="additional_charge">
                            Tagihan Tambahan
                        </option>
                        <option value="customer_advance">
                            Talangan Customer oleh Developer
                        </option>
                    </Select>
                    <Select
                        label="Kategori Biaya *"
                        value={form.data.category}
                        error={form.errors.category}
                        onChange={(e) =>
                            form.setData("category", e.target.value)
                        }
                    >
                        <option value="biaya_akad">Biaya Akad</option>
                        <option value="dp_ditalangi">
                            DP Ditanggung Sementara
                        </option>
                        <option value="notaris">Notaris</option>
                        <option value="administrasi">Administrasi</option>
                        <option value="kelebihan_bangunan">
                            Kelebihan Bangunan
                        </option>
                        <option value="utilitas">Utilitas</option>
                        <option value="denda">Denda</option>
                        <option value="lainnya">Lainnya</option>
                    </Select>
                    <Input
                        label="Uraian Tagihan *"
                        value={form.data.description}
                        error={form.errors.description}
                        onChange={(e) =>
                            form.setData("description", e.target.value)
                        }
                        help="Tuliskan alasan bisnis yang akan tampil pada invoice customer."
                    />
                    <CurrencyInput
                        label="Nominal *"
                        value={form.data.amount}
                        error={form.errors.amount}
                        onChange={(value) => form.setData("amount", value)}
                        required
                    />
                    <Input
                        label="Tanggal Transaksi *"
                        type="date"
                        value={form.data.charge_date}
                        error={form.errors.charge_date}
                        onChange={(e) =>
                            form.setData("charge_date", e.target.value)
                        }
                    />
                    <Input
                        label="Jatuh Tempo Pengembalian *"
                        type="date"
                        value={form.data.due_date}
                        error={form.errors.due_date}
                        onChange={(e) =>
                            form.setData("due_date", e.target.value)
                        }
                    />
                </section>
                {advance && (
                    <section className="grid gap-5 rounded-xl border border-amber-300/70 bg-amber-50/70 p-6 md:grid-cols-2 dark:border-amber-400/20 dark:bg-amber-400/8">
                        <div className="md:col-span-2">
                            <h2 className="flex items-center gap-2 text-xl font-black">
                                <Landmark /> Realisasi Dana Talangan
                            </h2>
                            <p className="text-sm text-ink-soft">
                                Wajib karena approval akan mengkredit Kas/Bank
                                developer dan membentuk Piutang Talangan
                                Customer.
                            </p>
                        </div>
                        <Select
                            label="Rekening Sumber Dana *"
                            value={form.data.master_bank_id}
                            error={form.errors.master_bank_id}
                            onChange={(e) =>
                                form.setData("master_bank_id", e.target.value)
                            }
                        >
                            <option value="">Pilih rekening developer</option>
                            {banks.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </Select>
                        <Input
                            label="Dibayarkan Kepada *"
                            value={form.data.paid_to}
                            error={form.errors.paid_to}
                            onChange={(e) =>
                                form.setData("paid_to", e.target.value)
                            }
                        />
                        <Input
                            label="Nomor Referensi Pembayaran"
                            value={form.data.payment_reference}
                            error={form.errors.payment_reference}
                            onChange={(e) =>
                                form.setData(
                                    "payment_reference",
                                    e.target.value,
                                )
                            }
                        />
                        <Input
                            label="Bukti Pembayaran *"
                            type="file"
                            accept="image/*,.pdf"
                            error={form.errors.proof}
                            onChange={(e) =>
                                form.setData(
                                    "proof",
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </section>
                )}
                <section className="rounded-xl border border-white/80 bg-white/85 p-6 dark:border-white/10 dark:bg-white/7">
                    <Textarea
                        label="Catatan dan Dasar Persetujuan"
                        value={form.data.notes}
                        error={form.errors.notes}
                        onChange={(e) => form.setData("notes", e.target.value)}
                        help="Jelaskan dasar biaya, kesepakatan customer, dan rencana pengembalian."
                    />
                </section>
                <section className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50/70 p-5 dark:border-blue-400/20 dark:bg-blue-400/8">
                    <p className="flex max-w-3xl items-start gap-2 text-sm">
                        <ShieldAlert className="mt-0.5 shrink-0" size={17} />
                        <span>
                            Data disimpan sebagai draf. Invoice dan jurnal baru
                            dibuat setelah finalisasi memperoleh persetujuan
                            terakhir.
                        </span>
                    </p>
                    <Button disabled={form.processing}>
                        <Save size={16} /> Simpan Draf
                    </Button>
                </section>
            </form>
        </>
    );
}
Form.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Tagihan/Talangan Customer"}>
        {page}
    </AdminLayout>
);
