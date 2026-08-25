import { Head, Link } from "@inertiajs/react";
import {
    ArrowRight,
    CalendarClock,
    CheckCircle2,
    Clock3,
    FileWarning,
    LayoutGrid,
    MessageCircle,
    PhoneCall,
    Users,
} from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";

function money(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function Hero({ title, description, points = [], roles = [] }) {
    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-gold-deep">
                        Marketing Workspace
                    </p>
                    <h2 className="mt-2 text-3xl font-extrabold text-ink dark:text-white">
                        {title}
                    </h2>
                    <p className="mt-3 max-w-3xl text-sm leading-7 text-ink-soft dark:text-white/62">
                        {description}
                    </p>
                </div>
                <div className="rounded-2xl border border-silver-deep/60 bg-silver-soft px-4 py-3 text-sm font-bold text-ink-soft dark:border-white/10 dark:bg-white/6 dark:text-white/70">
                    <div className="flex items-center gap-2">
                        <PhoneCall size={16} />
                        {roles.length ? roles.join(", ") : "Tidak ada role"}
                    </div>
                </div>
            </div>

            <div className="mt-6 grid gap-3 md:grid-cols-2">
                {points.map((point) => (
                    <div
                        className="flex items-center gap-3 rounded-2xl border border-silver-deep/60 bg-silver-soft/70 px-4 py-3 text-sm font-bold text-ink/78 dark:border-white/10 dark:bg-white/6 dark:text-white/72"
                        key={point}
                    >
                        <CheckCircle2 className="text-gold-deep" size={18} />
                        {point}
                    </div>
                ))}
            </div>
        </section>
    );
}

function StatCard({ label, value, Icon }) {
    return (
        <article className="rounded-3xl border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex items-center justify-between">
                <span className="grid h-11 w-11 place-items-center rounded-2xl bg-silver text-ink-soft dark:bg-white/10 dark:text-white/70">
                    <Icon size={20} />
                </span>
            </div>
            <strong className="mt-4 block text-2xl font-extrabold text-ink dark:text-white">
                {value}
            </strong>
            <p className="mt-1 text-sm font-bold text-ink-soft dark:text-white/58">
                {label}
            </p>
        </article>
    );
}

function MenuCard({ menu }) {
    return (
        <Link
            className="group block rounded-3xl border border-silver-deep/60 bg-white/80 p-5 shadow-soft transition hover:-translate-y-0.5 hover:border-gold/70 hover:shadow-[0_16px_40px_rgba(31,37,43,0.12)] dark:border-white/10 dark:bg-white/6"
            href={menu.href ?? "#"}
        >
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h3 className="text-lg font-extrabold text-ink dark:text-white">
                        {menu.label}
                    </h3>
                    <p className="mt-2 text-sm leading-6 text-ink-soft dark:text-white/60">
                        {menu.description}
                    </p>
                </div>
                <div className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-champagne to-gold text-gold-deep">
                    <ArrowRight
                        className="transition group-hover:translate-x-0.5"
                        size={18}
                    />
                </div>
            </div>
        </Link>
    );
}

