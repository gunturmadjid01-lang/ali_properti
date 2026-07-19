import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    CircleDashed,
    ClipboardCheck,
    Eye,
    FileText,
    FolderOpen,
    Printer,
    Sparkles,
    UserRound,
} from "lucide-react";
import { useState } from "react";
import { Button } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const emptyLabel = (value) =>
    value === null || value === undefined || value === ""
        ? "Belum ditentukan"
        : value;
const rupiah = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
const stageValue = (step, key, value) => {
    const field = (step.fields ?? []).find((item) => item.name === key);
    if (field?.type === "currency") return rupiah(value);
    if (field?.type === "number")
        return Number(value || 0).toLocaleString("id-ID");
    if (field?.type === "boolean")
        return value === true || String(value) === "1" ? "Ya" : "Tidak";
    if (field?.type === "select")
        return field.options?.[value] ?? String(value);
    return typeof value === "object" ? JSON.stringify(value) : String(value);
};

function ProcessStep({ step }) {
    const tone =
        {
            completed: "border-emerald-300 bg-emerald-50",
            pending_approval: "border-blue-300 bg-blue-50",
            available: "border-amber-300 bg-amber-50",
            in_progress: "border-amber-300 bg-amber-50",
            waiting: "border-slate-200 bg-slate-50",
        }[step.status] || "border-slate-200";
    const completedChecks = (step.checklist ?? []).filter(
        (item) => item.completed,
    ).length;

    return (
        <article className={`rounded-xl border p-5 ${tone}`}>
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 gap-3">
                    <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white font-black">
                        {step.sequence}
                    </div>
                    <div>
                        <p className="text-xs font-black uppercase tracking-wider text-ink-soft">
                            {step.category}
                        </p>
                        <h3 className="text-lg font-black">{step.label}</h3>
                        <p className="mt-1 text-sm text-ink-soft">
                            {step.description}
                        </p>
                    </div>
                </div>
                <div className="text-right">
                    <b>{step.status_label}</b>
                    {step.approval_stage && (
                        <p className="text-xs text-blue-700">
                            {step.approval_stage}
                        </p>
                    )}
                </div>
            </div>
            <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div className="rounded-lg bg-white/75 p-3">
                    <span className="text-ink-soft">PIC</span>
                    <p className="font-bold">{emptyLabel(step.assignee)}</p>
                </div>
                <div className="rounded-lg bg-white/75 p-3">
                    <span className="text-ink-soft">Rencana</span>
                    <p className="font-bold">{emptyLabel(step.planned_date)}</p>
                </div>
                <div className="rounded-lg bg-white/75 p-3">
                    <span className="text-ink-soft">Checklist</span>
                    <p className="font-bold">
                        {completedChecks}/{step.checklist?.length ?? 0} selesai
                    </p>
                </div>
                <div className="rounded-lg bg-white/75 p-3">
                    <span className="text-ink-soft">Dokumen</span>
                    <p className="font-bold">
                        {step.documents?.length ?? 0} berkas
                    </p>
                </div>
            </div>
            {step.status === "waiting" && (
                <p className="mt-3 text-sm text-ink-soft">
                    Menunggu prasyarat proses sebelumnya.
                </p>
            )}
            <div className="mt-4">
                <Button
                    as={Link}
                    href={`/admin/penjualan-terintegrasi/tahapan/${step.id}`}
                    size="sm"
                >
                    {step.status === "waiting"
                        ? "Lihat Persyaratan"
                        : "Buka Proses"}
                </Button>
            </div>
        </article>
    );
}

const stageHasInput = (step) =>
    step.status !== "waiting" &&
    (step.status !== "available" ||
        step.assignee ||
        step.actual_date ||
        step.notes ||
        Object.keys(step.metadata ?? {}).length ||
        step.documents?.length ||
        step.checklist?.some((item) => item.completed));

