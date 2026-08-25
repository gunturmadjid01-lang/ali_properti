import { Head, router, useForm } from "@inertiajs/react";
import { ArrowLeft, CalendarCheck } from "lucide-react";
import { useMemo } from "react";
import { Button, Dropdown, Form, Input } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function FormPage({
    title,
    baseUrl,
    actionUrl,
    method = "post",
    row = {},
    options = {},
}) {
    const form = useForm({
        costumer_id: row.costumer_id ?? "",
        perumahan_id: row.perumahan_id ?? "",
        detail_rumah_id: row.detail_rumah_id ?? "",
        tanggal_survey: row.tanggal_survey ?? "",
        metode_survey: row.metode_survey ?? "kunjungan_lokasi",
        status: row.status ?? "dijadwalkan",
    });
    const units = useMemo(
        () =>
            (options.detailRumahs ?? []).filter(
                (unit) =>
                    !form.data.perumahan_id ||
                    unit.perumahan_id === String(form.data.perumahan_id),
            ),
        [options.detailRumahs, form.data.perumahan_id],
    );
    const submit = (event) => {
        event.preventDefault();
        form[method](actionUrl, { onSuccess: () => router.visit(baseUrl) });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-widest text-ink-soft">
                        Marketing / Jadwal Survey Unit
                    </p>
                    <h1 className="mt-2 text-3xl font-extrabold">{title}</h1>
                    <p className="mt-2 max-w-3xl text-ink-soft">
                        Halaman ini hanya membuat atau mengubah jadwal. Hasil
                        survey dicatat setelah kegiatan melalui halaman Hasil
                        Survey.
                    </p>
                </section>
                <Form
                    title="Data Jadwal Survey"
                    description="Pilih customer, unit yang akan dilihat, serta waktu kunjungan."
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
                                <CalendarCheck size={16} /> Simpan Jadwal
                            </Button>
                        </>
                    }
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Select
                            label="Customer *"
                            value={form.data.costumer_id}
                            options={options.customers ?? []}
                            error={form.errors.costumer_id}
                            onChange={(value) =>
                                form.setData("costumer_id", value)
                            }
                        />
                        {options.hidePerumahan ? (
                            <div className="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                                <b>Perumahan aktif:</b>
                                <br />
                                {options.activePerumahan || "-"}
                            </div>
                        ) : (
                            <Select
                                label="Perumahan *"
                                value={form.data.perumahan_id}
                                options={options.perumahans ?? []}
                                error={form.errors.perumahan_id}
                                onChange={(value) =>
                                    form.setData({
                                        ...form.data,
                                        perumahan_id: value,
                                        detail_rumah_id: "",
                                    })
                                }
                            />
                        )}
                        <Select
                            label="Unit yang Dilihat"
                            value={form.data.detail_rumah_id}
                            options={units}
                            error={form.errors.detail_rumah_id}
                            onChange={(_, selected) =>
                                form.setData({
                                    ...form.data,
                                    detail_rumah_id: selected?.value ?? "",
                                    perumahan_id:
                                        selected?.perumahan_id ??
                                        form.data.perumahan_id,
                                })
                            }
                        />
                        <Input
                            type="datetime-local"
                            label="Tanggal & Jam Survey *"
                            value={form.data.tanggal_survey}
                            error={form.errors.tanggal_survey}
                            onChange={(event) =>
                                form.setData(
                                    "tanggal_survey",
                                    event.target.value,
                                )
                            }
                        />
                        <Select
                            label="Metode Survey *"
                            value={form.data.metode_survey}
                            options={options.methodOptions ?? []}
                            error={form.errors.metode_survey}
                            onChange={(value) =>
                                form.setData("metode_survey", value)
                            }
                        />
                    </div>
                    <p className="rounded-lg bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900">
                        Canvassing, event, kunjungan partner, atau kunjungan ke
                        rumah customer tidak dicatat di sini. Gunakan Kunjungan
                        Customer / Canvassing.
                    </p>
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

FormPage.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Jadwal Survey"}>
        {page}
    </AdminLayout>
);
