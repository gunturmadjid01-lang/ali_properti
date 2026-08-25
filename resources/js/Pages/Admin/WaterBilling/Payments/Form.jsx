import { Head, router, useForm } from "@inertiajs/react";
import { ArrowLeft, Save } from "lucide-react";
import { Button, Dropdown, Input, Textarea } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
export default function Form({ title, actionUrl, housing, periods, owners }) {
    const form = useForm({
        perumahan_id: "",
        water_billing_period_id: "",
        unit_ownership_id: "",
        payment_date: new Date().toISOString().slice(0, 10),
        amount: "",
        payment_method: "transfer",
        reference_no: "",
        proof: null,
        notes: "",
    });
    const periodOptions = periods.filter(
        (x) => x.perumahan_id === form.data.perumahan_id,
    );
    const ownerOptions = owners.filter(
        (x) => x.perumahan_id === form.data.perumahan_id,
    );
    const choosePeriod = (v) => {
        const item = periods.find((x) => x.value === v);
        form.setData({
            ...form.data,
            water_billing_period_id: v,
            amount: item?.amount ?? form.data.amount,
        });
    };
    const submit = (e) => {
        e.preventDefault();
        form.post(actionUrl, { forceFormData: true });
    };
    return (
        <>
            <Head title={title} />
            <section className="rounded-xl border bg-white/85 p-6 shadow-soft dark:bg-white/7">
                <p className="text-xs font-black uppercase tracking-widest text-ink-soft">
                    Keuangan / Pembayaran Air
                </p>
                <h1 className="mt-2 text-3xl font-black">{title}</h1>
                <p className="mt-2 text-sm text-ink-soft">
                    Pilih perumahan, periode aktif, lalu pemilik unit yang
                    melakukan pembayaran.
                </p>
                <form
                    className="mt-6 grid gap-5 md:grid-cols-2"
                    onSubmit={submit}
                >
                    <label className="grid gap-2 text-sm font-bold">
                        <span>Perumahan</span>
                        <Dropdown
                            value={form.data.perumahan_id}
                            options={housing}
                            onChange={(v) =>
                                form.setData({
                                    ...form.data,
                                    perumahan_id: v,
                                    water_billing_period_id: "",
                                    unit_ownership_id: "",
                                    amount: "",
                                })
                            }
                        />
                        <small className="text-red-600">
                            {form.errors.perumahan_id}
                        </small>
                    </label>
                    <label className="grid gap-2 text-sm font-bold">
                        <span>Periode Tagihan</span>
                        <Dropdown
                            value={form.data.water_billing_period_id}
                            options={periodOptions}
                            disabled={!form.data.perumahan_id}
                            onChange={choosePeriod}
                        />
                        <small className="text-red-600">
                            {form.errors.water_billing_period_id}
                        </small>
                    </label>
                    <label className="grid gap-2 text-sm font-bold md:col-span-2">
                        <span>Pemilik Unit</span>
                        <Dropdown
                            value={form.data.unit_ownership_id}
                            options={ownerOptions}
                            disabled={!form.data.perumahan_id}
                            onChange={(v) =>
                                form.setData("unit_ownership_id", v)
                            }
                        />
                        <small className="text-red-600">
                            {form.errors.unit_ownership_id}
                        </small>
                    </label>
                    <Input
                        label="Tanggal Pembayaran"
                        type="date"
                        value={form.data.payment_date}
                        error={form.errors.payment_date}
                        onChange={(e) =>
                            form.setData("payment_date", e.target.value)
                        }
                    />
                    <Input
                        label="Nominal Dibayar (Rp)"
                        type="number"
                        value={form.data.amount}
                        error={form.errors.amount}
                        onChange={(e) => form.setData("amount", e.target.value)}
                    />
                    <label className="grid gap-2 text-sm font-bold">
                        <span>Metode Pembayaran</span>
                        <Dropdown
                            value={form.data.payment_method}
                            options={[
                                { value: "transfer", label: "Transfer Bank" },
                                { value: "cash", label: "Tunai" },
                                { value: "qris", label: "QRIS" },
                            ]}
                            onChange={(v) => form.setData("payment_method", v)}
                        />
                    </label>
                    <Input
                        label="Nomor Referensi"
                        value={form.data.reference_no}
                        error={form.errors.reference_no}
                        onChange={(e) =>
                            form.setData("reference_no", e.target.value)
                        }
                    />
                    <label className="grid gap-2 text-sm font-bold">
                        <span>Bukti Pembayaran</span>
                        <input
                            className="rounded-lg border p-3"
                            type="file"
                            accept=".jpg,.jpeg,.png,.pdf,.webp"
                            onChange={(e) =>
                                form.setData(
                                    "proof",
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <small className="text-red-600">
                            {form.errors.proof}
                        </small>
                    </label>
                    <Textarea
                        label="Catatan"
                        value={form.data.notes}
                        error={form.errors.notes}
                        onChange={(e) => form.setData("notes", e.target.value)}
                    />
                    <div className="flex justify-end gap-2 border-t pt-5 md:col-span-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.visit("/admin/pembayaran-air")
                            }
                        >
                            <ArrowLeft size={16} /> Kembali
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <Save size={16} /> Simpan Pembayaran
                        </Button>
                    </div>
                </form>
            </section>
        </>
    );
}
Form.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Pembayaran Air"}>
        {page}
    </AdminLayout>
);
