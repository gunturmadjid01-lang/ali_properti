import { Head, router, usePage } from "@inertiajs/react";
import {
    Boxes,
    FileDown,
    PackageCheck,
    ReceiptText,
    RotateCcw,
    Search,
} from "lucide-react";
import { useMemo, useState } from "react";
import { Button, Dropdown, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const number = (value) =>
    Number(value ?? 0).toLocaleString("id-ID", {
        maximumFractionDigits: 2,
    });

const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

const date = (value) =>
    value
        ? new Intl.DateTimeFormat("id-ID", {
              day: "2-digit",
              month: "short",
              year: "numeric",
          }).format(new Date(`${value}T00:00:00`))
        : "-";

export default function MaterialUsage({
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
        filters.period_type ?? "monthly",
    );
    const [referenceDate, setReferenceDate] = useState(
        filters.reference_date ?? "",
    );
    const [month, setMonth] = useState(filters.month ?? "");
    const [perumahanId, setPerumahanId] = useState(filters.perumahan_id ?? "");
    const [unitId, setUnitId] = useState(filters.detail_rumah_id ?? "");

    const projectOptions = useMemo(
        () => [{ value: "", label: "Semua Perumahan" }, ...options.perumahans],
        [options.perumahans],
    );
    const unitOptions = useMemo(
        () => [
            { value: "", label: "Semua Unit" },
            ...options.units.filter(
                (unit) => !perumahanId || unit.perumahan_id === perumahanId,
            ),
        ],
        [options.units, perumahanId],
    );

    const params = () => ({
        period_type: periodType,
        reference_date: referenceDate,
        month,
        perumahan_id: perumahanId,
        detail_rumah_id: unitId,
    });

    const submit = (event) => {
        event.preventDefault();
        router.get(baseUrl, params(), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const openPrint = () => {
        const query = new URLSearchParams();
        Object.entries(params()).forEach(([key, value]) => {
            if (value) query.set(key, value);
        });
        window.open(
            `${printUrl}?${query.toString()}`,
            "_blank",
            "noopener,noreferrer",
        );
    };

    const cards = [
        ["Transaksi Pemakaian", report.totals.transactions, ReceiptText],
        ["Jenis Material", report.totals.materials, Boxes],
        ["Baris Barang", report.totals.item_lines, PackageCheck],
        ["Estimasi Nilai HPP", money(report.totals.amount), FileDown],
    ];

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="border-b border-silver-deep/60 pb-5 dark:border-white/10">
                    <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-ink-soft">
                        Monitoring Material Proyek
                    </p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">
                        {title}
                    </h2>
                    <p className="mt-2 max-w-4xl leading-7 text-ink-soft dark:text-white/60">
                        {description}
                    </p>
                </section>

                <form
                    className="grid gap-4 rounded-lg border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8"
                    onSubmit={submit}
                >
                    <div className="grid gap-4 lg:grid-cols-3">
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Perumahan</span>
                            <Dropdown
                                value={perumahanId}
                                options={projectOptions}
                                onChange={(value) => {
                                    setPerumahanId(value);
                                    setUnitId("");
                                }}
                            />
                        </label>
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Unit / Blok</span>
                            <Dropdown
                                value={unitId}
                                options={unitOptions}
                                onChange={setUnitId}
                            />
                            {errors.detail_rumah_id && (
                                <small className="text-red-600">
                                    {errors.detail_rumah_id}
                                </small>
                            )}
                        </label>
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Jenis Periode</span>
                            <Dropdown
                                value={periodType}
                                options={options.periodTypes}
                                searchable={false}
                                onChange={setPeriodType}
                            />
                        </label>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-3">
                        {periodType === "monthly" ? (
                            <Input
                                label="Bulan"
                                type="month"
                                value={month}
                                error={errors.month}
                                onChange={(event) =>
                                    setMonth(event.target.value)
                                }
                            />
                        ) : (
                            <Input
                                label={
                                    periodType === "weekly"
                                        ? "Tanggal dalam Minggu"
                                        : "Tanggal"
                                }
                                type="date"
                                value={referenceDate}
                                error={errors.reference_date}
                                onChange={(event) =>
                                    setReferenceDate(event.target.value)
                                }
                            />
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit">
                            <Search size={17} /> Tampilkan Laporan
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.get(baseUrl, {}, { replace: true })
                            }
                        >
                            <RotateCcw size={17} /> Atur Ulang
                        </Button>
                        {permissions.canExport && (
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

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(([label, value, Icon]) => (
                        <article
                            className="rounded-lg border border-white/80 bg-white/85 p-4 shadow-soft dark:border-white/10 dark:bg-white/8"
                            key={label}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft dark:text-white/50">
                                        {label}
                                    </p>
                                    <p className="mt-2 text-2xl font-extrabold">
                                        {value}
                                    </p>
                                </div>
                                <Icon className="text-emerald-600" size={22} />
                            </div>
                        </article>
                    ))}
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/90 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                        <h3 className="text-lg font-extrabold">
                            Ringkasan Pemakaian per Barang
                        </h3>
                        <p className="mt-1 text-sm font-semibold text-ink-soft dark:text-white/55">
                            {report.scope.project} · {report.scope.unit} ·{" "}
                            {report.period.label}
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-sm">
                            <thead className="bg-slate-100 text-xs uppercase dark:bg-white/10">
                                <tr>
                                    <th className="px-4 py-3 text-left">
                                        Kode / Material
                                    </th>
                                    <th className="px-4 py-3 text-left">
                                        Jenis / Merek
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Jumlah
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Harga HPP
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Estimasi Nilai
                                    </th>
                                    <th className="px-4 py-3 text-center">
                                        Transaksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-white/10">
                                {report.summary.map((row) => (
                                    <tr
                                        key={`${row.material_code}-${row.unit_name}`}
                                    >
                                        <td className="px-4 py-3">
                                            <strong>{row.material}</strong>
                                            <div className="text-xs text-ink-soft">
                                                {row.material_code}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {[row.material_type, row.brand]
                                                .filter(Boolean)
                                                .join(" · ") || "-"}
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold">
                                            {number(row.quantity)}{" "}
                                            {row.unit_name}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {money(row.unit_price)}
                                        </td>
                                        <td className="px-4 py-3 text-right font-extrabold">
                                            {money(row.amount)}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            {row.transaction_count}
                                        </td>
                                    </tr>
                                ))}
                                {report.summary.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-10 text-center font-bold text-ink-soft"
                                            colSpan={6}
                                        >
                                            Belum ada pemakaian barang pada
                                            filter ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/90 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                        <h3 className="text-lg font-extrabold">
                            Rincian Pemakaian pada Progress
                        </h3>
                        <p className="mt-1 text-sm text-ink-soft dark:text-white/55">
                            Setiap baris berasal dari transaksi pemakaian yang
                            terhubung langsung ke progress pembangunan.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1250px] text-sm">
                            <thead className="bg-slate-100 text-xs uppercase dark:bg-white/10">
                                <tr>
                                    <th className="px-4 py-3 text-left">
                                        Tanggal / Kode
                                    </th>
                                    <th className="px-4 py-3 text-left">
                                        Perumahan / Unit
                                    </th>
                                    <th className="px-4 py-3 text-left">
                                        Tahapan / Progress
                                    </th>
                                    <th className="px-4 py-3 text-left">
                                        Barang
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Jumlah
                                    </th>
                                    <th className="px-4 py-3 text-right">
                                        Nilai HPP
                                    </th>
                                    <th className="px-4 py-3 text-left">
                                        Keterangan
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-white/10">
                                {report.details.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-3">
                                            <strong>{date(row.date)}</strong>
                                            <div className="text-xs text-ink-soft">
                                                {row.code}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <strong>{row.project}</strong>
                                            <div className="text-xs text-ink-soft">
                                                {row.unit}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <strong>{row.stage}</strong>
                                            <div className="text-xs text-ink-soft">
                                                {row.progress} ·{" "}
                                                {number(
                                                    row.progress_percentage,
                                                )}
                                                %
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <strong>{row.material}</strong>
                                            <div className="text-xs text-ink-soft">
                                                {row.work_item ||
                                                    row.material_code}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold">
                                            {number(row.quantity)}{" "}
                                            {row.unit_name}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {money(row.amount)}
                                        </td>
                                        <td className="max-w-[260px] px-4 py-3">
                                            {row.note || "-"}
                                        </td>
                                    </tr>
                                ))}
                                {report.details.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-4 py-10 text-center font-bold text-ink-soft"
                                            colSpan={7}
                                        >
                                            Tidak ada rincian pemakaian barang.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
                <p className="text-xs font-semibold text-ink-soft dark:text-white/50">
                    Estimasi nilai memakai harga HPP material yang tercatat saat
                    laporan ditampilkan.
                </p>
            </div>
        </>
    );
}

MaterialUsage.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Laporan Pemakaian Barang"}>
        {page}
    </AdminLayout>
);
