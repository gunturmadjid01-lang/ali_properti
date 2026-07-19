import { Head, router } from "@inertiajs/react";
import {
    ArrowLeft,
    BriefcaseBusiness,
    Building2,
    CalendarDays,
    CheckCircle2,
    Download,
    Edit3,
    Eye,
    FileCheck2,
    FileText,
    Home,
    Landmark,
    Lock,
    MapPin,
    Phone,
    Printer,
    ShieldCheck,
    Unlock,
    UserRound,
    WalletCards,
    XCircle,
} from "lucide-react";
import { Button } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const money = (v) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(v || 0));
const date = (v) =>
    v
        ? new Date(`${v}T00:00:00`).toLocaleDateString("id-ID", {
              day: "2-digit",
              month: "long",
              year: "numeric",
          })
        : "-";
const tone = (row) =>
    row.approval_status === "approved" || row.status === "disetujui"
        ? "emerald"
        : row.approval_status === "rejected" || row.status === "ditolak"
          ? "red"
          : row.record_status === "locked"
            ? "amber"
            : "slate";
const tones = {
    emerald: "border-emerald-200 bg-emerald-50 text-emerald-800",
    red: "border-red-200 bg-red-50 text-red-800",
    amber: "border-amber-200 bg-amber-50 text-amber-800",
    slate: "border-slate-200 bg-slate-50 text-slate-700",
};

