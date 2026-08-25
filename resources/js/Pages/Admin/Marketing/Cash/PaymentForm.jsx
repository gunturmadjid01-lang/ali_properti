import { Head, router, useForm } from "@inertiajs/react";
import { ArrowLeft, CreditCard } from "lucide-react";
import {
    Button,
    Dropdown,
    Form,
    Input,
    Textarea,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;

export default function PaymentForm({
    title,
    baseUrl,
    actionUrl,
    row,
    paymentMethods = [],
}) {
    const form = useForm({
        tanggal_pembayaran: new Date().toISOString().slice(0, 10),
        nominal: "",
        metode_pembayaran: "transfer",
        keterangan: "",
        bukti_pembayaran: null,
    });
    const submit = (event) => {
        event.preventDefault();
        form.post(actionUrl, {
            forceFormData: true,
            onSuccess: () => router.visit(baseUrl),
        });
    };
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-widest text-ink-soft">
                        Marketing / Pembayaran Cash
                    </p>
                    <h1 className="mt-2 text-3xl font-extrabold">{title}</h1>
                    <div className="mt-4 grid gap-3 rounded-lg bg-silver-soft p-4 text-sm md:grid-cols-3">
                        <p>
                            <b>Customer:</b> {row.customer}
                        </p>
                        <p>
                            <b>Unit:</b> {row.unit}
                        </p>
                        <p>
                            <b>Sisa Tagihan:</b> {money(row.sisa_tagihan)}
                        </p>
                    </div>
                </section>
                <Form
                    title="Data Pembayaran"
                    onSubmit={submit}
                    actions={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => router.visit(baseUrl)}
                            >
                                <ArrowLeft size={16} /> Kembali
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                <CreditCard size={16} /> Simpan Pembayaran
                            </Button>
                        </>
                    }
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Input
                            type="date"
                            label="Tanggal Pembayaran *"
                            value={form.data.tanggal_pembayaran}
                            error={form.errors.tanggal_pembayaran}
                            onChange={(event) =>
                                form.setData(
                                    "tanggal_pembayaran",
                                    event.target.value,
                                )
                            }
                        />
                        <Input
                            type="number"
                            min="1"
                            max={row.sisa_tagihan}
                            label="Nominal *"
                            value={form.data.nominal}
                            error={form.errors.nominal}
                            onChange={(event) =>
                                form.setData("nominal", event.target.value)
                            }
                        />
                        <Select
                            label="Metode Pembayaran *"
                            value={form.data.metode_pembayaran}
                            options={paymentMethods}
                            error={form.errors.metode_pembayaran}
                            onChange={(value) =>
                                form.setData("metode_pembayaran", value)
                            }
                        />
                        <Input
                            type="file"
                            accept="image/*"
                            label="Bukti Pembayaran"
                            error={form.errors.bukti_pembayaran}
                            onChange={(event) =>
                                form.setData(
                                    "bukti_pembayaran",
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <Textarea
                            className="md:col-span-2"
                            label="Keterangan"
                            value={form.data.keterangan}
                            error={form.errors.keterangan}
                            onChange={(event) =>
                                form.setData("keterangan", event.target.value)
                            }
                        />
                    </div>
                </Form>
            </div>
        </>
    );
}

function Select({ label, value, options, onChange, error }) {
    return (
        <div className="grid gap-2">
            <span className="text-sm font-extrabold">{label}</span>
            <Dropdown value={value} options={options} onChange={onChange} />
            {error && (
                <span className="text-xs font-bold text-red-600">{error}</span>
            )}
        </div>
    );
}
PaymentForm.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Pembayaran Cash"}>
        {page}
    </AdminLayout>
);
