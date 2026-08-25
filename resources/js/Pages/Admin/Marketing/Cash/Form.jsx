import { Head, router, useForm } from "@inertiajs/react";
import { ArrowLeft, PlusCircle } from "lucide-react";
import { useMemo } from "react";
import {
    Button,
    Dropdown,
    Form,
    Input,
    Textarea,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;

export default function CashForm({
    title,
    baseUrl,
    actionUrl,
    sprOptions = [],
}) {
    const form = useForm({
        spr_id: "",
        tanggal_transaksi: new Date().toISOString().slice(0, 10),
        catatan: "",
    });
    const selected = useMemo(
        () =>
            sprOptions.find((item) => item.value === form.data.spr_id) ?? null,
        [form.data.spr_id, sprOptions],
    );
    const submit = (event) => {
        event.preventDefault();
        form.post(actionUrl, { onSuccess: () => router.visit(baseUrl) });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-widest text-ink-soft">
                        Marketing / Transaksi Cash
                    </p>
                    <h1 className="mt-2 text-3xl font-extrabold">{title}</h1>
                    <p className="mt-2 text-ink-soft">
                        Pilih SPR cash yang sudah disetujui. Customer, unit, dan
                        nilai transaksi dibaca otomatis.
                    </p>
                </section>
                <Form
                    title="Data Transaksi"
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
                                <PlusCircle size={16} /> Simpan Draft
                            </Button>
                        </>
                    }
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Select
                            label="SPR Cash *"
                            value={form.data.spr_id}
                            options={sprOptions}
                            error={form.errors.spr_id}
                            onChange={(value) => form.setData("spr_id", value)}
                        />
                        <Input
                            label="Tanggal Transaksi *"
                            type="date"
                            value={form.data.tanggal_transaksi}
                            error={form.errors.tanggal_transaksi}
                            onChange={(event) =>
                                form.setData(
                                    "tanggal_transaksi",
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    {selected && (
                        <div className="grid gap-3 md:grid-cols-4">
                            {[
                                ["Customer", selected.customer],
                                ["Unit", selected.unit],
                                ["Perumahan", selected.perumahan],
                                ["Harga Jual", money(selected.harga_jual)],
                                ["Booking Fee", money(selected.booking_fee)],
                                ["Uang Muka", money(selected.uang_muka)],
                                [
                                    "Sisa Sementara",
                                    money(selected.sisa_sementara),
                                ],
                            ].map(([label, value]) => (
                                <div
                                    className="rounded-lg bg-silver-soft p-4"
                                    key={label}
                                >
                                    <p className="text-xs font-bold uppercase text-ink-soft">
                                        {label}
                                    </p>
                                    <p className="mt-1 font-bold">{value}</p>
                                </div>
                            ))}
                        </div>
                    )}
                    <Textarea
                        label="Catatan"
                        value={form.data.catatan}
                        error={form.errors.catatan}
                        onChange={(event) =>
                            form.setData("catatan", event.target.value)
                        }
                    />
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
CashForm.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Transaksi Cash"}>
        {page}
    </AdminLayout>
);
