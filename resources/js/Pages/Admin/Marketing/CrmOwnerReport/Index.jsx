import { Head, router } from "@inertiajs/react";
import {
    AlertTriangle,
    CalendarDays,
    ClipboardCheck,
    Download,
    FileWarning,
    Filter,
    Printer,
    UserRoundX,
    Users,
} from "lucide-react";
import { useState } from "react";
import { Button, Dropdown, Input } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const statusLabel = (value, statuses = []) =>
    statuses.find((item) => item.value === value)?.label ??
    value?.replaceAll("_", " ");
const BarList = ({ title, rows = [] }) => {
    const max = Math.max(1, ...rows.map((row) => Number(row.total)));
    return (
        <section className="rounded-lg border bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
            <h2 className="text-lg font-extrabold">{title}</h2>
            <div className="mt-4 grid gap-3">
                {rows.slice(0, 12).map((row) => (
                    <div key={row.label}>
                        <div className="flex justify-between gap-3 text-xs font-bold">
                            <span>{row.label}</span>
                            <span>{row.total}</span>
                        </div>
                        <div className="mt-1 h-2 overflow-hidden rounded bg-silver-soft">
                            <div
                                className="h-full rounded bg-gold"
                                style={{
                                    width: `${(Number(row.total) / max) * 100}%`,
                                }}
                            />
                        </div>
                    </div>
                ))}
                {!rows.length && (
                    <p className="text-sm text-ink-soft">
                        Belum ada data pada periode ini.
                    </p>
                )}
            </div>
        </section>
    );
};

