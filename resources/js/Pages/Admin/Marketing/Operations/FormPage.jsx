import { Head, router, useForm } from "@inertiajs/react";
import { ArrowLeft, Save } from "lucide-react";
import {
    Button,
    CurrencyInput,
    Dropdown,
    Form,
    Input,
    Textarea,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const statusOptions = (values) =>
    values.map((value) => ({
        value,
        label: value
            .replaceAll("_", " ")
            .replace(/^./, (char) => char.toUpperCase()),
    }));

export default function FormPage({
    title,
    section,
    type,
    baseUrl,
    actionUrl,
    method,
    row = {},
    options = {},
}) {
    const form = useForm({
        type:
            type === "commission"
                ? "commission"
                : type === "target"
                  ? "target"
                  : undefined,
        nama_campaign: row.nama_campaign ?? "",
        kanal: row.kanal ?? (type === "template" ? "whatsapp" : ""),
        tanggal_mulai: row.tanggal_mulai ?? "",
        tanggal_selesai: row.tanggal_selesai ?? "",
        anggaran: row.anggaran ?? 0,
        realisasi_biaya: row.realisasi_biaya ?? 0,
        target_lead: row.target_lead ?? 0,
        status:
            row.status ??
            (type === "reminder"
                ? "menunggu"
                : type === "template"
                  ? "aktif"
                  : "draft"),
        keterangan: row.keterangan ?? "",
        costumer_id: String(row.costumer_id ?? ""),
        user_id: String(row.user_id ?? ""),
        jenis: row.jenis ?? "follow_up",
        judul: row.judul ?? "",
        remind_at: row.remind_at ?? "",
        catatan: row.catatan ?? "",
        nama_template: row.nama_template ?? "",
        tahapan: row.tahapan ?? "",
        isi_template: row.isi_template ?? "",
        tahun: row.tahun ?? new Date().getFullYear(),
        bulan: row.bulan ?? new Date().getMonth() + 1,
        target_follow_up: row.target_follow_up ?? 0,
        target_visit: row.target_visit ?? 0,
        target_survey: row.target_survey ?? 0,
        target_reservation: row.target_reservation ?? 0,
        target_spr: row.target_spr ?? 0,
        target_closing: row.target_closing ?? 0,
        target_nilai_penjualan: row.target_nilai_penjualan ?? 0,
        spr_id: String(row.spr_id ?? ""),
        dasar_perhitungan: row.dasar_perhitungan ?? 0,
        persentase: row.persentase ?? 0,
        tanggal_jatuh_tempo: row.tanggal_jatuh_tempo ?? "",
        tanggal_dibayar: row.tanggal_dibayar ?? "",
    });

    const submit = (event) => {
        event.preventDefault();
        form[method](actionUrl);
    };

    const selectSpr = (value) => {
        const selected = options.sprs?.find(
            (item) => String(item.value) === String(value),
        );
        form.setData((data) => ({
            ...data,
            spr_id: value,
            dasar_perhitungan: selected?.amount ?? data.dasar_perhitungan,
            user_id: selected?.user_id
                ? String(selected.user_id)
                : data.user_id,
        }));
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-widest text-ink-soft">
                        Marketing / Operasional / Form
                    </p>
                    <h1 className="mt-2 text-3xl font-extrabold">{title}</h1>
                    <p className="mt-2 text-sm text-ink-soft">
                        Form dipisahkan dari halaman daftar agar alur input
                        lebih jelas dan halaman daftar tetap ringan.
                    </p>
                </section>

                <Form
                    title="Data Utama"
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
                                <Save size={16} />{" "}
                                {form.processing ? "Menyimpan..." : "Simpan"}
                            </Button>
                        </>
                    }
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        {type === "campaign" && <CampaignFields form={form} />}
                        {type === "reminder" && (
                            <ReminderFields form={form} options={options} />
                        )}
                        {type === "template" && <TemplateFields form={form} />}
                        {type === "target" && (
                            <TargetFields form={form} options={options} />
                        )}
                        {type === "commission" && (
                            <CommissionFields
                                form={form}
                                options={options}
                                selectSpr={selectSpr}
                            />
                        )}
                    </div>
                </Form>
            </div>
        </>
    );
}

