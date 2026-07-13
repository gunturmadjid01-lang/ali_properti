import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, Eye } from "lucide-react";
import { Accordion, Button } from "../../../../Components/UI";
import AuditCell from "../../../../Components/UI/AuditCell";
import AdminLayout from "../../../../Layouts/AdminLayout";

function money(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function Pagination({ links = [] }) {
    if (links.length <= 3) return null;

    return (
        <div className="flex flex-wrap justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
            {links.map((link, index) => (
                <Button
                    as={Link}
                    className={
                        !link.url ? "pointer-events-none opacity-45" : ""
                    }
                    href={link.url ?? "#"}
                    key={`${link.label}-${index}`}
                    preserveScroll
                    size="sm"
                    variant={link.active ? "dark" : "outline"}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

function Detail({
    title = "Detail Perumahan",
    perumahan = {},
    rows = { data: [], links: [] },
    baseUrl,
}) {
    const pageTitle = `${title} ${perumahan.nama_perusahaan ?? ""}`.trim();
    const overviewAccordion = {
        title: "Detail Perumahan",
        content: (
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {[
                    ["Nama Perumahan", perumahan.nama_perusahaan ?? "-"],
                    ["Cabang", perumahan.cabang ?? "-"],
                    ["Alamat", perumahan.alamat ?? "-"],
                    ["Jumlah Unit", perumahan.jumlah_unit ?? "0"],
                    ["Status", perumahan.status ?? "-"],
                    ["HPP Perumahan", money(perumahan.total_hpp_perumahan)],
                    [
                        "Realisasi Kawasan",
                        money(perumahan.total_realisasi_perumahan),
                    ],
                ].map(([label, value]) => (
                    <div
                        className="rounded-lg border border-silver-deep/50 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5"
                        key={label}
                    >
                        <p className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                            {label}
                        </p>
                        <p className="mt-1 text-sm font-bold text-ink dark:text-white">
                            {value}
                        </p>
                    </div>
                ))}
            </div>
        ),
    };

    return (
        <>
            <Head title={pageTitle} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <Button
                                as={Link}
                                href={baseUrl}
                                variant="ghost"
                                size="sm"
                                className="mb-3"
                            >
                                <ArrowLeft size={16} /> Kembali
                            </Button>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                                Detail Perumahan
                            </p>
                            <h2 className="mt-2 font-display text-3xl font-extrabold">
                                {perumahan.nama_perusahaan}
                            </h2>
                            <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">
                                {perumahan.cabang ?? "-"} | {perumahan.alamat}
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                                    HPP Perumahan
                                </p>
                                <p className="mt-1 text-xl font-extrabold">
                                    {money(perumahan.total_hpp_perumahan)}
                                </p>
                            </div>
                            <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                                    Realisasi Kawasan
                                </p>
                                <p className="mt-1 text-xl font-extrabold">
                                    {money(perumahan.total_realisasi_perumahan)}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <Accordion items={[overviewAccordion]} defaultOpen={0} />

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b border-silver-deep/60 px-5 py-4 dark:border-white/10">
                        <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">
                            Daftar Rumah
                        </p>
                        <h3 className="mt-0.5 text-base font-extrabold">
                            Kapling / Unit Rumah
                        </h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {[
                                        "Blok",
                                        "Nomor",
                                        "Tipe",
                                        "Progress",
                                        "Status Bangun",
                                        "Harga Jual",
                                        "Audit",
                                        "RAB HPP",
                                        "Realisasi",
                                        "Aksi",
                                    ].map((column) => (
                                        <th
                                            className="px-4 py-3 font-extrabold"
                                            key={column}
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr
                                        className="transition hover:bg-silver/70 dark:hover:bg-white/5"
                                        key={row.id}
                                    >
                                        <td className="px-4 py-3 font-semibold">
                                            {row.blok_label}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {row.nomor_rumah}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {row.tipe_rumah ?? "-"}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {row.progress_terakhir}%
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {row.status_pembangunan}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {money(row.harga_jual)}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            <AuditCell
                                                createdBy={row.created_by}
                                                updatedBy={row.updated_by}
                                            />
                                        </td>
                                        <td className="px-4 py-3 font-extrabold">
                                            {money(row.total_rab)}
                                        </td>
                                        <td className="px-4 py-3 font-extrabold">
                                            {money(row.total_realisasi)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <Button
                                                as={Link}
                                                href={row.detail_url}
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Eye size={15} /> Detail Unit
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center font-bold text-ink-soft dark:text-white/50"
                                            colSpan={10}
                                        >
                                            Belum ada data rumah/unit untuk
                                            perumahan ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>
        </>
    );
}

Detail.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail Perumahan"}>
        {page}
    </AdminLayout>
);

export default Detail;
