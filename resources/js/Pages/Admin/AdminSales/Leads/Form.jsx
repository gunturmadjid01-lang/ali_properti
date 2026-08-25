import { Head, useForm } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Form({ title, options = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        nama: "",
        telepon: "",
        email: "",
        marketing_lead_source_id: "",
        lead_source_channel: "website",
        perumahan_id: "",
        lead_priority: "normal",
        preferred_payment_method: "",
        keterangan: "",
    });
    const input = (name, label, type = "text") => (
        <label className="grid gap-1">
            <b className="text-sm">{label}</b>
            <input
                type={type}
                className="rounded-xl border p-3"
                value={data[name]}
                onChange={(e) => setData(name, e.target.value)}
            />
            <small className="text-red-600">{errors[name]}</small>
        </label>
    );
    return (
        <>
            <Head title={title} />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post("/admin/admin-sales/leads");
                }}
                className="mx-auto grid max-w-3xl gap-5 rounded-3xl border bg-white p-6"
            >
                <div>
                    <h1 className="text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-ink-soft">
                        Cukup masukkan data awal yang benar. Identitas lengkap
                        dapat dilengkapi setelah lead terverifikasi.
                    </p>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    {input("nama", "Nama calon customer")}
                    {input("telepon", "Nomor telepon")}
                    {input("email", "Email", "email")}
                    <label className="grid gap-1">
                        <b className="text-sm">Sumber lead</b>
                        <select
                            className="rounded-xl border p-3"
                            value={data.marketing_lead_source_id}
                            onChange={(e) =>
                                setData(
                                    "marketing_lead_source_id",
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">Pilih sumber</option>
                            {(options.sources || []).map((x) => (
                                <option key={x.value} value={x.value}>
                                    {x.label}
                                </option>
                            ))}
                        </select>
                        <small className="text-red-600">
                            {errors.marketing_lead_source_id}
                        </small>
                    </label>
                    <label className="grid gap-1">
                        <b className="text-sm">Kanal</b>
                        <select
                            className="rounded-xl border p-3"
                            value={data.lead_source_channel}
                            onChange={(e) =>
                                setData("lead_source_channel", e.target.value)
                            }
                        >
                            {[
                                "website",
                                "whatsapp_company",
                                "office_phone",
                                "walk_in",
                                "company_social",
                                "ads",
                                "exhibition",
                                "company_referral",
                                "other",
                            ].map((x) => (
                                <option key={x}>{x}</option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1">
                        <b className="text-sm">Perumahan</b>
                        <select
                            className="rounded-xl border p-3"
                            value={data.perumahan_id}
                            onChange={(e) =>
                                setData("perumahan_id", e.target.value)
                            }
                        >
                            <option value="">Belum ditentukan</option>
                            {(options.perumahans || []).map((x) => (
                                <option key={x.value} value={x.value}>
                                    {x.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1">
                        <b className="text-sm">Prioritas</b>
                        <select
                            className="rounded-xl border p-3"
                            value={data.lead_priority}
                            onChange={(e) =>
                                setData("lead_priority", e.target.value)
                            }
                        >
                            {["low", "normal", "high", "urgent"].map((x) => (
                                <option key={x}>{x}</option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1">
                        <b className="text-sm">Minat pembayaran</b>
                        <select
                            className="rounded-xl border p-3"
                            value={data.preferred_payment_method}
                            onChange={(e) =>
                                setData(
                                    "preferred_payment_method",
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">Belum ditentukan</option>
                            <option value="cash">Cash</option>
                            <option value="cash_installment">
                                Cash Bertahap
                            </option>
                            <option value="kpr">KPR</option>
                        </select>
                    </label>
                </div>
                <label className="grid gap-1">
                    <b className="text-sm">Catatan awal</b>
                    <textarea
                        className="min-h-28 rounded-xl border p-3"
                        value={data.keterangan}
                        onChange={(e) => setData("keterangan", e.target.value)}
                    />
                </label>
                <button
                    disabled={processing}
                    className="rounded-xl bg-ink p-3 font-bold text-white"
                >
                    Simpan Lead Perusahaan
                </button>
            </form>
        </>
    );
}
Form.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Lead"}>{page}</AdminLayout>
);
