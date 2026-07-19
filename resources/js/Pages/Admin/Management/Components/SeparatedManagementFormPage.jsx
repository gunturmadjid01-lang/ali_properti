import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, ClipboardList, Info, Save, Sparkles } from "lucide-react";
import { Button } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
import FieldRenderer from "./FieldRenderer";
import BranchLocationMap from "../../../../Components/BranchLocationMap";

export default function SeparatedManagementFormPage({
    title,
    description,
    baseUrl,
    actionUrl,
    method,
    fields,
    options,
    initialData,
}) {
    const form = useForm(initialData);
    const hasBranchLocationMap = fields.some(
        (field) => field.name === "attendance_radius_meters",
    );
    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true, forceFormData: true };
        if (method === "put") {
            form.transform((data) => ({ ...data, _method: "put" }));
            form.post(actionUrl, {
                ...requestOptions,
                onFinish: () => form.transform((data) => data),
            });
            return;
        }
        form.post(actionUrl, requestOptions);
    };

    return (
        <>
            <Head title={title} />
            <div className="mx-auto grid max-w-6xl gap-5 pb-8">
                <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#27323b] px-6 py-5 text-white shadow-lg">
                    <div className="absolute -right-12 -top-20 h-52 w-52 rounded-full bg-gold/15 blur-2xl" />
                    <div className="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-champagne">
                                <ClipboardList size={15} /> Form Master Data
                            </p>
                            <h1 className="mt-2 font-display text-2xl font-black md:text-3xl">
                                {title}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm text-white/65">
                                {description}
                            </p>
                        </div>
                        <Button
                            as={Link}
                            href={baseUrl}
                            variant="outline"
                            className="border-white/20 bg-white/10 text-white hover:bg-white/20"
                        >
                            <ArrowLeft size={17} /> Kembali
                        </Button>
                    </div>
                </section>

                {Object.keys(form.errors).length > 0 && (
                    <div className="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                        <Info size={18} /> Periksa kembali kolom yang ditandai
                        merah.
                    </div>
                )}

                <form className="grid gap-4" onSubmit={submit}>
                    <section className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                        <header className="flex items-center justify-between gap-3 border-b border-silver-deep/50 bg-silver-soft/55 px-5 py-4 dark:border-white/10 dark:bg-white/[0.03]">
                            <div>
                                <h2 className="text-sm font-black">
                                    Informasi Utama
                                </h2>
                                <p className="mt-1 text-xs font-semibold text-ink-soft dark:text-white/50">
                                    Susun data dengan lengkap sebelum disimpan.
                                </p>
                            </div>
                            <span className="flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-black shadow-sm dark:bg-white/10">
                                <Sparkles size={13} />{" "}
                                {method === "put" ? "Mode Ubah" : "Data Baru"}
                            </span>
                        </header>
                        <div className="grid gap-4 p-5 md:grid-cols-2 lg:grid-cols-3">
                            {fields
                                .filter(
                                    (field) =>
                                        (!field.showWhen ||
                                            Boolean(
                                                form.data[field.showWhen],
                                            )) &&
                                        (!hasBranchLocationMap ||
                                            ![
                                                "latitude",
                                                "longtitude",
                                                "attendance_radius_meters",
                                            ].includes(field.name)),
                                )
                                .map((field) => (
                                    <div
                                        className={
                                            field.full ||
                                            ["textarea", "image"].includes(
                                                field.type,
                                            )
                                                ? "md:col-span-2 lg:col-span-3"
                                                : ""
                                        }
                                        key={field.name}
                                    >
                                        <FieldRenderer
                                            field={field}
                                            value={form.data[field.name]}
                                            error={form.errors[field.name]}
                                            options={options}
                                            onChange={form.setData}
                                        />
                                    </div>
                                ))}
                            {hasBranchLocationMap && (
                                <div className="md:col-span-2 lg:col-span-3">
                                    <BranchLocationMap
                                        latitude={form.data.latitude}
                                        longitude={form.data.longtitude}
                                        radius={
                                            form.data.attendance_radius_meters
                                        }
                                        errors={form.errors}
                                        onChange={form.setData}
                                    />
                                </div>
                            )}
                        </div>
                    </section>

                    <div className="flex flex-wrap justify-end gap-3 rounded-xl border border-silver-deep/60 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-graphite">
                        <Button
                            as={Link}
                            href={baseUrl}
                            type="button"
                            variant="outline"
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <Save size={17} />{" "}
                            {form.processing ? "Menyimpan..." : "Simpan Data"}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

SeparatedManagementFormPage.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Master Data"}>
        {page}
    </AdminLayout>
);
