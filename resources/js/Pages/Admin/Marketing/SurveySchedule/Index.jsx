import { Head, router } from "@inertiajs/react";
import {
    CalendarCheck,
    CheckCircle2,
    Edit3,
    Home,
    Lock,
    MapPin,
    Search,
    Trash2,
    Unlock,
} from "lucide-react";
import { useMemo, useState } from "react";
import Pagination from "../../../../Components/Pagination";
import {
    Button,
    Dropdown,
    Input,
    TableActions,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Index({
    title,
    description,
    baseUrl,
    rows = { data: [], links: [] },
    filters = {},
    options = {},
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [status, setStatus] = useState(filters.status ?? "");
    const [perumahanId, setPerumahanId] = useState(filters.perumahan_id ?? "");
    const [unitId, setUnitId] = useState(filters.detail_rumah_id ?? "");
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? "");
    const [dateTo, setDateTo] = useState(filters.date_to ?? "");
    const units = useMemo(
        () =>
            (options.detailRumahs ?? []).filter(
                (unit) =>
                    !perumahanId || unit.perumahan_id === String(perumahanId),
            ),
        [options.detailRumahs, perumahanId],
    );
    const filter = (event) => {
        event.preventDefault();
        router.get(
            baseUrl,
            {
                search,
                status,
                perumahan_id: perumahanId,
                detail_rumah_id: unitId,
                date_from: dateFrom,
                date_to: dateTo,
            },
            { preserveState: true, replace: true },
        );
    };
    const remove = (row) => {
        if (window.confirm(`Hapus jadwal survey ${row.kode_survey}?`))
            router.delete(`${baseUrl}/${row.id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                        Marketing / Tahap 4
                    </p>
                    <div className="mt-2 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h1 className="text-3xl font-extrabold">{title}</h1>
                            <p className="mt-2 max-w-3xl text-ink-soft">
                                {description}
                            </p>
                        </div>
                        {permissions.canCreate && (
                            <Button
                                type="button"
                                onClick={() =>
                                    router.visit(`${baseUrl}/create`)
                                }
                            >
                                <CalendarCheck size={17} /> Tambah Jadwal Survey
                            </Button>
                        )}
                    </div>
                    <div className="mt-5 grid gap-3 md:grid-cols-3">
                        <Info
                            icon={Home}
                            title="Khusus survey unit"
                            text="Dipakai saat customer melihat perumahan atau unit yang diminati."
                        />
                        <Info
                            icon={MapPin}
                            title="Bukan kunjungan bebas"
                            text="Canvassing, event, dan kunjungan ke rumah customer dicatat pada menu Kunjungan Customer."
                        />
                        <Info
                            icon={CheckCircle2}
                            title="Hasil terpisah"
                            text="Jadwal dibuat dahulu; hasil, keberatan, dan tindak lanjut diisi melalui halaman Hasil Survey."
                        />
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 xl:grid-cols-[1.2fr_1fr_1fr_1fr_150px_150px_auto]"
                        onSubmit={filter}
                    >
                        <Input
                            label="Cari Survei / Pelanggan"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Select
                            label="Status"
                            value={status}
                            options={[
                                { value: "", label: "Semua Status" },
                                ...(options.statusOptions ?? []),
                            ]}
                            onChange={setStatus}
                        />
                        <Select
                            label="Perumahan"
                            value={perumahanId}
                            options={[
                                { value: "", label: "Semua Perumahan" },
                                ...(options.perumahans ?? []),
                            ]}
                            onChange={(value) => {
                                setPerumahanId(value);
                                setUnitId("");
                            }}
                        />
                        <Select
                            label="Unit"
                            value={unitId}
                            options={[
                                { value: "", label: "Semua Unit" },
                                ...units,
                            ]}
                            onChange={setUnitId}
                        />
                        <Input
                            label="Dari"
                            type="date"
                            value={dateFrom}
                            onChange={(event) =>
                                setDateFrom(event.target.value)
                            }
                        />
                        <Input
                            label="Sampai"
                            type="date"
                            value={dateTo}
                            onChange={(event) => setDateTo(event.target.value)}
                        />
                        <div className="flex items-end">
                            <Button type="submit">
                                <Search size={16} /> Cari
                            </Button>
                        </div>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                <tr>
                                    {[
                                        "Kode",
                                        "Jadwal",
                                        "Customer",
                                        "Lokasi",
                                        "Marketing",
                                        "Status",
                                        "Tindak Lanjut",
                                        "Kunci",
                                        "Aksi",
                                    ].map((label) => (
                                        <th className="px-5 py-4" key={label}>
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">
                                            {row.kode_survey}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.tanggal_survey_display}
                                        </td>
                                        <td className="px-5 py-4">
                                            <b>{row.customer}</b>
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.telepon}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.perumahan}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.unit}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.marketing}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.status_label}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.rencana_follow_up_display ||
                                                "-"}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.record_status}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                {row.can_edit && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.visit(
                                                                `${baseUrl}/${row.id}/hasil`,
                                                            )
                                                        }
                                                    >
                                                        <CheckCircle2
                                                            size={14}
                                                        />{" "}
                                                        Hasil Survey
                                                    </Button>
                                                )}
                                                {row.can_edit && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.visit(
                                                                `${baseUrl}/${row.id}/edit`,
                                                            )
                                                        }
                                                    >
                                                        <Edit3 size={14} /> Ubah
                                                        Jadwal
                                                    </Button>
                                                )}
                                                {row.can_delete && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="text-red-600"
                                                        onClick={() =>
                                                            remove(row)
                                                        }
                                                    >
                                                        <Trash2 size={14} />
                                                    </Button>
                                                )}
                                                {row.can_lock && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `${baseUrl}/${row.id}/lock`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Lock size={14} /> Kunci
                                                    </Button>
                                                )}
                                                {row.can_unlock && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `${baseUrl}/${row.id}/unlock`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Unlock size={14} />{" "}
                                                        Unlock
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
                                            colSpan={9}
                                        >
                                            Belum ada jadwal survey.
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

function Info({ icon: Icon, title, text }) {
    return (
        <div className="rounded-2xl border border-silver-deep/60 bg-silver-soft/70 p-4 text-sm">
            <div className="flex items-center gap-2 font-black">
                <Icon size={17} /> {title}
            </div>
            <p className="mt-2 text-ink-soft">{text}</p>
        </div>
    );
}

function Select({ label, value, options, onChange }) {
    return (
        <div className="grid gap-2">
            <span className="text-sm font-extrabold">{label}</span>
            <Dropdown value={value} options={options} onChange={onChange} />
        </div>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Jadwal Survey"}>
        {page}
    </AdminLayout>
);
