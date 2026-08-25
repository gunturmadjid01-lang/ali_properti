import { Head, Link } from "@inertiajs/react";
import AdminLayout from "../../../Layouts/AdminLayout";

export default function Dashboard({
    title,
    cards = [],
    workItems = [],
    recentLogs = [],
}) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <header className="rounded-3xl border bg-white p-6">
                    <p className="text-xs font-black uppercase tracking-widest text-gold-deep">
                        Administrasi penjualan terhubung
                    </p>
                    <h1 className="mt-2 text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-sm text-ink-soft">
                        Pantau lead perusahaan, pekerjaan Marketing, dokumen,
                        reservasi, SPR, dan SLA tugas dari data sumber.
                    </p>
                </header>
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {cards.map((card) => (
                        <Link
                            key={card.label}
                            href={card.href}
                            className="rounded-2xl border bg-white p-5 transition hover:-translate-y-1 hover:border-gold"
                        >
                            <p className="text-sm text-ink-soft">
                                {card.label}
                            </p>
                            <p className="mt-2 text-3xl font-black">
                                {card.count}
                            </p>
                            <p className="mt-3 text-xs font-bold text-gold-deep">
                                Buka data sumber →
                            </p>
                        </Link>
                    ))}
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-xl font-black">Tugas Prioritas</h2>
                        <Link
                            className="text-sm font-bold text-gold-deep"
                            href="/admin/admin-sales/tugas"
                        >
                            Semua tugas
                        </Link>
                    </div>
                    <div className="grid gap-3">
                        {workItems.map((x) => (
                            <Link
                                key={x.id}
                                href={`/admin/admin-sales/tugas/${x.id}`}
                                className="flex flex-wrap justify-between gap-3 rounded-xl border p-4"
                            >
                                <div>
                                    <b>
                                        {x.work_no} · {x.title}
                                    </b>
                                    <p className="text-sm text-ink-soft">
                                        {x.customer || "Tanpa customer"} ·{" "}
                                        {x.category}
                                    </p>
                                </div>
                                <div className="text-right text-sm">
                                    <b
                                        className={
                                            x.overdue ? "text-red-600" : ""
                                        }
                                    >
                                        {x.due_at || "Tanpa tenggat"}
                                    </b>
                                    <p>{x.status}</p>
                                </div>
                            </Link>
                        ))}
                        {!workItems.length && (
                            <p className="py-8 text-center text-ink-soft">
                                Tidak ada tugas aktif.
                            </p>
                        )}
                    </div>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="mb-4 text-xl font-black">
                        Audit Aktivitas Terbaru
                    </h2>
                    {recentLogs.map((x) => (
                        <div key={x.id} className="border-b py-3 text-sm">
                            <b>{x.event.replaceAll("_", " ")}</b> ·{" "}
                            {x.status || "-"}
                            <p className="text-ink-soft">
                                {x.user || "Sistem"} · {x.at}{" "}
                                {x.reason ? `· ${x.reason}` : ""}
                            </p>
                        </div>
                    ))}
                </section>
            </div>
        </>
    );
}
Dashboard.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Admin Sales"}>
        {page}
    </AdminLayout>
);
