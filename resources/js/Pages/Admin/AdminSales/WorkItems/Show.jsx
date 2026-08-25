import { Head, useForm } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";
export default function Show({ title, item, logs = [] }) {
    const form = useForm({
        status: item.status,
        resolution_note: item.resolution_note || "",
    });
    const update = (event) => {
        event.preventDefault();
        form.post(`/admin/admin-sales/tugas/${item.id}/status`, {
            preserveScroll: true,
        });
    };
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <section className="rounded-3xl border bg-white p-6">
                    <p className="font-bold text-gold-deep">{item.work_no}</p>
                    <h1 className="mt-2 text-3xl font-black">{item.title}</h1>
                    <p className="mt-3 text-ink-soft">
                        {item.description || "Tanpa deskripsi"}
                    </p>
                    <div className="mt-5 grid gap-3 md:grid-cols-4">
                        <p>
                            <b>Customer</b>
                            <br />
                            {item.customer || "-"}
                        </p>
                        <p>
                            <b>PIC</b>
                            <br />
                            {item.assignee || "-"}
                        </p>
                        <p>
                            <b>Tenggat</b>
                            <br />
                            {item.due_at || "-"}
                        </p>
                        <p>
                            <b>Status</b>
                            <br />
                            {item.status}
                        </p>
                    </div>
                </section>
                <form
                    onSubmit={update}
                    className="rounded-2xl border bg-white p-5"
                >
                    <h2 className="text-xl font-black">
                        Perbarui Status Tugas
                    </h2>
                    <div className="mt-4 grid gap-4">
                        <label className="grid gap-2">
                            <span className="font-bold">Status</span>
                            <select
                                className="rounded-xl border p-3"
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData("status", event.target.value)
                                }
                            >
                                <option value="open">Terbuka</option>
                                <option value="in_progress">
                                    Sedang Dikerjakan
                                </option>
                                <option value="waiting">Menunggu</option>
                                <option value="completed">Selesai</option>
                                <option value="cancelled">Dibatalkan</option>
                            </select>
                        </label>
                        {form.errors.status && (
                            <p className="text-sm font-bold text-red-600">
                                {form.errors.status}
                            </p>
                        )}
                        <label className="grid gap-2">
                            <span className="font-bold">
                                Catatan penyelesaian atau hambatan
                            </span>
                            <textarea
                                rows="4"
                                className="rounded-xl border p-3"
                                value={form.data.resolution_note}
                                onChange={(event) =>
                                    form.setData(
                                        "resolution_note",
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                        {form.errors.resolution_note && (
                            <p className="text-sm font-bold text-red-600">
                                {form.errors.resolution_note}
                            </p>
                        )}
                        <button
                            disabled={form.processing}
                            className="w-fit rounded-xl bg-ink px-5 py-3 font-bold text-white disabled:opacity-50"
                        >
                            {form.processing ? "Menyimpan..." : "Simpan Status"}
                        </button>
                    </div>
                </form>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="text-xl font-black">Riwayat Audit</h2>
                    {logs.map((x) => (
                        <div key={x.id} className="border-b py-3 text-sm">
                            <b>{x.event?.replaceAll("_", " ")}</b>:{" "}
                            {x.old_status || "-"} → {x.new_status || "-"}
                            <p className="text-ink-soft">
                                {x.user?.name || "Sistem"} ·{" "}
                                {x.reason || "Tanpa catatan"}
                            </p>
                        </div>
                    ))}
                </section>
            </div>
        </>
    );
}
Show.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail Tugas"}>
        {page}
    </AdminLayout>
);
