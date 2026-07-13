import { Head, router, usePage } from "@inertiajs/react";
import { FileDown, RotateCcw, Search } from "lucide-react";
import { useMemo, useState } from "react";
import { Button, Dropdown, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

const percent = (value) =>
    `${Number(value ?? 0).toLocaleString("id-ID", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;

export default function ConstructionProgress({
    title,
    description,
    baseUrl,
    printUrl,
    filters,
    options,
    report,
    permissions,
}) {
    const { errors = {} } = usePage().props;
    const [periodType, setPeriodType] = useState(
        filters.period_type ?? "range",
    );
    const [date, setDate] = useState(filters.date ?? "");
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? "");
    const [dateTo, setDateTo] = useState(filters.date_to ?? "");
    const [month, setMonth] = useState(filters.month ?? "");
    const [firstUnit, setFirstUnit] = useState(filters.unit_ids?.[0] ?? "");
    const [secondUnit, setSecondUnit] = useState(filters.unit_ids?.[1] ?? "");

    const secondUnitOptions = useMemo(() => {
        const first = options.units.find((unit) => unit.value === firstUnit);
        if (!first) return [];

        return [
            { value: "", label: "Tanpa unit kedua" },
            ...options.units.filter(
                (unit) =>
                    unit.value !== firstUnit &&
                    unit.project_id === first.project_id &&
                    unit.building_type === first.building_type,
            ),
        ];
    }, [firstUnit, options.units]);

    const params = () => ({
        period_type: periodType,
        date,
        date_from: dateFrom,
        date_to: dateTo,
        month,
        unit_ids: [firstUnit, secondUnit].filter(Boolean),
    });

    const applyFilters = (event) => {
        event.preventDefault();
        router.get(baseUrl, params(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const reset = () => router.get(baseUrl, {}, { replace: true });

    const openPrint = () => {
        const query = new URLSearchParams();
        Object.entries(params()).forEach(([key, value]) => {
            if (Array.isArray(value))
                value.forEach((item) => query.append(`${key}[]`, item));
            else if (value) query.set(key, value);
        });
        window.open(
            `${printUrl}?${query.toString()}`,
            "_blank",
            "noopener,noreferrer",
        );
    };

    let lastStage = null;

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="border-b border-silver-deep/60 pb-5 dark:border-white/10">
                    <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-ink-soft">
                        Monitoring Proyek
                    </p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">
                        {title}
                    </h2>
                    <p className="mt-2 max-w-4xl leading-7 text-ink-soft dark:text-white/60">
                        {description}
                    </p>
                </section>

                <form
                    className="grid gap-4 rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"
                    onSubmit={applyFilters}
                >
                    <div className="grid gap-4 lg:grid-cols-3">
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Jenis Periode</span>
                            <Dropdown
                                value={periodType}
                                options={options.periodTypes}
                                searchable={false}
                                onChange={setPeriodType}
                            />
                        </label>
                        {periodType === "daily" && (
                            <Input
                                label="Tanggal Laporan"
                                type="date"
                                value={date}
                                onChange={(event) =>
                                    setDate(event.target.value)
                                }
                            />
                        )}
                        {periodType === "range" && (
                            <>
                                <Input
                                    label="Tanggal Mulai"
                                    type="date"
                                    value={dateFrom}
                                    error={errors.date_from}
                                    onChange={(event) =>
                                        setDateFrom(event.target.value)
                                    }
                                />
                                <Input
                                    label="Tanggal Selesai"
                                    type="date"
                                    value={dateTo}
                                    error={errors.date_to}
                                    onChange={(event) =>
                                        setDateTo(event.target.value)
                                    }
                                />
                            </>
                        )}
                        {periodType === "monthly" && (
                            <Input
                                label="Bulan"
                                type="month"
                                value={month}
                                error={errors.month}
                                onChange={(event) =>
                                    setMonth(event.target.value)
                                }
                            />
                        )}
                    </div>

                    <div className="grid gap-4 lg:grid-cols-2">
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Blok / Unit Pertama</span>
                            <Dropdown
                                label="Pilih blok atau unit"
                                value={firstUnit}
                                options={options.units}
                                onChange={(value) => {
                                    setFirstUnit(value);
                                    setSecondUnit("");
                                }}
                            />
                        </label>
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Blok / Unit Kedua (Opsional)</span>
                            <Dropdown
                                label={
                                    firstUnit
                                        ? "Pilih unit pembanding"
                                        : "Pilih unit pertama dahulu"
                                }
                                value={secondUnit}
                                options={secondUnitOptions}
                                disabled={!firstUnit}
                                onChange={setSecondUnit}
                            />
                        </label>
                    </div>
                    {errors.unit_ids && (
                        <p className="text-sm font-bold text-red-600 dark:text-red-300">
                            {errors.unit_ids}
                        </p>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button type="submit">
                            <Search size={17} /> Tampilkan Laporan
                        </Button>
                        <Button type="button" variant="outline" onClick={reset}>
                            <RotateCcw size={17} /> Reset
                        </Button>
                        {permissions.canExport && report.units.length > 0 && (
                            <Button
                                type="button"
                                variant="dark"
                                onClick={openPrint}
                            >
                                <FileDown size={17} /> Cetak / Simpan PDF
                            </Button>
                        )}
                    </div>
                </form>

                {report.units.length > 0 ? (
                    <section className="overflow-hidden rounded-lg border border-white/80 bg-white/90 shadow-soft dark:border-white/10 dark:bg-white/8">
                        <div className="border-b border-silver-deep/60 bg-emerald-100/70 px-5 py-4 text-center dark:border-white/10 dark:bg-emerald-500/10">
                            <h3 className="text-xl font-extrabold uppercase">
                                Laporan Progress Pembangunan
                            </h3>
                            <p className="mt-1 text-sm font-bold text-ink-soft dark:text-white/60">
                                {report.project} · Tipe {report.building_type} ·{" "}
                                {report.period.label}
                            </p>
                        </div>
                        <div className="grid border-b border-slate-300 bg-white dark:border-white/10 dark:bg-transparent sm:grid-cols-2">
                            {report.units.map((unit) => (
                                <div
                                    className="px-5 py-3 text-sm sm:border-r sm:last:border-r-0 dark:border-white/10"
                                    key={`${unit.id}-contract`}
                                >
                                    <span className="font-bold text-ink-soft dark:text-white/55">
                                        Nilai SPK {unit.label}:{" "}
                                    </span>
                                    <strong>
                                        {money(unit.contract_total)}
                                    </strong>
                                </div>
                            ))}
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-[1000px] w-full border-collapse text-sm">
                                <thead className="bg-slate-200 text-xs uppercase dark:bg-white/10">
                                    <tr>
                                        <th
                                            className="border border-slate-400 px-3 py-3"
                                            rowSpan={2}
                                        >
                                            No.
                                        </th>
                                        <th
                                            className="border border-slate-400 px-3 py-3 text-left"
                                            rowSpan={2}
                                        >
                                            Jenis Pekerjaan
                                        </th>
                                        <th
                                            className="border border-slate-400 px-3 py-3"
                                            rowSpan={2}
                                        >
                                            Jumlah Harga
                                        </th>
                                        <th
                                            className="border border-slate-400 px-3 py-3"
                                            rowSpan={2}
                                        >
                                            Bobot (%)
                                        </th>
                                        {report.units.map((unit) => (
                                            <th
                                                className="border border-slate-400 px-3 py-2"
                                                colSpan={2}
                                                key={unit.id}
                                            >
                                                {unit.label}
                                            </th>
                                        ))}
                                    </tr>
                                    <tr>
                                        {report.units.flatMap((unit) => [
                                            <th
                                                className="border border-slate-400 px-3 py-2"
                                                key={`${unit.id}-c`}
                                            >
                                                Kumulatif (%)
                                            </th>,
                                            <th
                                                className="border border-slate-400 px-3 py-2"
                                                key={`${unit.id}-w`}
                                            >
                                                Tertimbang (%)
                                            </th>,
                                        ])}
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.rows.map((row) => {
                                        const stageChanged =
                                            row.stage !== lastStage;
                                        lastStage = row.stage;
                                        return [
                                            stageChanged && (
                                                <tr
                                                    className="bg-slate-100 font-extrabold uppercase dark:bg-white/5"
                                                    key={`stage-${row.key}`}
                                                >
                                                    <td
                                                        className="border border-slate-300 px-3 py-2 text-center"
                                                        colSpan={
                                                            4 +
                                                            report.units
                                                                .length *
                                                                2
                                                        }
                                                    >
                                                        {row.stage}
                                                    </td>
                                                </tr>
                                            ),
                                            <tr key={row.key}>
                                                <td className="border border-slate-300 px-3 py-2 text-center">
                                                    {row.no}
                                                </td>
                                                <td className="border border-slate-300 px-3 py-2 font-semibold">
                                                    {row.work}
                                                </td>
                                                <td className="border border-slate-300 px-3 py-2 text-right font-semibold">
                                                    {money(row.amount)}
                                                </td>
                                                <td className="border border-slate-300 px-3 py-2 text-right">
                                                    {percent(row.weight)}
                                                </td>
                                                {row.units.flatMap(
                                                    (unit, index) => [
                                                        <td
                                                            className="border border-slate-300 px-3 py-2 text-right"
                                                            key={`${row.key}-${index}-c`}
                                                        >
                                                            {percent(
                                                                unit.cumulative,
                                                            )}
                                                        </td>,
                                                        <td
                                                            className="border border-slate-300 px-3 py-2 text-right font-extrabold"
                                                            key={`${row.key}-${index}-w`}
                                                        >
                                                            {percent(
                                                                unit.weighted,
                                                            )}
                                                        </td>,
                                                    ],
                                                )}
                                            </tr>,
                                        ];
                                    })}
                                    {report.rows.length === 0 && (
                                        <tr>
                                            <td
                                                className="px-5 py-10 text-center font-bold"
                                                colSpan={8}
                                            >
                                                Belum ada item SPK pada unit
                                                yang dipilih.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                                {report.rows.length > 0 && (
                                    <tfoot className="font-extrabold">
                                        {[
                                            [
                                                "Bobot kumulatif",
                                                "cumulative_weight",
                                                "percent",
                                            ],
                                            [
                                                "Bobot periode sebelumnya",
                                                "previous_weight",
                                                "percent",
                                            ],
                                            [
                                                "Bobot periode ini",
                                                "period_weight",
                                                "percent",
                                            ],
                                            [
                                                "Total opname",
                                                "opname_total",
                                                "money",
                                            ],
                                            [
                                                "Pembayaran sebelumnya",
                                                "payment_previous",
                                                "money",
                                            ],
                                            [
                                                "Pembayaran saat ini",
                                                "payment_period",
                                                "money",
                                            ],
                                            [
                                                "Total pembayaran SPK",
                                                "payment_total",
                                                "money",
                                            ],
                                        ].map(([label, key, format]) => (
                                            <tr
                                                className={
                                                    key.includes("payment") ||
                                                    key.includes("opname")
                                                        ? "bg-orange-50 dark:bg-orange-500/10"
                                                        : ""
                                                }
                                                key={key}
                                            >
                                                <td
                                                    className="border border-slate-300 px-3 py-2 italic"
                                                    colSpan={4}
                                                >
                                                    {label}
                                                </td>
                                                {report.units.map((unit) => (
                                                    <td
                                                        className="border border-slate-300 px-3 py-2 text-right"
                                                        colSpan={2}
                                                        key={`${unit.id}-${key}`}
                                                    >
                                                        {format === "money"
                                                            ? money(unit[key])
                                                            : percent(
                                                                  unit[key],
                                                              )}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tfoot>
                                )}
                            </table>
                        </div>
                        <p className="border-t border-slate-300 px-5 py-3 text-xs font-semibold text-ink-soft dark:border-white/10 dark:text-white/55">
                            Pembayaran dihitung hanya dari termin berstatus Dana
                            Cair pada SPK yang terhubung ke progress unit
                            terpilih.
                        </p>
                    </section>
                ) : (
                    <section className="rounded-lg border border-dashed border-silver-deep p-10 text-center font-bold text-ink-soft dark:border-white/15 dark:text-white/50">
                        Pilih minimal satu blok/unit untuk menampilkan laporan.
                    </section>
                )}
            </div>
        </>
    );
}

ConstructionProgress.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Laporan Progress Pembangunan"}>
        {page}
    </AdminLayout>
);
