import { Head, Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import {
    Activity,
    AlertTriangle,
    Banknote,
    BarChart3,
    BookOpen,
    Boxes,
    Building2,
    CheckCircle2,
    ChevronRight,
    Clock3,
    FileText,
    HardHat,
    Home,
    KeyRound,
    PackageCheck,
    Eye,
    ReceiptText,
    ShieldCheck,
    ShoppingCart,
    Target,
    TrendingUp,
    UserCheck,
    UserPlus,
    Users,
    WalletCards,
    Warehouse,
    Wrench,
} from "lucide-react";
import AdminLayout from "../../Layouts/AdminLayout";

const iconMap = {
    activity: Activity,
    alert: AlertTriangle,
    book: BookOpen,
    boxes: Boxes,
    building: Building2,
    cart: ShoppingCart,
    check: CheckCircle2,
    clock: Clock3,
    file: FileText,
    "hard-hat": HardHat,
    home: Home,
    key: KeyRound,
    receipt: ReceiptText,
    shield: ShieldCheck,
    trending: TrendingUp,
    "user-check": UserCheck,
    "user-plus": UserPlus,
    users: Users,
    wallet: WalletCards,
    warehouse: Warehouse,
    wrench: Wrench,
    target: Target,
    eye: Eye,
    chart: BarChart3,
};

const accents = {
    users: [
        "from-slate-700 to-slate-900",
        "bg-slate-100 text-slate-700 dark:bg-slate-400/15 dark:text-slate-300",
    ],
    approval: [
        "from-rose-500 to-red-700",
        "bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-300",
    ],
    property: [
        "from-amber-500 to-yellow-700",
        "bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300",
    ],
    marketing: [
        "from-sky-500 to-blue-700",
        "bg-blue-100 text-blue-700 dark:bg-blue-400/15 dark:text-blue-300",
    ],
    warehouse: [
        "from-teal-500 to-emerald-700",
        "bg-teal-100 text-teal-700 dark:bg-teal-400/15 dark:text-teal-300",
    ],
    assets: [
        "from-orange-500 to-amber-700",
        "bg-orange-100 text-orange-700 dark:bg-orange-400/15 dark:text-orange-300",
    ],
    finance: [
        "from-emerald-500 to-green-700",
        "bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300",
    ],
};

const formatValue = (value, format) => {
    const number = Number(value ?? 0);
    if (format === "currency")
        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(number);
    if (format === "percent")
        return `${new Intl.NumberFormat("id-ID", { maximumFractionDigits: 2 }).format(number)}%`;
    if (format === "decimal")
        return new Intl.NumberFormat("id-ID", {
            maximumFractionDigits: 2,
        }).format(number);
    return new Intl.NumberFormat("id-ID").format(number);
};

function StatCard({ stat, sectionKey }) {
    const Icon = iconMap[stat.icon] ?? Activity;
    const [, iconClass] = accents[sectionKey] ?? accents.users;
    return (
        <article className="group rounded-lg border border-white/80 bg-white/82 p-4 shadow-soft transition hover:-translate-y-0.5 hover:shadow-[0_20px_48px_rgba(31,37,43,0.12)] dark:border-white/10 dark:bg-white/7">
            <div className="flex items-start justify-between gap-4">
                <span
                    className={`grid h-9 w-9 shrink-0 place-items-center rounded-lg ${iconClass}`}
                >
                    <Icon size={17} />
                </span>
                <TrendingUp className="text-ink-soft/35" size={14} />
            </div>
            <strong
                className={`mt-3 block truncate font-extrabold tracking-tight ${stat.format === "currency" ? "text-base xl:text-lg" : "text-2xl"}`}
                title={formatValue(stat.value, stat.format)}
            >
                {formatValue(stat.value, stat.format)}
            </strong>
            <p
                className="mt-1 truncate text-xs font-bold text-ink-soft dark:text-white/58"
                title={stat.label}
            >
                {stat.label}
            </p>
        </article>
    );
}

function BarChart({ chart }) {
    const values = chart.datasets.flatMap((dataset) =>
        dataset.data.map(Number),
    );
    const max = Math.max(...values, 1);
    return (
        <div>
            <div className="flex h-56 items-end gap-3 border-b border-silver-deep/60 px-1 pb-2 dark:border-white/10">
                {chart.labels.map((label, index) => (
                    <div
                        className="flex h-full min-w-0 flex-1 flex-col justify-end"
                        key={`${label}-${index}`}
                    >
                        <div className="flex h-[185px] items-end justify-center gap-1.5">
                            {chart.datasets.map((dataset) => {
                                const value = Number(dataset.data[index] ?? 0);
                                return (
                                    <div
                                        className="group relative w-full max-w-9 rounded-t-md transition hover:opacity-80"
                                        key={dataset.label}
                                        style={{
                                            height: `${Math.max(value > 0 ? 5 : 1, (value / max) * 100)}%`,
                                            backgroundColor: dataset.color,
                                        }}
                                        title={`${dataset.label}: ${formatValue(value, chart.unit === "Rp" ? "currency" : "number")}`}
                                    >
                                        <span className="pointer-events-none absolute -top-7 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-ink px-2 py-1 text-[10px] font-bold text-white group-hover:block">
                                            {chart.unit === "Rp"
                                                ? formatValue(value, "currency")
                                                : formatValue(value, "number")}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                        <span className="mt-2 truncate text-center text-[10px] font-extrabold uppercase text-ink-soft">
                            {label}
                        </span>
                    </div>
                ))}
            </div>
            <div className="mt-4 flex flex-wrap gap-4">
                {chart.datasets.map((dataset) => (
                    <span
                        className="flex items-center gap-2 text-xs font-bold text-ink-soft"
                        key={dataset.label}
                    >
                        <i
                            className="h-2.5 w-2.5 rounded-full"
                            style={{ backgroundColor: dataset.color }}
                        />
                        {dataset.label}
                    </span>
                ))}
            </div>
        </div>
    );
}

function DonutChart({ chart }) {
    const dataset = chart.datasets[0] ?? { data: [], colors: [] };
    const total = dataset.data.reduce(
        (sum, value) => sum + Number(value || 0),
        0,
    );
    let cursor = 0;
    const segments = dataset.data.map((value, index) => {
        const start = cursor;
        const percentage = total ? (Number(value) / total) * 100 : 0;
        cursor += percentage;
        return `${dataset.colors?.[index] ?? "#94a3b8"} ${start}% ${cursor}%`;
    });
    return (
        <div className="grid items-center gap-6 sm:grid-cols-[190px_1fr]">
            <div
                className="relative mx-auto grid h-44 w-44 place-items-center rounded-full"
                style={{
                    background: total
                        ? `conic-gradient(${segments.join(",")})`
                        : "#e2e8f0",
                }}
            >
                <div className="grid h-28 w-28 place-items-center rounded-full bg-white text-center shadow-inner dark:bg-[#181d24]">
                    <div>
                        <strong className="block text-2xl">
                            {formatValue(total, "number")}
                        </strong>
                        <span className="text-xs font-bold text-ink-soft">
                            {chart.unit}
                        </span>
                    </div>
                </div>
            </div>
            <div className="grid gap-3">
                {chart.labels.map((label, index) => (
                    <div
                        className="flex items-center justify-between gap-3"
                        key={label}
                    >
                        <span className="flex min-w-0 items-center gap-2 text-sm font-bold">
                            <i
                                className="h-3 w-3 shrink-0 rounded-full"
                                style={{
                                    backgroundColor:
                                        dataset.colors?.[index] ?? "#94a3b8",
                                }}
                            />
                            <span className="truncate">{label}</span>
                        </span>
                        <strong className="text-sm">
                            {formatValue(dataset.data[index], "number")}
                        </strong>
                    </div>
                ))}
            </div>
        </div>
    );
}

function Dashboard({
    sections = [],
    charts = [],
    shortcuts = [],
    context = {},
    filters = {},
    marketing_activity = null,
}) {
    const user = usePage().props.auth?.user;
    const [period, setPeriod] = useState(filters.period ?? "month");
    const [periodValue, setPeriodValue] = useState(filters.value ?? "");
    const changePeriod = (nextPeriod) => {
        const now = new Date();
        const nextValue =
            nextPeriod === "day"
                ? now.toISOString().slice(0, 10)
                : nextPeriod === "year"
                  ? String(now.getFullYear())
                  : now.toISOString().slice(0, 7);
        setPeriod(nextPeriod);
        setPeriodValue(nextValue);
    };
    const applyPeriod = (event) => {
        event.preventDefault();
        router.get(
            "/admin/dashboard",
            { period, value: periodValue },
            { preserveState: true, preserveScroll: true },
        );
    };
    return (
        <>
            <Head title="Dasbor" />
            <div className="grid gap-7">
                <section className="relative overflow-hidden rounded-xl bg-gradient-to-br from-[#20262d] via-[#29313a] to-[#101419] p-6 text-white shadow-[0_24px_70px_rgba(15,23,42,0.24)] md:p-8">
                    <div className="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-amber-400/15 blur-3xl" />
                    <div className="relative grid gap-6 xl:grid-cols-[1fr_460px] xl:items-end">
                        <div>
                            <p className="text-xs font-extrabold uppercase tracking-[0.2em] text-amber-300">
                                Dasbor Berbasis Hak Akses
                            </p>
                            <h2 className="mt-3 font-display text-3xl font-extrabold md:text-4xl">
                                Selamat datang, {user?.name ?? "Pengguna"}
                            </h2>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-white/65">
                                Ringkasan ini otomatis menyesuaikan hak akses
                                Anda dan menampilkan kondisi operasional terbaru
                                dari modul yang diizinkan.
                            </p>
                        </div>
                        <form
                            className="rounded-lg border border-white/10 bg-white/7 p-4 backdrop-blur"
                            onSubmit={applyPeriod}
                        >
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-[10px] font-bold uppercase tracking-wider text-white/45">
                                        Periode statistik
                                    </p>
                                    <p className="mt-1 text-sm font-extrabold text-amber-200">
                                        {context.period_label}
                                    </p>
                                </div>
                                <p className="text-right text-[10px] text-white/40">
                                    {(context.roles ?? [])
                                        .map((role) =>
                                            role.replaceAll("_", " "),
                                        )
                                        .join(", ") || "Tanpa role"}
                                    <br />
                                    {context.generated_at}
                                </p>
                            </div>
                            <div className="mt-4 grid grid-cols-[130px_1fr_auto] gap-2">
                                <select
                                    className="h-10 rounded-lg border border-white/15 bg-[#151a20] px-3 text-xs font-bold text-white outline-none"
                                    value={period}
                                    onChange={(event) =>
                                        changePeriod(event.target.value)
                                    }
                                >
                                    <option value="day">Harian</option>
                                    <option value="month">Bulanan</option>
                                    <option value="year">Tahunan</option>
                                </select>
                                {period === "year" ? (
                                    <input
                                        className="h-10 min-w-0 rounded-lg border border-white/15 bg-[#151a20] px-3 text-xs font-bold text-white outline-none"
                                        type="number"
                                        min="2000"
                                        max="2100"
                                        value={periodValue}
                                        onChange={(event) =>
                                            setPeriodValue(event.target.value)
                                        }
                                    />
                                ) : (
                                    <input
                                        className="h-10 min-w-0 rounded-lg border border-white/15 bg-[#151a20] px-3 text-xs font-bold text-white outline-none [color-scheme:dark]"
                                        type={
                                            period === "day" ? "date" : "month"
                                        }
                                        value={periodValue}
                                        onChange={(event) =>
                                            setPeriodValue(event.target.value)
                                        }
                                    />
                                )}
                                <button
                                    className="h-10 rounded-lg bg-amber-400 px-4 text-xs font-black text-slate-950 transition hover:bg-amber-300"
                                    type="submit"
                                >
                                    Terapkan
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                {marketing_activity && (
                    <section className="grid gap-3">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <p className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-blue-600">
                                    Prioritas Owner & Manager
                                </p>
                                <h3 className="mt-1 text-xl font-extrabold">
                                    Aktivitas Marketing
                                </h3>
                                <p className="text-xs font-bold text-ink-soft">
                                    Periode: {marketing_activity.period_label}
                                </p>
                            </div>
                            <Link
                                className="text-sm font-extrabold text-blue-700 hover:underline"
                                href="/admin/marketing/tools/monitoring-aktivitas"
                            >
                                Buka monitoring lengkap →
                            </Link>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                            {marketing_activity.items.map((item) => {
                                const Icon = iconMap[item.icon] ?? Activity;
                                const tones = {
                                    sky: "text-sky-600 bg-sky-100",
                                    violet: "text-orange-600 bg-orange-100",
                                    emerald: "text-emerald-600 bg-emerald-100",
                                    amber: "text-amber-600 bg-amber-100",
                                    blue: "text-blue-600 bg-blue-100",
                                    red: "text-red-600 bg-red-100",
                                };
                                return (
                                    <article
                                        className="rounded-xl border border-white/80 bg-white/85 p-4 shadow-soft dark:border-white/10 dark:bg-white/7"
                                        key={item.label}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-xs font-bold text-ink-soft">
                                                    {item.label}
                                                </p>
                                                <strong className="mt-2 block text-2xl">
                                                    {formatValue(
                                                        item.value,
                                                        "number",
                                                    )}
                                                </strong>
                                            </div>
                                            <span
                                                className={`grid h-10 w-10 place-items-center rounded-lg ${tones[item.tone] ?? tones.sky}`}
                                            >
                                                <Icon size={20} />
                                            </span>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    </section>
                )}

                {sections.map((section) => (
                    <section className="grid gap-3" key={section.key}>
                        <div className="flex items-center gap-3">
                            <span
                                className={`h-8 w-1.5 rounded-full bg-gradient-to-b ${(accents[section.key] ?? accents.users)[0]}`}
                            />
                            <div>
                                <h3 className="text-lg font-extrabold">
                                    {section.title}
                                </h3>
                                <p className="text-[11px] font-bold text-ink-soft">
                                    Periode: {context.period_label}
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                            {section.stats.map((stat) => (
                                <StatCard
                                    stat={stat}
                                    sectionKey={section.key}
                                    key={`${section.key}-${stat.label}`}
                                />
                            ))}
                        </div>
                    </section>
                ))}

                {!sections.length && (
                    <section className="rounded-xl border border-dashed border-silver-deep bg-white/70 p-10 text-center dark:border-white/10 dark:bg-white/5">
                        <ShieldCheck
                            className="mx-auto text-ink-soft"
                            size={36}
                        />
                        <h3 className="mt-4 text-xl font-extrabold">
                            Belum ada modul dashboard
                        </h3>
                        <p className="mt-2 text-sm text-ink-soft">
                            Minta administrator memberikan permission modul yang
                            diperlukan untuk menampilkan statistik.
                        </p>
                    </section>
                )}

                {!!charts.length && (
                    <section className="grid gap-5 xl:grid-cols-2">
                        {charts.map((chart, index) => (
                            <article
                                className="rounded-xl border border-white/80 bg-white/82 p-5 shadow-soft dark:border-white/10 dark:bg-white/7"
                                key={`${chart.title}-${index}`}
                            >
                                <div className="mb-6 flex items-center justify-between">
                                    <div>
                                        <p className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-ink-soft">
                                            Statistik
                                        </p>
                                        <h3 className="mt-1 text-lg font-extrabold">
                                            {chart.title}
                                        </h3>
                                    </div>
                                    <span className="grid h-10 w-10 place-items-center rounded-lg bg-silver text-ink-soft dark:bg-white/10">
                                        <Activity size={19} />
                                    </span>
                                </div>
                                {chart.type === "donut" ? (
                                    <DonutChart chart={chart} />
                                ) : (
                                    <BarChart chart={chart} />
                                )}
                            </article>
                        ))}
                    </section>
                )}

                {!!shortcuts.length && (
                    <section className="rounded-xl border border-white/80 bg-white/82 p-5 shadow-soft dark:border-white/10 dark:bg-white/7">
                        <div className="mb-4">
                            <p className="text-[10px] font-extrabold uppercase tracking-[0.16em] text-ink-soft">
                                Akses Cepat
                            </p>
                            <h3 className="mt-1 text-lg font-extrabold">
                                Menu yang tersedia untuk Anda
                            </h3>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {shortcuts.map((item) => (
                                <Link
                                    className="flex items-center justify-between gap-3 rounded-lg border border-silver-deep/60 bg-silver-soft px-4 py-3 text-sm font-extrabold transition hover:border-amber-400 hover:bg-amber-50 dark:border-white/10 dark:bg-white/6 dark:hover:bg-amber-400/10"
                                    href={item.href}
                                    key={item.href}
                                >
                                    <span>{item.label}</span>
                                    <ChevronRight size={16} />
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

Dashboard.layout = (page) => <AdminLayout title="Dasbor">{page}</AdminLayout>;
export default Dashboard;
