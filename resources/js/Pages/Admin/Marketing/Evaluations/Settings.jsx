import { Head, Link, router, useForm } from "@inertiajs/react";
import { ArrowLeft, Lock, LockOpen, Save } from "lucide-react";
import { Button, Input, Textarea } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

function SettingRow({ row, canManage }) {
    const form = useForm({
        weight: row.weight,
        description: row.description || "",
        is_active: Boolean(row.is_active),
    });
    const locked = row.record_status === "locked";
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.put(
                    `/admin/marketing/evaluasi-marketing/pengaturan/${row.id}`,
                );
            }}
            className="grid gap-3 rounded-2xl border bg-white/85 p-5 md:grid-cols-[1fr_140px_180px] md:items-start"
        >
            <div>
                <h2 className="font-black">{row.label}</h2>
                <p className="text-xs text-ink-soft">
                    {row.metric_key} · {row.approval_status || "belum diajukan"}
                    {row.approval_total_steps
                        ? ` tahap ${row.approval_step}/${row.approval_total_steps}`
                        : ""}
                </p>
                <Textarea
                    className="mt-3"
                    value={form.data.description}
                    disabled={locked || !canManage}
                    onChange={(e) =>
                        form.setData("description", e.target.value)
                    }
                />
            </div>
            <Input
                type="number"
                min="0"
                max="100"
                step="0.01"
                label="Bobot (%)"
                value={form.data.weight}
                disabled={locked || !canManage}
                error={form.errors.weight}
                onChange={(e) => form.setData("weight", e.target.value)}
            />
            <div className="grid gap-2 pt-7">
                {canManage &&
                    (locked ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    `/admin/marketing/evaluasi-marketing/pengaturan/${row.id}/unlock`,
                                )
                            }
                        >
                            <LockOpen size={15} /> Unlock
                        </Button>
                    ) : (
                        <>
                            <Button type="submit" disabled={form.processing}>
                                <Save size={15} /> Simpan Draft
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/admin/marketing/evaluasi-marketing/pengaturan/${row.id}/lock`,
                                    )
                                }
                            >
                                <Lock size={15} /> Finalisasi
                            </Button>
                        </>
                    ))}
                {row.can_review && (
                    <>
                        <Button
                            type="button"
                            onClick={() =>
                                router.post(
                                    `/admin/marketing/evaluasi-marketing/pengaturan/${row.id}/review/approve`,
                                )
                            }
                        >
                            Setujui
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    `/admin/marketing/evaluasi-marketing/pengaturan/${row.id}/review/reject`,
                                )
                            }
                        >
                            Tolak
                        </Button>
                    </>
                )}
            </div>
        </form>
    );
}
export default function Settings({ title, rows, totalWeight, canManage }) {
    return (
        <>
            <Head title={title} />
            <div className="mx-auto grid max-w-5xl gap-5">
                <header className="rounded-3xl bg-[#171d24] p-6 text-white">
                    <p className="text-xs font-black uppercase tracking-widest text-amber-300">
                        Konfigurasi tersimpan di database
                    </p>
                    <h1 className="mt-2 text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-sm text-white/65">
                        Total bobot aktif wajib 100% sebelum konfigurasi dapat
                        difinalisasi.
                    </p>
                    <div
                        className={`mt-4 inline-flex rounded-xl px-4 py-2 text-lg font-black ${Number(totalWeight) === 100 ? "bg-emerald-500" : "bg-red-500"}`}
                    >
                        Total {Number(totalWeight)}%
                    </div>
                </header>
                {rows.map((row) => (
                    <SettingRow key={row.id} row={row} canManage={canManage} />
                ))}
                <Button
                    as={Link}
                    className="justify-self-start"
                    variant="outline"
                    href="/admin/marketing/evaluasi-marketing"
                >
                    <ArrowLeft size={16} /> Kembali
                </Button>
            </div>
        </>
    );
}
Settings.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Setting Evaluasi Marketing"}>{page}</AdminLayout>
);