function CampaignFields({ form }) {
    return (
        <>
            <Input
                label="Nama Campaign *"
                value={form.data.nama_campaign}
                error={form.errors.nama_campaign}
                onChange={(event) =>
                    form.setData("nama_campaign", event.target.value)
                }
            />
            <Input
                label="Kanal *"
                value={form.data.kanal}
                error={form.errors.kanal}
                onChange={(event) => form.setData("kanal", event.target.value)}
            />
            <Input
                type="date"
                label="Tanggal Mulai *"
                value={form.data.tanggal_mulai}
                error={form.errors.tanggal_mulai}
                onChange={(event) =>
                    form.setData("tanggal_mulai", event.target.value)
                }
            />
            <Input
                type="date"
                label="Tanggal Selesai"
                value={form.data.tanggal_selesai}
                error={form.errors.tanggal_selesai}
                onChange={(event) =>
                    form.setData("tanggal_selesai", event.target.value)
                }
            />
            <CurrencyInput
                label="Anggaran *"
                value={form.data.anggaran}
                error={form.errors.anggaran}
                onChange={(value) => form.setData("anggaran", value)}
            />
            <CurrencyInput
                label="Realisasi Biaya"
                value={form.data.realisasi_biaya}
                error={form.errors.realisasi_biaya}
                onChange={(value) => form.setData("realisasi_biaya", value)}
            />
            <Input
                type="number"
                min="0"
                label="Target Lead"
                value={form.data.target_lead}
                error={form.errors.target_lead}
                onChange={(event) =>
                    form.setData("target_lead", event.target.value)
                }
            />
            <Select
                label="Status *"
                value={form.data.status}
                options={statusOptions([
                    "draft",
                    "aktif",
                    "selesai",
                    "dibatalkan",
                ])}
                onChange={(value) => form.setData("status", value)}
                error={form.errors.status}
            />
            <Textarea
                className="md:col-span-2"
                label="Keterangan"
                value={form.data.keterangan}
                error={form.errors.keterangan}
                onChange={(event) =>
                    form.setData("keterangan", event.target.value)
                }
            />
        </>
    );
}

function ReminderFields({ form, options }) {
    return (
        <>
            <Select
                label="Pelanggan"
                value={form.data.costumer_id}
                options={options.customers ?? []}
                onChange={(value) => form.setData("costumer_id", value)}
                error={form.errors.costumer_id}
            />
            {options.hideUser ? (
                <InfoField
                    label="Petugas"
                    value={`${options.currentUser ?? "User aktif"} (otomatis)`}
                />
            ) : (
                <Select
                    label="Petugas"
                    value={form.data.user_id}
                    options={options.users ?? []}
                    onChange={(value) => form.setData("user_id", value)}
                    error={form.errors.user_id}
                />
            )}
            <Input
                label="Jenis *"
                value={form.data.jenis}
                error={form.errors.jenis}
                onChange={(event) => form.setData("jenis", event.target.value)}
            />
            <Input
                label="Judul *"
                value={form.data.judul}
                error={form.errors.judul}
                onChange={(event) => form.setData("judul", event.target.value)}
            />
            <Input
                type="datetime-local"
                label="Waktu Reminder *"
                value={form.data.remind_at}
                error={form.errors.remind_at}
                onChange={(event) =>
                    form.setData("remind_at", event.target.value)
                }
            />
            <Select
                label="Status *"
                value={form.data.status}
                options={statusOptions(["menunggu", "selesai", "dibatalkan"])}
                onChange={(value) => form.setData("status", value)}
                error={form.errors.status}
            />
            <Textarea
                className="md:col-span-2"
                label="Catatan"
                value={form.data.catatan}
                error={form.errors.catatan}
                onChange={(event) =>
                    form.setData("catatan", event.target.value)
                }
            />
        </>
    );
}

function TemplateFields({ form }) {
    return (
        <>
            <Input
                label="Nama Template *"
                value={form.data.nama_template}
                error={form.errors.nama_template}
                onChange={(event) =>
                    form.setData("nama_template", event.target.value)
                }
            />
            <Select
                label="Kanal *"
                value={form.data.kanal}
                options={statusOptions(["whatsapp", "sms", "email"])}
                onChange={(value) => form.setData("kanal", value)}
                error={form.errors.kanal}
            />
            <Input
                label="Tahapan"
                value={form.data.tahapan}
                error={form.errors.tahapan}
                onChange={(event) =>
                    form.setData("tahapan", event.target.value)
                }
            />
            <Select
                label="Status *"
                value={form.data.status}
                options={statusOptions(["aktif", "nonaktif"])}
                onChange={(value) => form.setData("status", value)}
                error={form.errors.status}
            />
            <Textarea
                className="md:col-span-2"
                label="Isi Template *"
                value={form.data.isi_template}
                error={form.errors.isi_template}
                onChange={(event) =>
                    form.setData("isi_template", event.target.value)
                }
            />
        </>
    );
}

