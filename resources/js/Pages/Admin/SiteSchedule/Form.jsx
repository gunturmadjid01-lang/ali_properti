import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    Plus,
    Save,
} from "lucide-react";
import { Fragment, useMemo } from "react";
import {
    Button,
    Dropdown,
    FieldLabel,
    Input,
    Textarea,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const numberFormat = new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 2,
});
const toNumber = (value) => Number(String(value ?? "").replace(",", ".")) || 0;
const sumNumbers = (items) =>
    items.reduce((sum, value) => sum + toNumber(value), 0);
const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
const periodColumns = (count) =>
    Array.from({ length: Math.max(1, Number(count || 1)) }, (_, index) => ({
        index,
        periode: index + 1,
    }));
const normalizeInteger = (value) => String(value ?? "").replace(/[^0-9]/g, "");
const normalizeDecimal = (value) =>
    String(value ?? "")
        .replace(",", ".")
        .replace(/[^0-9.]/g, "");
const filled = (value) =>
    String(value ?? "").trim() !== "" && toNumber(value) > 0;
const formatNumber = (value) =>
    Number.isInteger(Number(value || 0))
        ? String(Number(value || 0))
        : String(Number(Number(value || 0).toFixed(2)));
const distribute = (weight, count) => {
    const total = toNumber(weight);
    const duration = Math.max(1, Number(count || 1));
    const base = Math.floor((total / duration) * 100) / 100;
    const values = Array.from({ length: duration }, () => base);
    values[duration - 1] = Number(
        (values[duration - 1] + total - base * duration).toFixed(2),
    );
    return values.map(formatNumber);
};

function SelectField({
    label,
    required = false,
    value,
    options,
    onChange,
    error,
    placeholder,
}) {
    return (
        <div className="grid gap-2">
            <FieldLabel required={required}>{label}</FieldLabel>
            <Dropdown
                value={value}
                options={options}
                label={placeholder ?? `Pilih ${label.toLowerCase()}`}
                onChange={onChange}
            />
            {error && (
                <span className="text-xs font-bold text-red-600">{error}</span>
            )}
        </div>
    );
}

function Section({ number, title, description, children }) {
    return (
        <section className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
            <header className="flex items-center gap-3 border-b border-silver-deep/50 bg-silver-soft/55 px-5 py-3 dark:border-white/10 dark:bg-white/[0.03]">
                <span className="grid h-8 w-8 place-items-center rounded-lg bg-ink text-xs font-black text-white dark:bg-white dark:text-ink">
                    {number}
                </span>
                <div>
                    <h2 className="text-sm font-black">{title}</h2>
                    <p className="text-xs text-ink-soft">{description}</p>
                </div>
            </header>
            <div className="p-5">{children}</div>
        </section>
    );
}