function DataTable({ title, rows = [], columns = [] }) {
    if (rows.length === 0) {
        return null;
    }

    return (
        <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                <h3 className="text-base font-extrabold text-ink dark:text-white">
                    {title}
                </h3>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                    <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    className="px-4 py-3 font-extrabold"
                                    key={column.key}
                                >
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                        {rows.map((row) => (
                            <tr
                                className="transition hover:bg-silver/70 dark:hover:bg-white/5"
                                key={row.id}
                            >
                                {columns.map((column) => (
                                    <td
                                        className="px-4 py-3 font-semibold text-ink/80 dark:text-white/72"
                                        key={column.key}
                                    >
                                        {row[column.key] ?? "-"}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function TodayWorkspace({ today = {} }) {
    const counts = today.counts ?? {};
    const cards = [
        ["Perlu Ditindaklanjuti", counts.due ?? 0, Clock3],
        ["Terlambat", counts.overdue ?? 0, FileWarning],
        ["Kunjungan Hari Ini", counts.visits ?? 0, CalendarClock],
        ["Dokumen Belum Lengkap", counts.incomplete_documents ?? 0, LayoutGrid],
    ];

    return (
        <section className="grid gap-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-xs font-extrabold uppercase tracking-widest text-gold-deep">
                        Meja Kerja Hari Ini
                    </p>
                    <h2 className="text-2xl font-extrabold">
                        Yang perlu Anda kerjakan sekarang
                    </h2>
                </div>
                <Link
                    href="/admin/marketing/jejak-follow-up/create"
                    className="text-sm font-extrabold text-gold-deep"
                >
                    + Catat follow-up
                </Link>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map(([label, value, Icon]) => (
                    <StatCard
                        key={label}
                        label={label}
                        value={value}
                        Icon={Icon}
                    />
                ))}
            </div>
            <div className="grid gap-4 xl:grid-cols-[1.45fr_0.8fr]">
                <div className="overflow-hidden rounded-3xl border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/6">
                    <div className="border-b border-silver-deep/60 p-5 dark:border-white/10">
                        <h3 className="font-extrabold">
                            Prioritas Prospek Saya
                        </h3>
                        <p className="mt-1 text-sm text-ink-soft">
                            Lead baru, follow-up jatuh tempo, dan yang
                            terlambat.
                        </p>
                    </div>
                    <div className="divide-y divide-silver-deep/50 dark:divide-white/10">
                        {(today.customers ?? []).map((row) => (
                            <div
                                key={row.id}
                                className={`grid gap-3 p-4 sm:grid-cols-[1fr_auto] sm:items-center ${row.overdue ? "bg-red-50/70 dark:bg-red-500/5" : ""}`}
                            >
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link
                                            href={row.show_url}
                                            className="font-extrabold hover:text-gold-deep"
                                        >
                                            {row.name}
                                        </Link>
                                        <span className="rounded-full bg-silver px-2 py-1 text-[11px] font-extrabold">
                                            {row.status}
                                        </span>
                                        <span className="text-xs font-bold text-gold-deep">
                                            Skor {row.score}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs font-semibold text-ink-soft">
                                        {row.next_action_at
                                            ? `${row.overdue ? "Terlambat · " : ""}${row.next_action_at}`
                                            : "Lead baru belum dihubungi"}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {row.phone && (
                                        <a
                                            className="rounded-xl border px-3 py-2 text-xs font-extrabold"
                                            href={`tel:${row.phone}`}
                                        >
                                            <PhoneCall size={14} />
                                        </a>
                                    )}
                                    {row.phone && (
                                        <a
                                            className="rounded-xl border px-3 py-2 text-xs font-extrabold text-emerald-700"
                                            href={`https://wa.me/${String(row.phone).replace(/\D/g, "").replace(/^0/, "62")}`}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <MessageCircle size={14} />
                                        </a>
                                    )}
                                    <Link
                                        className="rounded-xl bg-ink px-3 py-2 text-xs font-extrabold text-white dark:bg-white dark:text-ink"
                                        href={row.follow_up_url}
                                    >
                                        Catat hasil
                                    </Link>
                                </div>
                            </div>
                        ))}
                        {!(today.customers ?? []).length && (
                            <p className="p-6 text-sm font-semibold text-ink-soft">
                                Tidak ada follow-up jatuh tempo. Bagus,
                                pekerjaan hari ini terkendali.
                            </p>
                        )}
                    </div>
                </div>
                <div className="grid gap-4">
                    <div className="rounded-3xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/6">
                        <h3 className="font-extrabold">Kunjungan</h3>
                        <div className="mt-3 grid gap-2">
                            {(today.visits ?? []).map((row) => (
                                <Link
                                    href={row.url}
                                    key={row.id}
                                    className="rounded-2xl bg-silver-soft p-3 text-sm"
                                >
                                    <strong>
                                        {row.at} · {row.customer}
                                    </strong>
                                    <span className="mt-1 block text-xs text-ink-soft">
                                        {row.location || "Lokasi belum dicatat"}
                                    </span>
                                </Link>
                            ))}
                            {!(today.visits ?? []).length && (
                                <p className="text-sm text-ink-soft">
                                    Tidak ada kunjungan hari ini.
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="rounded-3xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/6">
                        <h3 className="font-extrabold">Pengingat</h3>
                        <div className="mt-3 grid gap-2">
                            {(today.reminders ?? []).map((row) => (
                                <div
                                    key={row.id}
                                    className={`rounded-2xl p-3 text-sm ${row.overdue ? "bg-red-50 dark:bg-red-500/10" : "bg-silver-soft"}`}
                                >
                                    <strong>{row.title}</strong>
                                    <span className="mt-1 block text-xs text-ink-soft">
                                        {row.customer || "Tanpa customer"} ·{" "}
                                        {row.at}
                                    </span>
                                </div>
                            ))}
                            {!(today.reminders ?? []).length && (
                                <p className="text-sm text-ink-soft">
                                    Tidak ada pengingat tertunda.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

function DailyWorkdesk({ today = {}, roles = [] }) {
    const counts = today.counts ?? {};
    const actions = today.quick_actions ?? [];
    return (
        <>
            <Head title="Meja Kerja Hari Ini" />
            <div className="grid gap-6">
                <section className="rounded-3xl bg-gradient-to-br from-[#20262d] to-[#11161c] p-6 text-white shadow-soft md:p-8">
                    <p className="text-xs font-black uppercase tracking-[0.18em] text-amber-300">
                        {today.eyebrow || "Buku Kerja Harian Marketing"}
                    </p>
                    <div className="mt-3 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h1 className="text-3xl font-black">
                                {today.heading ||
                                    "Apa yang Anda kerjakan hari ini?"}
                            </h1>
                            <p className="mt-2 max-w-3xl text-sm leading-6 text-white/65">
                                {today.description ||
                                    "Masukkan setiap prospek, follow-up, kunjungan, survei, dan aktivitas lapangan. Hasil serta rencana berikutnya menjadi bukti kerja dan bahan monitoring atasan."}
                            </p>
                        </div>
                        <span className="rounded-2xl border border-white/15 px-4 py-3 text-xs font-bold text-white/70">
                            {roles.join(", ") || "marketing"}
                        </span>
                    </div>
                </section>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {actions.map((action, index) => {
                        const Icon =
                            [
                                Users,
                                PhoneCall,
                                CalendarClock,
                                LayoutGrid,
                                Clock3,
                            ][index] ?? ArrowRight;
                        return (
                            <Link
                                key={action.label}
                                href={action.href}
                                className="group rounded-2xl border border-silver-deep/60 bg-white/85 p-5 shadow-soft transition hover:-translate-y-0.5 hover:border-gold dark:border-white/10 dark:bg-white/7"
                            >
                                <span className="grid h-11 w-11 place-items-center rounded-xl bg-amber-100 text-amber-700">
                                    <Icon size={20} />
                                </span>
                                <h2 className="mt-4 font-black">
                                    {action.label}
                                </h2>
                                <p className="mt-1 text-xs leading-5 text-ink-soft">
                                    {action.description}
                                </p>
                            </Link>
                        );
                    })}
                </section>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        ["Aktivitas Dicatat", counts.activities ?? 0],
                        ["Harus Ditindaklanjuti", counts.due ?? 0],
                        ["Terlambat", counts.overdue ?? 0],
                        ["Kunjungan Hari Ini", counts.visits ?? 0],
                        [
                            "Berkas Belum Lengkap",
                            counts.incomplete_documents ?? 0,
                        ],
                    ].map(([label, value]) => (
                        <article
                            key={label}
                            className="rounded-2xl border bg-white/80 p-4 dark:border-white/10 dark:bg-white/7"
                        >
                            <strong className="text-2xl font-black">
                                {value}
                            </strong>
                            <p className="mt-1 text-xs font-bold text-ink-soft">
                                {label}
                            </p>
                        </article>
                    ))}
                </section>

                <section className="overflow-hidden rounded-3xl border border-white/80 bg-white/85 shadow-soft dark:border-white/10 dark:bg-white/7">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b p-5 dark:border-white/10">
                        <div>
                            <h2 className="text-xl font-black">
                                Aktivitas Hari Ini
                            </h2>
                            <p className="text-sm text-ink-soft">
                                Jejak kerja yang sudah dicatat pada modul
                                operasional.
                            </p>
                        </div>
                        {today.monitoring_url && (
                            <Link
                                href={today.monitoring_url}
                                className="text-sm font-black text-gold-deep"
                            >
                                Monitoring tim →
                            </Link>
                        )}
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft text-left text-xs uppercase text-ink-soft">
                                <tr>
                                    <th className="p-4">Waktu</th>
                                    <th>Aktivitas</th>
                                    <th>Pelanggan</th>
                                    <th>Hasil / Tujuan</th>
                                    <th>Status</th>
                                    <th className="p-4 text-right">Detail</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y dark:divide-white/10">
                                {(today.activities ?? []).map((row) => (
                                    <tr key={row.id}>
                                        <td className="p-4 font-bold">
                                            {row.time || "-"}
                                        </td>
                                        <td className="font-black">
                                            {row.type}
                                        </td>
                                        <td>
                                            {row.customer || "Aktivitas umum"}
                                        </td>
                                        <td className="max-w-md py-3 text-ink-soft">
                                            {row.result || "Belum ada hasil"}
                                        </td>
                                        <td>
                                            <span className="rounded-full bg-silver px-2 py-1 text-xs font-bold">
                                                {String(
                                                    row.status || "draft",
                                                ).replaceAll("_", " ")}
                                            </span>
                                        </td>
                                        <td className="p-4 text-right">
                                            <Link
                                                href={row.url}
                                                className="font-black text-gold-deep"
                                            >
                                                Buka
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {!(today.activities ?? []).length && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="p-10 text-center text-sm font-semibold text-ink-soft"
                                        >
                                            Belum ada aktivitas dicatat hari
                                            ini. Gunakan tombol input di atas
                                            untuk memulai laporan kerja.
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

function Index({
    title,
    description,
    points = [],
    menus = [],
    featured = [],
    summary = {},
    roles = [],
    customers = [],
    progressRows = [],
    today = null,
}) {
    if (today) {
        return <DailyWorkdesk today={today} roles={roles} />;
    }
    const stats = [
        ["Total Pelanggan", summary.total_customers ?? 0, Users],
        ["Prospek Tinggi", summary.high_prospects ?? 0, PhoneCall],
        ["Berkas Pelanggan", summary.documents ?? 0, LayoutGrid],
        ["Kemajuan 30 Hari", summary.recent_progress ?? 0, ArrowRight],
    ];

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <Hero
                    title={title}
                    description={description}
                    points={points}
                    roles={roles}
                />

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {stats.map(([label, value, Icon]) => (
                        <StatCard
                            key={label}
                            label={label}
                            value={typeof value === "number" ? value : value}
                            Icon={Icon}
                        />
                    ))}
                </section>

                {today && <TodayWorkspace today={today} />}

                <section className="grid gap-6 xl:grid-cols-[1fr_0.92fr]">
                    <div className="grid gap-4">
                        {featured.length > 0 && (
                            <div className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                                <h3 className="text-lg font-extrabold text-ink dark:text-white">
                                    Fokus Marketing
                                </h3>
                                <div className="mt-4 grid gap-3">
                                    {featured.map((item) => (
                                        <div
                                            className="rounded-2xl bg-silver-soft px-4 py-3 dark:bg-white/6"
                                            key={item.label}
                                        >
                                            <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft dark:text-white/45">
                                                {item.label}
                                            </p>
                                            <p className="mt-1 text-sm font-bold text-ink dark:text-white">
                                                {item.value}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <DataTable
                            title="Calon Konsumen Terbaru"
                            rows={customers}
                            columns={[
                                { key: "nama", label: "Nama" },
                                { key: "no_identitas", label: "No Identitas" },
                                { key: "telepon", label: "Telepon" },
                                { key: "pekerjaan", label: "Pekerjaan" },
                                { key: "penghasilan", label: "Penghasilan" },
                            ]}
                        />

                        <DataTable
                            title="Kemajuan Pembangunan Terbaru"
                            rows={progressRows}
                            columns={[
                                { key: "tanggal", label: "Tanggal" },
                                { key: "proyek", label: "Proyek" },
                                { key: "unit", label: "Unit" },
                                { key: "persentase", label: "Persentase" },
                                { key: "user", label: "Input Oleh" },
                            ]}
                        />
                    </div>

                    <div className="grid gap-4">
                        <section className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <h3 className="text-lg font-extrabold text-ink dark:text-white">
                                Menu Kerja
                            </h3>
                            <p className="mt-2 text-sm leading-7 text-ink-soft dark:text-white/60">
                                Menu di bawah disusun untuk alur kerja marketing
                                yang paling sering dipakai.
                            </p>
                            <div className="mt-5 grid gap-4">
                                {menus.map((menu) => (
                                    <MenuCard key={menu.label} menu={menu} />
                                ))}
                            </div>
                        </section>

                        <section className="rounded-3xl border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                            <h3 className="text-lg font-extrabold text-ink dark:text-white">
                                Aksi Cepat
                            </h3>
                            <div className="mt-4 grid gap-3">
                                <Link
                                    className="rounded-2xl border border-silver-deep/60 bg-silver-soft px-4 py-3 text-sm font-bold text-ink transition hover:bg-silver dark:border-white/10 dark:bg-white/6 dark:text-white"
                                    href="/admin/marketing/calon-konsumen"
                                >
                                    Buka data calon konsumen
                                </Link>
                                <Link
                                    className="rounded-2xl border border-silver-deep/60 bg-silver-soft px-4 py-3 text-sm font-bold text-ink transition hover:bg-silver dark:border-white/10 dark:bg-white/6 dark:text-white"
                                    href="/admin/marketing/laporan"
                                >
                                    Lihat laporan marketing
                                </Link>
                            </div>
                        </section>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Marketing"}>{page}</AdminLayout>
);

export default Index;
