import { Head, Link, useForm } from "@inertiajs/react";
import AdminLayout from "../../../Layouts/AdminLayout";

export default function Review({
    title,
    kind,
    backUrl,
    submitUrl,
    statusOptions = [],
    currentReview = {},
    sections = [],
    evidence = [],
    mapUrl,
    logs = [],
}) {
    const form = useForm({
        status: currentReview.status || "",
        note: currentReview.note || "",
    });
    const submit = (event) => {
        event.preventDefault();
        form.post(submitUrl, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="rounded-3xl border bg-white p-6">
                    <Link
                        href={backUrl}
                        className="text-sm font-bold text-gold-deep"
                    >
                        ← Kembali ke antrean
                    </Link>
                    <p className="mt-5 text-xs font-black uppercase tracking-widest text-gold-deep">
                        Pemeriksaan administrasi {kind}
                    </p>
                    <h1 className="mt-2 text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-sm text-ink-soft">
                        Data di bawah merupakan laporan asli Marketing.
                        Pemeriksaan hanya menambahkan keputusan dan catatan
                        audit.
                    </p>
                </header>

                {sections.map((section) => (
                    <section
                        key={section.title}
                        className="rounded-2xl border bg-white p-5"
                    >
                        <h2 className="mb-4 text-xl font-black">
                            {section.title}
                        </h2>
                        <dl className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {section.items.map((item) => (
                                <div
                                    key={item.label}
                                    className="rounded-xl bg-silver-soft p-4"
                                >
                                    <dt className="text-xs font-bold uppercase tracking-wide text-ink-soft">
                                        {item.label}
                                    </dt>
                                    <dd className="mt-1 whitespace-pre-wrap font-semibold">
                                        {item.value || "-"}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                ))}

                {(evidence.length > 0 || mapUrl) && (
                    <section className="rounded-2xl border bg-white p-5">
                        <h2 className="mb-4 text-xl font-black">
                            Bukti dan Lokasi
                        </h2>
                        <div className="flex flex-wrap gap-3">
                            {evidence.map((item) => (
                                <a
                                    key={item.label}
                                    href={item.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-xl border px-4 py-3 font-bold text-gold-deep"
                                >
                                    Buka {item.label}
                                </a>
                            ))}
                            {mapUrl && (
                                <a
                                    href={mapUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-xl border px-4 py-3 font-bold text-gold-deep"
                                >
                                    Buka Lokasi GPS
                                </a>
                            )}
                        </div>
                    </section>
                )}

                <form
                    onSubmit={submit}
                    className="rounded-2xl border bg-white p-5"
                >
                    <h2 className="text-xl font-black">
                        Keputusan Pemeriksaan
                    </h2>
                    {currentReview.reviewed_at && (
                        <p className="mt-1 text-sm text-ink-soft">
                            Terakhir diperiksa {currentReview.reviewed_at}
                        </p>
                    )}
                    <div className="mt-5 grid gap-5">
                        <div className="grid gap-2 sm:grid-cols-2">
                            {statusOptions.map((option) => (
                                <label
                                    key={option.value}
                                    className={`cursor-pointer rounded-xl border p-4 ${form.data.status === option.value ? "border-gold bg-gold/10" : ""}`}
                                >
                                    <input
                                        type="radio"
                                        name="status"
                                        value={option.value}
                                        checked={
                                            form.data.status === option.value
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                "status",
                                                event.target.value,
                                            )
                                        }
                                        className="mr-3"
                                    />
                                    <b>{option.label}</b>
                                </label>
                            ))}
                        </div>
                        {form.errors.status && (
                            <p className="text-sm font-bold text-red-600">
                                {form.errors.status}
                            </p>
                        )}
                        <label className="grid gap-2">
                            <span className="font-bold">
                                Catatan pemeriksaan dan arahan perbaikan
                            </span>
                            <textarea
                                rows="6"
                                value={form.data.note}
                                onChange={(event) =>
                                    form.setData("note", event.target.value)
                                }
                                className="rounded-xl border p-3"
                                placeholder="Jelaskan hasil pemeriksaan secara spesifik..."
                            />
                        </label>
                        {form.errors.note && (
                            <p className="text-sm font-bold text-red-600">
                                {form.errors.note}
                            </p>
                        )}
                        <button
                            disabled={form.processing}
                            className="w-fit rounded-xl bg-ink px-6 py-3 font-black text-white disabled:opacity-50"
                        >
                            {form.processing
                                ? "Menyimpan..."
                                : "Simpan Pemeriksaan"}
                        </button>
                    </div>
                </form>

                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="mb-4 text-xl font-black">Riwayat Audit</h2>
                    <div className="grid gap-3">
                        {logs.map((log) => (
                            <div
                                key={log.id}
                                className="rounded-xl border p-4 text-sm"
                            >
                                <b>
                                    {log.event?.replaceAll("_", " ")} ·{" "}
                                    {log.status || "-"}
                                </b>
                                <p className="mt-1 text-ink-soft">
                                    {log.user || "Sistem"} · {log.at}
                                    {log.note ? ` · ${log.note}` : ""}
                                </p>
                            </div>
                        ))}
                        {!logs.length && (
                            <p className="py-5 text-center text-ink-soft">
                                Belum ada riwayat pemeriksaan.
                            </p>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

Review.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Pemeriksaan Admin Sales"}>
        {page}
    </AdminLayout>
);
