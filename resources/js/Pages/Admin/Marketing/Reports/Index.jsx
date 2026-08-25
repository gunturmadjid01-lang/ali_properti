import { Head, Link, router } from "@inertiajs/react";
import { Download, FileText, Filter, Printer, RotateCcw } from "lucide-react";
import { useState } from "react";
import Pagination from "../../../../Components/Pagination";
import { Button, Dropdown, Input } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const blank = [{ value: "", label: "Semua" }];

const reportHelp = {
    activities:
        "Aktivitas marketing otomatis dari input lead, follow-up, kunjungan, survey, action plan, dan perubahan status customer.",
    visits: "Peta berasal dari GPS check-in/check-out pada menu Kunjungan Customer / Canvassing. Marketing tidak mengetik koordinat manual.",
    "inactive-customers":
        "Customer tidak aktif dihitung otomatis dari aktivitas terakhir. Jika belum pernah ada aktivitas, sistem memakai tanggal input customer.",
};

function LabeledDropdown({ label, ...props }) {
    return (
        <div className="grid gap-2">
            <span className="text-sm font-extrabold text-ink dark:text-white">
                {label}
            </span>
            <Dropdown {...props} label={label} />
        </div>
    );
}

export default function Index({
    title,
    reportType,
    reportTypes,
    report,
    filters,
    options = {},
    canExport,
}) {
    const [form, setForm] = useState(filters);
    const set = (key, value) => setForm((old) => ({ ...old, [key]: value }));
    const query = new URLSearchParams(
        Object.entries(form).filter(
            ([, value]) => value !== "" && value != null,
        ),
    ).toString();
    const apply = (event) => {
        event.preventDefault();
        router.get(`/admin/marketing/reports/${reportType}`, form, {
            preserveState: true,
            replace: true,
        });
    };
    const reset = () => router.get(`/admin/marketing/reports/${reportType}`);

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <header className="rounded-3xl bg-[#171d24] p-6 text-white shadow-soft">
                    <p className="text-xs font-black uppercase tracking-[.2em] text-amber-300">
                        Pusat laporan marketing
                    </p>
                    <div className="mt-2 flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h1 className="text-3xl font-black">{title}</h1>
                            <p className="mt-2 text-sm text-white/65">
                                {reportHelp[reportType] ??
                                    "Data berasal langsung dari aktivitas dan transaksi terkait, bukan input laporan terpisah."}
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2 print:hidden">
                            <Button
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer size={16} /> Cetak
                            </Button>
                            {canExport &&
                                ["csv", "excel", "pdf"].map((format) => (
                                    <Button
                                        as="a"
                                        key={format}
                                        href={`/admin/marketing/reports/${reportType}/export/${format}?${query}`}
                                    >
                                        <Download size={16} />{" "}
                                        {format.toUpperCase()}
                                    </Button>
                                ))}
                        </div>
                    </div>
                </header>
                <nav className="flex gap-2 overflow-x-auto pb-2 print:hidden">
                    {Object.entries(reportTypes).map(([key, label]) => (
                        <Button
                            key={key}
                            as={Link}
                            size="sm"
                            variant={key === reportType ? "primary" : "outline"}
                            href={`/admin/marketing/reports/${key}`}
                        >
                            {label}
                        </Button>
                    ))}
                </nav>
                <form
                    onSubmit={apply}
                    className="grid gap-3 rounded-2xl border bg-white/85 p-5 shadow-soft dark:border-white/10 dark:bg-white/8 md:grid-cols-4 xl:grid-cols-6 print:hidden"
                >
                    <Input
                        type="date"
                        label="Tanggal mulai"
                        value={form.date_from || ""}
                        onChange={(e) => set("date_from", e.target.value)}
                    />
                    <Input
                        type="date"
                        label="Tanggal selesai"
                        value={form.date_to || ""}
                        onChange={(e) => set("date_to", e.target.value)}
                    />
                    <LabeledDropdown
                        label="Marketing"
                        value={String(form.marketing_id || "")}
                        options={[...blank, ...(options.marketings || [])]}
                        onChange={(v) => set("marketing_id", v)}
                    />
                    <LabeledDropdown
                        label="Customer"
                        value={String(form.customer_id || "")}
                        options={[...blank, ...(options.customers || [])]}
                        onChange={(v) => set("customer_id", v)}
                    />
                    <LabeledDropdown
                        label="Perumahan"
                        value={String(form.perumahan_id || "")}
                        options={[...blank, ...(options.perumahans || [])]}
                        onChange={(v) => set("perumahan_id", v)}
                    />
                    <LabeledDropdown
                        label="Status customer"
                        value={String(form.status || "")}
                        options={[...blank, ...(options.statuses || [])]}
                        onChange={(v) => set("status", v)}
                    />
                    <LabeledDropdown
                        label="Sumber lead"
                        value={String(form.lead_source_id || "")}
                        options={[...blank, ...(options.leadSources || [])]}
                        onChange={(v) => set("lead_source_id", v)}
                    />
                    <LabeledDropdown
                        label="Campaign"
                        value={String(form.campaign_id || "")}
                        options={[...blank, ...(options.campaigns || [])]}
                        onChange={(v) => set("campaign_id", v)}
                    />
                    <LabeledDropdown
                        label="Metode bayar"
                        value={String(form.payment_plan || "")}
                        options={[...blank, ...(options.paymentOptions || [])]}
                        onChange={(v) => set("payment_plan", v)}
                    />
                    <LabeledDropdown
                        label="Tingkat minat"
                        value={String(form.interest_level || "")}
                        options={[...blank, ...(options.interestOptions || [])]}
                        onChange={(v) => set("interest_level", v)}
                    />
                    <LabeledDropdown
                        label="Unit diminati"
                        value={String(form.unit_id || "")}
                        options={[...blank, ...(options.units || [])]}
                        onChange={(v) => set("unit_id", v)}
                    />
                    <LabeledDropdown
                        label="Data minat unit"
                        value={String(form.has_unit_interest || "")}
                        options={[
                            ...blank,
                            { value: "1", label: "Ada minat unit" },
                        ]}
                        onChange={(v) => set("has_unit_interest", v)}
                    />
                    {reportType === "visits" && (
                        <>
                            <LabeledDropdown
                                label="Jenis kunjungan"
                                value={form.visit_type || ""}
                                options={[
                                    ...blank,
                                    ...(options.visitTypes || []),
                                ]}
                                onChange={(v) => set("visit_type", v)}
                            />
                            <LabeledDropdown
                                label="Status kunjungan"
                                value={form.visit_status || ""}
                                options={[
                                    ...blank,
                                    ...(options.visitStatuses || []),
                                ]}
                                onChange={(v) => set("visit_status", v)}
                            />
                        </>
                    )}
                    {reportType === "inactive-customers" && (
                        <Input
                            type="number"
                            min="1"
                            max="365"
                            label="Tidak aktif minimal (hari)"
                            value={form.inactive_days || 7}
                            onChange={(e) =>
                                set("inactive_days", e.target.value)
                            }
                        />
                    )}
                    <div className="flex items-end gap-2">
                        <Button type="submit">
                            <Filter size={16} /> Terapkan
                        </Button>
                        <Button type="button" variant="outline" onClick={reset}>
                            <RotateCcw size={16} /> Reset
                        </Button>
                    </div>
                </form>
                <section className="overflow-hidden rounded-2xl border bg-white/90 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b p-4">
                        <p className="text-sm font-bold text-ink-soft">
                            Filter aktif:{" "}
                            {Object.entries(form)
                                .filter(([, v]) => v)
                                .map(
                                    ([k, v]) =>
                                        `${k.replaceAll("_", " ")}: ${v}`,
                                )
                                .join(" · ") || "periode berjalan"}
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft text-left text-xs uppercase tracking-wider">
                                <tr>
                                    {report.columns.map((c) => (
                                        <th
                                            key={c}
                                            className="whitespace-nowrap px-4 py-3"
                                        >
                                            {c}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {report.rows.data.map((row, i) => (
                                    <tr key={i}>
                                        {row.map((cell, j) => (
                                            <td
                                                key={j}
                                                className="max-w-sm whitespace-normal px-4 py-3"
                                            >
                                                {cell ?? "-"}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                                {!report.rows.data.length && (
                                    <tr>
                                        <td
                                            colSpan={report.columns.length}
                                            className="p-12 text-center"
                                        >
                                            <FileText className="mx-auto text-ink-soft" />
                                            <p className="mt-3 font-bold text-ink-soft">
                                                Tidak ada data sesuai filter.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={report.rows.links} />
                </section>
            </div>
        </>
    );
}
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Laporan Marketing"}>
        {page}
    </AdminLayout>
);
