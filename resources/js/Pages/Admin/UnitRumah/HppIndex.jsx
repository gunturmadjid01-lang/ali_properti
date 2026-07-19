import { Head, Link, router } from "@inertiajs/react";
import {
    Calculator,
    Eye,
    RotateCcw,
    Search,
    SquareCheckBig,
} from "lucide-react";
import { useMemo, useState } from "react";
import { Button, Dropdown, Input, TableActions } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { useResourcePermissions } from "../../../Utils/permissions";

function money(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function Pagination({ links = [] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
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

export default function HppIndex({
    title,
    description,
    baseUrl,
    rows,
    filters = {},
    options = {},
}) {
    const permissions = useResourcePermissions("rab-unit", baseUrl);
    const [search, setSearch] = useState(filters.search ?? "");
    const [block, setBlock] = useState(filters.block ?? "");
    const [type, setType] = useState(filters.type ?? "");
    const [perPage, setPerPage] = useState(filters.per_page ?? "10");
    const [selectedUnits, setSelectedUnits] = useState([]);
    const canViewHpp =
        permissions.canView || permissions.canCreate || permissions.canManage;
    const canManageHpp = permissions.canCreate || permissions.canManage;
    const allTargets = options.hppUnitTargets ?? [];
    const pageSummary = useMemo(
        () =>
            rows.data.reduce(
                (summary, row) => ({
                    rab: summary.rab + Number(row.total_rab ?? 0),
                    realisasi:
                        summary.realisasi + Number(row.total_realisasi ?? 0),
                    sisa: summary.sisa + Number(row.sisa_anggaran ?? 0),
                }),
                { rab: 0, realisasi: 0, sisa: 0 },
            ),
        [rows.data],
    );

    const submitFilters = (event) => {
        event.preventDefault();
        router.get(
            baseUrl,
            { search, block, type, per_page: perPage },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const resetFilters = () => {
        setSearch("");
        setBlock("");
        setType("");
        setPerPage("10");
        router.get(
            baseUrl,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const selectedProjectId = allTargets.find((target) =>
        selectedUnits.includes(String(target.value)),
    )?.perumahan_id;
    const activeProjectId = selectedProjectId ?? rows.data[0]?.perumahan_id;
    const selectableTargets = allTargets.filter(
        (target) => String(target.perumahan_id) === String(activeProjectId),
    );
    const allSelectableSelected =
        selectableTargets.length > 0 &&
        selectableTargets.every((target) =>
            selectedUnits.includes(String(target.value)),
        );

    const toggleUnit = (row) => {
        const id = String(row.id);
        setSelectedUnits((current) => {
            if (current.includes(id)) {
                return current.filter((value) => value !== id);
            }

            const currentProjectId = allTargets.find((target) =>
                current.includes(String(target.value)),
            )?.perumahan_id;
            if (
                currentProjectId &&
                String(currentProjectId) !== String(row.perumahan_id)
            ) {
                return current;
            }

            return [...current, id];
        });
    };

    const toggleAllSelectable = () => {
        const ids = selectableTargets.map((target) => String(target.value));
        setSelectedUnits(allSelectableSelected ? [] : ids);
    };

    const openSelectedUnits = () => {
        const source = allTargets.find((target) =>
            selectedUnits.includes(String(target.value)),
        );
        if (!source?.url) {
            return;
        }

        const params = new URLSearchParams();
        selectedUnits.forEach((id) => params.append("targets[]", id));
        router.visit(`${source.url}?${params.toString()}`);
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="border-b border-silver-deep/60 pb-5 dark:border-white/10">
                    <p className="text-xs font-extrabold uppercase text-ink-soft">
                        RAB & Anggaran Unit Rumah
                    </p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">
                        {title}
                    </h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">
                        {description}
                    </p>
                </section>

                <section className="grid gap-px overflow-hidden rounded-lg border border-silver-deep/60 bg-silver-deep/60 dark:border-white/10 dark:bg-white/10 md:grid-cols-3">
                    {[
                        ["Total RAB Halaman Ini", pageSummary.rab],
                        ["Total Realisasi", pageSummary.realisasi],
                        ["Sisa Anggaran", pageSummary.sisa],
                    ].map(([label, value]) => (
                        <div
                            className="bg-white px-5 py-4 dark:bg-graphite"
                            key={label}
                        >
                            <p className="text-xs font-extrabold uppercase text-ink-soft dark:text-white/50">
                                {label}
                            </p>
                            <p className="mt-2 text-xl font-extrabold">
                                {money(value)}
                            </p>
                        </div>
                    ))}
                </section>

                {canManageHpp && (
                    <section className="flex flex-col gap-3 border-y border-silver-deep/60 py-5 dark:border-white/10 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-extrabold uppercase text-ink-soft">
                                Unit Terpilih
                            </p>
                            <p className="mt-1 text-xl font-extrabold">
                                {selectedUnits.length} unit rumah
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={selectableTargets.length === 0}
                                onClick={toggleAllSelectable}
                            >
                                <SquareCheckBig size={17} />{" "}
                                {allSelectableSelected
                                    ? "Batalkan Semua"
                                    : "Pilih Semua Satu Proyek"}
                            </Button>
                            <Button
                                type="button"
                                disabled={selectedUnits.length === 0}
                                onClick={openSelectedUnits}
                            >
                                <Calculator size={17} /> Tambah HPP{" "}
                                {selectedUnits.length > 0
                                    ? `${selectedUnits.length} Unit`
                                    : ""}
                            </Button>
                        </div>
                    </section>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 lg:grid-cols-[1.4fr_1fr_1fr_0.8fr_auto_auto] lg:items-end"
                        onSubmit={submitFilters}
                    >
                        <Input
                            label="Pencarian"
                            value={search}
                            placeholder="Cari perumahan, blok, nomor, tipe..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Filter Blok
                            </span>
                            <Dropdown
                                value={block}
                                options={options.filterBlokOptions}
                                onChange={setBlock}
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Filter Tipe
                            </span>
                            <Dropdown
                                value={type}
                                options={options.tipeRumahOptions}
                                onChange={setType}
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Show Page
                            </span>
                            <Dropdown
                                value={perPage}
                                options={options.perPageOptions}
                                searchable={false}
                                onChange={setPerPage}
                            />
                        </div>
                        <Button type="submit">
                            <Search size={17} /> Cari
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={resetFilters}
                        >
                            <RotateCcw size={17} /> Atur Ulang
                        </Button>
                    </form>

                    <div className="border-t border-silver-deep/60 px-5 py-3 text-sm font-bold text-ink-soft dark:border-white/10 dark:text-white/55">
                        Menampilkan {rows.from ?? 0} - {rows.to ?? 0} dari{" "}
                        {rows.total ?? 0} unit rumah.
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {[
                                        "Pilih",
                                        "Perumahan",
                                        "Rumah",
                                        "Tipe",
                                        "Total RAB",
                                        "Realisasi",
                                        "Sisa Anggaran",
                                        "Pemakaian",
                                        "Status Bangun",
                                        "Aksi",
                                    ].map((column) => (
                                        <th
                                            className="px-5 py-4 font-extrabold"
                                            key={column}
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 text-center">
                                            <input
                                                aria-label={`Pilih unit ${row.blok_label} ${row.nomor_rumah}`}
                                                type="checkbox"
                                                checked={selectedUnits.includes(
                                                    String(row.id),
                                                )}
                                                disabled={
                                                    Boolean(
                                                        selectedProjectId,
                                                    ) &&
                                                    String(
                                                        selectedProjectId,
                                                    ) !==
                                                        String(row.perumahan_id)
                                                }
                                                onChange={() => toggleUnit(row)}
                                            />
                                        </td>
                                        <td className="px-5 py-4 font-semibold">
                                            {row.perumahan}
                                        </td>
                                        <td className="px-5 py-4 font-extrabold">
                                            {row.blok_label} {row.nomor_rumah}
                                        </td>
                                        <td className="px-5 py-4 font-semibold">
                                            {row.tipe_rumah ?? "-"}
                                        </td>
                                        <td className="px-5 py-4 font-extrabold">
                                            {money(row.total_rab)}
                                        </td>
                                        <td className="px-5 py-4 font-extrabold">
                                            {money(row.total_realisasi)}
                                        </td>
                                        <td className="px-5 py-4 font-extrabold">
                                            {money(row.sisa_anggaran)}
                                        </td>
                                        <td className="px-5 py-4">
                                            <div className="min-w-32">
                                                <div className="h-2 overflow-hidden rounded-full bg-silver-deep/70 dark:bg-white/10">
                                                    <div
                                                        className="h-full bg-emerald-600"
                                                        style={{
                                                            width: `${Math.min(100, Number(row.persentase_anggaran ?? 0))}%`,
                                                        }}
                                                    />
                                                </div>
                                                <p className="mt-1 text-xs font-extrabold">
                                                    {Number(
                                                        row.persentase_anggaran ??
                                                            0,
                                                    ).toFixed(2)}
                                                    %
                                                </p>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 font-semibold">
                                            {row.status_pembangunan}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                {canViewHpp && (
                                                    <Button
                                                        as={Link}
                                                        href={row.hpp_url}
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        {row.has_hpp ? (
                                                            <Eye size={15} />
                                                        ) : (
                                                            <Calculator
                                                                size={15}
                                                            />
                                                        )}
                                                        {row.has_hpp
                                                            ? "Lihat RAB"
                                                            : "Tambah HPP"}
                                                    </Button>
                                                )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center font-bold text-ink-soft"
                                            colSpan={10}
                                        >
                                            Belum ada unit rumah.
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

HppIndex.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "HPP Unit Rumah"}>
        {page}
    </AdminLayout>
);
