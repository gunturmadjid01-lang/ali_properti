import { Head, router, useForm } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2 } from "lucide-react";
import {
    Button,
    Dropdown,
    Form,
    Input,
    Textarea,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function ResultForm({
    title,
    baseUrl,
    actionUrl,
    row = {},
    statusOptions = [],
}) {
    const form = useForm({
        status: row.status ?? "dijadwalkan",
        tanggal_survey: row.tanggal_survey ?? "",
        hasil_survey: row.hasil_survey ?? "",
        catatan: row.catatan ?? "",
        rencana_follow_up_at: row.rencana_follow_up_at ?? "",
    });
    const submit = (event) => {
        event.preventDefault();
        form.put(actionUrl, { onSuccess: () => router.visit(baseUrl) });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-widest text-ink-soft">
                        Marketing / Hasil Survey Unit
                    </p>
                    <h1 className="mt-2 text-3xl font-extrabold">{title}</h1>
                    <div className="mt-4 grid gap-3 rounded-lg bg-silver-soft p-4 text-sm md:grid-cols-2">
                        <p>
                            <b>Customer:</b> {row.customer} ({row.kode_customer}
                            )
                        </p>
                        <p>
                            <b>Telepon:</b> {row.telepon}
                        </p>
                        <p>
                            <b>Jadwal:</b> {row.tanggal_survey_display}
                        </p>
                        <p>
                            <b>Lokasi:</b> {row.location}
                        </p>
                    </div>
                </section>
                <Form
                    title="Status dan Hasil Survey"
                    description="Catat hasil aktual, keberatan customer, dan jadwal tindak lanjut."
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
                                <CheckCircle2 size={16} /> Simpan Hasil
                            </Button>
                        </>
                    }
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Select
                            label="Status Survey *"
                            value={form.data.status}
                            options={statusOptions}
                            error={form.errors.status}
                            onChange={(value) => form.setData("status", value)}
                        />
                        {form.data.status === "reschedule" && (
                            <Input
                                type="datetime-local"
                                label="Jadwal Survey Baru *"
                                value={form.data.tanggal_survey}
                                error={form.errors.tanggal_survey}
                                onChange={(event) =>
                                    form.setData(
                                        "tanggal_survey",
                                        event.target.value,
                                    )
                                }
                            />
                        )}
                        <Textarea
                            className="md:col-span-2"
                            label="Hasil Survey *"
                            value={form.data.hasil_survey}
                            error={form.errors.hasil_survey}
                            onChange={(event) =>
                                form.setData("hasil_survey", event.target.value)
                            }
                        />
                        <Textarea
                            className="md:col-span-2"
                            label="Minat, Keberatan, dan Kebutuhan Customer"
                            value={form.data.catatan}
                            error={form.errors.catatan}
                            onChange={(event) =>
                                form.setData("catatan", event.target.value)
                            }
                        />
                        <Input
                            type="datetime-local"
                            label="Tindak Lanjut Berikutnya"
                            value={form.data.rencana_follow_up_at}
                            error={form.errors.rencana_follow_up_at}
                            onChange={(event) =>
                                form.setData(
                                    "rencana_follow_up_at",
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                </Form>
            </div>
        </>
    );
}

function Select({ label, value, options, onChange, error }) {
    return (
        <div className="grid gap-2">
            <span className="text-sm font-extrabold">{label}</span>
            <Dropdown
                value={String(value ?? "")}
                options={options}
                onChange={onChange}
            />
            {error && (
                <span className="text-xs font-bold text-red-600">{error}</span>
            )}
        </div>
    );
}

ResultForm.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Hasil Survey"}>
        {page}
    </AdminLayout>
);
