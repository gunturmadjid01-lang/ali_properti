import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Lightbulb,
    Plus,
    Save,
    Trash2,
} from "lucide-react";
import { useState } from "react";
import {
    Button,
    CurrencyInput,
    Dropdown,
    Input,
    Textarea,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const cashSteps = [
    "Informasi Skema",
    "Booking Fee & Uang Muka",
    "Jadwal Pembayaran",
    "Denda & Tunggakan",
    "Syarat Serah Terima",
    "Persyaratan Pelanggan",
    "Pratinjau & Simpan",
];
const kprSteps = [
    "Informasi Produk",
    "Pembiayaan & Tenor",
    "Margin & Simulasi",
    "Biaya, Jatuh Tempo & Denda",
    "Kelayakan Pelanggan",
    "Syarat Serah Terima",
    "Pengaturan Lanjutan",
    "Pratinjau & Simpan",
];
const cashHelp = [
    {
        title: "Menentukan identitas dan cakupan skema",
        details: [
            "Cabang adalah kantor yang memiliki skema.",
            "Perumahan dapat dipilih lebih dari satu selama masih dalam cabang yang sama.",
            "Draf belum dapat dipilih di SPR; Aktif dapat digunakan; Nonaktif hanya disimpan sebagai riwayat.",
        ],
        example:
            "CB-GRIYA-24 — Tunai Bertahap Griya 24 Bulan untuk Griya Asri dan Griya Indah.",
    },
    {
        title: "Menentukan pembayaran awal pelanggan",
        details: [
            "Persentase berarti DP dihitung dari harga transaksi.",
            "Nominal berarti batas DP berupa nilai Rupiah tetap.",
            "Booking dapat mengurangi DP, mengurangi harga jual, atau menjadi biaya terpisah.",
        ],
        example:
            "Booking Fee Rp5.000.000 mengurangi DP, dengan minimum DP 20%.",
    },
    {
        title: "Menentukan bagaimana sistem membuat tagihan",
        details: [
            "Bulanan Sama Rata: isi lama cicilan dalam bulan; jumlah tagihan dihitung otomatis.",
            "Mingguan Sama Rata: isi lama cicilan dalam minggu; jumlah tagihan dihitung otomatis.",
            "Tahapan Persentase: buat tahap seperti DP, pondasi, atap, dan pelunasan.",
            "Berdasarkan Kemajuan: tagihan mengikuti pencapaian pembangunan.",
            "Jadwal Custom: nama, nilai, dan jatuh tempo setiap tagihan diatur sendiri. Form Tahap hanya muncul untuk model bertahap atau custom.",
            "Nominal Tetap: nilai tahap berupa Rupiah yang tidak berubah.",
            "Persentase Harga Jual/Final: nilai dihitung dari harga transaksi. Persentase Sisa: dihitung dari kewajiban yang belum dialokasikan.",
            "Sisa Otomatis: sistem mengambil seluruh sisa kewajiban; gunakan pada tahap terakhir.",
            "Jatuh Tempo bulan ke-0 berarti saat kontrak dimulai, bulan ke-1 berarti satu bulan setelahnya.",
        ],
        example:
            "Bulanan 12 bulan menghasilkan 12 tagihan. Model tahapan dapat berisi DP 20%, Pondasi 30%, Atap 30%, lalu Pelunasan Sisa Otomatis.",
    },
    {
        title: "Menentukan aturan keterlambatan",
        details: [
            "Tidak Ada Denda tidak membutuhkan nilai denda.",
            "Nominal Tetap memakai nilai Rupiah.",
            "Persentase dari Tagihan dihitung sekali dari tagihan.",
            "Per Hari atau Per Bulan bertambah mengikuti lama keterlambatan.",
            "Masa Tenggang adalah jumlah hari sebelum denda mulai dihitung.",
        ],
        example: "Masa tenggang 7 hari, lalu denda 0,1% dari tagihan.",
    },
    {
        title: "Menentukan kapan rumah boleh diserahkan",
        details: [
            "DP harus lunas memblokir serah terima jika masih ada sisa DP.",
            "Minimum pembayaran adalah persentase harga yang sudah dibayar.",
            "Tidak ada tunggakan berarti semua tagihan jatuh tempo sudah lunas.",
            "Minimum progress diambil dari progres pembangunan tim teknik.",
        ],
        example:
            "DP lunas, pembayaran minimal 30%, tanpa tunggakan, dan progres pembangunan 100%.",
    },
    {
        title: "Menentukan syarat bisnis pelanggan",
        details: [
            "Isi kewajiban non-dokumen seperti survei lokasi atau penandatanganan surat.",
            "Syarat Wajib harus dipenuhi sebelum proses dilanjutkan.",
            "Berkas identitas tetap dikelola dari menu Dokumen Pelanggan.",
        ],
        example:
            "Pelanggan wajib mengikuti survei lokasi dan menandatangani surat pemesanan.",
    },
    {
        title: "Periksa konfigurasi sebelum disimpan",
        details: [
            "Periksa perumahan, periode, status, DP, jadwal, denda, dan syarat serah terima.",
            "Gunakan Simpan Draf jika konfigurasi masih perlu ditinjau.",
        ],
        example:
            "Aktifkan skema hanya setelah seluruh nilai sesuai kebijakan perusahaan.",
    },
];
const kprHelp = [
    {
        title: "Menentukan identitas dan cakupan produk",
        details: [
            "Cabang adalah pemilik produk.",
            "Perumahan dapat dipilih lebih dari satu dalam cabang yang sama.",
            "Draf belum dapat dipilih pada SPR; Aktif dapat digunakan; Nonaktif hanya untuk riwayat.",
        ],
        example:
            "KPRD-GRIYA-60 — KPR Developer sampai 60 bulan untuk beberapa perumahan.",
    },
    {
        title: "Menentukan batas pembiayaan dan tenor",
        details: [
            "DP Persentase mengikuti harga transaksi; DP Nominal memakai nilai Rupiah tetap.",
            "Pembiayaan Persentase membatasi bagian harga yang dibiayai; Nominal membatasi nilai Rupiah maksimum.",
            "Mode Rentang menghasilkan tenor dari minimum, maksimum, dan kelipatan.",
            "Mode Pilihan Khusus digunakan bila pilihan tenor tidak berurutan.",
        ],
        example:
            "DP 20%, pembiayaan maksimum 80%, tenor 12–60 bulan dengan kelipatan 12 bulan menghasilkan 12, 24, 36, 48, dan 60 bulan.",
    },
    {
        title: "Menentukan cara margin dihitung",
        details: [
            "Tanpa Margin membagi pokok tanpa tambahan margin.",
            "Flat menghitung margin dari pokok awal.",
            "Efektif menghitung dari sisa pokok.",
            "Anuitas menghasilkan angsuran total relatif tetap.",
            "Margin Tetap Internal mengikuti kebijakan khusus developer.",
            "Sama Semua Tenor memakai satu nilai; Berbeda per Tenor membutuhkan nilai per pilihan tenor.",
        ],
        example:
            "Margin Flat 8% per tahun dan berlaku sama untuk seluruh tenor.",
    },
    {
        title: "Menentukan biaya dan keterlambatan",
        details: [
            "Nominal Tetap memakai Rupiah; Persentase Pembiayaan dihitung dari pokok; Persentase Harga Jual dihitung dari harga rumah.",
            "Denda Nominal memakai Rupiah, sedangkan metode persentase memakai angka persen.",
            "Masa Tenggang adalah jumlah hari setelah jatuh tempo sebelum denda berlaku.",
        ],
        example:
            "Biaya administrasi Rp1.500.000 dan denda 0,1% dari angsuran setelah tenggang 7 hari.",
    },
    {
        title: "Menentukan batas kemampuan pelanggan",
        details: [
            "Minimum penghasilan adalah penghasilan bulanan terendah.",
            "Rasio cicilan membandingkan estimasi cicilan terhadap penghasilan.",
            "Usia maksimum diperiksa saat pengajuan.",
            "Pasangan wajib berarti data pasangan harus dilibatkan dalam analisis.",
        ],
        example:
            "Penghasilan minimum Rp5.000.000, rasio cicilan maksimum 30%, usia maksimum 55 tahun.",
    },
    {
        title: "Menentukan kapan rumah boleh diserahkan",
        details: [
            "DP harus lunas memblokir serah terima jika DP tersisa.",
            "Tidak ada tunggakan berarti seluruh angsuran jatuh tempo sudah lunas.",
            "Dokumen pelanggan dikelola terpusat melalui menu Dokumen Pelanggan.",
        ],
        example:
            "DP lunas, kontrak aktif, tidak ada tunggakan, dan seluruh clearance disetujui.",
    },
    {
        title: "Mengatur kondisi khusus",
        details: [
            "Pelunasan dipercepat mengizinkan pelanggan melunasi sebelum tenor berakhir.",
            "Restrukturisasi mengizinkan perubahan tenor atau jadwal karena kondisi tertentu.",
            "Pembatalan mengaktifkan aturan refund, penalti, dan pelepasan unit.",
            "Biarkan nonaktif jika kebijakan tersebut tidak digunakan.",
        ],
        example:
            "Aktifkan Pelunasan Dipercepat dan wajibkan approval Manager untuk potongan margin.",
    },
    {
        title: "Periksa konfigurasi sebelum disimpan",
        details: [
            "Periksa pembiayaan, tenor, margin, biaya, kelayakan, dan serah terima.",
            "Gunakan Simpan Draf bila produk masih menunggu review.",
        ],
        example:
            "Aktifkan produk hanya setelah simulasi dan seluruh batas kebijakan sudah benar.",
    },
];
const opt = (items) => items.map(([value, label]) => ({ value, label }));
const selectOptions = {
    status: opt([
        ["draft", "Draf"],
        ["aktif", "Aktif"],
        ["nonaktif", "Tidak Aktif"],
    ]),
    dpType: opt([
        ["percentage", "Persentase"],
        ["nominal", "Nominal"],
    ]),
    deduct: opt([
        ["down_payment", "Uang Muka"],
        ["sale_price", "Harga Jual"],
        ["none", "Tidak Mengurangi"],
    ]),
    cashModel: opt([
        ["equal_monthly", "Cicilan Bulanan Sama Rata"],
        ["equal_weekly", "Cicilan Mingguan Sama Rata"],
        ["percentage_steps", "Tahapan Persentase"],
        ["progress_steps", "Tahapan Berdasarkan Kemajuan"],
        ["custom", "Jadwal Custom"],
    ]),
    cashPenalty: opt([
        ["none", "Tidak Ada Denda"],
        ["fixed", "Nominal Tetap"],
        ["invoice_percentage", "Persentase dari Tagihan"],
        ["daily_percentage", "Persentase per Hari"],
        ["monthly_percentage", "Persentase per Bulan"],
    ]),
    kprPenalty: opt([
        ["none", "Tidak Ada Denda"],
        ["fixed", "Nominal Tetap"],
        ["installment_percentage", "Persentase dari Angsuran"],
        ["daily_percentage", "Persentase per Hari"],
        ["monthly_percentage", "Persentase per Bulan"],
    ]),
    margin: opt([
        ["none", "Tanpa Margin"],
        ["flat", "Flat"],
        ["effective", "Efektif"],
        ["annuity", "Anuitas"],
        ["internal_fixed", "Margin Tetap Internal"],
    ]),
};
const emptyRequirement = () => ({
    category: "Administratif",
    name: "",
    required: true,
    condition: "all",
    notes: "",
});
const emptyStep = () => ({
    name: "",
    calculation_type: "fixed",
    value: "",
    due_offset_months: "1",
});
const emptyFee = () => ({
    type: "Administrasi",
    method: "fixed",
    value: "",
    payment_time: "contract",
    financed: false,
    required: true,
    notes: "",
});

function Field({ label, error, children, help }) {
    return (
        <label className="grid gap-2 text-sm font-extrabold">
            <span>{label}</span>
            {children}
            {help && (
                <small className="font-semibold text-ink-soft">{help}</small>
            )}
            {error && <small className="font-bold text-red-600">{error}</small>}
        </label>
    );
}
function Toggle({ label, checked, onChange }) {
    return (
        <label className="flex items-center gap-3 rounded-lg border border-silver-deep/60 p-3 text-sm font-bold">
            <input
                type="checkbox"
                checked={Boolean(checked)}
                onChange={(e) => onChange(e.target.checked)}
            />
            {label}
        </label>
    );
}
function GuidanceCard({ guide, open, onToggle }) {
    return (
        <aside className="mb-5 overflow-hidden rounded-xl border border-amber-300/60 bg-amber-50/80 dark:border-amber-300/20 dark:bg-amber-300/8">
            <button
                className="flex w-full items-center justify-between gap-3 p-4 text-left"
                type="button"
                onClick={onToggle}
            >
                <span className="flex items-center gap-2 font-black text-amber-900 dark:text-amber-100">
                    <Lightbulb size={18} />
                    Petunjuk & Contoh Pengisian
                </span>
                <ChevronDown
                    size={18}
                    className={`transition ${open ? "rotate-180" : ""}`}
                />
            </button>
            {open && (
                <div className="border-t border-amber-300/50 px-4 py-4 text-sm text-amber-950 dark:border-amber-300/20 dark:text-amber-50">
                    <p className="font-black">{guide.title}</p>
                    {guide.details?.length > 0 && (
                        <ul className="mt-3 grid gap-2 pl-5 leading-6 opacity-85">
                            {guide.details.map((detail, index) => (
                                <li className="list-disc" key={index}>
                                    {detail}
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="mt-4 rounded-lg border border-amber-300/50 bg-white/60 p-3 leading-6 dark:bg-white/5">
                        <b>Contoh:</b>{" "}
                        {guide.example.replace(/^Contoh:\s*/, "")}
                    </div>
                </div>
            )}
        </aside>
    );
}
function MultiHousing({ form, options }) {
    const choices = (options.housing ?? []).filter(
        (x) =>
            !form.data.cabang_perusahaan_id ||
            String(x.cabang_perusahaan_id) ===
                String(form.data.cabang_perusahaan_id),
    );
    return (
        <Field
            label="Perumahan *"
            error={form.errors.perumahan_ids}
            help="Pilih satu atau beberapa perumahan dari cabang yang sama."
        >
            <div className="grid gap-2 rounded-lg border border-silver-deep/60 p-3 md:grid-cols-2">
                {choices.length ? (
                    choices.map((x) => (
                        <Toggle
                            key={x.value}
                            label={x.label}
                            checked={form.data.perumahan_ids.includes(
                                String(x.value),
                            )}
                            onChange={(yes) =>
                                form.setData(
                                    "perumahan_ids",
                                    yes
                                        ? [
                                              ...form.data.perumahan_ids,
                                              String(x.value),
                                          ]
                                        : form.data.perumahan_ids.filter(
                                              (id) => id !== String(x.value),
                                          ),
                                )
                            }
                        />
                    ))
                ) : (
                    <p className="text-sm text-ink-soft">
                        Pilih cabang untuk menampilkan perumahan.
                    </p>
                )}
            </div>
        </Field>
    );
}
function Repeater({
    items,
    onChange,
    make,
    children,
    addLabel,
    itemLabel = "Item",
}) {
    const set = (i, key, value) =>
        onChange(
            items.map((row, n) => (n === i ? { ...row, [key]: value } : row)),
        );
    return (
        <div className="grid gap-3">
            {items.map((row, i) => (
                <div
                    className="grid gap-3 rounded-lg border border-silver-deep/60 p-4"
                    key={i}
                >
                    <div className="flex justify-between">
                        <strong>
                            {itemLabel} {i + 1}
                        </strong>
                        <button
                            aria-label={`Hapus ${itemLabel} ${i + 1}`}
                            type="button"
                            onClick={() =>
                                onChange(items.filter((_, n) => n !== i))
                            }
                        >
                            <Trash2 size={17} />
                        </button>
                    </div>
                    {children(row, i, (key, value) => set(i, key, value))}
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                onClick={() => onChange([...items, make()])}
            >
                <Plus size={16} />
                {addLabel}
            </Button>
        </div>
    );
}

export default function MasterWizard({
    section,
    sectionTitle,
    indexUrl,
    actionUrl,
    method,
    options = {},
    row = null,
}) {
    const cash = section === "schemes",
        steps = cash ? cashSteps : kprSteps;
    const [page, setPage] = useState(0);
    const [helpOpen, setHelpOpen] = useState(true);
    const guides = cash ? cashHelp : kprHelp;
    const defaults = {
        code: row?.code ?? "",
        name: row?.name ?? "",
        cabang_perusahaan_id: String(row?.cabang_perusahaan_id ?? ""),
        perumahan_ids: (row?.perumahan_ids ?? []).map(String),
        unit_types: row?.unit_types ?? [],
        effective_from: row?.effective_from?.slice?.(0, 10) ?? "",
        effective_until: row?.effective_until?.slice?.(0, 10) ?? "",
        status: row?.status ?? "draft",
        notes: row?.notes ?? "",
        dp_type: row?.dp_type ?? "nominal",
        minimum_dp: row?.minimum_dp ?? "",
        minimum_booking_fee: row?.minimum_booking_fee ?? "",
        booking_fee_deducts: row?.booking_fee_deducts ?? "down_payment",
        payment_model: row?.payment_model ?? "equal_monthly",
        maximum_tenor_months: row?.maximum_tenor_months ?? 12,
        installment_count: row?.installment_count ?? 12,
        grace_period_days: row?.grace_period_days ?? 0,
        penalty_method: row?.penalty_method ?? "none",
        penalty_value: row?.penalty_value ?? "",
        schedule_config: row?.schedule_config ?? {
            interval: "monthly",
            first_due_basis: "contract",
            holiday_rule: "next_business_day",
            rounding: "nearest",
        },
        handover_config: row?.handover_config ?? {
            dp_paid: true,
            no_arrears: true,
            minimum_paid_percentage: 0,
            financial_clearance: true,
            technical_clearance: true,
            document_clearance: true,
            minimum_progress: 0,
            notes: "",
        },
        requirements: row?.requirements ?? [],
        steps: row?.steps ?? [],
        financing_type: row?.financing_type ?? "nominal",
        maximum_financing: row?.maximum_financing ?? "",
        financing_basis: row?.financing_basis ?? "final_price",
        tenor_mode: row?.tenor_mode ?? "range",
        minimum_tenor_months: row?.minimum_tenor_months ?? 12,
        tenor_increment: row?.tenor_increment ?? 12,
        allowed_tenors: row?.allowed_tenors ?? [],
        margin_method: row?.margin_method ?? "flat",
        margin_scope: row?.margin_scope ?? "all",
        annual_margin: row?.annual_margin ?? 0,
        margin_tiers: row?.margin_tiers ?? [],
        fees: row?.fees ?? [],
        minimum_income: row?.minimum_income ?? 0,
        maximum_age: row?.maximum_age ?? 60,
        eligibility_config: row?.eligibility_config ?? {
            jobs: [],
            minimum_work_months: 0,
            minimum_business_months: 0,
            maximum_installment_ratio: 30,
            minimum_age: 18,
            maximum_age_at_end: 65,
            spouse_required: false,
            guarantor_allowed: true,
        },
        advanced_config: row?.advanced_config ?? {
            early_settlement: false,
            restructuring: false,
            cancellation: false,
        },
    };
    const form = useForm(defaults);
    const setNested = (key, name, value) =>
        form.setData(key, { ...form.data[key], [name]: value });
    const select = (label, key, choices, help) => (
        <Field label={label} error={form.errors[key]} help={help}>
            <Dropdown
                value={String(form.data[key] ?? "")}
                options={choices}
                onChange={(v) => form.setData(key, v)}
            />
        </Field>
    );
    const money = (label, key) => (
        <CurrencyInput
            label={label}
            value={form.data[key]}
            error={form.errors[key]}
            onChange={(v) => form.setData(key, v)}
        />
    );
    const percent = (label, key) => (
        <Input
            label={label}
            type="number"
            min="0"
            max="100"
            value={form.data[key]}
            error={form.errors[key]}
            onChange={(e) => form.setData(key, e.target.value)}
        />
    );
    const automaticSchedule = ["equal_monthly", "equal_weekly"].includes(
        form.data.payment_model,
    );
    const scheduleUnit =
        form.data.payment_model === "equal_weekly" ? "minggu" : "bulan";
    const scheduleTenor =
        form.data.schedule_config?.tenor_value ??
        (form.data.payment_model === "equal_weekly"
            ? form.data.installment_count
            : form.data.maximum_tenor_months);
    form.transform((data) => {
        if (!cash) return data;
        if (["equal_monthly", "equal_weekly"].includes(data.payment_model)) {
            const tenor = Math.max(
                1,
                Number(
                    data.schedule_config?.tenor_value ??
                        data.installment_count ??
                        1,
                ),
            );
            return {
                ...data,
                installment_count: tenor,
                maximum_tenor_months:
                    data.payment_model === "equal_weekly"
                        ? Math.max(1, Math.ceil(tenor / 4))
                        : tenor,
                steps: [],
                schedule_config: {
                    ...data.schedule_config,
                    tenor_value: tenor,
                    tenor_unit:
                        data.payment_model === "equal_weekly"
                            ? "week"
                            : "month",
                },
            };
        }
        return { ...data, installment_count: data.steps.length };
    });
    const basic = (
        <div className="grid gap-4 md:grid-cols-2">
            <Input
                label={cash ? "Kode Skema" : "Kode Produk"}
                required
                value={form.data.code}
                error={form.errors.code}
                onChange={(e) => form.setData("code", e.target.value)}
            />
            <Input
                label={cash ? "Nama Skema" : "Nama Produk"}
                required
                value={form.data.name}
                error={form.errors.name}
                onChange={(e) => form.setData("name", e.target.value)}
            />
            <Field label="Cabang *" error={form.errors.cabang_perusahaan_id}>
                <Dropdown
                    value={form.data.cabang_perusahaan_id}
                    options={options.branches ?? []}
                    onChange={(v) =>
                        form.setData({
                            ...form.data,
                            cabang_perusahaan_id: v,
                            perumahan_ids: [],
                            unit_types: [],
                        })
                    }
                />
            </Field>
            <div className="md:col-span-2">
                <MultiHousing form={form} options={options} />
            </div>
            <Input
                label="Mulai Berlaku"
                type="date"
                value={form.data.effective_from}
                onChange={(e) => form.setData("effective_from", e.target.value)}
            />
            <Input
                label="Berakhir (opsional)"
                type="date"
                value={form.data.effective_until}
                onChange={(e) =>
                    form.setData("effective_until", e.target.value)
                }
            />
            {select("Status", "status", selectOptions.status)}
            <Textarea
                label="Catatan"
                value={form.data.notes}
                onChange={(e) => form.setData("notes", e.target.value)}
            />
        </div>
    );
    const penalty = (
        <div className="grid gap-4 md:grid-cols-2">
            {select(
                "Metode Denda",
                "penalty_method",
                cash ? selectOptions.cashPenalty : selectOptions.kprPenalty,
            )}
            {form.data.penalty_method !== "none" &&
                (form.data.penalty_method === "fixed"
                    ? money("Nilai Denda", "penalty_value")
                    : percent("Nilai Denda (%)", "penalty_value"))}
            <Input
                label="Masa Tenggang (hari)"
                type="number"
                min="0"
                value={form.data.grace_period_days}
                onChange={(e) =>
                    form.setData("grace_period_days", e.target.value)
                }
            />
        </div>
    );
    let content = basic;
    if (cash && page === 1)
        content = (
            <div className="grid gap-4 md:grid-cols-2">
                {money("Minimum Booking Fee", "minimum_booking_fee")}
                {select(
                    "Booking Fee Mengurangi",
                    "booking_fee_deducts",
                    selectOptions.deduct,
                )}
                {select("Jenis Uang Muka", "dp_type", selectOptions.dpType)}
                {form.data.dp_type === "nominal"
                    ? money("Minimum Uang Muka", "minimum_dp")
                    : percent("Minimum Uang Muka (%)", "minimum_dp")}
            </div>
        );
    if (cash && page === 2)
        content = (
            <div className="grid gap-5">
                <Field
                    label="Model Pembayaran"
                    help="Pilih cara sistem membentuk jadwal tagihan pelanggan."
                >
                    <Dropdown
                        value={form.data.payment_model}
                        options={selectOptions.cashModel}
                        onChange={(value) =>
                            form.setData({
                                ...form.data,
                                payment_model: value,
                                steps: [],
                                schedule_config: {
                                    ...form.data.schedule_config,
                                    tenor_value:
                                        value === "equal_weekly"
                                            ? 12
                                            : form.data.maximum_tenor_months,
                                },
                            })
                        }
                    />
                </Field>
                {automaticSchedule ? (
                    <div className="grid gap-4">
                        <Input
                            label={`Lama Cicilan (${scheduleUnit})`}
                            type="number"
                            min="1"
                            value={scheduleTenor}
                            onChange={(e) =>
                                setNested(
                                    "schedule_config",
                                    "tenor_value",
                                    e.target.value,
                                )
                            }
                        />
                        <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                            <b>Jadwal dibuat otomatis:</b>{" "}
                            {Number(scheduleTenor || 0)} tagihan{" "}
                            {form.data.payment_model === "equal_weekly"
                                ? "mingguan"
                                : "bulanan"}{" "}
                            dengan nilai sama rata. Selisih pembulatan
                            dimasukkan ke tagihan terakhir. Anda tidak perlu
                            mengisi tahap satu per satu.
                        </div>
                    </div>
                ) : (
                    <div className="grid gap-5">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Input
                                label="Batas Waktu Skema (bulan)"
                                type="number"
                                min="1"
                                value={form.data.maximum_tenor_months}
                                onChange={(e) =>
                                    form.setData(
                                        "maximum_tenor_months",
                                        e.target.value,
                                    )
                                }
                            />
                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                                <b>Jumlah tahap: {form.data.steps.length}</b>
                                <br />
                                Jumlah dihitung otomatis dari daftar tahap di
                                bawah.
                            </div>
                        </div>
                        <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 text-sm leading-6 text-ink-soft">
                            <b className="text-ink">Cara mengisi tahap:</b> Nama
                            Tahap menjelaskan tagihannya; Metode Perhitungan
                            menentukan sumber nilainya; Nilai diisi Rupiah atau
                            persen; Jatuh Tempo adalah bulan keberapa sejak
                            tanggal mulai kontrak. Gunakan <b>Sisa Otomatis</b>{" "}
                            pada tahap terakhir agar tidak ada selisih.
                        </div>
                        <Repeater
                            itemLabel="Tahap Pembayaran"
                            items={form.data.steps}
                            onChange={(v) => form.setData("steps", v)}
                            make={emptyStep}
                            addLabel="Tambah Tahap Pembayaran"
                        >
                            {(r, i, set) => (
                                <div className="grid gap-3 md:grid-cols-4">
                                    <Input
                                        label="Nama Tahap"
                                        placeholder="Contoh: DP, Pondasi, Pelunasan"
                                        value={r.name}
                                        onChange={(e) =>
                                            set("name", e.target.value)
                                        }
                                    />
                                    <Field label="Metode Perhitungan">
                                        <Dropdown
                                            value={r.calculation_type}
                                            options={opt([
                                                ["fixed", "Nominal Tetap"],
                                                [
                                                    "percentage_sale",
                                                    "Persentase Harga Jual",
                                                ],
                                                [
                                                    "percentage_final",
                                                    "Persentase Harga Akhir",
                                                ],
                                                [
                                                    "percentage_remaining",
                                                    "Persentase Sisa Kewajiban",
                                                ],
                                                ["remaining", "Sisa Otomatis"],
                                            ])}
                                            onChange={(v) =>
                                                set("calculation_type", v)
                                            }
                                        />
                                    </Field>
                                    {r.calculation_type === "remaining" ? (
                                        <div className="rounded-lg bg-silver-soft p-3 text-sm font-bold text-ink-soft">
                                            Nilai dihitung otomatis dari sisa
                                            kewajiban.
                                        </div>
                                    ) : r.calculation_type === "fixed" ? (
                                        <CurrencyInput
                                            label="Nilai Tahap"
                                            value={r.value}
                                            onChange={(v) => set("value", v)}
                                        />
                                    ) : (
                                        <Input
                                            label="Nilai Tahap (%)"
                                            type="number"
                                            min="0"
                                            max="100"
                                            value={r.value}
                                            onChange={(e) =>
                                                set("value", e.target.value)
                                            }
                                        />
                                    )}
                                    <Input
                                        label="Jatuh Tempo (bulan ke-)"
                                        type="number"
                                        min="0"
                                        value={r.due_offset_months}
                                        onChange={(e) =>
                                            set(
                                                "due_offset_months",
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            )}
                        </Repeater>
                    </div>
                )}
            </div>
        );
    if (cash && page === 3) content = penalty;
    if (cash && page === 4)
        content = (
            <div className="grid gap-3 md:grid-cols-2">
                <Toggle
                    label="DP harus lunas"
                    checked={form.data.handover_config.dp_paid}
                    onChange={(v) => setNested("handover_config", "dp_paid", v)}
                />
                <Toggle
                    label="Tidak boleh ada tunggakan"
                    checked={form.data.handover_config.no_arrears}
                    onChange={(v) =>
                        setNested("handover_config", "no_arrears", v)
                    }
                />
                <Input
                    label="Minimum pembayaran (%)"
                    type="number"
                    min="0"
                    max="100"
                    value={form.data.handover_config.minimum_paid_percentage}
                    onChange={(e) =>
                        setNested(
                            "handover_config",
                            "minimum_paid_percentage",
                            e.target.value,
                        )
                    }
                />
                <Input
                    label="Minimum progress (%)"
                    type="number"
                    value={form.data.handover_config.minimum_progress}
                    onChange={(e) =>
                        setNested(
                            "handover_config",
                            "minimum_progress",
                            e.target.value,
                        )
                    }
                />
            </div>
        );
    if (cash && page === 5)
        content = (
            <div className="grid gap-6">
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm font-semibold text-blue-900">
                    Dokumen identitas dan berkas customer dikelola satu kali
                    dari menu <b>Dokumen Pelanggan</b>, sehingga tidak perlu
                    dibuat ulang pada setiap skema.
                </div>
                <section className="grid gap-3 rounded-lg border p-4">
                    <h3 className="font-black">Ketentuan Kontrak</h3>
                    <p className="text-sm text-ink-soft">Ketentuan ini otomatis masuk ke SPR dan pemeriksaan kontrak. Staf transaksi tidak perlu mengetiknya ulang.</p>
                    <Toggle label="Pelunasan dipercepat diperbolehkan" checked={form.data.advanced_config.early_settlement} onChange={(value) => setNested("advanced_config", "early_settlement", value)}/>
                    {form.data.advanced_config.early_settlement && <Textarea label="Ketentuan Pelunasan Dipercepat" value={form.data.advanced_config.early_settlement_terms ?? ""} onChange={(event) => setNested("advanced_config", "early_settlement_terms", event.target.value)}/>} 
                    <Toggle label="Pembatalan kontrak diperbolehkan" checked={form.data.advanced_config.cancellation} onChange={(value) => setNested("advanced_config", "cancellation", value)}/>
                    {form.data.advanced_config.cancellation && <Textarea label="Ketentuan Pembatalan dan Refund" value={form.data.advanced_config.cancellation_terms ?? ""} onChange={(event) => setNested("advanced_config", "cancellation_terms", event.target.value)}/>} 
                </section>
                <Repeater
                    items={form.data.requirements}
                    onChange={(v) => form.setData("requirements", v)}
                    make={emptyRequirement}
                    addLabel="Tambah Persyaratan"
                >
                    {(r, i, set) => (
                        <div className="grid gap-3 md:grid-cols-2">
                            <Input
                                label="Nama Persyaratan"
                                value={r.name}
                                onChange={(e) => set("name", e.target.value)}
                            />
                            <Input
                                label="Kategori"
                                value={r.category}
                                onChange={(e) =>
                                    set("category", e.target.value)
                                }
                            />
                            <Toggle
                                label="Wajib"
                                checked={r.required}
                                onChange={(v) => set("required", v)}
                            />
                        </div>
                    )}
                </Repeater>
            </div>
        );
    if (!cash && page === 1)
        content = (
            <div className="grid gap-4 md:grid-cols-2">
                {select(
                    "Jenis Minimum Uang Muka",
                    "dp_type",
                    selectOptions.dpType,
                )}
                {form.data.dp_type === "nominal"
                    ? money("Minimum Uang Muka", "minimum_dp")
                    : percent("Minimum Uang Muka (%)", "minimum_dp")}
                {select(
                    "Jenis Maksimum Pembiayaan",
                    "financing_type",
                    opt([
                        ["percentage", "Persentase Harga Jual"],
                        ["nominal", "Nominal Maksimum"],
                    ]),
                )}
                {form.data.financing_type === "nominal"
                    ? money("Maksimum Pembiayaan", "maximum_financing")
                    : percent("Maksimum Pembiayaan (%)", "maximum_financing")}
                {select(
                    "Mode Tenor",
                    "tenor_mode",
                    opt([
                        ["range", "Rentang"],
                        ["custom", "Pilihan Khusus"],
                    ]),
                )}
                <Input
                    label="Tenor Minimum (bulan)"
                    type="number"
                    value={form.data.minimum_tenor_months}
                    onChange={(e) =>
                        form.setData("minimum_tenor_months", e.target.value)
                    }
                />
                <Input
                    label="Tenor Maksimum (bulan)"
                    type="number"
                    value={form.data.maximum_tenor_months}
                    onChange={(e) =>
                        form.setData("maximum_tenor_months", e.target.value)
                    }
                />
                <Input
                    label="Kelipatan Tenor"
                    type="number"
                    value={form.data.tenor_increment}
                    onChange={(e) =>
                        form.setData("tenor_increment", e.target.value)
                    }
                />
            </div>
        );
    if (!cash && page === 2)
        content = (
            <div className="grid gap-4 md:grid-cols-2">
                {select("Metode Margin", "margin_method", selectOptions.margin)}
                {select(
                    "Margin Berlaku",
                    "margin_scope",
                    opt([
                        ["all", "Sama untuk Semua Tenor"],
                        ["per_tenor", "Berbeda per Tenor"],
                    ]),
                )}
                {percent("Margin Tahunan (%)", "annual_margin")}
                <div className="rounded-lg bg-silver-soft p-4">
                    <b>Simulasi</b>
                    <p className="mt-2 text-sm text-ink-soft">
                        Estimasi dihitung ulang oleh backend saat SPR diproses.
                    </p>
                </div>
            </div>
        );
    if (!cash && page === 3)
        content = (
            <div className="grid gap-6">
                {penalty}
                <Repeater
                    items={form.data.fees}
                    onChange={(v) => form.setData("fees", v)}
                    make={emptyFee}
                    addLabel="Tambah Biaya"
                >
                    {(r, i, set) => (
                        <div className="grid gap-3 md:grid-cols-3">
                            <Input
                                label="Jenis Biaya"
                                value={r.type}
                                onChange={(e) => set("type", e.target.value)}
                            />
                            <Dropdown
                                value={r.method}
                                options={opt([
                                    ["fixed", "Nominal Tetap"],
                                    [
                                        "financing_percentage",
                                        "Persentase Pembiayaan",
                                    ],
                                    [
                                        "sale_percentage",
                                        "Persentase Harga Jual",
                                    ],
                                ])}
                                onChange={(v) => set("method", v)}
                            />
                            {r.method === "fixed" ? (
                                <CurrencyInput
                                    label="Nilai"
                                    value={r.value}
                                    onChange={(v) => set("value", v)}
                                />
                            ) : (
                                <Input
                                    label="Nilai (%)"
                                    type="number"
                                    value={r.value}
                                    onChange={(e) =>
                                        set("value", e.target.value)
                                    }
                                />
                            )}
                        </div>
                    )}
                </Repeater>
            </div>
        );
    if (!cash && page === 4)
        content = (
            <div className="grid gap-4 md:grid-cols-2">
                {money("Minimum Penghasilan", "minimum_income")}
                <Input
                    label="Maksimum Rasio Cicilan (%)"
                    type="number"
                    min="0"
                    max="100"
                    value={
                        form.data.eligibility_config.maximum_installment_ratio
                    }
                    onChange={(e) =>
                        setNested(
                            "eligibility_config",
                            "maximum_installment_ratio",
                            e.target.value,
                        )
                    }
                />
                <Input
                    label="Usia Maksimum Saat Pengajuan"
                    type="number"
                    value={form.data.maximum_age}
                    onChange={(e) =>
                        form.setData("maximum_age", e.target.value)
                    }
                />
                <Toggle
                    label="Pasangan wajib dilibatkan"
                    checked={form.data.eligibility_config.spouse_required}
                    onChange={(v) =>
                        setNested("eligibility_config", "spouse_required", v)
                    }
                />
            </div>
        );
    if (!cash && page === 5)
        content = (
            <div className="grid gap-4">
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm font-semibold text-blue-900">
                    Kelengkapan berkas customer mengikuti menu{" "}
                    <b>Dokumen Pelanggan</b>. Bagian ini hanya mengatur syarat
                    rumah boleh diserahterimakan.
                </div>
                <Toggle
                    label="DP harus lunas sebelum serah terima"
                    checked={form.data.handover_config.dp_paid}
                    onChange={(v) => setNested("handover_config", "dp_paid", v)}
                />
                <Toggle
                    label="Tidak boleh memiliki tunggakan"
                    checked={form.data.handover_config.no_arrears}
                    onChange={(v) =>
                        setNested("handover_config", "no_arrears", v)
                    }
                />
            </div>
        );
    if (!cash && page === 6)
        content = (
            <div className="grid gap-3">
                <Toggle
                    label="Pelunasan dipercepat diperbolehkan"
                    checked={form.data.advanced_config.early_settlement}
                    onChange={(v) =>
                        setNested("advanced_config", "early_settlement", v)
                    }
                />
                <Toggle
                    label="Restrukturisasi diperbolehkan"
                    checked={form.data.advanced_config.restructuring}
                    onChange={(v) =>
                        setNested("advanced_config", "restructuring", v)
                    }
                />
                <Toggle
                    label="Aturan pembatalan diaktifkan"
                    checked={form.data.advanced_config.cancellation}
                    onChange={(v) =>
                        setNested("advanced_config", "cancellation", v)
                    }
                />
            </div>
        );
    if (page === steps.length - 1)
        content = (
            <div className="grid gap-4">
                <div className="rounded-xl border border-gold/40 bg-gold/10 p-5">
                    <h3 className="text-lg font-black">
                        {form.data.code} — {form.data.name}
                    </h3>
                    <p className="mt-2 text-sm">
                        {form.data.perumahan_ids.length} perumahan · Status{" "}
                        {form.data.status} · Berlaku mulai{" "}
                        {form.data.effective_from || "-"}
                    </p>
                    <p className="mt-2 text-sm">
                        Semua nominal disimpan sebagai angka presisi dan
                        ditampilkan dalam format Rupiah.
                    </p>
                </div>
                {Object.keys(form.errors).length > 0 && (
                    <div className="rounded-lg bg-red-50 p-4 text-sm font-bold text-red-700">
                        Periksa kembali field yang belum valid pada langkah
                        sebelumnya.
                    </div>
                )}
            </div>
        );
    const submit = (e) => {
        e.preventDefault();
        form[method](actionUrl);
    };
    return (
        <>
            <Head title={`${row ? "Ubah" : "Tambah"} ${sectionTitle}`} />
            <div className="mx-auto grid max-w-6xl gap-5">
                <header className="flex items-center justify-between rounded-xl bg-white/80 p-5 shadow-soft">
                    <div>
                        <p className="text-xs font-black uppercase text-ink-soft">
                            Pengaturan Penjualan
                        </p>
                        <h1 className="text-2xl font-black">
                            {row ? "Ubah" : "Tambah"} {sectionTitle}
                        </h1>
                    </div>
                    <Button as={Link} href={indexUrl} variant="outline">
                        <ArrowLeft size={16} />
                        Kembali
                    </Button>
                </header>
                <nav className="flex gap-2 overflow-x-auto rounded-xl bg-white/80 p-3 shadow-soft">
                    {steps.map((x, i) => (
                        <button
                            type="button"
                            key={x}
                            onClick={() => {
                                setPage(i);
                                setHelpOpen(true);
                            }}
                            className={`whitespace-nowrap rounded-lg px-3 py-2 text-xs font-black ${i === page ? "bg-ink text-white" : "bg-silver-soft text-ink-soft"}`}
                        >
                            {i + 1}. {x}
                        </button>
                    ))}
                </nav>
                <form onSubmit={submit} className="grid gap-5">
                    <section className="rounded-xl bg-white/85 p-6 shadow-soft">
                        <h2 className="mb-5 text-xl font-black">
                            {steps[page]}
                        </h2>
                        <GuidanceCard
                            guide={guides[page]}
                            open={helpOpen}
                            onToggle={() => setHelpOpen(!helpOpen)}
                        />
                        {content}
                    </section>
                    <footer className="flex flex-wrap justify-between gap-3 rounded-xl bg-white/85 p-4 shadow-soft">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={page === 0}
                            onClick={() => {
                                setPage(Math.max(0, page - 1));
                                setHelpOpen(true);
                            }}
                        >
                            <ChevronLeft size={16} />
                            Sebelumnya
                        </Button>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => {
                                    form.setData("status", "draft");
                                    form[method](actionUrl);
                                }}
                            >
                                <Save size={16} />
                                Simpan Draft
                            </Button>
                            {page < steps.length - 1 ? (
                                <Button
                                    type="button"
                                    onClick={() => {
                                        setPage(page + 1);
                                        setHelpOpen(true);
                                    }}
                                >
                                    Selanjutnya
                                    <ChevronRight size={16} />
                                </Button>
                            ) : (
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    <Save size={16} />
                                    {form.processing
                                        ? "Menyimpan..."
                                        : "Simpan Konfigurasi"}
                                </Button>
                            )}
                        </div>
                    </footer>
                </form>
            </div>
        </>
    );
}
MasterWizard.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Pengaturan Penjualan"}>
        {page}
    </AdminLayout>
);
