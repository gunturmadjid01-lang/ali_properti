import { Head, Link, router } from "@inertiajs/react";
import AdminLayout from "../../../Layouts/AdminLayout";

const colors = {
    appraisal: "border-sky-300 bg-sky-50 text-sky-800",
    contract_preparation: "border-violet-300 bg-violet-50 text-violet-800",
    contract_signing: "border-fuchsia-300 bg-fuchsia-50 text-fuchsia-800",
    bank_disbursement: "border-emerald-300 bg-emerald-50 text-emerald-800",
    expected_disbursement: "border-green-300 bg-green-50 text-green-800",
    internal_handover: "border-amber-300 bg-amber-50 text-amber-800",
    internal_handover_record: "border-orange-300 bg-orange-50 text-orange-800",
    customer_handover: "border-teal-300 bg-teal-50 text-teal-800",
};

export default function SalesCalendar({
    title,
    month,
    events = [],
    filters = {},
    typeOptions = [],
    perumahanOptions = [],
}) {
    const [year, monthNumber] = month.split("-").map(Number);
    const firstDay = new Date(year, monthNumber - 1, 1);
    const daysInMonth = new Date(year, monthNumber, 0).getDate();
    const leading = (firstDay.getDay() + 6) % 7;
    const cells = [
        ...Array(leading).fill(null),
        ...Array.from({ length: daysInMonth }, (_, index) => index + 1),
    ];
    while (cells.length % 7) cells.push(null);
    const byDate = events.reduce(
        (groups, event) => ({
            ...groups,
            [event.date]: [...(groups[event.date] || []), event],
        }),
        {},
    );
    const navigate = (nextMonth) =>
        router.get(
            "/admin/admin-sales/kalender-penjualan",
            {
                month: nextMonth,
                type: filters.type || "",
                perumahan_id: filters.perumahan_id || "",
            },
            { preserveState: true },
        );
    const shiftMonth = (amount) => {
        const target = new Date(year, monthNumber - 1 + amount, 1);
        navigate(
            `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, "0")}`,
        );
    };
    const changeFilter = (key, value) =>
        router.get(
            "/admin/admin-sales/kalender-penjualan",
            { month, ...filters, [key]: value },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="rounded-3xl border bg-white p-6">
                    <p className="text-xs font-black uppercase tracking-widest text-gold-deep">
                        Agenda administrasi lintas transaksi
                    </p>
                    <h1 className="mt-2 text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-sm text-ink-soft">
                        Memantau OTS/appraisal, persiapan dan pelaksanaan akad,
                        pencairan, BAST, serta serah-terima dari jadwal
                        transaksi sumber.
                    </p>
                </header>

                <section className="flex flex-wrap items-center gap-3 rounded-2xl border bg-white p-4">
                    <button
                        onClick={() => shiftMonth(-1)}
                        className="rounded-xl border px-4 py-3 font-black"
                    >
                        ← Bulan sebelumnya
                    </button>
                    <input
                        type="month"
                        value={month}
                        onChange={(event) => navigate(event.target.value)}
                        className="rounded-xl border px-4 py-3 font-bold"
                    />
                    <button
                        onClick={() => shiftMonth(1)}
                        className="rounded-xl border px-4 py-3 font-black"
                    >
                        Bulan berikutnya →
                    </button>
                    <select
                        value={filters.type || ""}
                        onChange={(event) =>
                            changeFilter("type", event.target.value)
                        }
                        className="min-w-52 rounded-xl border px-4 py-3"
                    >
                        <option value="">Semua agenda</option>
                        {typeOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.perumahan_id || ""}
                        onChange={(event) =>
                            changeFilter("perumahan_id", event.target.value)
                        }
                        className="min-w-52 rounded-xl border px-4 py-3"
                    >
                        <option value="">Semua perumahan</option>
                        {perumahanOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </section>

                <section className="overflow-x-auto rounded-2xl border bg-white p-3">
                    <div className="min-w-[1050px]">
                        <div className="grid grid-cols-7">
                            {[
                                "Sen",
                                "Sel",
                                "Rab",
                                "Kam",
                                "Jum",
                                "Sab",
                                "Min",
                            ].map((day) => (
                                <div
                                    key={day}
                                    className="border-b p-3 text-center text-xs font-black uppercase text-ink-soft"
                                >
                                    {day}
                                </div>
                            ))}
                        </div>
                        <div className="grid grid-cols-7">
                            {cells.map((day, index) => {
                                const date = day
                                    ? `${month}-${String(day).padStart(2, "0")}`
                                    : null;
                                return (
                                    <div
                                        key={`${day}-${index}`}
                                        className={`min-h-40 border-b border-r p-2 ${day ? "bg-white" : "bg-silver-soft/40"}`}
                                    >
                                        {day && (
                                            <>
                                                <p className="mb-2 text-sm font-black">
                                                    {day}
                                                </p>
                                                <div className="grid gap-2">
                                                    {(byDate[date] || []).map(
                                                        (event) => (
                                                            <Link
                                                                key={event.id}
                                                                href={event.url}
                                                                className={`rounded-lg border p-2 text-xs ${colors[event.type] || "border-slate-300 bg-slate-50"} ${event.overdue ? "ring-2 ring-red-500" : ""}`}
                                                            >
                                                                <b className="block">
                                                                    {
                                                                        event.type_label
                                                                    }
                                                                </b>
                                                                <span className="mt-1 block font-semibold">
                                                                    {event.customer ||
                                                                        event.title}
                                                                </span>
                                                                <span className="block opacity-80">
                                                                    {event.unit ||
                                                                        event.housing ||
                                                                        event.reference ||
                                                                        "-"}
                                                                </span>
                                                            </Link>
                                                        ),
                                                    )}
                                                </div>
                                            </>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="text-xl font-black">
                        Daftar Agenda Bulan Ini
                    </h2>
                    <div className="mt-4 grid gap-3">
                        {events.map((event) => (
                            <Link
                                key={`list-${event.id}`}
                                href={event.url}
                                className={`flex flex-wrap justify-between gap-3 rounded-xl border p-4 ${event.overdue ? "border-red-400 bg-red-50" : ""}`}
                            >
                                <div>
                                    <b>
                                        {event.date} · {event.type_label}
                                    </b>
                                    <p className="text-sm text-ink-soft">
                                        {event.customer || "Tanpa customer"} ·{" "}
                                        {event.housing || "-"} ·{" "}
                                        {event.unit || "-"}
                                    </p>
                                </div>
                                <div className="text-right text-sm">
                                    <b>{event.status}</b>
                                    <p>{event.pic || "PIC belum ditentukan"}</p>
                                </div>
                            </Link>
                        ))}
                        {!events.length && (
                            <p className="py-8 text-center text-ink-soft">
                                Belum ada agenda pada bulan dan filter ini.
                            </p>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

SalesCalendar.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Kalender Proses Penjualan"}>
        {page}
    </AdminLayout>
);