export default function Show({ title, baseUrl, row, documentTemplates = [] }) {
    const d = row.detail || {},
        statusTone = tone(row);
    const requirements = [];
    const post = (action, data = {}) =>
        router.post(`${baseUrl}/${row.id}/${action}`, data, {
            preserveScroll: true,
        });
    const reject = () => {
        const note = prompt("Alasan penolakan SPR");
        if (note) post("reject", { catatan: note });
    };
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <header className="overflow-hidden rounded-2xl border bg-white/90 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b bg-gradient-to-r from-sky-950 via-sky-900 to-cyan-800 p-6 text-white">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-[.2em] text-sky-200">
                                    Surat Pemesanan Rumah
                                </p>
                                <h1 className="mt-2 text-3xl font-black">
                                    {row.kode_spr}
                                </h1>
                                <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-sky-100">
                                    <span className="flex items-center gap-2">
                                        <CalendarDays size={16} />
                                        {date(row.tanggal_spr)}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <UserRound size={16} />
                                        {row.customer}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <Home size={16} />
                                        {row.unit}
                                    </span>
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    className="border-white/30 bg-white/10 text-white hover:bg-white/20"
                                    onClick={() => router.visit(baseUrl)}
                                >
                                    <ArrowLeft size={16} />
                                    Kembali
                                </Button>
                                {row.can_edit && (
                                    <Button
                                        onClick={() =>
                                            router.visit(
                                                `${baseUrl}/${row.id}/edit`,
                                            )
                                        }
                                    >
                                        <Edit3 size={16} />
                                        Ubah SPR
                                    </Button>
                                )}
                                {row.can_lock && (
                                    <Button
                                        type="button"
                                        onClick={() => post("lock")}
                                    >
                                        <Lock size={16} />
                                        Kunci & Ajukan
                                    </Button>
                                )}
                                {row.can_unlock && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="border-white/30 bg-white/10 text-white"
                                        onClick={() => post("unlock")}
                                    >
                                        <Unlock size={16} />
                                        Buka Kunci
                                    </Button>
                                )}
                                {row.can_review_approval && (
                                    <>
                                        <Button onClick={() => post("approve")}>
                                            <CheckCircle2 size={16} />
                                            Setujui
                                        </Button>
                                        <Button
                                            variant="danger"
                                            onClick={reject}
                                        >
                                            <XCircle size={16} />
                                            Tolak
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="grid gap-3 p-5 md:grid-cols-4">
                        <Stat
                            label="Status SPR"
                            value={row.business_status_label}
                        />
                        <Stat label="Kunci" value={row.record_status_label} />
                        <Stat
                            label="Persetujuan"
                            value={row.status_label}
                            className={tones[statusTone]}
                        />
                        <Stat
                            label="Lampiran SPR"
                            value={`${row.berkas_count} dokumen dasar`}
                        />
                    </div>
                </header>

                <section className="flex flex-wrap gap-2 rounded-xl border bg-white/90 p-4 shadow-soft">
                    <Button
                        variant="outline"
                        onClick={() =>
                            window.open(
                                `${baseUrl}/${row.id}/preview`,
                                "_blank",
                            )
                        }
                    >
                        <Eye size={16} /> Pratinjau Ringkasan ERP
                    </Button>
                    <Button
                        onClick={() =>
                            window.open(`${baseUrl}/${row.id}/print`, "_blank")
                        }
                    >
                        <Printer size={16} /> Cetak / PDF
                    </Button>
                </section>
                {row.approval_status === "pending" && (
                    <section className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                        <ShieldCheck className="mt-0.5 shrink-0" />
                        <div>
                            <b>
                                Menunggu approval tahap{" "}
                                {row.approval_current_step}/
                                {row.approval_total_steps}
                            </b>
                            <p className="mt-1 text-sm">
                                Penanggung jawab tahap aktif:{" "}
                                {row.approval_reviewer_label ||
                                    "sesuai Pengaturan Persetujuan"}
                                .
                            </p>
                        </div>
                    </section>
                )}

                <div className="grid gap-6 xl:grid-cols-[1fr_1fr]">
                    <Card title="Pelanggan" icon={UserRound}>
                        <Info label="Nama Lengkap" value={row.customer} />
                        <Info
                            label="NIK / Identitas"
                            value={row.no_identitas}
                        />
                        <Info
                            label="Telepon"
                            value={d.customer_phone}
                            icon={Phone}
                        />
                        <Info label="Email" value={d.customer_email} />
                        <Info
                            label="Pekerjaan"
                            value={d.customer_job}
                            icon={BriefcaseBusiness}
                        />
                        <Info
                            label="Alamat"
                            value={d.customer_address}
                            wide
                            icon={MapPin}
                        />
                    </Card>
                    <Card title="Unit & Perumahan" icon={Home}>
                        <Info
                            label="Perumahan"
                            value={row.perumahan}
                            icon={Building2}
                        />
                        <Info label="Blok / Unit" value={row.unit} />
                        <Info label="Tipe Rumah" value={d.unit_type} />
                        <Info
                            label="Luas Bangunan"
                            value={
                                d.building_area ? `${d.building_area} m²` : "-"
                            }
                        />
                        <Info
                            label="Luas Tanah"
                            value={d.land_area ? `${d.land_area} m²` : "-"}
                        />
                        <Info
                            label="Alamat Perumahan"
                            value={d.housing_address}
                            wide
                            icon={MapPin}
                        />
                    </Card>
                </div>

                <section className="rounded-2xl border bg-white/90 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex items-center gap-3">
                        <span className="rounded-xl bg-emerald-100 p-2 text-emerald-700">
                            <WalletCards />
                        </span>
                        <div>
                            <h2 className="text-xl font-black">
                                Nilai Transaksi & Pembayaran
                            </h2>
                            <p className="text-sm text-ink-soft">
                                Ringkasan nilai yang disepakati pada SPR.
                            </p>
                        </div>
                    </div>
                    <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <MoneyStat label="Harga Dasar" value={row.harga_jual} />
                        <MoneyStat
                            label="Total Penambahan"
                            value={row.total_penambahan}
                        />
                        <MoneyStat
                            label="Harga Akhir"
                            value={row.nilai_pengajuan_akhir || row.harga_jual}
                            strong
                        />
                        <MoneyStat
                            label={row.financing_label || "Sisa Pembayaran"}
                            value={row.financing_value}
                            strong
                        />
                    </div>
                    <div className="mt-5 grid gap-4 border-t pt-5 md:grid-cols-3">
                        <Info
                            label="Metode Pembayaran"
                            value={row.metode_pembayaran}
                        />
                        <Info
                            label="Booking Fee"
                            value={money(row.booking_fee)}
                        />
                        <Info
                            label="Termasuk DP"
                            value={row.booking_fee_includes_dp ? "Ya" : "Tidak"}
                        />
                        <Info label="Uang Muka" value={money(row.uang_muka)} />
                        <Info
                            label="Jatuh Tempo DP"
                            value={date(row.tanggal_jatuh_tempo_dp)}
                        />
                        <Info
                            label="Jatuh Tempo Angsuran"
                            value={date(row.tanggal_jatuh_tempo_angsuran)}
                        />
                        {row.jumlah_termin && (
                            <>
                                <Info
                                    label="Jumlah Termin"
                                    value={`${row.jumlah_termin} kali`}
                                />
                                <Info
                                    label="Nominal per Termin"
                                    value={money(row.nominal_termin)}
                                />
                            </>
                        )}
                        {row.metode_key === "kpr_bank" && (
                            <>
                                <Info
                                    label="Bank"
                                    value={row.bank_kredit}
                                    icon={Landmark}
                                />
                                <Info label="Cabang" value={d.bank_branch} />
                                <Info
                                    label="Produk Kredit"
                                    value={d.bank_product}
                                />
                                <Info
                                    label="Tenor / Bunga"
                                    value={`${row.kpr_tenor_bulan || "-"} bulan / ${row.kpr_bunga_tahunan || 0}%`}
                                />
                            </>
                        )}
                    </div>
                    {(row.penambahan_tanah || row.penambahan_lain_lain) && (
                        <div className="mt-5 rounded-xl bg-slate-50 p-4 text-sm">
                            <b>Rincian penambahan</b>
                            <div className="mt-2 grid gap-2 md:grid-cols-2">
                                {row.penambahan_tanah && (
                                    <p>
                                        Tanah: {row.penambahan_tanah} —{" "}
                                        {money(row.total_penambahan_tanah)}
                                    </p>
                                )}
                                {row.penambahan_lain_lain && (
                                    <p>
                                        Lain-lain: {row.penambahan_lain_lain} —{" "}
                                        {money(row.total_penambahan_lain_lain)}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                </section>

                <div className="grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
                    <section className="rounded-2xl border bg-white/90 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="text-xl font-black">
                                    Lampiran Dasar SPR
                                </h2>
                                <p className="text-sm text-ink-soft">
                                    Hanya dokumen dasar pemesanan. Persyaratan pembiayaan muncul pada tahap penjualan terkait.
                                </p>
                            </div>
                            <FileCheck2 />
                        </div>
                        <div className="mt-4 grid gap-3">
                            {requirements.map((doc) => (
                                <div
                                    className={`rounded-xl border p-4 ${doc.uploaded ? "border-emerald-200 bg-emerald-50" : doc.required ? "border-red-200 bg-red-50" : "bg-slate-50"}`}
                                    key={`${doc.document_id}-${doc.party_scope}`}
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <b>{doc.label}</b>
                                        <span className="text-xs font-black">
                                            {doc.uploaded
                                                ? "LENGKAP"
                                                : doc.required
                                                  ? "WAJIB · BELUM ADA"
                                                  : "OPSIONAL"}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-ink-soft">
                                        Untuk {doc.party_scope} · {doc.source}
                                    </p>
                                </div>
                            ))}
                        </div>
                        <h3 className="mt-6 font-black">
                            Lampiran Terhubung ({row.berkas_count})
                        </h3>
                        <div className="mt-3 grid gap-2">
                            {row.berkas?.map((doc) => (
                                <a
                                    href={
                                        doc.path_file
                                            ? `/media/${doc.path_file}`
                                            : "#"
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                    className="flex items-center justify-between rounded-lg border p-3 hover:border-sky-400"
                                    key={doc.id}
                                >
                                    <span>
                                        <b className="block">
                                            {doc.dokumen_label}
                                        </b>
                                        <small>
                                            {doc.nama_file ||
                                                doc.keterangan ||
                                                "-"}
                                        </small>
                                    </span>
                                    <Download size={17} />
                                </a>
                            ))}
                            {!row.berkas?.length && (
                                <Empty text="Belum ada lampiran yang diunggah." />
                            )}
                        </div>
                    </section>
                    <section className="rounded-2xl border bg-white/90 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="flex items-center gap-3">
                            <span className="rounded-xl bg-sky-100 p-2 text-sky-700">
                                <Printer />
                            </span>
                            <div>
                                <h2 className="text-xl font-black">
                                    Dokumen Surat Pemesanan Rumah
                                </h2>
                                <p className="text-sm text-ink-soft">
                                    Hanya dokumen SPR yang tersedia di halaman ini. PPJB dan BAST dicetak pada tahap prosesnya masing-masing.
                                </p>
                            </div>
                        </div>
                        <div className="mt-5 grid gap-3">
                            {documentTemplates.map((t) => (
                                <a
                                    className="group flex items-center justify-between rounded-xl border p-4 transition hover:border-sky-500 hover:bg-sky-50"
                                    href={`/admin/marketing/spr/${row.id}/dokumen/${t.id}`}
                                    key={t.id}
                                >
                                    <span className="flex items-center gap-3">
                                        <FileText className="text-sky-700" />
                                        <span>
                                            <b className="block">{t.name}</b>
                                            <small className="text-ink-soft">
                                                {t.description}
                                            </small>
                                        </span>
                                    </span>
                                    <Printer size={18} />
                                </a>
                            ))}
                        </div>
                        <div className="mt-6 border-t pt-5">
                            <h3 className="font-black">Catatan SPR</h3>
                            <p className="mt-2 whitespace-pre-wrap rounded-xl bg-slate-50 p-4 text-sm leading-6">
                                {row.catatan || "Tidak ada catatan."}
                            </p>
                            <div className="mt-4 grid grid-cols-2 gap-3 text-xs text-ink-soft">
                                <p>
                                    Dibuat oleh
                                    <br />
                                    <b className="text-ink">{row.created_by}</b>
                                </p>
                                <p>
                                    Dibuat pada
                                    <br />
                                    <b className="text-ink">{row.created_at}</b>
                                </p>
                                <p>
                                    Terakhir diperbarui
                                    <br />
                                    <b className="text-ink">{row.updated_at}</b>
                                </p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}
function Card({ title, icon: Icon, children }) {
    return (
        <section className="rounded-2xl border bg-white/90 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="mb-5 flex items-center gap-3">
                <span className="rounded-xl bg-sky-100 p-2 text-sky-700">
                    <Icon />
                </span>
                <h2 className="text-xl font-black">{title}</h2>
            </div>
            <div className="grid gap-5 md:grid-cols-2">{children}</div>
        </section>
    );
}
function Info({ label, value, wide, icon: Icon }) {
    return (
        <div className={wide ? "md:col-span-2" : ""}>
            <p className="flex items-center gap-1 text-[11px] font-extrabold uppercase tracking-wider text-ink-soft">
                {Icon && <Icon size={13} />} {label}
            </p>
            <p className="mt-1 whitespace-pre-wrap font-bold">
                {value === null || value === undefined || value === ""
                    ? "-"
                    : String(value)}
            </p>
        </div>
    );
}
function Stat({ label, value, className = "" }) {
    return (
        <div
            className={`rounded-xl border p-3 ${className || "border-slate-200 bg-slate-50"}`}
        >
            <p className="text-[10px] font-black uppercase tracking-wider opacity-70">
                {label}
            </p>
            <p className="mt-1 text-sm font-black">{value || "-"}</p>
        </div>
    );
}
function MoneyStat({ label, value, strong }) {
    return (
        <div
            className={`rounded-xl border p-4 ${strong ? "border-emerald-200 bg-emerald-50" : "bg-slate-50"}`}
        >
            <p className="text-xs font-black uppercase text-ink-soft">
                {label}
            </p>
            <p className="mt-2 text-xl font-black">{money(value)}</p>
        </div>
    );
}
function Empty({ text }) {
    return (
        <p className="rounded-xl border border-dashed p-5 text-center text-sm text-ink-soft">
            {text}
        </p>
    );
}
Show.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail SPR"}>{page}</AdminLayout>
);
