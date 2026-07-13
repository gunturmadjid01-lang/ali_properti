import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    BriefcaseBusiness,
    Info,
    MapPinned,
    Save,
    ShieldCheck,
    UserRoundCog,
    WalletCards,
} from "lucide-react";
import { Button } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
import FieldRenderer from "../Components/FieldRenderer";

const sections = [
    {
        title: "Data Pegawai",
        description: "Identitas, jabatan, dan status kepegawaian.",
        icon: BriefcaseBusiness,
        fields: [
            "kantor_cabang_id",
            "employee_number",
            "name",
            "job_title",
            "join_date",
            "employment_type",
            "employment_status",
            "phone",
        ],
    },
    {
        title: "Akses Login",
        description: "Aktifkan hanya jika pegawai membutuhkan akses ke aplikasi.",
        icon: ShieldCheck,
        fields: ["has_login_access", "email", "password"],
    },
    {
        title: "Data Penggajian",
        description: "Informasi pajak, BPJS, dan rekening pembayaran gaji.",
        icon: WalletCards,
        fields: [
            "tax_number",
            "bpjs_health_number",
            "bpjs_employment_number",
            "payroll_bank_name",
            "payroll_bank_account",
            "payroll_bank_holder",
        ],
    },
    {
        title: "Role dan Penugasan",
        description: "Tentukan kewenangan serta area kerja pegawai.",
        icon: MapPinned,
        fields: ["role_ids", "gudang_ids", "perumahan_ids"],
    },
];

export default function FormPage({
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
    const fieldsByName = new Map(fields.map((field) => [field.name, field]));

    const renderField = (name) => {
        const source = fieldsByName.get(name);
        if (!source || (source.showWhen && !form.data[source.showWhen])) {
            return null;
        }

        const field = {
            ...source,
            required: name === "password" ? method === "post" : source.required,
            placeholder:
                name === "password" && method === "put"
                    ? "Kosongkan jika password tidak diubah"
                    : source.placeholder,
        };

        return (
            <div
                className={source.full ? "md:col-span-2 xl:col-span-3" : ""}
                key={name}
            >
                <FieldRenderer
                    field={field}
                    value={form.data[name]}
                    error={form.errors[name]}
                    options={options}
                    onChange={form.setData}
                />
            </div>
        );
    };

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true };

        if (method === "put") {
            form.put(actionUrl, requestOptions);
            return;
        }

        form.post(actionUrl, requestOptions);
    };

    return (
        <>
            <Head title={title} />
            <div className="mx-auto grid max-w-7xl gap-5 pb-8">
                <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#253039] px-6 py-5 text-white shadow-lg">
                    <div className="absolute -right-12 -top-20 h-52 w-52 rounded-full bg-gold/15 blur-2xl" />
                    <div className="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div className="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.18em] text-champagne">
                                <UserRoundCog size={16} /> Management User
                            </div>
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
                        <Info className="mt-0.5 shrink-0" size={18} />
                        Periksa kembali kolom yang ditandai merah.
                    </div>
                )}

                <form className="grid gap-4" onSubmit={submit}>
                    <div className="flex flex-col gap-2 rounded-xl border border-silver-deep/60 bg-white/80 px-4 py-3 shadow-sm dark:border-white/10 dark:bg-white/[0.04] sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-xs font-bold text-ink-soft dark:text-white/55">
                            Kolom bertanda <span className="font-black text-red-600">*</span> wajib diisi.
                        </p>
                        <span className="w-fit rounded-full bg-silver-soft px-3 py-1 text-xs font-black dark:bg-white/10">
                            {method === "put" ? "Mode Edit" : "Data Baru"}
                        </span>
                    </div>

                    {sections.map(({ title: sectionTitle, description: sectionDescription, icon: Icon, fields: fieldNames }) => (
                        <section
                            className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]"
                            key={sectionTitle}
                        >
                            <header className="flex items-center gap-3 border-b border-silver-deep/50 bg-silver-soft/55 px-5 py-3.5 dark:border-white/10 dark:bg-white/[0.03]">
                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-ink text-white dark:bg-white dark:text-ink">
                                    <Icon size={18} />
                                </span>
                                <div>
                                    <h2 className="text-sm font-black text-ink dark:text-white">{sectionTitle}</h2>
                                    <p className="mt-0.5 text-xs font-semibold text-ink-soft dark:text-white/50">{sectionDescription}</p>
                                </div>
                            </header>
                            <div className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                                {fieldNames.map(renderField)}
                            </div>
                        </section>
                    ))}

                    <div className="flex flex-wrap justify-end gap-3 rounded-xl border border-silver-deep/60 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-graphite">
                        <Button as={Link} href={baseUrl} type="button" variant="outline">
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <Save size={17} />
                            {form.processing
                                ? "Menyimpan..."
                                : method === "put"
                                  ? "Simpan Perubahan"
                                  : "Simpan User"}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

FormPage.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form User"}>{page}</AdminLayout>
);
