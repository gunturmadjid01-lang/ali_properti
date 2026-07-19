import { TableActions } from "../../../Components/UI";
import { Head, Link, router } from "@inertiajs/react";
import {
    Activity,
    AlertTriangle,
    BarChart3,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    Clock3,
    Filter,
    MapPin,
    RotateCcw,
    Search,
    Settings2,
    TimerOff,
    UserCheck,
    Users,
} from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";
import Pagination from "../../../Components/Pagination";

const tones = {
    slate: "bg-slate-950 text-white",
    blue: "bg-blue-50 text-blue-700",
    emerald: "bg-emerald-50 text-emerald-700",
    amber: "bg-amber-50 text-amber-700",
    red: "bg-red-50 text-red-700",
    violet: "bg-violet-50 text-violet-700",
};
const icons = [Activity, UserCheck, CheckCircle2, Clock3, MapPin, TimerOff];
const timeLabels = {
    on_time: "Tepat waktu",
    late: "Terlambat masuk",
    early_leave: "Pulang cepat",
    late_leave: "Pulang terlambat",
};

function TrendChart({ data = [] }) {
    const max = Math.max(
        1,
        ...data.flatMap((x) => [x.check_in, x.check_out, x.outside]),
    );
    return (
        <div>
            <div className="mb-5 flex flex-wrap gap-4 text-xs font-bold">
                <span className="flex items-center gap-2">
                    <i className="h-2.5 w-2.5 rounded-full bg-emerald-500" />
                    Masuk
                </span>
                <span className="flex items-center gap-2">
                    <i className="h-2.5 w-2.5 rounded-full bg-blue-500" />
                    Pulang
                </span>
                <span className="flex items-center gap-2">
                    <i className="h-2.5 w-2.5 rounded-full bg-red-500" />
                    Luar radius
                </span>
            </div>
            <div className="flex h-64 items-end gap-2 overflow-x-auto border-b border-slate-200 pb-7">
                {data.map((item) => (
                    <div
                        key={item.date}
                        className="group relative flex h-full min-w-12 flex-1 items-end justify-center gap-1"
                    >
                        <div
                            className="w-2.5 rounded-t bg-emerald-500 transition-all"
                            style={{
                                height: `${Math.max(item.check_in ? 5 : 0, (item.check_in / max) * 100)}%`,
                            }}
                        />
                        <div
                            className="w-2.5 rounded-t bg-blue-500 transition-all"
                            style={{
                                height: `${Math.max(item.check_out ? 5 : 0, (item.check_out / max) * 100)}%`,
                            }}
                        />
                        <div
                            className="w-2.5 rounded-t bg-red-500 transition-all"
                            style={{
                                height: `${Math.max(item.outside ? 5 : 0, (item.outside / max) * 100)}%`,
                            }}
                        />
                        <span className="absolute -bottom-6 whitespace-nowrap text-[10px] font-bold text-slate-400">
                            {item.label}
                        </span>
                        <div className="pointer-events-none absolute bottom-full z-10 mb-2 hidden rounded-xl bg-slate-950 px-3 py-2 text-xs font-bold text-white shadow-xl group-hover:block">
                            Masuk {item.check_in} · Pulang {item.check_out} ·
                            Luar {item.outside}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function Index({
    rows,
    filters,
    filterOptions = {},
    statistics = [],
    chart = [],
    canManageSettings = false,
}) {
    const apply = (e) => {
        e.preventDefault();
        router.get(
            "/admin/absensi-pegawai",
            Object.fromEntries(new FormData(e.currentTarget)),
            { preserveState: true, replace: true },
        );
    };
    const total = statistics[0]?.value || 0,
        outside = statistics[4]?.value || 0,
        late = statistics[3]?.value || 0;
    return (
        <>
            <Head title="Dashboard Absensi Pegawai" />
            <div className="space-y-6">
                <section className="relative overflow-hidden rounded-[2rem] bg-slate-950 p-7 text-white shadow-xl">
                    <div className="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-amber-400/15 blur-3xl" />
                    <div className="relative flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
                        <div>
                            <div className="flex items-center gap-2 text-xs font-black uppercase tracking-[.18em] text-amber-300">
                                <BarChart3 size={16} />
                                Dashboard Kehadiran
                            </div>
                            <h1 className="mt-3 text-3xl font-black md:text-4xl">
                                Analitik Absensi Pegawai
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-white/55">
                                Pantau kedisiplinan waktu, validitas lokasi GPS,
                                dan bukti kehadiran seluruh perusahaan dalam
                                satu laporan.
                            </p>
                        </div>
                        {canManageSettings && (
                            <Link
                                href="/admin/pengaturan-absensi"
                                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-amber-400 px-5 font-black text-slate-950"
                            >
                                <Settings2 size={18} />
                                Pengaturan Jam
                            </Link>
                        )}
                    </div>
                </section>
                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    {statistics.map((item, index) => {
                        const Icon = icons[index] || Activity;
                        return (
                            <article
                                key={item.label}
                                className={`rounded-2xl border border-white p-4 shadow-sm ${tones[item.tone] || tones.slate}`}
                            >
                                <Icon size={20} />
                                <p className="mt-4 text-3xl font-black">
                                    {item.value.toLocaleString("id-ID")}
                                </p>
                                <p className="mt-1 text-xs font-bold opacity-70">
                                    {item.label}
                                </p>
                            </article>
                        );
                    })}
                </section>
                <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center gap-3">
                        <span className="grid h-10 w-10 place-items-center rounded-xl bg-slate-950 text-white">
                            <Filter size={18} />
                        </span>
                        <div>
                            <h2 className="font-black">Filter Laporan</h2>
                            <p className="text-xs text-slate-500">
                                Kombinasikan beberapa filter untuk laporan yang
                                lebih spesifik.
                            </p>
                        </div>
                    </div>
                    <form
                        onSubmit={apply}
                        className="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                    >
                        <label className="relative xl:col-span-2">
                            <Search
                                className="absolute left-3 top-3 text-slate-400"
                                size={18}
                            />
                            <input
                                name="search"
                                defaultValue={filters.search}
                                className="w-full rounded-xl border border-slate-300 py-2.5 pl-10 pr-3"
                                placeholder="Cari nama atau nomor pegawai"
                            />
                        </label>
                        <input
                            name="date_from"
                            type="date"
                            defaultValue={filters.date_from}
                            className="rounded-xl border border-slate-300 px-3 py-2.5"
                        />
                        <input
                            name="date_to"
                            type="date"
                            defaultValue={filters.date_to}
                            className="rounded-xl border border-slate-300 px-3 py-2.5"
                        />
                        <select
                            name="branch_id"
                            defaultValue={filters.branch_id}
                            className="rounded-xl border border-slate-300 px-3 py-2.5"
                        >
                            <option value="">Semua perusahaan</option>
                            {(filterOptions.branches || []).map((x) => (
                                <option key={x.value} value={x.value}>
                                    {x.label}
                                </option>
                            ))}
                        </select>
                        <select
                            name="type"
                            defaultValue={filters.type}
                            className="rounded-xl border border-slate-300 px-3 py-2.5"
                        >
                            <option value="">Masuk & pulang</option>
                            <option value="check_in">Absen masuk</option>
                            <option value="check_out">Absen pulang</option>
                        </select>
                        <select
                            name="radius"
                            defaultValue={filters.radius}
                            className="rounded-xl border border-slate-300 px-3 py-2.5"
                        >
                            <option value="">Semua status radius</option>
                            <option value="inside">Dalam radius</option>
                            <option value="outside">Di luar radius</option>
                        </select>
                        <select
                            name="time_status"
                            defaultValue={filters.time_status}
                            className="rounded-xl border border-slate-300 px-3 py-2.5"
                        >
                            <option value="">Semua status waktu</option>
                            {Object.entries(timeLabels).map(([v, l]) => (
                                <option key={v} value={v}>
                                    {l}
                                </option>
                            ))}
                        </select>
                        <div className="flex gap-2 xl:col-span-4 xl:justify-end">
                            <Link
                                href="/admin/absensi-pegawai"
                                className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 px-4 font-black text-slate-600"
                            >
                                <RotateCcw size={16} />
                                Reset
                            </Link>
                            <button className="min-h-11 rounded-xl bg-slate-950 px-6 font-black text-white">
                                Terapkan Filter
                            </button>
                        </div>
                    </form>
                </section>
                <section className="grid gap-5 xl:grid-cols-[1.7fr_.8fr]">
                    <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="mb-5 flex items-center justify-between">
                            <div>
                                <p className="text-xs font-black uppercase tracking-wider text-amber-700">
                                    Tren Harian
                                </p>
                                <h2 className="text-xl font-black">
                                    Aktivitas Kehadiran
                                </h2>
                            </div>
                            <CalendarDays className="text-slate-400" />
                        </div>
                        <TrendChart data={chart} />
                    </article>
                    <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-black uppercase tracking-wider text-amber-700">
                            Kualitas Absensi
                        </p>
                        <h2 className="text-xl font-black">
                            Ringkasan Kepatuhan
                        </h2>
                        <div
                            className="mx-auto mt-7 grid h-44 w-44 place-items-center rounded-full"
                            style={{
                                background: `conic-gradient(#ef4444 0 ${(outside / Math.max(1, total)) * 100}%, #f59e0b ${(outside / Math.max(1, total)) * 100}% ${((outside + late) / Math.max(1, total)) * 100}%, #10b981 ${((outside + late) / Math.max(1, total)) * 100}% 100%)`,
                            }}
                        >
                            <div className="grid h-28 w-28 place-items-center rounded-full bg-white text-center">
                                <div>
                                    <p className="text-3xl font-black">
                                        {total}
                                    </p>
                                    <p className="text-xs font-bold text-slate-400">
                                        catatan
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div className="mt-7 space-y-3 text-sm font-bold">
                            <div className="flex justify-between">
                                <span className="text-slate-500">
                                    Di luar radius
                                </span>
                                <span className="text-red-600">{outside}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">
                                    Terlambat masuk
                                </span>
                                <span className="text-amber-600">{late}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">
                                    Catatan lainnya
                                </span>
                                <span className="text-emerald-600">
                                    {Math.max(0, total - outside - late)}
                                </span>
                            </div>
                        </div>
                    </article>
                </section>
                <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <header className="flex items-center justify-between border-b px-5 py-4">
                        <div>
                            <h2 className="text-lg font-black">Data Absensi</h2>
                            <p className="text-xs text-slate-500">
                                {rows.total} catatan sesuai filter
                            </p>
                        </div>
                        <Users className="text-slate-400" />
                    </header>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-[11px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    {[
                                        "Pegawai",
                                        "Perusahaan",
                                        "Waktu",
                                        "Lokasi",
                                        "Status Jam",
                                        "Aksi",
                                    ].map((x) => (
                                        <th className="px-5 py-3" key={x}>
                                            {x}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.data.map((row) => (
                                    <tr
                                        className="border-t transition hover:bg-slate-50"
                                        key={row.id}
                                    >
                                        <td className="px-5 py-4">
                                            <p className="font-black">
                                                {row.employee}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {row.employee_number}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4 font-semibold">
                                            {row.branch}
                                        </td>
                                        <td className="px-5 py-4">
                                            <p className="font-black">
                                                {row.type === "check_in"
                                                    ? "Masuk"
                                                    : "Pulang"}{" "}
                                                · {row.time}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {row.date}
                                            </p>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span
                                                className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-black ${row.within_radius ? "bg-emerald-100 text-emerald-700" : "bg-red-100 text-red-700"}`}
                                            >
                                                <MapPin size={13} />
                                                {row.within_radius
                                                    ? "Dalam radius"
                                                    : `Di luar · ${row.distance} m`}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-black">
                                                {timeLabels[row.time_status] ||
                                                    row.time_status}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                <Link
                                                    href={`/admin/absensi-pegawai/${row.id}`}
                                                    className="inline-flex items-center gap-1 rounded-xl bg-amber-50 px-3 py-2 font-black text-amber-700"
                                                >
                                                    Detail{" "}
                                                    <ChevronRight size={15} />
                                                </Link>
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {!rows.data.length && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="p-12 text-center"
                                        >
                                            <AlertTriangle className="mx-auto text-slate-300" />
                                            <p className="mt-2 font-black text-slate-500">
                                                Tidak ada data sesuai filter.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="border-t p-4">
                        <Pagination links={rows.links} />
                    </div>
                </section>
            </div>
        </>
    );
}
Index.layout = (page) => (
    <AdminLayout title="Dashboard Absensi">{page}</AdminLayout>
);
