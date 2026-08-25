import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Show({
    title,
    lead,
    assignments = [],
    logs = [],
    options = {},
}) {
    const [verify, setVerify] = useState({ status: "verified", note: "" });
    const [assign, setAssign] = useState({
        marketing_id: "",
        response_hours: 2,
        reason: "",
    });
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <section className="rounded-3xl border bg-white p-6">
                    <p className="font-bold text-gold-deep">{lead.code}</p>
                    <h1 className="mt-2 text-3xl font-black">{lead.name}</h1>
                    <div className="mt-5 grid gap-3 md:grid-cols-4">
                        <p>
                            <b>Kontak</b>
                            <br />
                            {lead.phone}
                            <br />
                            {lead.email || "-"}
                        </p>
                        <p>
                            <b>Sumber</b>
                            <br />
                            {lead.source}
                            <br />
                            {lead.channel}
                        </p>
                        <p>
                            <b>Verifikasi</b>
                            <br />
                            {lead.verification_status}
                        </p>
                        <p>
                            <b>Assignment</b>
                            <br />
                            {lead.assignment_status}
                            <br />
                            {lead.marketing || "Belum dibagi"}
                        </p>
                    </div>
                </section>
                {lead.verification_status !== "verified" && (
                    <form
                        className="grid gap-3 rounded-2xl border bg-white p-5 md:grid-cols-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.post(
                                `/admin/admin-sales/lead/${lead.id}/verify`,
                                verify,
                            );
                        }}
                    >
                        <h2 className="text-xl font-black md:col-span-3">
                            Verifikasi Lead
                        </h2>
                        <select
                            className="rounded-xl border p-3"
                            value={verify.status}
                            onChange={(e) =>
                                setVerify({ ...verify, status: e.target.value })
                            }
                        >
                            {[
                                "verified",
                                "duplicate",
                                "spam",
                                "needs_revision",
                            ].map((x) => (
                                <option key={x}>{x}</option>
                            ))}
                        </select>
                        <textarea
                            className="rounded-xl border p-3"
                            placeholder="Catatan wajib"
                            value={verify.note}
                            onChange={(e) =>
                                setVerify({ ...verify, note: e.target.value })
                            }
                        ></textarea>
                        <button className="rounded-xl bg-ink p-3 font-bold text-white">
                            Simpan Verifikasi
                        </button>
                    </form>
                )}
                {lead.verification_status === "verified" &&
                    lead.assignment_status !== "responded" && (
                        <form
                            className="grid gap-3 rounded-2xl border bg-white p-5 md:grid-cols-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.post(
                                    `/admin/admin-sales/leads/${lead.id}/assign`,
                                    assign,
                                );
                            }}
                        >
                            <h2 className="text-xl font-black md:col-span-4">
                                Bagikan ke Marketing
                            </h2>
                            <select
                                className="rounded-xl border p-3"
                                value={assign.marketing_id}
                                onChange={(e) =>
                                    setAssign({
                                        ...assign,
                                        marketing_id: e.target.value,
                                    })
                                }
                            >
                                <option value="">Pilih Marketing</option>
                                {(options.marketings || []).map((x) => (
                                    <option key={x.value} value={x.value}>
                                        {x.label}
                                    </option>
                                ))}
                            </select>
                            <input
                                type="number"
                                min="1"
                                max="72"
                                className="rounded-xl border p-3"
                                value={assign.response_hours}
                                onChange={(e) =>
                                    setAssign({
                                        ...assign,
                                        response_hours: e.target.value,
                                    })
                                }
                            />
                            <textarea
                                className="rounded-xl border p-3"
                                placeholder="Alasan pembagian"
                                value={assign.reason}
                                onChange={(e) =>
                                    setAssign({
                                        ...assign,
                                        reason: e.target.value,
                                    })
                                }
                            ></textarea>
                            <button className="rounded-xl bg-ink p-3 font-bold text-white">
                                Bagikan Lead
                            </button>
                        </form>
                    )}
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="text-xl font-black">Riwayat Pembagian</h2>
                    {assignments.map((x) => (
                        <div key={x.id} className="border-b py-3 text-sm">
                            <b>
                                {x.from_marketing?.name || "Belum dibagi"} →{" "}
                                {x.to_marketing?.name}
                            </b>
                            <p>
                                {x.status} · {x.reason} · {x.assigned_at}
                            </p>
                        </div>
                    ))}
                    {!assignments.length && (
                        <p className="py-5 text-ink-soft">
                            Belum pernah dibagikan.
                        </p>
                    )}
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="text-xl font-black">Audit Lead</h2>
                    {logs.map((x) => (
                        <div key={x.id} className="border-b py-3 text-sm">
                            <b>{x.event?.replaceAll("_", " ")}</b>
                            <p>
                                {x.user?.name || "Sistem"} ·{" "}
                                {x.old_status || "-"} → {x.new_status || "-"} ·{" "}
                                {x.reason || "-"}
                            </p>
                        </div>
                    ))}
                </section>
            </div>
        </>
    );
}
Show.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail Lead"}>
        {page}
    </AdminLayout>
);