function SummaryOverview({ summary = {} }) {
    const entries = Object.entries(summary);
    const featuredLabels = [
        "Customer",
        "Perumahan",
        "Unit",
        "Metode Pembayaran",
        "Harga Jual",
        "Status",
    ];
    const featured = featuredLabels
        .map((label) => entries.find(([key]) => key === label))
        .filter(Boolean);
    const details = entries.filter(
        ([label]) => !featuredLabels.includes(label),
    );
    return (
        <section className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
            <div className="grid gap-px bg-silver-deep/50 sm:grid-cols-2 xl:grid-cols-3 dark:bg-white/10">
                {featured.map(([label, value]) => (
                    <div
                        className="bg-white px-5 py-4 dark:bg-graphite"
                        key={label}
                    >
                        <p className="text-[11px] font-black uppercase tracking-[0.14em] text-ink-soft">
                            {label}
                        </p>
                        <p className="mt-1.5 text-base font-black text-ink dark:text-white">
                            {emptyLabel(value)}
                        </p>
                    </div>
                ))}
            </div>
            <div className="border-t border-silver-deep/50 px-5 py-4 dark:border-white/10">
                <div className="grid gap-x-8 gap-y-3 sm:grid-cols-2 xl:grid-cols-3">
                    {details.map(([label, value]) => (
                        <div
                            className="flex items-start justify-between gap-4 border-b border-dashed border-silver-deep/50 pb-2"
                            key={label}
                        >
                            <span className="text-xs font-bold text-ink-soft">
                                {label}
                            </span>
                            <span className="text-right text-sm font-extrabold">
                                {emptyLabel(value)}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function EnteredStages({ steps = [] }) {
    const entered = steps.filter(stageHasInput);
    const completed = steps.filter(
        (step) => step.status === "completed",
    ).length;
    const progress = steps.length
        ? Math.round((completed / steps.length) * 100)
        : 0;
    return (
        <section className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
            <header className="border-b border-silver-deep/50 bg-gradient-to-r from-emerald-50 via-white to-amber-50 px-5 py-5 dark:border-white/10 dark:from-emerald-500/10 dark:via-transparent dark:to-amber-500/10">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700">
                            <Sparkles size={15} /> Ringkasan Tahapan
                        </p>
                        <h2 className="mt-1 text-xl font-black">
                            Tahap yang sudah diinput
                        </h2>
                        <p className="mt-1 text-sm text-ink-soft">
                            Menampilkan pekerjaan yang sudah memiliki PIC, data,
                            checklist, dokumen, atau progres.
                        </p>
                    </div>
                    <div className="min-w-64">
                        <div className="mb-2 flex justify-between text-xs font-bold">
                            <span>
                                {completed}/{steps.length} tahap selesai
                            </span>
                            <span>{progress}%</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-silver-deep/70">
                            <div
                                className="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-700"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                    </div>
                </div>
            </header>
            <div className="grid gap-0">
                {entered.map((step, index) => {
                    const metadata = Object.entries(step.metadata ?? {}).filter(
                        ([, value]) =>
                            value !== null &&
                            value !== undefined &&
                            value !== "",
                    );
                    const checks = (step.checklist ?? []).filter(
                        (item) => item.completed,
                    ).length;
                    return (
                        <article
                            className="relative border-b border-silver-deep/50 px-5 py-5 last:border-b-0 dark:border-white/10"
                            key={step.id}
                        >
                            <div className="flex gap-4">
                                <div className="relative flex shrink-0 flex-col items-center">
                                    <span
                                        className={`grid h-10 w-10 place-items-center rounded-full font-black text-white shadow-sm ${step.status === "completed" ? "bg-emerald-600" : step.status === "pending_approval" ? "bg-blue-600" : "bg-amber-500"}`}
                                    >
                                        {step.status === "completed" ? (
                                            <CheckCircle2 size={19} />
                                        ) : (
                                            step.sequence
                                        )}
                                    </span>
                                    {index < entered.length - 1 && (
                                        <span className="absolute top-11 h-[calc(100%+0.5rem)] w-px bg-silver-deep" />
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <p className="text-[11px] font-black uppercase tracking-wider text-ink-soft">
                                                {step.category}
                                            </p>
                                            <h3 className="text-lg font-black">
                                                {step.label}
                                            </h3>
                                        </div>
                                        <span className="w-fit rounded-full bg-silver-soft px-3 py-1 text-xs font-black dark:bg-white/10">
                                            {step.status_label}
                                            {step.approval_stage
                                                ? ` · ${step.approval_stage}`
                                                : ""}
                                        </span>
                                    </div>
                                    <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs font-bold text-ink-soft">
                                        <span className="flex items-center gap-1.5">
                                            <UserRound size={14} />
                                            {emptyLabel(step.assignee)}
                                        </span>
                                        <span className="flex items-center gap-1.5">
                                            <CalendarDays size={14} />
                                            {step.actual_date ||
                                                step.planned_date ||
                                                "Tanggal belum diisi"}
                                        </span>
                                        <span className="flex items-center gap-1.5">
                                            <ClipboardCheck size={14} />
                                            {checks}/
                                            {step.checklist?.length ?? 0}{" "}
                                            checklist
                                        </span>
                                        <span className="flex items-center gap-1.5">
                                            <FolderOpen size={14} />
                                            {step.documents?.length ?? 0}{" "}
                                            dokumen
                                        </span>
                                    </div>
                                    {metadata.length > 0 && (
                                        <div className="mt-4 grid gap-2 rounded-xl bg-silver-soft/70 p-3 sm:grid-cols-2 xl:grid-cols-3 dark:bg-white/5">
                                            {metadata
                                                .slice(0, 6)
                                                .map(([key, value]) => (
                                                    <div key={key}>
                                                        <p className="text-[10px] font-black uppercase tracking-wider text-ink-soft">
                                                            {key.replaceAll(
                                                                "_",
                                                                " ",
                                                            )}
                                                        </p>
                                                        <p className="mt-0.5 text-sm font-bold">
                                                            {stageValue(
                                                                step,
                                                                key,
                                                                value,
                                                            )}
                                                        </p>
                                                    </div>
                                                ))}
                                        </div>
                                    )}
                                    {step.notes && (
                                        <p className="mt-3 rounded-lg border-l-4 border-gold bg-champagne/25 px-3 py-2 text-sm">
                                            {step.notes}
                                        </p>
                                    )}
                                    <Button
                                        as={Link}
                                        href={`/admin/penjualan-terintegrasi/tahapan/${step.id}`}
                                        variant="outline"
                                        size="sm"
                                        className="mt-4"
                                    >
                                        Lihat detail tahap
                                    </Button>
                                </div>
                            </div>
                        </article>
                    );
                })}
                {entered.length === 0 && (
                    <div className="px-6 py-12 text-center">
                        <CircleDashed
                            className="mx-auto text-ink-soft"
                            size={34}
                        />
                        <h3 className="mt-3 font-black">
                            Belum ada tahap yang diinput
                        </h3>
                        <p className="mt-1 text-sm text-ink-soft">
                            Mulai isi tahap pada tab Proses sampai Huni.
                        </p>
                    </div>
                )}
            </div>
        </section>
    );
}

export default function Show({
    title,
    indexUrl,
    record = {},
    tabs = [],
    section,
}) {
    const [activeTab, setActiveTab] = useState(tabs[0] ?? "Ringkasan");
    const scheduleTabs = [
        "Tahapan Pembayaran",
        "Jadwal & Tagihan",
        "Jadwal Pembayaran",
        "Tagihan",
        "Jadwal Angsuran",
        "Piutang & Tunggakan",
        "Monitoring Pencairan",
    ];
    const timelineTabs = [
        "Versi & Riwayat",
        "Histori",
        "SLIK",
        "Appraisal / Survei",
        "Keputusan Bank",
        "SP3K",
        "Jadwal & Pelaksanaan Akad",
        "Perubahan Bank",
    ];
    const showSchedules = scheduleTabs.includes(activeTab);
    const showTimeline = timelineTabs.includes(activeTab);
    const detailRows =
        activeTab === "Pembayaran"
            ? record.payments
            : activeTab === "Pembangunan"
              ? record.construction
              : activeTab === "Serah Terima"
                ? record.handover
                : activeTab === "After Sales"
                  ? record.afterSales
                  : null;
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-ink via-graphite to-[#26343b] px-6 py-6 text-white shadow-lg">
                    <div className="absolute -right-20 -top-28 h-72 w-72 rounded-full bg-gold/15 blur-3xl" />
                    <div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p className="text-[11px] font-black uppercase tracking-[0.18em] text-champagne">
                                Detail Proses Terintegrasi
                            </p>
                            <h1 className="mt-2 font-display text-3xl font-black md:text-4xl">
                                {record.heading}
                            </h1>
                            <p className="mt-2 text-sm font-semibold text-white/60">
                                {record.subtitle}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                as={Link}
                                href={indexUrl}
                                variant="outline"
                                className="border-white/15 bg-white/5 text-white hover:bg-white/10"
                            >
                                <ArrowLeft size={16} /> Kembali
                            </Button>
                            <Button
                                as="a"
                                href={`${indexUrl}/records/${record.id}/preview`}
                                target="_blank"
                                variant="outline"
                                className="border-white/15 bg-white/5 text-white hover:bg-white/10"
                            >
                                <Eye size={16} /> Pratinjau
                            </Button>
                            <Button
                                as="a"
                                href={`${indexUrl}/records/${record.id}/print`}
                                target="_blank"
                            >
                                <Printer size={16} /> Cetak
                            </Button>
                        </div>
                    </div>
                </section>
                <nav className="flex gap-1 overflow-x-auto rounded-2xl border border-silver-deep/60 bg-white p-2 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                    {tabs.map((tab) => (
                        <button
                            className={`shrink-0 rounded-xl px-4 py-2.5 text-xs font-black transition ${activeTab === tab ? "bg-ink text-white shadow-sm dark:bg-white dark:text-ink" : "text-ink-soft hover:bg-silver-soft dark:hover:bg-white/10"}`}
                            key={tab}
                            type="button"
                            onClick={() => setActiveTab(tab)}
                        >
                            {tab}
                        </button>
                    ))}
                </nav>
                {activeTab === "Ringkasan" && (
                    <div className="grid gap-5">
                        <SummaryOverview summary={record.summary} />
                        {record.processSteps?.length > 0 && (
                            <EnteredStages steps={record.processSteps} />
                        )}
                    </div>
                )}
                {activeTab === "Proses sampai Huni" && (
                    <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <h2 className="text-2xl font-black">
                            Perjalanan Pelanggan sampai Menempati Unit
                        </h2>
                        <p className="mt-1 text-ink-soft">
                            Halaman ini hanya menampilkan progres. Buka tahap
                            terkait untuk mengisi data, checklist, dokumen, dan
                            approval.
                        </p>
                        <div className="mt-6 grid gap-4">
                            {(record.processSteps ?? []).map((step) => (
                                <ProcessStep step={step} key={step.id} />
                            ))}
                        </div>
                    </section>
                )}
                {showSchedules && (
                    <section className="overflow-hidden rounded-xl border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="border-b p-5">
                            <h2 className="text-xl font-black">{activeTab}</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-silver-soft/70 text-left text-xs uppercase">
                                    <tr>
                                        <th className="px-5 py-4">
                                            Invoice / Uraian
                                        </th>
                                        <th className="px-5 py-4">
                                            Jatuh Tempo
                                        </th>
                                        <th className="px-5 py-4">Nominal</th>
                                        <th className="px-5 py-4">Dibayar</th>
                                        <th className="px-5 py-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(record.schedules ?? []).map(
                                        (row, index) => (
                                            <tr
                                                className="border-t"
                                                key={index}
                                            >
                                                <td className="px-5 py-4">
                                                    <b>
                                                        {row.invoice ||
                                                            row.description}
                                                    </b>
                                                    <div>
                                                        {row.invoice &&
                                                            row.description}
                                                    </div>
                                                    {row.url && (
                                                        <Button
                                                            as={Link}
                                                            href={row.url}
                                                            size="sm"
                                                            variant="outline"
                                                            className="mt-2"
                                                        >
                                                            Pratinjau / Cetak
                                                        </Button>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    {row.due_date}
                                                </td>
                                                <td className="px-5 py-4">
                                                    {row.amount}
                                                </td>
                                                <td className="px-5 py-4">
                                                    {row.paid}
                                                </td>
                                                <td className="px-5 py-4">
                                                    {row.status}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                    {!record.schedules?.length && (
                                        <tr>
                                            <td
                                                className="px-5 py-10 text-center text-ink-soft"
                                                colSpan="5"
                                            >
                                                Belum ada jadwal resmi. Jadwal
                                                dibuat setelah kontrak mendapat
                                                persetujuan akhir.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
                {showTimeline && (
                    <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <h2 className="text-xl font-black">{activeTab}</h2>
                        <div className="mt-5 grid gap-3">
                            {(record.timeline ?? []).map((item, index) => (
                                <div
                                    className="flex gap-4 rounded-lg border p-4"
                                    key={index}
                                >
                                    <CheckCircle2
                                        className="mt-0.5 text-emerald-600"
                                        size={20}
                                    />
                                    <div>
                                        <p className="font-black">
                                            {item.title}
                                        </p>
                                        <p className="text-xs text-ink-soft">
                                            {item.date}
                                        </p>
                                        <p className="mt-1 text-sm">
                                            {emptyLabel(item.notes)}
                                        </p>
                                    </div>
                                </div>
                            ))}
                            {!record.timeline?.length && (
                                <p className="rounded-lg border border-dashed p-8 text-center text-ink-soft">
                                    Belum ada histori pada tahap ini.
                                </p>
                            )}
                        </div>
                    </section>
                )}
                {detailRows && (
                    <section className="rounded-xl border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <h2 className="text-xl font-black">{activeTab}</h2>
                        <div className="mt-5 grid gap-3">
                            {detailRows.map((item, index) => (
                                <div
                                    className="rounded-lg border p-4"
                                    key={index}
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-black">
                                                {item.label}
                                            </p>
                                            <p className="text-xs text-ink-soft">
                                                {item.date}
                                            </p>
                                        </div>
                                        <b>{item.value || item.status}</b>
                                    </div>
                                    {item.notes && (
                                        <p className="mt-2 text-sm">
                                            {item.notes}
                                        </p>
                                    )}
                                    {item.url && (
                                        <Button
                                            as={Link}
                                            href={item.url}
                                            variant="outline"
                                            size="sm"
                                            className="mt-3"
                                        >
                                            Pratinjau / Cetak
                                        </Button>
                                    )}
                                </div>
                            ))}
                            {!detailRows.length && (
                                <p className="rounded-lg border border-dashed p-8 text-center text-ink-soft">
                                    Belum ada data {activeTab.toLowerCase()}{" "}
                                    untuk transaksi ini.
                                </p>
                            )}
                        </div>
                    </section>
                )}
                {activeTab !== "Ringkasan" &&
                    !showSchedules &&
                    !showTimeline &&
                    !detailRows && (
                        <section className="rounded-xl border border-white/80 bg-white/80 p-8 text-center shadow-soft dark:border-white/10 dark:bg-white/8">
                            <FileText
                                className="mx-auto text-ink-soft"
                                size={32}
                            />
                            <h2 className="mt-3 text-xl font-black">
                                {activeTab}
                            </h2>
                            <p className="mt-2 text-ink-soft">
                                Belum ada data pada bagian ini.
                            </p>
                        </section>
                    )}
            </div>
        </>
    );
}
Show.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail Penjualan"}>
        {page}
    </AdminLayout>
);
