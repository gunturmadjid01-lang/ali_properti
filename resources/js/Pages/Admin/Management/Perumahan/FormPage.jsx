import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Building2, Info, Save, Sparkles } from "lucide-react";
import { Button } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
import FieldRenderer from "../Components/FieldRenderer";

const FormSections = ({ items }) => (
    <div className="grid gap-4">
        {items.map((item, index) => (
            <section
                className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]"
                key={item.title}
            >
                <header className="flex items-center gap-3 border-b border-silver-deep/50 bg-silver-soft/55 px-5 py-3 dark:border-white/10 dark:bg-white/[0.03]">
                    <span className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-ink text-xs font-black text-white dark:bg-white dark:text-ink">
                        {index + 1}
                    </span>
                    <h2 className="text-sm font-black text-ink dark:text-white">
                        {item.title.replace(/^\d+\.\s*/, "")}
                    </h2>
                </header>
                <div className="p-5">{item.content}</div>
            </section>
        ))}
    </div>
);

export default function FormPage({
    title,
    description,
    baseUrl,
    actionUrl,
    method,
    projectCode,
    fields,
    options,
    initialData,
}) {
    const form = useForm(initialData);
    const fieldsByName = new Map(fields.map((field) => [field.name, field]));

    const renderFields = (names) => (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {names.map((name) => {
                const field = fieldsByName.get(name);
                if (!field) return null;

                return (
                    <div
                        className={
                            field.full ||
                            ["logo", "alamat", "deskripsi"].includes(name)
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
                );
            })}
        </div>
    );

    const submit = (event) => {
        event.preventDefault();

        if (method === "put") {
            form.transform((data) => ({ ...data, _method: "put" }));
            form.post(actionUrl, {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => form.transform((data) => data),
            });
            return;
        }

        form.post(actionUrl, { forceFormData: true, preserveScroll: true });
    };

    const groups = [
        {
            title: "Identitas dan Status Proyek",
            content: renderFields([
                "cabang_id",
                "nama_perusahaan",
                "developer_name",
                "status",
                "logo",
            ]),
        },
        {
            title: "Lahan, Unit, dan Nilai Proyek",
            content: renderFields([
                "luas_lahan",
                "luas_komersial",
                "luas_fasos_fasum",
                "jumlah_unit",
                "total_blok",
                "harga_mulai",
            ]),
        },
        {
            title: "Jadwal, Legalitas, dan Marketing",
            content: renderFields([
                "tanggal_mulai",
                "tanggal_target_selesai",
                "jenis_sertifikat",
                "nomor_sertifikat_induk",
                "nama_marketing",
                "phone_marketing",
                "email_marketing",
            ]),
        },
        {
            title: "Lokasi dan Keterangan",
            content: renderFields([
                "latitude",
                "longtitude",
                "alamat",
                "deskripsi",
            ]),
        },
    ];

    return (
        <>
            <Head title={title} />
            <div className="mx-auto grid max-w-7xl gap-5 pb-8">
                <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#253039] px-6 py-5 text-white shadow-lg">
                    <div className="absolute -right-16 -top-24 h-52 w-52 rounded-full bg-gold/15 blur-2xl" />
                    <div className="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div className="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-champagne">
                                <Building2 size={15} /> Master Proyek Perumahan
                            </div>
                            <h1 className="mt-2 font-display text-2xl font-black md:text-3xl">
                                {title}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm text-white/65">
                                {description}
                            </p>
                            <p className="mt-3 flex items-center gap-2 text-xs font-bold text-white/75">
                                <Sparkles size={14} className="text-gold" />
                                {projectCode
                                    ? `Kode proyek: ${projectCode}`
                                    : "Kode proyek dibuat otomatis saat disimpan"}
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
                        <Info className="mt-0.5 shrink-0" size={18} /> Periksa
                        kembali kolom yang ditandai merah.
                    </div>
                )}

                <form className="grid gap-4" onSubmit={submit}>
                    <div className="flex flex-col gap-3 rounded-xl border border-silver-deep/60 bg-white/80 px-4 py-3 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-xs font-bold text-ink-soft dark:text-white/55">
                            Kolom bertanda{" "}
                            <span className="font-black text-red-600 dark:text-red-400">
                                *
                            </span>{" "}
                            wajib diisi.
                        </p>
                        <span className="shrink-0 rounded-full bg-silver-soft px-3 py-1 text-xs font-black dark:bg-white/10">
                            {method === "put" ? "Mode Ubah" : "Data Baru"}
                        </span>
                    </div>

                    <FormSections items={groups} />

                    <div className="flex flex-wrap justify-end gap-3 rounded-xl border border-silver-deep/60 bg-white/85 p-4 shadow-sm dark:border-white/10 dark:bg-graphite">
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
                            {form.processing
                                ? "Menyimpan..."
                                : method === "put"
                                  ? "Simpan Perubahan"
                                  : "Simpan Perumahan"}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

FormPage.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Perumahan"}>
        {page}
    </AdminLayout>
);
