import { Head, Link, router } from "@inertiajs/react";
import { ChevronLeft, ChevronRight, List, CalendarDays } from "lucide-react";
import { useMemo, useState } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";

const typeMeta = {
    visit: ["Kunjungan", "bg-blue-100 text-blue-800 border-blue-200"],
    survey: ["Survey", "bg-purple-100 text-purple-800 border-purple-200"],
    follow_up: ["Follow-up", "bg-amber-100 text-amber-800 border-amber-200"],
    reminder: ["Reminder", "bg-red-100 text-red-800 border-red-200"],
    action_plan: [
        "Rencana Kerja",
        "bg-emerald-100 text-emerald-800 border-emerald-200",
    ],
};
const iso = (d) => {
    const y = d.getFullYear(),
        m = String(d.getMonth() + 1).padStart(2, "0"),
        day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
};
export default function Index({
    title,
    month,
    calendarStart,
    calendarEnd,
    events = [],
    filters = {},
    options = {},
    canViewAll,
}) {
    const [view, setView] = useState("month");
    const [form, setForm] = useState({
        ...filters,
        types: filters.types || [],
    });
    const days = useMemo(() => {
        const out = [],
            d = new Date(`${calendarStart}T00:00:00`),
            end = new Date(`${calendarEnd}T00:00:00`);
        while (d <= end) {
            out.push(new Date(d));
            d.setDate(d.getDate() + 1);
        }
        return out;
    }, [calendarStart, calendarEnd]);
    const grouped = useMemo(
        () =>
            events.reduce((a, e) => {
                (a[e.date] ??= []).push(e);
                return a;
            }, {}),
        [events],
    );
    const move = (delta) => {
        const d = new Date(`${month}-01T00:00:00`);
        d.setMonth(d.getMonth() + delta);
        router.get(
            "/admin/marketing/kalender-kegiatan",
            {
                ...form,
                month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`,
            },
            { preserveState: true },
        );
    };
    const apply = (e) => {
        e.preventDefault();
        router.get(
            "/admin/marketing/kalender-kegiatan",
            { ...form, month },
            { preserveState: true },
        );
    };
    const monthLabel = new Intl.DateTimeFormat("id-ID", {
        month: "long",
        year: "numeric",
    }).format(new Date(`${month}-01T00:00:00`));
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="rounded-3xl border bg-white p-6">
                    <p className="text-xs font-black uppercase tracking-widest text-gold-deep">
                        Agenda tim yang dapat dipertanggungjawabkan
                    </p>
                    <div className="mt-2 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-black">{title}</h1>
                            <p className="mt-2 text-ink-soft">
                                Kunjungan, survey, follow-up, reminder, dan
                                rencana kerja dalam satu kalender.
                            </p>
                        </div>
                        <div className="flex rounded-xl border bg-white p-1">
                            <button
                                onClick={() => setView("month")}
                                className={`flex gap-2 rounded-lg px-3 py-2 font-bold ${view === "month" ? "bg-ink text-white" : ""}`}
                            >
                                <CalendarDays size={17} /> Kalender
                            </button>
                            <button
                                onClick={() => setView("list")}
                                className={`flex gap-2 rounded-lg px-3 py-2 font-bold ${view === "list" ? "bg-ink text-white" : ""}`}
                            >
                                <List size={17} /> Daftar
                            </button>
                        </div>
                    </div>
                </header>
                <form
                    onSubmit={apply}
                    className="grid gap-3 rounded-2xl border bg-white p-4 lg:grid-cols-4"
                >
                    {canViewAll && (
                        <select
                            className="rounded-xl border p-3"
                            value={form.marketing_id || ""}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    marketing_id: e.target.value,
                                })
                            }
                        >
                            <option value="">Semua Marketing</option>
                            {(options.marketings || []).map((x) => (
                                <option key={x.value} value={x.value}>
                                    {x.label}
                                </option>
                            ))}
                        </select>
                    )}
                    <select
                        className="rounded-xl border p-3"
                        value={form.perumahan_id || ""}
                        onChange={(e) =>
                            setForm({ ...form, perumahan_id: e.target.value })
                        }
                    >
                        <option value="">Semua Perumahan</option>
                        {(options.perumahans || []).map((x) => (
                            <option key={x.value} value={x.value}>
                                {x.label}
                            </option>
                        ))}
                    </select>
                    <div className="flex flex-wrap gap-2">
                        {Object.entries(typeMeta).map(([key, [label]]) => (
                            <label
                                key={key}
                                className="flex items-center gap-1 text-xs"
                            >
                                <input
                                    type="checkbox"
                                    checked={
                                        !form.types.length ||
                                        form.types.includes(key)
                                    }
                                    onChange={() =>
                                        setForm({
                                            ...form,
                                            types: form.types.includes(key)
                                                ? form.types.filter(
                                                      (x) => x !== key,
                                                  )
                                                : [...form.types, key],
                                        })
                                    }
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                    <button className="rounded-xl bg-ink p-3 font-bold text-white">
                        Terapkan Filter
                    </button>
                </form>
                <div className="flex items-center justify-between rounded-2xl border bg-white p-3">
                    <button
                        onClick={() => move(-1)}
                        className="rounded-lg border p-2"
                    >
                        <ChevronLeft />
                    </button>
                    <h2 className="text-xl font-black capitalize">
                        {monthLabel}
                    </h2>
                    <button
                        onClick={() => move(1)}
                        className="rounded-lg border p-2"
                    >
                        <ChevronRight />
                    </button>
                </div>
                {view === "month" ? (
                    <section className="overflow-x-auto rounded-2xl border bg-white">
                        <div className="min-w-[900px]">
                            <div className="grid grid-cols-7 bg-silver-soft text-center text-xs font-black uppercase">
                                {[
                                    "Sen",
                                    "Sel",
                                    "Rab",
                                    "Kam",
                                    "Jum",
                                    "Sab",
                                    "Min",
                                ].map((x) => (
                                    <div key={x} className="p-3">
                                        {x}
                                    </div>
                                ))}
                            </div>
                            <div className="grid grid-cols-7">
                                {days.map((d) => {
                                    const key = iso(d),
                                        inside = key.startsWith(month),
                                        today = key === iso(new Date());
                                    return (
                                        <div
                                            key={key}
                                            className={`min-h-36 border-r border-t p-2 ${inside ? "bg-white" : "bg-slate-50 text-slate-400"}`}
                                        >
                                            <div
                                                className={`mb-2 flex h-7 w-7 items-center justify-center rounded-full text-xs font-black ${today ? "bg-gold text-ink" : ""}`}
                                            >
                                                {d.getDate()}
                                            </div>
                                            <div className="grid gap-1">
                                                {(grouped[key] || [])
                                                    .slice(0, 4)
                                                    .map((e) => (
                                                        <Link
                                                            key={e.key}
                                                            href={e.url}
                                                            title={`${e.marketing || ""} ${e.note || ""}`}
                                                            className={`rounded-lg border px-2 py-1 text-[11px] ${typeMeta[e.type]?.[1]}`}
                                                        >
                                                            <b>{e.time}</b>{" "}
                                                            {e.title}
                                                            <span className="block truncate opacity-75">
                                                                {e.marketing}
                                                            </span>
                                                        </Link>
                                                    ))}
                                                {(grouped[key] || []).length >
                                                    4 && (
                                                    <button
                                                        onClick={() =>
                                                            setView("list")
                                                        }
                                                        className="text-left text-xs font-bold text-gold-deep"
                                                    >
                                                        +
                                                        {grouped[key].length -
                                                            4}{" "}
                                                        kegiatan
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </section>
                ) : (
                    <section className="grid gap-3">
                        {events.map((e) => (
                            <Link
                                key={e.key}
                                href={e.url}
                                className="grid gap-3 rounded-2xl border bg-white p-4 md:grid-cols-[140px_1fr_180px]"
                            >
                                <div>
                                    <b>
                                        {new Intl.DateTimeFormat("id-ID", {
                                            weekday: "short",
                                            day: "2-digit",
                                            month: "short",
                                        }).format(new Date(e.start))}
                                    </b>
                                    <p>{e.time}</p>
                                </div>
                                <div>
                                    <span
                                        className={`rounded-full border px-2 py-1 text-xs ${typeMeta[e.type]?.[1]}`}
                                    >
                                        {typeMeta[e.type]?.[0]}
                                    </span>
                                    <h3 className="mt-2 font-black">
                                        {e.title}
                                    </h3>
                                    <p className="text-sm text-ink-soft">
                                        {e.note || "Tanpa catatan"}
                                    </p>
                                </div>
                                <div className="text-sm">
                                    <b>{e.marketing || "-"}</b>
                                    <p>{e.housing || "-"}</p>
                                    <p>{e.status}</p>
                                </div>
                            </Link>
                        ))}
                        {!events.length && (
                            <p className="rounded-2xl border bg-white p-10 text-center text-ink-soft">
                                Tidak ada kegiatan pada periode ini.
                            </p>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Kalender Kegiatan"}>
        {page}
    </AdminLayout>
);