export default function ScheduleForm({
    title,
    mode,
    baseUrl,
    indexUrl,
    options = {},
    initialData = null,
}) {
    const editing = mode === "edit";
    const spkOptions = options.spkKontraktors ?? [];
    const createForm = useForm({
        spk_kontraktor_id: "",
        perumahan_id: "",
        detail_rumah_id: "",
        tanggal_mulai: new Date().toISOString().slice(0, 10),
        jumlah_periode: 8,
        status: "direncanakan",
        catatan: "",
        items: [],
    });
    const editForm = useForm(
        initialData ?? {
            perumahan_id: "",
            detail_rumah_id: "",
            tahapan_pembangunan_id: "",
            nama_pekerjaan: "",
            tanggal_mulai: "",
            tanggal_target: "",
            target_progress: 0,
            realisasi_progress: 0,
            status: "direncanakan",
            kendala: "",
            catatan: "",
        },
    );
    const form = editing ? editForm : createForm;
    const selectedSpk = useMemo(
        () =>
            spkOptions.find(
                (row) =>
                    String(row.value) ===
                    String(createForm.data.spk_kontraktor_id),
            ) ?? null,
        [createForm.data.spk_kontraktor_id, spkOptions],
    );
    const weeks = useMemo(
        () => periodColumns(createForm.data.jumlah_periode),
        [createForm.data.jumlah_periode],
    );
    const unitOptions = (options.detailRumahs ?? []).filter(
        (row) =>
            !editForm.data.perumahan_id ||
            String(row.perumahan_id) === String(editForm.data.perumahan_id),
    );
    const stageSource = editForm.data.detail_rumah_id
        ? options.tahapanPembangunansUnit
        : options.tahapanPembangunansKawasan;
    const stageOptions = (stageSource ?? []).filter((row) => {
        if (
            row.perumahan_id &&
            String(row.perumahan_id) !== String(editForm.data.perumahan_id)
        )
            return false;
        if (
            editForm.data.detail_rumah_id &&
            row.detail_rumah_id &&
            String(row.detail_rumah_id) !==
                String(editForm.data.detail_rumah_id)
        )
            return false;
        return true;
    });

    const buildItems = (spk, count) =>
        (spk?.groups ?? []).map((group) => ({
            tahapan_pembangunan_id: "",
            nama_pekerjaan: group.judul_tahapan ?? "",
            target_progress: group.group_percent ?? 0,
            allocations: Array.from({ length: Number(count || 1) }, () => ""),
        }));
    const selectSpk = (id) => {
        const spk =
            spkOptions.find((row) => String(row.value) === String(id)) ?? null;
        createForm.setData({
            ...createForm.data,
            spk_kontraktor_id: id,
            perumahan_id: spk?.perumahan_id ?? "",
            detail_rumah_id: spk?.detail_rumah_id ?? "",
            items: buildItems(spk, createForm.data.jumlah_periode),
        });
    };
    const changeDuration = (value) => {
        const count = Math.max(
            1,
            Math.min(52, Number(normalizeInteger(value) || 1)),
        );
        createForm.setData({
            ...createForm.data,
            jumlah_periode: count,
            items: createForm.data.items.map((item) => ({
                ...item,
                allocations: Array.from(
                    { length: count },
                    (_, index) => item.allocations?.[index] ?? "",
                ),
            })),
        });
    };
    const setItem = (itemIndex, key, value) =>
        createForm.setData(
            "items",
            createForm.data.items.map((item, index) =>
                index === itemIndex ? { ...item, [key]: value } : item,
            ),
        );
    const toggleWeek = (itemIndex, periodIndex) => {
        createForm.setData(
            "items",
            createForm.data.items.map((item, index) => {
                if (index !== itemIndex) return item;
                const current = (item.allocations ?? [])
                    .map((value, i) => (filled(value) ? i : null))
                    .filter((value) => value !== null);
                const selected = current.includes(periodIndex)
                    ? current.filter((value) => value !== periodIndex)
                    : [...current, periodIndex].sort((a, b) => a - b);
                const values = distribute(
                    item.target_progress,
                    selected.length || 1,
                );
                return {
                    ...item,
                    allocations: Array.from(
                        { length: Number(createForm.data.jumlah_periode || 1) },
                        (_, i) => {
                            const position = selected.indexOf(i);
                            return position >= 0 ? values[position] : "";
                        },
                    ),
                };
            }),
        );
    };

    const submit = (event) => {
        event.preventDefault();
        if (editing) {
            editForm.put(baseUrl, { preserveScroll: true });
            return;
        }
        createForm.post(baseUrl, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="mx-auto grid max-w-7xl gap-5 pb-8">
                <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#26343d] px-6 py-5 text-white shadow-lg">
                    <div className="absolute -right-16 -top-24 h-52 w-52 rounded-full bg-gold/15 blur-2xl" />
                    <div className="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.16em] text-gold">
                                <CalendarClock size={15} /> Pengawasan Lapangan
                            </p>
                            <h1 className="mt-2 text-2xl font-black md:text-3xl">
                                {title}
                            </h1>
                            <p className="mt-2 text-sm text-white/65">
                                {editing
                                    ? `Perbarui jadwal ${initialData?.kode_jadwal ?? ""}.`
                                    : "Susun time schedule berdasarkan SPK yang telah disetujui."}
                            </p>
                        </div>
                        <Button
                            as={Link}
                            href={indexUrl}
                            variant="outline"
                            className="border-white/20 bg-white/10 text-white hover:bg-white/20"
                        >
                            <ArrowLeft size={17} /> Kembali ke Data
                        </Button>
                    </div>
                </section>

                {Object.keys(form.errors).length > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">
                        Periksa kembali kolom yang ditandai.{" "}
                        {Object.values(form.errors)[0]}
                    </div>
                )}

                <form className="grid gap-4" onSubmit={submit}>
                    {!editing ? (
                        <>
                            <Section
                                number="1"
                                title="Kontrak dan Periode"
                                description="Pilih SPK sebagai sumber pekerjaan jadwal."
                            >
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <div className="md:col-span-2">
                                        <SelectField
                                            label="SPK"
                                            required
                                            value={
                                                createForm.data
                                                    .spk_kontraktor_id
                                            }
                                            options={spkOptions}
                                            error={
                                                createForm.errors
                                                    .spk_kontraktor_id
                                            }
                                            onChange={selectSpk}
                                            placeholder="Pilih SPK yang disetujui"
                                        />
                                    </div>
                                    <Input
                                        label="Tanggal Mulai"
                                        required
                                        type="date"
                                        value={createForm.data.tanggal_mulai}
                                        error={createForm.errors.tanggal_mulai}
                                        onChange={(event) =>
                                            createForm.setData(
                                                "tanggal_mulai",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Input
                                        label="Durasi (Minggu)"
                                        required
                                        type="text"
                                        inputMode="numeric"
                                        value={createForm.data.jumlah_periode}
                                        error={createForm.errors.jumlah_periode}
                                        onChange={(event) =>
                                            changeDuration(event.target.value)
                                        }
                                    />
                                </div>
                                {selectedSpk && (
                                    <div className="mt-4 grid gap-3 md:grid-cols-3">
                                        {[
                                            [
                                                "Perumahan",
                                                selectedSpk.perumahan_label,
                                            ],
                                            [
                                                "Unit Rumah",
                                                selectedSpk.unit_label,
                                            ],
                                            [
                                                "Nilai SPK",
                                                money(selectedSpk.total_nilai),
                                            ],
                                        ].map(([label, value]) => (
                                            <div
                                                className="rounded-xl border border-silver-deep/60 bg-silver-soft/45 p-4 dark:border-white/10 dark:bg-white/5"
                                                key={label}
                                            >
                                                <p className="text-[10px] font-black uppercase tracking-wider text-ink-soft">
                                                    {label}
                                                </p>
                                                <p className="mt-1 font-black">
                                                    {value || "-"}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </Section>

                            <Section
                                number="2"
                                title="Pembagian Mingguan"
                                description="Centang minggu pelaksanaan; bobot dibagi otomatis."
                            >
                                {!createForm.data.items.length ? (
                                    <div className="rounded-xl border border-dashed border-silver-deep p-8 text-center text-sm font-bold text-ink-soft">
                                        Pilih SPK terlebih dahulu untuk memuat
                                        tahapan pekerjaan.
                                    </div>
                                ) : (
                                    <div className="grid gap-3">
                                        {createForm.data.items.map(
                                            (item, itemIndex) => {
                                                const total = sumNumbers(
                                                    item.allocations ?? [],
                                                );
                                                return (
                                                    <Fragment
                                                        key={`${item.nama_pekerjaan}-${itemIndex}`}
                                                    >
                                                        <article className="rounded-xl border border-silver-deep/60 p-4 dark:border-white/10">
                                                            <div className="grid gap-3 lg:grid-cols-[minmax(260px,1fr)_130px]">
                                                                <Input
                                                                    label="Tahap Pekerjaan"
                                                                    required
                                                                    value={
                                                                        item.nama_pekerjaan
                                                                    }
                                                                    error={
                                                                        createForm
                                                                            .errors[
                                                                            `items.${itemIndex}.nama_pekerjaan`
                                                                        ]
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setItem(
                                                                            itemIndex,
                                                                            "nama_pekerjaan",
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                />
                                                                <div className="rounded-lg bg-silver-soft/60 p-3 text-center dark:bg-white/5">
                                                                    <p className="text-[10px] font-black uppercase text-ink-soft">
                                                                        Bobot /
                                                                        Terbagi
                                                                    </p>
                                                                    <p className="mt-1 font-black">
                                                                        {numberFormat.format(
                                                                            item.target_progress,
                                                                        )}
                                                                        % /{" "}
                                                                        {numberFormat.format(
                                                                            total,
                                                                        )}
                                                                        %
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div className="mt-4 grid grid-cols-4 gap-2 md:grid-cols-8 xl:grid-cols-13">
                                                                {weeks.map(
                                                                    (week) => {
                                                                        const value =
                                                                            item
                                                                                .allocations?.[
                                                                                week
                                                                                    .index
                                                                            ] ??
                                                                            "";
                                                                        const checked =
                                                                            filled(
                                                                                value,
                                                                            );
                                                                        return (
                                                                            <label
                                                                                className={`flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold transition ${checked ? "border-emerald-400 bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10" : "border-silver-deep/60 text-ink-soft dark:border-white/10"}`}
                                                                                key={
                                                                                    week.periode
                                                                                }
                                                                            >
                                                                                <input
                                                                                    type="checkbox"
                                                                                    checked={
                                                                                        checked
                                                                                    }
                                                                                    onChange={() =>
                                                                                        toggleWeek(
                                                                                            itemIndex,
                                                                                            week.index,
                                                                                        )
                                                                                    }
                                                                                />
                                                                                <span>
                                                                                    M
                                                                                    {
                                                                                        week.periode
                                                                                    }
                                                                                </span>
                                                                                <span className="ml-auto text-[10px]">
                                                                                    {checked
                                                                                        ? `${numberFormat.format(value)}%`
                                                                                        : "-"}
                                                                                </span>
                                                                            </label>
                                                                        );
                                                                    },
                                                                )}
                                                            </div>
                                                        </article>
                                                    </Fragment>
                                                );
                                            },
                                        )}
                                    </div>
                                )}
                            </Section>

                            <Section
                                number="3"
                                title="Status dan Catatan"
                                description="Lengkapi status awal dan informasi tambahan."
                            >
                                <div className="grid gap-4 md:grid-cols-3">
                                    <SelectField
                                        label="Status"
                                        required
                                        value={createForm.data.status}
                                        options={statusOptions}
                                        error={createForm.errors.status}
                                        onChange={(value) =>
                                            createForm.setData("status", value)
                                        }
                                    />
                                    <Textarea
                                        className="md:col-span-2"
                                        label="Catatan"
                                        value={createForm.data.catatan}
                                        error={createForm.errors.catatan}
                                        onChange={(event) =>
                                            createForm.setData(
                                                "catatan",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </Section>
                        </>
                    ) : (
                        <>
                            <Section
                                number="1"
                                title="Lokasi dan Pekerjaan"
                                description="Identitas pekerjaan yang dijadwalkan."
                            >
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <SelectField
                                        label="Perumahan"
                                        required
                                        value={editForm.data.perumahan_id}
                                        options={options.perumahans ?? []}
                                        error={editForm.errors.perumahan_id}
                                        onChange={(value) =>
                                            editForm.setData({
                                                ...editForm.data,
                                                perumahan_id: value,
                                                detail_rumah_id: "",
                                                tahapan_pembangunan_id: "",
                                            })
                                        }
                                    />
                                    <SelectField
                                        label="Unit Rumah"
                                        value={editForm.data.detail_rumah_id}
                                        options={[
                                            {
                                                value: "",
                                                label: "Kawasan / tanpa unit",
                                            },
                                            ...unitOptions,
                                        ]}
                                        error={editForm.errors.detail_rumah_id}
                                        onChange={(value) =>
                                            editForm.setData({
                                                ...editForm.data,
                                                detail_rumah_id: value,
                                                tahapan_pembangunan_id: "",
                                            })
                                        }
                                    />
                                    <SelectField
                                        label="Tahapan Pembangunan"
                                        value={
                                            editForm.data.tahapan_pembangunan_id
                                        }
                                        options={[
                                            {
                                                value: "",
                                                label: "Tanpa tahapan",
                                            },
                                            ...stageOptions,
                                        ]}
                                        error={
                                            editForm.errors
                                                .tahapan_pembangunan_id
                                        }
                                        onChange={(value) =>
                                            editForm.setData(
                                                "tahapan_pembangunan_id",
                                                value,
                                            )
                                        }
                                    />
                                    <Input
                                        className="md:col-span-2 lg:col-span-3"
                                        label="Nama Pekerjaan"
                                        required
                                        value={editForm.data.nama_pekerjaan}
                                        error={editForm.errors.nama_pekerjaan}
                                        onChange={(event) =>
                                            editForm.setData(
                                                "nama_pekerjaan",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </Section>
                            <Section
                                number="2"
                                title="Periode dan Target"
                                description="Atur rentang pelaksanaan serta target jadwal."
                            >
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <Input
                                        label="Tanggal Mulai"
                                        required
                                        type="date"
                                        value={editForm.data.tanggal_mulai}
                                        error={editForm.errors.tanggal_mulai}
                                        onChange={(event) =>
                                            editForm.setData(
                                                "tanggal_mulai",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Input
                                        label="Tanggal Target"
                                        required
                                        type="date"
                                        value={editForm.data.tanggal_target}
                                        error={editForm.errors.tanggal_target}
                                        onChange={(event) =>
                                            editForm.setData(
                                                "tanggal_target",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Input
                                        label="Target Kemajuan (%)"
                                        required
                                        type="text"
                                        inputMode="decimal"
                                        value={editForm.data.target_progress}
                                        error={editForm.errors.target_progress}
                                        onChange={(event) =>
                                            editForm.setData(
                                                "target_progress",
                                                normalizeDecimal(
                                                    event.target.value,
                                                ),
                                            )
                                        }
                                    />
                                    <SelectField
                                        label="Status"
                                        required
                                        value={editForm.data.status}
                                        options={statusOptions}
                                        error={editForm.errors.status}
                                        onChange={(value) =>
                                            editForm.setData("status", value)
                                        }
                                    />
                                </div>
                            </Section>
                            <Section
                                number="3"
                                title="Kendala dan Catatan"
                                description="Rekam informasi pelaksanaan lapangan."
                            >
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Textarea
                                        label="Kendala"
                                        value={editForm.data.kendala}
                                        error={editForm.errors.kendala}
                                        onChange={(event) =>
                                            editForm.setData(
                                                "kendala",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Textarea
                                        label="Catatan"
                                        value={editForm.data.catatan}
                                        error={editForm.errors.catatan}
                                        onChange={(event) =>
                                            editForm.setData(
                                                "catatan",
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </Section>
                        </>
                    )}

                    <div className="flex flex-wrap justify-end gap-3 rounded-xl border border-silver-deep/60 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-graphite">
                        <Button
                            as={Link}
                            href={indexUrl}
                            type="button"
                            variant="outline"
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                (!editing &&
                                    (!createForm.data.spk_kontraktor_id ||
                                        !createForm.data.items.length))
                            }
                        >
                            <Save size={17} />{" "}
                            {form.processing
                                ? "Menyimpan..."
                                : editing
                                  ? "Simpan Perubahan"
                                  : "Simpan Time Schedule"}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

const statusOptions = [
    { value: "direncanakan", label: "Direncanakan" },
    { value: "berjalan", label: "Berjalan" },
    { value: "terlambat", label: "Terlambat" },
    { value: "tertahan", label: "Tertahan" },
    { value: "selesai", label: "Selesai" },
];

ScheduleForm.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Jadwal Lapangan"}>
        {page}
    </AdminLayout>
);
