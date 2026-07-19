import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Home, Save, Sparkles } from "lucide-react";
import {
    Button,
    CurrencyInput,
    Dropdown,
    FieldLabel,
    Input,
    Textarea,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const FormSections = ({ items }) => (
    <div className="grid gap-4">
        {items.map((item, index) => (
            <section
                className="overflow-hidden rounded-xl border border-silver-deep/60 bg-white dark:border-white/10 dark:bg-white/[0.025]"
                key={item.title}
            >
                <header className="flex items-center gap-3 border-b border-silver-deep/50 bg-silver-soft/55 px-4 py-3 dark:border-white/10 dark:bg-white/[0.03]">
                    <span className="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-ink text-xs font-black text-white dark:bg-white dark:text-ink">
                        {index + 1}
                    </span>
                    <h2 className="text-sm font-black text-ink dark:text-white">
                        {item.title}
                    </h2>
                </header>
                <div className="p-4 md:p-5">{item.content}</div>
            </section>
        ))}
    </div>
);

const Error = ({ message }) =>
    message && (
        <span className="text-xs font-bold text-red-600 dark:text-red-300">
            {message}
        </span>
    );

function SelectField({
    label,
    value,
    options,
    error,
    onChange,
    required = false,
}) {
    return (
        <label className="grid gap-2 text-sm font-extrabold">
            <FieldLabel required={required}>{label}</FieldLabel>
            <Dropdown
                value={value}
                label={`Pilih ${label}`}
                options={options}
                onChange={onChange}
            />
            <Error message={error} />
        </label>
    );
}

export default function Form({
    title,
    description,
    baseUrl,
    actionUrl,
    method,
    editing,
    initialData,
    options,
}) {
    const form = useForm(initialData);
    const submit = (event) => {
        event.preventDefault();
        method === "put"
            ? form.put(actionUrl, { preserveScroll: true })
            : form.post(actionUrl, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="mx-auto grid max-w-7xl gap-6 pb-10">
                <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#26333d] px-6 py-5 text-white shadow-lg md:px-7">
                    <div className="absolute -right-12 -top-20 h-56 w-56 rounded-full bg-emerald-400/10 blur-2xl" />
                    <div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-3xl">
                            <div className="mb-4 flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-champagne">
                                <Home size={16} /> Master Kapling & Unit
                            </div>
                            <h1 className="font-display text-2xl font-black md:text-3xl">
                                {title}
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-7 text-white/65">
                                {description}
                            </p>
                            <div className="mt-5 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-bold backdrop-blur">
                                <Sparkles size={15} className="text-gold" />
                                {editing
                                    ? "Mode pembaruan satu unit"
                                    : "Dapat membuat beberapa nomor unit berurutan"}
                            </div>
                        </div>
                        <Button
                            as={Link}
                            href={baseUrl}
                            variant="outline"
                            className="border-white/20 bg-white/10 text-white hover:bg-white/20"
                        >
                            <ArrowLeft size={17} /> Kembali ke Data
                        </Button>
                    </div>
                </section>

                <form className="grid gap-4" onSubmit={submit}>
                    <section className="rounded-2xl border border-silver-deep/60 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/[0.04] md:p-6">
                        <p className="mb-5 text-xs font-bold text-ink-soft dark:text-white/55">
                            Kolom bertanda{" "}
                            <span className="font-black text-red-600 dark:text-red-400">
                                *
                            </span>{" "}
                            wajib diisi.
                        </p>
                        {Object.keys(form.errors).length > 0 && (
                            <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                                Data belum bisa disimpan. Periksa kolom yang
                                ditandai.
                            </div>
                        )}
                        <FormSections
                            items={[
                                {
                                    title: "Identitas Unit",
                                    content: (
                                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                            <SelectField
                                                label="Perumahan"
                                                required
                                                value={form.data.perumahan_id}
                                                options={options.perumahans}
                                                error={form.errors.perumahan_id}
                                                onChange={(value) =>
                                                    form.setData(
                                                        "perumahan_id",
                                                        value,
                                                    )
                                                }
                                            />
                                            <SelectField
                                                label="Blok"
                                                required
                                                value={form.data.kode_nlok}
                                                options={options.blokOptions}
                                                error={form.errors.kode_nlok}
                                                onChange={(value) =>
                                                    form.setData(
                                                        "kode_nlok",
                                                        value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label={
                                                    editing
                                                        ? "Nomor Rumah"
                                                        : "Nomor Mulai"
                                                }
                                                value={form.data.nomor_rumah}
                                                required
                                                error={form.errors.nomor_rumah}
                                                onChange={(event) =>
                                                    form.setData(
                                                        "nomor_rumah",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            {!editing && (
                                                <Input
                                                    label="Jumlah Unit Dibuat"
                                                    type="number"
                                                    min="1"
                                                    value={
                                                        form.data.jumlah_unit
                                                    }
                                                    required
                                                    error={
                                                        form.errors.jumlah_unit
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "jumlah_unit",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            )}
                                            <Input
                                                label="Luas Tanah"
                                                value={form.data.luas_tanah}
                                                required
                                                error={form.errors.luas_tanah}
                                                onChange={(event) =>
                                                    form.setData(
                                                        "luas_tanah",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <CurrencyInput
                                                label="Harga Jual Dasar"
                                                value={form.data.harga_jual}
                                                required
                                                error={form.errors.harga_jual}
                                                onChange={(value) =>
                                                    form.setData(
                                                        "harga_jual",
                                                        value,
                                                    )
                                                }
                                            />
                                            <SelectField
                                                label="Status Pembangunan"
                                                required
                                                value={
                                                    form.data.status_pembangunan
                                                }
                                                options={
                                                    options.statusPembangunan
                                                }
                                                error={
                                                    form.errors
                                                        .status_pembangunan
                                                }
                                                onChange={(value) =>
                                                    form.setData(
                                                        "status_pembangunan",
                                                        value,
                                                    )
                                                }
                                            />
                                            <SelectField
                                                label="Status Penjualan"
                                                required
                                                value={
                                                    form.data.status_penjualan
                                                }
                                                options={
                                                    options.statusPenjualan
                                                }
                                                error={
                                                    form.errors.status_penjualan
                                                }
                                                onChange={(value) =>
                                                    form.setData(
                                                        "status_penjualan",
                                                        value,
                                                    )
                                                }
                                            />
                                        </div>
                                    ),
                                },
                                {
                                    title: "Spesifikasi Bangunan",
                                    content: (
                                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                            <Input
                                                label="Tipe Rumah"
                                                required={
                                                    form.data
                                                        .status_pembangunan !==
                                                    "kapling"
                                                }
                                                value={form.data.tipe_rumah}
                                                error={form.errors.tipe_rumah}
                                                onChange={(event) =>
                                                    form.setData(
                                                        "tipe_rumah",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Model Unit"
                                                value={form.data.model_unit}
                                                error={form.errors.model_unit}
                                                onChange={(event) =>
                                                    form.setData(
                                                        "model_unit",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Luas Bangunan"
                                                value={form.data.luas_bangunan}
                                                error={
                                                    form.errors.luas_bangunan
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        "luas_bangunan",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Jumlah Lantai"
                                                type="number"
                                                min="0"
                                                value={form.data.jumlah_lantai}
                                                error={
                                                    form.errors.jumlah_lantai
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        "jumlah_lantai",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Kamar Tidur"
                                                type="number"
                                                min="0"
                                                value={form.data.kamar_tidur}
                                                error={form.errors.kamar_tidur}
                                                onChange={(event) =>
                                                    form.setData(
                                                        "kamar_tidur",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Kamar Mandi"
                                                type="number"
                                                min="0"
                                                value={form.data.kamar_mandi}
                                                error={form.errors.kamar_mandi}
                                                onChange={(event) =>
                                                    form.setData(
                                                        "kamar_mandi",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Daya Listrik"
                                                value={form.data.daya_listrik}
                                                error={form.errors.daya_listrik}
                                                onChange={(event) =>
                                                    form.setData(
                                                        "daya_listrik",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Sumber Air"
                                                value={form.data.sumber_air}
                                                error={form.errors.sumber_air}
                                                onChange={(event) =>
                                                    form.setData(
                                                        "sumber_air",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Carport"
                                                value={form.data.carport}
                                                error={form.errors.carport}
                                                onChange={(event) =>
                                                    form.setData(
                                                        "carport",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <SelectField
                                                label="Arah Hadap"
                                                value={form.data.arah_hadap}
                                                options={options.arahHadap}
                                                error={form.errors.arah_hadap}
                                                onChange={(value) =>
                                                    form.setData(
                                                        "arah_hadap",
                                                        value,
                                                    )
                                                }
                                            />
                                            <SelectField
                                                label="Posisi Unit"
                                                value={form.data.posisi_unit}
                                                options={options.posisiUnit}
                                                error={form.errors.posisi_unit}
                                                onChange={(value) =>
                                                    form.setData(
                                                        "posisi_unit",
                                                        value,
                                                    )
                                                }
                                            />
                                            <div className="md:col-span-2 lg:col-span-4">
                                                <Textarea
                                                    label="Spesifikasi"
                                                    value={
                                                        form.data.spesifikasi
                                                    }
                                                    error={
                                                        form.errors.spesifikasi
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "spesifikasi",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                    ),
                                },
                                {
                                    title: "Kemajuan & Status Data",
                                    content: (
                                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                            <Input
                                                label="Kemajuan Awal (%)"
                                                type="number"
                                                min="0"
                                                max="100"
                                                value={
                                                    form.data.progress_terakhir
                                                }
                                                required
                                                error={
                                                    form.errors
                                                        .progress_terakhir
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        "progress_terakhir",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Tanggal Mulai Bangun"
                                                type="date"
                                                value={
                                                    form.data
                                                        .tanggal_mulai_bangun
                                                }
                                                error={
                                                    form.errors
                                                        .tanggal_mulai_bangun
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        "tanggal_mulai_bangun",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <Input
                                                label="Tanggal Selesai Bangun"
                                                type="date"
                                                value={
                                                    form.data
                                                        .tanggal_selesai_bangun
                                                }
                                                error={
                                                    form.errors
                                                        .tanggal_selesai_bangun
                                                }
                                                onChange={(event) =>
                                                    form.setData(
                                                        "tanggal_selesai_bangun",
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                            <SelectField
                                                label="Status Data"
                                                required
                                                value={form.data.status}
                                                options={[
                                                    {
                                                        value: "aktif",
                                                        label: "Aktif",
                                                    },
                                                    {
                                                        value: "nonaktif",
                                                        label: "Nonaktif",
                                                    },
                                                ]}
                                                error={form.errors.status}
                                                onChange={(value) =>
                                                    form.setData(
                                                        "status",
                                                        value,
                                                    )
                                                }
                                            />
                                            <div className="md:col-span-2 lg:col-span-4">
                                                <Textarea
                                                    label="Catatan Unit"
                                                    value={form.data.catatan}
                                                    error={form.errors.catatan}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            "catatan",
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                    ),
                                },
                            ]}
                        />
                        <div className="mt-5 flex flex-wrap justify-end gap-3 border-t border-silver-deep/60 pt-4 dark:border-white/10">
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
                                    : editing
                                      ? "Simpan Perubahan"
                                      : "Tambah Unit"}
                            </Button>
                        </div>
                    </section>
                </form>
            </div>
        </>
    );
}

Form.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Unit"}>{page}</AdminLayout>
);