export default function Index({
    title,
    filters,
    options = {},
    summary,
    pipeline,
    performance,
    customers,
    charts,
}) {
    const [form, setForm] = useState(filters);
    const set = (key, value) =>
        setForm((current) => ({ ...current, [key]: value }));
    const cards = [
        ["Total Customer", summary.total_customers, Users],
        ["Customer Baru Periode", summary.new_customers, CalendarDays],
        ["Belum Ditugaskan", summary.unassigned, UserRoundX],
        ["Tidak Aktif ≥ 7 Hari", summary.stale_leads, AlertTriangle],
        ["Action Plan Terlambat", summary.overdue_actions, ClipboardCheck],
        ["Dokumen Belum Lengkap", summary.incomplete_documents, FileWarning],
    ];
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-widest text-ink-soft">
                        Laporan Read-only Owner
                    </p>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="mt-2 text-3xl font-extrabold">
                                {title}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm text-ink-soft">
                                Monitoring customer dan kinerja marketing
                                berdasarkan status. Halaman ini tidak
                                menyediakan perubahan data.
                            </p>
                        </div>
                        <div className="flex gap-2 print:hidden">
                            <Button
                                variant="outline"
                                type="button"
                                onClick={() => window.print()}
                            >
                                <Printer size={16} /> Cetak
                            </Button>
                            <Button
                                as="a"
                                href={`/admin/marketing/laporan-crm-owner/export?${new URLSearchParams(form).toString()}`}
                            >
                                <Download size={16} /> Ekspor CSV
                            </Button>
                        </div>
                    </div>
                </section>
                <form
                    className="grid gap-4 rounded-lg border bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8 md:grid-cols-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get("/admin/marketing/laporan-crm-owner", form, {
                            preserveState: true,
                            replace: true,
                        });
                    }}
                >
                    <Input
                        type="date"
                        label="Dari"
                        value={form.date_from}
                        onChange={(event) =>
                            set("date_from", event.target.value)
                        }
                    />
                    <Input
                        type="date"
                        label="Sampai"
                        value={form.date_to}
                        onChange={(event) => set("date_to", event.target.value)}
                    />
                    <div className="grid gap-2">
                        <span className="text-sm font-extrabold">
                            Marketing
                        </span>
                        <Dropdown
                            value={String(form.marketing_id)}
                            options={[
                                { value: "", label: "Semua Marketing" },
                                ...(options.marketings || []),
                            ]}
                            onChange={(value) => set("marketing_id", value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <span className="text-sm font-extrabold">
                            Perumahan
                        </span>
                        <Dropdown
                            value={String(form.perumahan_id)}
                            options={[
                                { value: "", label: "Semua Perumahan" },
                                ...(options.perumahans || []),
                            ]}
                            onChange={(value) => set("perumahan_id", value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <span className="text-sm font-extrabold">
                            Sumber Lead
                        </span>
                        <Dropdown
                            value={String(form.source_id || "")}
                            options={[
                                { value: "", label: "Semua Sumber" },
                                ...(options.sources || []),
                            ]}
                            onChange={(value) => set("source_id", value)}
                        />
                    </div>
                    <Button className="self-end" type="submit">
                        <Filter size={16} /> Terapkan
                    </Button>
                </form>
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    {cards.map(([label, value, Icon]) => (
                        <article
                            className="rounded-lg border bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"
                            key={label}
                        >
                            <Icon size={20} className="text-gold-deep" />
                            <p className="mt-4 text-3xl font-extrabold">
                                {value}
                            </p>
                            <p className="mt-1 text-xs font-bold uppercase tracking-wider text-ink-soft">
                                {label}
                            </p>
                        </article>
                    ))}
                </section>
                <section className="grid gap-4 lg:grid-cols-3">
                    <BarList
                        title="Aktivitas Marketing per Hari"
                        rows={charts.daily_activity}
                    />
                    <BarList title="Sumber Lead" rows={charts.lead_sources} />
                    <BarList
                        title="Alasan Pembatalan"
                        rows={charts.cancellations}
                    />
                </section>
                <section className="rounded-lg border bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <h2 className="text-xl font-extrabold">
                        Funnel Berdasarkan Status
                    </h2>
                    <div className="mt-4 grid gap-3 md:grid-cols-4 xl:grid-cols-8">
                        {pipeline.map((item) => (
                            <button
                                type="button"
                                className="rounded-lg border p-4 text-left transition hover:border-gold"
                                key={item.value}
                                onClick={() => {
                                    set("status", item.value);
                                    router.get(
                                        "/admin/marketing/laporan-crm-owner",
                                        { ...form, status: item.value },
                                    );
                                }}
                            >
                                <p className="text-2xl font-extrabold">
                                    {item.total}
                                </p>
                                <p className="mt-1 text-xs font-bold uppercase text-ink-soft">
                                    {item.label}
                                </p>
                                <p className="mt-2 text-xs text-ink-soft">
                                    {item.period_total} periode ini
                                </p>
                            </button>
                        ))}
                    </div>
                </section>
                <section className="overflow-hidden rounded-lg border bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="p-5">
                        <h2 className="text-xl font-extrabold">
                            Kinerja Marketing
                        </h2>
                        <p className="mt-1 text-sm text-ink-soft">
                            Skor mempertimbangkan aktivitas nyata, kepatuhan
                            SLA, closing, dan keterlambatan.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft text-left text-xs uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Marketing</th>
                                    <th className="px-4 py-3">Lead</th>
                                    <th className="px-4 py-3">SLA</th>
                                    <th className="px-4 py-3">Follow-up</th>
                                    <th className="px-4 py-3">Kunjungan</th>
                                    <th className="px-4 py-3">Terverifikasi</th>
                                    <th className="px-4 py-3">Terlambat</th>
                                    <th className="px-4 py-3">Closing</th>
                                    <th className="px-4 py-3">
                                        Capaian Target
                                    </th>
                                    <th className="px-4 py-3">Skor</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {performance.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-3 font-extrabold">
                                            {row.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.new_customers}/
                                            {row.target_lead || "-"}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.sla_percent}%
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.follow_ups}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.visits_completed}/
                                            {row.target_visit ||
                                                row.visits_planned}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.visits_verified}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.actions_overdue}
                                        </td>
                                        <td className="px-4 py-3 font-extrabold">
                                            {row.closing}/
                                            {row.target_closing || "-"}
                                        </td>
                                        <td className="px-4 py-3 font-extrabold">
                                            {row.target_achievement}%
                                        </td>
                                        <td className="px-4 py-3 text-lg font-black">
                                            {row.performance_score}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
                <section className="overflow-hidden rounded-lg border bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="p-5">
                        <h2 className="text-xl font-extrabold">
                            Detail Customer Berdasarkan Status
                        </h2>
                        <p className="mt-1 text-sm text-ink-soft">
                            Diurutkan dari customer yang paling lama tidak
                            memiliki aktivitas.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft text-left text-xs uppercase tracking-wider">
                                <tr>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Marketing</th>
                                    <th className="px-4 py-3">Perumahan</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Usia Customer</th>
                                    <th className="px-4 py-3">Usia Lead</th>
                                    <th className="px-4 py-3">Tidak Aktif</th>
                                    <th className="px-4 py-3">Next Action</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {customers.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-3">
                                            <p className="font-extrabold">
                                                {row.name}
                                            </p>
                                            <p className="text-xs text-ink-soft">
                                                {row.code} · {row.source}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.marketing}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.housing}
                                        </td>
                                        <td className="px-4 py-3 font-bold">
                                            {statusLabel(
                                                row.status,
                                                options.statuses || [],
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.customer_age ?? "-"} tahun
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.lead_age_days} hari
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.inactive_days} hari
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.next_action_at ?? "-"}
                                        </td>
                                    </tr>
                                ))}
                                {customers.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="8"
                                            className="p-10 text-center font-bold text-ink-soft"
                                        >
                                            Tidak ada data pada filter ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Laporan CRM"}>
        {page}
    </AdminLayout>
);
