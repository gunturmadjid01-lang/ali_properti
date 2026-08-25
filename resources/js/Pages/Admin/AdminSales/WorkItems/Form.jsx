import { Head, useForm } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";
export default function Form({ title, options = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        category: "lead",
        title: "",
        description: "",
        marketing_lead_id: "",
        costumer_id: "",
        assigned_to: "",
        priority: "medium",
        status: "open",
        due_at: "",
    });
    const field = (name, label, type = "text") => (
        <label className="grid gap-1">
            <span className="text-sm font-bold">{label}</span>
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
                className="mx-auto grid max-w-3xl gap-5 rounded-3xl border bg-white p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    post("/admin/admin-sales/tugas");
                }}
            >
                <h1 className="text-3xl font-black">{title}</h1>
                <div className="grid gap-4 md:grid-cols-2">
                    <label className="grid gap-1">
                        <span className="text-sm font-bold">Kategori</span>
                        <select
                            className="rounded-xl border p-3"
                            value={data.category}
                            onChange={(e) =>
                                setData("category", e.target.value)
                            }
                        >
                            {[
                                "lead",
                                "customer",
                                "visit",
                                "document",
                                "reservation",
                                "booking_fee",
                                "spr",
                                "kpr",
                                "payment",
                                "closing",
                                "other",
                            ].map((x) => (
                                <option key={x}>{x}</option>
                            ))}
                        </select>
                    </label>
                    {field("title", "Judul")}
                </div>
                {field("description", "Deskripsi")}
                <div className="grid gap-4 md:grid-cols-2">
                    <label className="grid gap-1">
                        <span className="text-sm font-bold">Lead / Prospek</span>
                        <select className="rounded-xl border p-3" value={data.marketing_lead_id} onChange={(e) => setData("marketing_lead_id", e.target.value)}>
                            <option value="">Tanpa Lead</option>
                            {(options.leads || []).map((x) => <option key={x.value} value={x.value}>{x.label}</option>)}
                        </select>
                    </label>
                    <label className="grid gap-1">
                        <span className="text-sm font-bold">Customer</span>
                        <select
                            className="rounded-xl border p-3"
                            value={data.costumer_id}
                            onChange={(e) =>
                                setData("costumer_id", e.target.value)
                            }
                        >
                            <option value="">Tanpa customer</option>
                            {(options.customers || []).map((x) => (
                                <option key={x.value} value={x.value}>
                                    {x.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1">
                        <span className="text-sm font-bold">
                            PIC Admin Sales
                        </span>
                        <select
                            className="rounded-xl border p-3"
                            value={data.assigned_to}
                            onChange={(e) =>
                                setData("assigned_to", e.target.value)
                            }
                        >
                            <option value="">Pilih PIC</option>
                            {(options.adminSales || []).map((x) => (
                                <option key={x.value} value={x.value}>
                                    {x.label}
                                </option>
                            ))}
                        </select>
                        <small className="text-red-600">
                            {errors.assigned_to}
                        </small>
                    </label>
                    {field("due_at", "Tenggat", "datetime-local")}
                    <label className="grid gap-1">
                        <span className="text-sm font-bold">Prioritas</span>
                        <select
                            className="rounded-xl border p-3"
                            value={data.priority}
                            onChange={(e) =>
                                setData("priority", e.target.value)
                            }
                        >
                            {["low", "medium", "high", "urgent"].map((x) => (
                                <option key={x}>{x}</option>
                            ))}
                        </select>
                    </label>
                </div>
                <button
                    disabled={processing}
                    className="rounded-xl bg-ink p-3 font-bold text-white"
                >
                    Simpan Tugas
                </button>
            </form>
        </>
    );
}
Form.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Tugas"}>{page}</AdminLayout>
);