function TargetFields({ form, options }) {
    const metrics = [
        ["target_lead", "Target Lead"],
        ["target_follow_up", "Target Follow Up"],
        ["target_visit", "Target Kunjungan"],
        ["target_survey", "Target Survey"],
        ["target_reservation", "Target Reservasi"],
        ["target_spr", "Target SPR"],
        ["target_closing", "Target Closing"],
    ];

    return (
        <>
            <Select
                className="md:col-span-2"
                label="Marketing *"
                value={form.data.user_id}
                options={options.users ?? []}
                onChange={(value) => form.setData("user_id", value)}
                error={form.errors.user_id}
            />
            <Input
                type="number"
                min="2020"
                max="2100"
                label="Tahun *"
                value={form.data.tahun}
                error={form.errors.tahun}
                onChange={(event) => form.setData("tahun", event.target.value)}
            />
            <Input
                type="number"
                min="1"
                max="12"
                label="Bulan *"
                value={form.data.bulan}
                error={form.errors.bulan}
                onChange={(event) => form.setData("bulan", event.target.value)}
            />
            {metrics.map(([name, label]) => (
                <Input
                    key={name}
                    type="number"
                    min="0"
                    label={`${label} *`}
                    value={form.data[name]}
                    error={form.errors[name]}
                    onChange={(event) => form.setData(name, event.target.value)}
                />
            ))}
            <CurrencyInput
                label="Target Nilai Penjualan *"
                value={form.data.target_nilai_penjualan}
                error={form.errors.target_nilai_penjualan}
                onChange={(value) =>
                    form.setData("target_nilai_penjualan", value)
                }
            />
            <Textarea
                className="md:col-span-2"
                label="Catatan"
                value={form.data.catatan}
                error={form.errors.catatan}
                onChange={(event) =>
                    form.setData("catatan", event.target.value)
                }
            />
        </>
    );
}

function CommissionFields({ form, options, selectSpr }) {
    return (
        <>
            <Select
                className="md:col-span-2"
                label="SPR Disetujui *"
                value={form.data.spr_id}
                options={options.sprs ?? []}
                onChange={selectSpr}
                error={form.errors.spr_id}
            />
            <Select
                label="Marketing *"
                value={form.data.user_id}
                options={options.users ?? []}
                onChange={(value) => form.setData("user_id", value)}
                error={form.errors.user_id}
            />
            <CurrencyInput
                label="Dasar Perhitungan *"
                value={form.data.dasar_perhitungan}
                error={form.errors.dasar_perhitungan}
                onChange={(value) => form.setData("dasar_perhitungan", value)}
            />
            <Input
                type="number"
                min="0"
                max="100"
                step="0.01"
                label="Persentase *"
                value={form.data.persentase}
                error={form.errors.persentase}
                onChange={(event) =>
                    form.setData("persentase", event.target.value)
                }
            />
            <Select
                label="Status *"
                value={form.data.status}
                options={statusOptions([
                    "draft",
                    "diajukan",
                    "disetujui",
                    "dibayar",
                    "dibatalkan",
                ])}
                onChange={(value) => form.setData("status", value)}
                error={form.errors.status}
            />
            <Input
                type="date"
                label="Jatuh Tempo"
                value={form.data.tanggal_jatuh_tempo}
                error={form.errors.tanggal_jatuh_tempo}
                onChange={(event) =>
                    form.setData("tanggal_jatuh_tempo", event.target.value)
                }
            />
            <Input
                type="date"
                label="Tanggal Dibayar"
                value={form.data.tanggal_dibayar}
                error={form.errors.tanggal_dibayar}
                onChange={(event) =>
                    form.setData("tanggal_dibayar", event.target.value)
                }
            />
            <Textarea
                className="md:col-span-2"
                label="Catatan"
                value={form.data.catatan}
                error={form.errors.catatan}
                onChange={(event) =>
                    form.setData("catatan", event.target.value)
                }
            />
        </>
    );
}

function Select({ className = "", label, value, options, onChange, error }) {
    return (
        <div className={`grid gap-2 ${className}`}>
            <span className="text-sm font-extrabold">{label}</span>
            <Dropdown value={value} options={options} onChange={onChange} />
            {error && (
                <span className="text-xs font-bold text-red-600">{error}</span>
            )}
        </div>
    );
}

function InfoField({ label, value }) {
    return (
        <div className="rounded-lg bg-silver-soft p-4">
            <p className="text-xs font-extrabold uppercase text-ink-soft">
                {label}
            </p>
            <p className="mt-1 font-bold">{value}</p>
        </div>
    );
}

FormPage.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Operasional Marketing"}>
        {page}
    </AdminLayout>
);
