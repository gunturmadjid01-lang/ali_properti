import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, Lock, LockOpen, Pencil } from "lucide-react";
import { Button } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Show({ title, row, canManage }) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <header className="rounded-3xl bg-[#171d24] p-6 text-white">
                    <p className="text-xs font-black uppercase tracking-widest text-amber-300">
                        {row.evaluation_no}
                    </p>
                    <div className="mt-2 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-black">
                                {row.marketing}
                            </h1>
                            <p className="mt-2 text-white/65">
                                {row.period_start} – {row.period_end} ·{" "}
                                {row.perumahan || "Semua perumahan"}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-5xl font-black text-amber-300">
                                {row.total_score}
                            </p>
                            <p className="uppercase">
                                {row.rating?.replaceAll("_", " ")}
                            </p>
                        </div>
                    </div>
                </header>
                <section className="grid gap-4 md:grid-cols-3">
                    {row.details.map((d) => (
                        <article
                            key={d.metric_key}
                            className="rounded-2xl border bg-white/85 p-5"
                        >
                            <div className="flex justify-between">
                                <b>{d.label}</b>
                                <span className="font-black">
                                    {d.score}/{d.weight}
                                </span>
                            </div>
                            <div className="mt-3 h-2 overflow-hidden rounded-full bg-silver-soft">
                                <div
                                    className="h-full bg-gold"
                                    style={{
                                        width: `${Math.min(100, d.achievement)}%`,
                                    }}
                                />
                            </div>
                            <p className="mt-2 text-xs text-ink-soft">
                                Capaian {d.achievement}% · sumber:{" "}
                                {Object.entries(d.evidence || {})
                                    .map(
                                        ([k, v]) =>
                                            `${k.replaceAll("_", " ")} ${v}`,
                                    )
                                    .join(", ") || "-"}
                            </p>
                        </article>
                    ))}
                </section>
                <section className="grid gap-4 rounded-2xl border bg-white/85 p-6 md:grid-cols-2">
                    <div>
                        <b>Catatan Manager</b>
                        <p className="mt-2 whitespace-pre-wrap text-sm text-ink-soft">
                            {row.manager_note || "-"}
                        </p>
                    </div>
                    <div>
                        <b>Rencana Coaching</b>
                        <p className="mt-2 whitespace-pre-wrap text-sm text-ink-soft">
                            {row.coaching_plan || "-"}
                        </p>
                    </div>
                </section>
                <div className="flex flex-wrap justify-between gap-3">
                    <Button
                        as={Link}
                        href="/admin/marketing/evaluasi-marketing"
                        variant="outline"
                    >
                        <ArrowLeft size={16} /> Kembali
                    </Button>
                    {canManage && (
                        <div className="flex gap-2">
                            {row.record_status === "draft" ? (
                                <>
                                    <Button
                                        as={Link}
                                        variant="outline"
                                        href={`/admin/marketing/evaluasi-marketing/${row.id}/edit`}
                                    >
                                        <Pencil size={16} /> Edit
                                    </Button>
                                    <Button
                                        onClick={() =>
                                            router.post(
                                                `/admin/marketing/evaluasi-marketing/${row.id}/lock`,
                                            )
                                        }
                                    >
                                        <Lock size={16} /> Finalisasi
                                    </Button>
                                </>
                            ) : (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            `/admin/marketing/evaluasi-marketing/${row.id}/unlock`,
                                        )
                                    }
                                >
                                    <LockOpen size={16} /> Unlock
                                </Button>
                            )}
                        </div>
                    )}
                    {row.can_review && (
                        <div className="flex gap-2">
                            <Button
                                onClick={() =>
                                    router.post(
                                        `/admin/marketing/evaluasi-marketing/${row.id}/review/approve`,
                                    )
                                }
                            >
                                Setujui Tahap
                            </Button>
                            <Button
                                variant="danger"
                                onClick={() =>
                                    router.post(
                                        `/admin/marketing/evaluasi-marketing/${row.id}/review/reject`,
                                    )
                                }
                            >
                                Tolak
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
Show.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail Evaluasi Marketing"}>{page}</AdminLayout>
);
