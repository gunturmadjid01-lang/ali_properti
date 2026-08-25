import { Head, Link } from "@inertiajs/react";
import AdminLayout from "../../../Layouts/AdminLayout";

const queues = {
    "lead-unverified": "Lead Belum Diverifikasi",
    "lead-unassigned": "Lead Belum Dibagikan",
    "response-overdue": "Respons Terlambat",
    "followup-review": "Pemeriksaan Follow-up",
    "followup-revision": "Follow-up Perlu Revisi",
    "visit-review": "Pemeriksaan Kunjungan",
    "visit-revision": "Kunjungan Perlu Revisi",
};
export default function Monitoring({ title, queue, rows }) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="rounded-3xl border bg-white p-6">
                    <h1 className="text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-ink-soft">
                        Pemeriksaan hanya menambahkan catatan administrasi dan
                        audit; isi aktivitas Marketing tidak diubah.
                    </p>
                </header>
                <nav className="flex flex-wrap gap-2">
                    {Object.entries(queues).map(([key, label]) => (
                        <Link
                            key={key}
                            href={`/admin/admin-sales/monitoring?queue=${key}`}
                            className={`rounded-full border px-4 py-2 text-sm font-bold ${queue === key ? "bg-ink text-white" : "bg-white"}`}
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
                <section className="overflow-x-auto rounded-2xl border bg-white">
                    <table className="min-w-full text-sm">
                        <thead className="bg-silver-soft text-left">
                            <tr>
                                <th className="p-4">Data</th>
                                <th className="p-4">PIC</th>
                                <th className="p-4">Status / Waktu</th>
                                <th className="p-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.data.map((x) => (
                                <tr key={x.id}>
                                    <td className="p-4">
                                        <b>
                                            {x.name ||
                                                x.lead?.name ||
                                                x.nama ||
                                                x.costumer?.nama ||
                                                x.contact_name ||
                                                `Data #${x.id}`}
                                        </b>
                                        <p className="text-xs text-ink-soft">
                                            {x.lead_no ||
                                                x.lead?.lead_no ||
                                                x.kode_costumer ||
                                                x.costumer?.kode_costumer ||
                                                x.catatan ||
                                                x.result ||
                                                x.objective}
                                        </p>
                                    </td>
                                    <td className="p-4">
                                        {x.assigned_marketing?.name ||
                                            x.user?.name ||
                                            x.marketing?.name ||
                                            "Belum ditugaskan"}
                                    </td>
                                    <td className="p-4">
                                        {x.verification_status ||
                                            x.lead_verification_status ||
                                            x.admin_review_status ||
                                            x.status_lead ||
                                            x.status}
                                        <br />
                                        <span className="text-xs">
                                            {x.first_response_due_at ||
                                                x.followed_up_at ||
                                                x.finished_at}
                                        </span>
                                    </td>
                                    <td className="p-4">
                                        {queue === "lead-unverified" ? (
                                            <Link
                                                className="rounded-lg border px-3 py-2 font-bold"
                                                href={`/admin/admin-sales/lead/${x.id}/verify`}
                                            >
                                                Verifikasi
                                            </Link>
                                        ) : queue === "followup-review" ||
                                          queue === "visit-review" ? (
                                            <Link
                                                className="rounded-lg border px-3 py-2 font-bold"
                                                href={`/admin/admin-sales/review/${queue === "followup-review" ? "follow-up" : "visit"}/${x.id}`}
                                            >
                                                Periksa
                                            </Link>
                                        ) : queue.endsWith("-revision") ? (
                                            <Link
                                                className="rounded-lg border px-3 py-2 font-bold"
                                                href={
                                                    queue ===
                                                    "followup-revision"
                                                        ? `/admin/marketing/jejak-follow-up/${x.id}`
                                                        : `/admin/marketing/crm/visits/${x.id}`
                                                }
                                            >
                                                Buka data revisi
                                            </Link>
                                        ) : (
                                            <Link
                                                className="rounded-lg border px-3 py-2 font-bold"
                                                href={`/admin/marketing/leads/${x.id}`}
                                            >
                                                Lihat Lead
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {!rows.data.length && (
                                <tr>
                                    <td
                                        colSpan="4"
                                        className="p-10 text-center text-ink-soft"
                                    >
                                        Tidak ada data pada antrean ini.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}
Monitoring.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Monitoring Admin Sales"}>
        {page}
    </AdminLayout>
);
