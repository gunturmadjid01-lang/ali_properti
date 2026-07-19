import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    CalendarClock,
    Edit3,
    Eye,
    Lock,
    Plus,
    Search,
    Trash2,
    Unlock,
    X,
} from "lucide-react";
import { Fragment, useMemo, useState } from "react";
import Pagination from "../../../Components/Pagination";
import {
    Button,
    Dropdown,
    Form,
    Input,
    Modal,
    Textarea,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { useResourcePermissions } from "../../../Utils/permissions";

const numberFormat = new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 2,
});

const makePeriodColumns = (count) =>
    Array.from({ length: Math.max(1, Number(count || 1)) }, (_, index) => ({
        index,
        periode: index + 1,
        month: Math.floor(index / 4) + 1,
        week: (index % 4) + 1,
    }));

const toNumber = (value) => Number(String(value ?? "").replace(",", ".")) || 0;
const sumNumbers = (items) =>
    items.reduce((sum, value) => sum + toNumber(value), 0);
function money(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}
const normalizeDecimalInput = (value) => {
    const cleaned = String(value ?? "")
        .replace(",", ".")
        .replace(/[^0-9.]/g, "");
    const [first, ...rest] = cleaned.split(".");

    return rest.length ? `${first}.${rest.join("")}` : first;
};
const normalizeIntegerInput = (value) =>
    String(value ?? "").replace(/[^0-9]/g, "");
const isAllocationFilled = (value) =>
    String(value ?? "").trim() !== "" && toNumber(value) > 0;
const stripRomanPrefix = (value) =>
    String(value ?? "")
        .replace(/^\s*[IVXLCDM]+\s*[\.\-]?\s+/i, "")
        .trim();
const formatInputNumber = (value) => {
    const number = Math.max(0, Number(value || 0));

    return Number.isInteger(number)
        ? String(number)
        : String(Number(number.toFixed(2)));
};
const distributeWeight = (weight, duration) => {
    const total = toNumber(weight);
    const count = Math.max(1, Number(duration || 1));
    const base = Math.floor((total / count) * 100) / 100;
    const values = Array.from({ length: count }, () => base);
    const remainder = Number((total - base * count).toFixed(2));

    values[count - 1] = Number((values[count - 1] + remainder).toFixed(2));

    return values.map(formatInputNumber);
};
const setAllocationByWeeks = (targetProgress, selectedWeeks, periodCount) => {
    const weeks = (selectedWeeks ?? [])
        .map((week) => Number(week))
        .filter(
            (week) => Number.isInteger(week) && week > 0 && week <= periodCount,
        )
        .sort((a, b) => a - b);
    const allocations = Array.from({ length: periodCount }, () => "");

    if (weeks.length === 0) {
        return allocations;
    }

    const values = distributeWeight(targetProgress, weeks.length);
    weeks.forEach((week, index) => {
        allocations[week - 1] = values[index] ?? "";
    });

    return allocations;
};
const getPlanPeriodCount = (planRows = [], fallback = 1) => {
    const maxFromPlan = Math.max(
        0,
        ...planRows.flatMap((row) =>
            (row.allocations ?? []).map((allocation) =>
                Number(allocation.periode_ke || 0),
            ),
        ),
    );

    return Math.max(1, Number(fallback || 1), maxFromPlan);
};
const buildPreviewPlanRows = (row) => {
    const planRows = row?.spk_plan ?? [];

    if (planRows.length > 0) {
        return planRows;
    }

    return [
        {
            urut: 1,
            nama_tahap_pekerjaan: row?.nama_pekerjaan ?? "-",
            target_progress: row?.target_progress ?? 0,
            group_total: row?.target_progress ?? 0,
            items: [],
            allocations: row?.allocations ?? [],
        },
    ];
};
const getPlanColumns = (planRows = [], fallback = 1) =>
    makePeriodColumns(getPlanPeriodCount(planRows, fallback));
const getMonthGroupsFromColumns = (columns = []) =>
    columns.reduce((groups, column) => {
        const last = groups[groups.length - 1];
        if (last && last.month === column.month) {
            last.count += 1;
            return groups;
        }
        return [...groups, { month: column.month, count: 1 }];
    }, []);
const getPlanWeekValue = (planRow, period) =>
    Number(
        (planRow.allocations ?? []).find(
            (allocation) => Number(allocation.periode_ke) === Number(period),
        )?.bobot_persen ?? 0,
    );
const sumPlanWeek = (planRows, period) =>
    sumNumbers(planRows.map((planRow) => getPlanWeekValue(planRow, period)));
const getPlanCumulative = (values = []) =>
    values.reduce((carry, value) => {
        const next = Number(
            (carry.length ? carry[carry.length - 1] : 0) + Number(value || 0),
        ).toFixed(2);
        return [...carry, Number(next)];
    }, []);

export default function Index({
    title,
    baseUrl,
    rows = { data: [], links: [] },
    filters = {},
    options = {},
    auth = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [filterPerumahan, setFilterPerumahan] = useState(
        filters.perumahan_id ?? "",
    );
    const [filterUnit, setFilterUnit] = useState(filters.detail_rumah_id ?? "");
    const [detail, setDetail] = useState(null);
    const [editing, setEditing] = useState(null);
    const permissions = useResourcePermissions("site-schedule", baseUrl);
    const activePerumahanId = auth?.active_perumahan?.id
        ? String(auth.active_perumahan.id)
        : "";
    const spkKontraktors = useMemo(() => {
        const source = options.spkKontraktors ?? [];

        if (!activePerumahanId) {
            return source;
        }

        return source.filter(
            (row) => String(row.perumahan_id ?? "") === activePerumahanId,
        );
    }, [activePerumahanId, options.spkKontraktors]);
    const batchForm = useForm({
        spk_kontraktor_id: "",
        perumahan_id: "",
        detail_rumah_id: "",
        tanggal_mulai: new Date().toISOString().slice(0, 10),
        jumlah_periode: 8,
        status: "direncanakan",
        catatan: "",
        items: [],
    });
    const editForm = useForm({
        perumahan_id: "",
        detail_rumah_id: "",
        tahapan_pembangunan_id: "",
        nama_pekerjaan: "",
        tanggal_mulai: "",
        tanggal_target: "",
        target_progress: 0,
        realisasi_progress: 0,
        status: "direncanakan",
        kendala: "",
        catatan: "",
    });
    const perumahans = options.perumahans ?? [];
    const detailRumahs = options.detailRumahs ?? [];
    const selectedSpk = useMemo(
        () =>
            spkKontraktors.find(
                (row) =>
                    String(row.value) ===
                    String(batchForm.data.spk_kontraktor_id),
            ) ?? null,
        [batchForm.data.spk_kontraktor_id, spkKontraktors],
    );
    const displayPeriodCount = useMemo(
        () =>
            Math.max(
                1,
                ...rows.data.flatMap((row) =>
                    (row.allocations ?? []).map((allocation) =>
                        Number(allocation.periode_ke || 0),
                    ),
                ),
                Number(batchForm.data.jumlah_periode || 1),
            ),
        [batchForm.data.jumlah_periode, rows.data],
    );
    const displayPeriodColumns = useMemo(
        () => makePeriodColumns(displayPeriodCount),
        [displayPeriodCount],
    );
    const monthGroups = useMemo(
        () =>
            displayPeriodColumns.reduce((groups, column) => {
                const last = groups[groups.length - 1];
                if (last && last.month === column.month) {
                    last.count += 1;
                    return groups;
                }
                return [...groups, { month: column.month, count: 1 }];
            }, []),
        [displayPeriodColumns],
    );
    const scheduleRows = rows.data ?? [];
    const batchPeriodColumns = useMemo(
        () => makePeriodColumns(Number(batchForm.data.jumlah_periode || 1)),
        [batchForm.data.jumlah_periode],
    );
    const weeklyPlanTotals = displayPeriodColumns.map((column) =>
        sumNumbers(
            scheduleRows.map(
                (row) =>
                    (row.allocations ?? []).find(
                        (allocation) =>
                            Number(allocation.periode_ke) === column.periode,
                    )?.bobot_persen ?? 0,
            ),
        ),
    );
    const weeklyPlanCumulative = weeklyPlanTotals.reduce(
        (carry, value) => [
            ...carry,
            Number((Number(carry[carry.length - 1] ?? 0) + value).toFixed(2)),
        ],
        [],
    );
    const realisasiTotal = Math.min(
        100,
        sumNumbers(
            scheduleRows.map((row) => Number(row.realisasi_progress || 0)),
        ),
    );
    const detailPlanRows = detail ? buildPreviewPlanRows(detail) : [];
    const detailPeriodColumns = getPlanColumns(
        detailPlanRows,
        detail?.allocations?.length || 1,
    );
    const detailMonthGroups = getMonthGroupsFromColumns(detailPeriodColumns);
    const detailWeeklyTotals = detailPeriodColumns.map((column) =>
        sumPlanWeek(detailPlanRows, column.periode),
    );
    const detailCumulativeTotals = getPlanCumulative(detailWeeklyTotals);
    const buildItemsFromSpk = (
        spk,
        periodCount = batchForm.data.jumlah_periode,
    ) =>
        (spk?.groups ?? []).map((group) => ({
            tahapan_pembangunan_id: "",
            nama_pekerjaan: group.judul_tahapan ?? "",
            target_progress: group.group_percent ?? 0,
            allocations: Array.from(
                { length: Number(periodCount || 1) },
                () => "",
            ),
            auto_start_week: "",
            auto_duration_week: "",
        }));
    const syncBatchFromSpk = (
        spkId,
        periodCount = batchForm.data.jumlah_periode,
    ) => {
        const spk =
            spkKontraktors.find((row) => String(row.value) === String(spkId)) ??
            null;
        const items = spk ? buildItemsFromSpk(spk, periodCount) : [];

        batchForm.setData({
            ...batchForm.data,
            spk_kontraktor_id: spkId,
            perumahan_id: spk?.perumahan_id ?? "",
            detail_rumah_id: spk?.detail_rumah_id ?? "",
            items,
        });
    };
    const setBatchItem = (itemIndex, key, value) => {
        batchForm.setData(
            "items",
            batchForm.data.items.map((item, index) =>
                index === itemIndex ? { ...item, [key]: value } : item,
            ),
        );
    };
    const setBatchAllocation = (itemIndex, periodIndex, value) => {
        batchForm.setData(
            "items",
            batchForm.data.items.map((item, index) => {
                if (index !== itemIndex) return item;

                const allocations = Array.from(
                    { length: Number(batchForm.data.jumlah_periode || 1) },
                    (_, allocationIndex) =>
                        item.allocations?.[allocationIndex] ?? "",
                );
                allocations[periodIndex] = normalizeDecimalInput(value);

                return { ...item, allocations };
            }),
        );
    };
    const toggleBatchWeek = (itemIndex, periodIndex) => {
        batchForm.setData(
            "items",
            batchForm.data.items.map((item, index) => {
                if (index !== itemIndex) return item;

                const periodCount = Number(batchForm.data.jumlah_periode || 1);
                const currentWeeks = (item.allocations ?? [])
                    .map((value, allocationIndex) =>
                        isAllocationFilled(value) ? allocationIndex + 1 : null,
                    )
                    .filter(Boolean);
                const weekNumber = periodIndex + 1;
                const nextWeeks = currentWeeks.includes(weekNumber)
                    ? currentWeeks.filter((week) => week !== weekNumber)
                    : [...currentWeeks, weekNumber];

                return {
                    ...item,
                    allocations: setAllocationByWeeks(
                        item.target_progress,
                        nextWeeks,
                        periodCount,
                    ),
                };
            }),
        );
    };
    const autoDistributedItem = (item, overrides = {}) => {
        const next = { ...item, ...overrides };
        const selectedWeeks = (next.allocations ?? [])
            .map((value, allocationIndex) =>
                isAllocationFilled(value) ? allocationIndex + 1 : null,
            )
            .filter(Boolean);

        return {
            ...next,
            allocations: setAllocationByWeeks(
                next.target_progress,
                selectedWeeks,
                Number(batchForm.data.jumlah_periode || 1),
            ),
        };
    };
    const setPeriodCount = (value) => {
        const nextCount = Math.max(
            1,
            Number(normalizeIntegerInput(value) || 1),
        );
        batchForm.setData({
            ...batchForm.data,
            jumlah_periode: nextCount,
            items: batchForm.data.items.map((item) => ({
                ...item,
                allocations: Array.from(
                    { length: nextCount },
                    (_, index) => item.allocations?.[index] ?? "",
                ),
            })),
        });
    };
    const normalizedBatchItems = () =>
        batchForm.data.items.map((item) => autoDistributedItem(item));
    const submitBatch = (event) => {
        event.preventDefault();
        batchForm.transform((data) => ({
            ...data,
            items: normalizedBatchItems(),
        }));
        batchForm.post(baseUrl, {
            preserveScroll: true,
            onSuccess: () => batchForm.reset(),
        });
    };
    const editRow = (row) => {
        router.visit(`${baseUrl}/${row.id}/edit`);
    };
    const submitEdit = (event) => {
        event.preventDefault();
        editForm.put(`${baseUrl}/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };
    const openSchedulePdfPreview = (row) => {
        const planRows = buildPreviewPlanRows(row);
        const periodColumns = getPlanColumns(
            planRows,
            row.allocations?.length || 1,
        );
        const monthlyGroups = getMonthGroupsFromColumns(periodColumns);
        const weeklyTotals = periodColumns.map((column) =>
            sumPlanWeek(planRows, column.periode),
        );
        const cumulativeTotals = getPlanCumulative(weeklyTotals);
        const html = `
            <!doctype html>
            <html>
            <head>
                <meta charset="utf-8" />
                <title>${row.kode_jadwal || "Pratinjau Jadwal"}</title>
                <style>
                    @page { size: landscape; margin: 12mm; }
                    body { font-family: Arial, sans-serif; color: #111827; margin: 0; }
                    .sheet { padding: 0; }
                    .title { text-align: center; margin-bottom: 18px; }
                    .title h1 { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: 0.02em; }
                    .title p { margin: 6px 0 0; font-size: 12px; color: #4b5563; }
                    .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; font-size: 11px; }
                    .box { border: 1px solid #d1d5db; padding: 10px 12px; border-radius: 8px; }
                    .box b { display: block; margin-bottom: 4px; }
                    table { width: 100%; border-collapse: collapse; font-size: 10px; }
                    th, td { border: 1px solid #9ca3af; padding: 5px 6px; }
                    thead th { background: #a3e635; }
                    .head2 th { background: #d9f99d; }
                    .total { background: #fde047; font-weight: 700; }
                    .summary { background: #bfdbfe; font-weight: 700; }
                    .realisasi { background: #d1fae5; font-weight: 700; }
                    .muted { color: #6b7280; }
                    .toolbar {
                        position: sticky;
                        top: 0;
                        z-index: 2;
                        display: flex;
                        justify-content: flex-end;
                        gap: 8px;
                        padding: 10px 0 14px;
                        background: #ffffff;
                    }
                    .toolbar button {
                        border: 1px solid #cbd5e1;
                        border-radius: 8px;
                        background: #111827;
                        color: #ffffff;
                        padding: 8px 14px;
                        font-size: 12px;
                        font-weight: 700;
                        cursor: pointer;
                    }
                    .toolbar button.secondary {
                        background: #ffffff;
                        color: #111827;
                    }
                </style>
            </head>
            <body>
                <div class="sheet">
                    <div class="toolbar">
                        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
                        <button type="button" class="secondary" onclick="window.close()">Tutup</button>
                    </div>
                    <div class="title">
                        <h1>TIME SCHEDULE PEKERJAAN</h1>
                        <p>${row.spk || "-"} | ${row.perumahan || "-"} | ${row.unit || "-"}</p>
                    </div>
                    <div class="meta">
                        <div class="box"><b>Jadwal</b>${row.kode_jadwal || "-"}</div>
                        <div class="box"><b>Lokasi</b>${row.perumahan || "-"} - ${row.unit || "-"}</div>
                        <div class="box"><b>Periode</b>${row.tanggal_mulai || "-"} s/d ${row.tanggal_target || "-"}</div>
                        <div class="box"><b>Status</b>${row.status || "-"}</div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="2">NO</th>
                                <th rowspan="2">JENIS PEK</th>
                                <th rowspan="2">BOBOT (%)</th>
                                <th rowspan="2">REALISASI</th>
                                ${monthlyGroups.map((group) => `<th colspan="${group.count}" class="center">BULAN ${group.month}</th>`).join("")}
                            </tr>
                            <tr class="head2">
                                ${periodColumns.map((column) => `<th>${column.week}</th>`).join("")}
                            </tr>
                        </thead>
                        <tbody>
                            ${planRows
                                .map(
                                    (planRow, index) => `
                                <tr>
                                    <td style="text-align:center">${planRow.urut ?? index + 1}</td>
                                    <td><strong>${planRow.nama_tahap_pekerjaan || "-"}</strong></td>
                                    <td style="text-align:right">${Number(planRow.target_progress || 0).toFixed(2)}</td>
                                    <td style="text-align:right">${Number(row.realisasi_progress || 0).toFixed(2)}</td>
                                    ${periodColumns
                                        .map((column) => {
                                            const planned = getPlanWeekValue(
                                                planRow,
                                                column.periode,
                                            );
                                            return `<td style="text-align:center">${planned ? planned.toFixed(2) : ""}</td>`;
                                        })
                                        .join("")}
                                </tr>
                            `,
                                )
                                .join("")}
                            <tr class="total">
                                <td colspan="2" style="text-align:center">TOTAL</td>
                                <td>${Number(row.target_progress || 0).toFixed(2)}</td>
                                <td>${Number(row.realisasi_progress || 0).toFixed(2)}</td>
                                ${periodColumns.map((column) => `<td style="text-align:center">${weeklyTotals[column.index] ? weeklyTotals[column.index].toFixed(2) : ""}</td>`).join("")}
                            </tr>
                            <tr class="summary">
                                <td colspan="4" style="text-align:right">RENCANA PRESTASI MINGGUAN</td>
                                ${weeklyTotals.map((value) => `<td style="text-align:center">${value ? value.toFixed(2) : "0.00"}</td>`).join("")}
                            </tr>
                            <tr class="summary">
                                <td colspan="4" style="text-align:right">RENCANA PRESTASI KOMULATIF</td>
                                ${cumulativeTotals.map((value) => `<td style="text-align:center">${value.toFixed(2)}</td>`).join("")}
                            </tr>
                        </tbody>
                    </table>
                    <div style="margin-top: 14px; font-size: 11px;" class="muted">Pratinjau ini bisa dicetak ke PDF dari dialog print browser.</div>
                </div>
            </body>
            </html>
        `;

        const win = window.open(
            "",
            "_blank",
            "noopener,noreferrer,width=1600,height=1000",
        );
        if (!win) return;

        win.document.open();
        win.document.write(html);
        win.document.close();
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-2xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-black uppercase tracking-[0.14em] text-ink-soft">
                                Pengawasan Lapangan
                            </p>
                            <h1 className="mt-1 text-2xl font-black">
                                Jadwal Lapangan
                            </h1>
                            <p className="mt-1 text-sm text-ink-soft">
                                Kelola time schedule pekerjaan berdasarkan SPK
                                yang telah disetujui.
                            </p>
                        </div>
                        {permissions.canCreate && (
                            <Button as={Link} href={`${baseUrl}/create`}>
                                <Plus size={17} /> Buat Jadwal
                            </Button>
                        )}
                    </div>
                </section>

                {false && permissions.canCreate && (
                    <Form
                        collapsible
                        title="Buat Time Schedule dari SPK"
                        description="Pilih SPK yang sudah dibuat, lalu sistem akan mengambil perumahan, unit, dan tahapan pekerjaannya secara otomatis."
                        onSubmit={submitBatch}
                        actions={
                            <Button
                                type="submit"
                                disabled={
                                    batchForm.processing ||
                                    batchForm.data.items.length === 0 ||
                                    !batchForm.data.spk_kontraktor_id
                                }
                            >
                                <CalendarClock size={17} /> Simpan Time Schedule
                            </Button>
                        }
                    >
                        {Object.keys(batchForm.errors).length > 0 && (
                            <div className="rounded-lg bg-red-50 p-4 text-sm font-bold text-red-700">
                                {Object.values(batchForm.errors).map(
                                    (error) => (
                                        <p key={error}>{error}</p>
                                    ),
                                )}
                            </div>
                        )}
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="grid gap-2 md:col-span-2">
                                <span className="text-sm font-extrabold">
                                    SPK
                                </span>
                                <Dropdown
                                    label="Pilih SPK"
                                    value={batchForm.data.spk_kontraktor_id}
                                    options={spkKontraktors}
                                    onChange={(value) =>
                                        syncBatchFromSpk(value)
                                    }
                                />
                                <p className="text-xs text-ink-soft dark:text-white/60">
                                    Begitu SPK dipilih, unit rumah dan tahapan
                                    pekerjaannya langsung ikut terisi.
                                </p>
                            </div>
                            <Input
                                label="Tanggal Mulai"
                                type="date"
                                value={batchForm.data.tanggal_mulai}
                                onChange={(event) =>
                                    batchForm.setData(
                                        "tanggal_mulai",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="Durasi Jadwal (Minggu)"
                                type="text"
                                inputMode="numeric"
                                value={batchForm.data.jumlah_periode}
                                onChange={(event) =>
                                    setPeriodCount(event.target.value)
                                }
                            />
                        </div>
                        {selectedSpk && (
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="rounded-lg border border-silver-deep/70 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                                        Perumahan
                                    </p>
                                    <p className="mt-2 text-lg font-extrabold">
                                        {selectedSpk.perumahan_label}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-silver-deep/70 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                                        Unit Rumah
                                    </p>
                                    <p className="mt-2 text-lg font-extrabold">
                                        {selectedSpk.unit_label}
                                    </p>
                                </div>
                                <div className="rounded-lg border border-silver-deep/70 bg-silver-soft/50 p-4 dark:border-white/10 dark:bg-white/5">
                                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                                        Total SPK
                                    </p>
                                    <p className="mt-2 text-lg font-extrabold">
                                        {money(selectedSpk.total_nilai)}
                                    </p>
                                    <p className="mt-1 text-xs text-ink-soft">
                                        {selectedSpk.group_count} tahap,{" "}
                                        {selectedSpk.item_count} item pekerjaan
                                    </p>
                                </div>
                            </div>
                        )}
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm font-bold text-ink-soft dark:text-white/55">
                                {selectedSpk
                                    ? "Tahapan diambil langsung dari item SPK yang dipilih. Jadwal ini tidak perlu pilih rumah lagi."
                                    : "Pilih SPK terlebih dahulu agar tahapan dan unitnya terisi otomatis."}
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    syncBatchFromSpk(
                                        batchForm.data.spk_kontraktor_id,
                                    )
                                }
                                disabled={!batchForm.data.spk_kontraktor_id}
                            >
                                <Plus size={15} /> Muat Tahapan SPK
                            </Button>
                        </div>
                        {batchForm.data.items.length > 0 && (
                            <div className="grid gap-2">
                                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                                    Centang minggu yang dipakai. Sistem akan
                                    membagi bobot otomatis sesuai minggu yang
                                    dipilih.
                                </div>
                                <div className="overflow-x-auto rounded-lg border border-silver-deep/60 dark:border-white/10">
                                    <table className="min-w-full text-xs">
                                        <thead className="bg-silver-soft/80 text-left uppercase tracking-wider text-ink-soft dark:bg-white/5">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Tahap Pekerjaan
                                                </th>
                                                <th className="min-w-28 px-4 py-3">
                                                    Bobot (%)
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                            {batchForm.data.items.map(
                                                (item, itemIndex) => {
                                                    const rowTotal = sumNumbers(
                                                        item.allocations ?? [],
                                                    );
                                                    const filledAllocations = (
                                                        item.allocations ?? []
                                                    )
                                                        .map(
                                                            (value, index) => ({
                                                                value,
                                                                week: index + 1,
                                                            }),
                                                        )
                                                        .filter((allocation) =>
                                                            isAllocationFilled(
                                                                allocation.value,
                                                            ),
                                                        );
                                                    return (
                                                        <Fragment
                                                            key={`${item.nama_pekerjaan}-${itemIndex}`}
                                                        >
                                                            <tr
                                                                key={`${item.nama_pekerjaan}-${itemIndex}-main`}
                                                            >
                                                                <td className="px-2 py-3">
                                                                    <div className="grid gap-2 text-left">
                                                                        <span className="text-[11px] font-bold uppercase tracking-wider text-ink-soft dark:text-white/50">
                                                                            Tahap
                                                                            Pekerjaan
                                                                        </span>
                                                                        <Input
                                                                            value={
                                                                                item.nama_pekerjaan
                                                                            }
                                                                            className="text-left"
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                setBatchItem(
                                                                                    itemIndex,
                                                                                    "nama_pekerjaan",
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                        />
                                                                        <p className="text-xs font-bold text-ink-soft dark:text-white/60">
                                                                            {filledAllocations.length >
                                                                            0
                                                                                ? `Terisi ${numberFormat.format(rowTotal)}%`
                                                                                : "Belum dibagi"}
                                                                        </p>
                                                                    </div>
                                                                </td>
                                                                <td className="px-2 py-3">
                                                                    <div className="flex h-full items-center rounded-xl border border-silver-deep/70 bg-silver-soft/60 px-4 py-3 text-right font-extrabold dark:border-white/10 dark:bg-white/5">
                                                                        {numberFormat.format(
                                                                            Number(
                                                                                item.target_progress ||
                                                                                    0,
                                                                            ),
                                                                        )}
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr
                                                                key={`${item.nama_pekerjaan}-${itemIndex}-weeks`}
                                                                className="bg-silver-soft/30 dark:bg-white/5"
                                                            >
                                                                <td
                                                                    className="px-4 py-3"
                                                                    colSpan={3}
                                                                >
                                                                    <div className="grid gap-3">
                                                                        <div className="flex items-center justify-between gap-2">
                                                                            <p className="text-xs font-bold uppercase tracking-wider text-ink-soft dark:text-white/60">
                                                                                Pembagian
                                                                                Mingguan
                                                                            </p>
                                                                            <p className="text-[11px] font-bold text-ink-soft dark:text-white/50">
                                                                                Centang
                                                                                minggu
                                                                                yang
                                                                                dipakai
                                                                            </p>
                                                                        </div>
                                                                        <div className="grid grid-cols-4 gap-2 md:grid-cols-8">
                                                                            {batchPeriodColumns.map(
                                                                                (
                                                                                    column,
                                                                                ) => {
                                                                                    const allocationValue =
                                                                                        item
                                                                                            .allocations?.[
                                                                                            column
                                                                                                .index
                                                                                        ] ??
                                                                                        "";
                                                                                    const checked =
                                                                                        isAllocationFilled(
                                                                                            allocationValue,
                                                                                        );

                                                                                    return (
                                                                                        <label
                                                                                            className="flex items-center gap-2 rounded-lg border border-silver-deep/50 bg-white/70 px-3 py-2 text-xs font-semibold text-ink-soft dark:border-white/10 dark:bg-white/5 dark:text-white/80"
                                                                                            key={
                                                                                                column.periode
                                                                                            }
                                                                                        >
                                                                                            <input
                                                                                                checked={
                                                                                                    checked
                                                                                                }
                                                                                                className="h-4 w-4 accent-emerald-600"
                                                                                                type="checkbox"
                                                                                                onChange={() =>
                                                                                                    toggleBatchWeek(
                                                                                                        itemIndex,
                                                                                                        column.index,
                                                                                                    )
                                                                                                }
                                                                                            />
                                                                                            <span className="min-w-8">
                                                                                                M
                                                                                                {
                                                                                                    column.periode
                                                                                                }
                                                                                            </span>
                                                                                            <span className="ml-auto text-[11px] text-ink-soft dark:text-white/50">
                                                                                                {checked
                                                                                                    ? `${numberFormat.format(Number(allocationValue || 0))}%`
                                                                                                    : "-"}
                                                                                            </span>
                                                                                        </label>
                                                                                    );
                                                                                },
                                                                            )}
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </Fragment>
                                                    );
                                                },
                                            )}
                                            <tr className="bg-silver-soft/80 font-extrabold dark:bg-white/5">
                                                <td className="px-4 py-3 text-right">
                                                    TOTAL BOBOT
                                                </td>
                                                <td className="px-4 py-3">
                                                    Total rencana terisi:{" "}
                                                    {numberFormat.format(
                                                        sumNumbers(
                                                            batchForm.data.items.flatMap(
                                                                (item) =>
                                                                    item.allocations ??
                                                                    [],
                                                            ),
                                                        ),
                                                    )}
                                                    %
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                        <Textarea
                            label="Catatan"
                            value={batchForm.data.catatan}
                            onChange={(event) =>
                                batchForm.setData("catatan", event.target.value)
                            }
                        />
                    </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 lg:grid-cols-[1.2fr_1fr_1fr_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                baseUrl,
                                {
                                    search,
                                    perumahan_id: filterPerumahan,
                                    detail_rumah_id: filterUnit,
                                },
                                { preserveState: true, replace: true },
                            );
                        }}
                    >
                        <Input
                            label="Cari Jadwal"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Perumahan
                            </span>
                            <Dropdown
                                value={filterPerumahan}
                                label="Semua Perumahan"
                                options={[
                                    { value: "", label: "Semua Perumahan" },
                                    ...perumahans,
                                ]}
                                onChange={(value) => {
                                    setFilterPerumahan(value);
                                    setFilterUnit("");
                                }}
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Unit</span>
                            <Dropdown
                                value={filterUnit}
                                label="Semua Unit"
                                options={[
                                    { value: "", label: "Semua Unit" },
                                    ...detailRumahs.filter(
                                        (row) =>
                                            !filterPerumahan ||
                                            row.perumahan_id ===
                                                String(filterPerumahan),
                                    ),
                                ]}
                                onChange={setFilterUnit}
                            />
                        </div>
                        <div className="flex items-end">
                            <Button className="w-full">
                                <Search size={16} /> Cari
                            </Button>
                        </div>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                <tr>
                                    {[
                                        "PEK",
                                        "Kontrak Kerja",
                                        "Lokasi",
                                        "Periode",
                                        "Target / Realisasi",
                                        "Status",
                                        "Kendala",
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
                                    <tr
                                        className={
                                            row.terlambat
                                                ? "bg-red-50/60 dark:bg-red-500/5"
                                                : ""
                                        }
                                        key={row.id}
                                    >
                                        <td className="px-5 py-4 font-bold">
                                            {row.nama_pekerjaan}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.tahapan}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.spk || "-"}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.perumahan}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.unit}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.tanggal_mulai} s/d{" "}
                                            {row.tanggal_target}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.batch_code ||
                                                    row.kode_jadwal}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.target_progress}% /{" "}
                                            {row.realisasi_progress}%
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.terlambat
                                                ? "Terlambat"
                                                : row.status}
                                        </td>
                                        <td className="max-w-xs px-5 py-4">
                                            {row.kendala || "-"}
                                        </td>
                                        <td className="min-w-44 px-5 py-4 text-xs">
                                            <span className="font-bold">
                                                Dibuat:
                                            </span>{" "}
                                            {row.created_by_name}
                                            <br />
                                            <span className="font-bold">
                                                Diubah:
                                            </span>{" "}
                                            {row.updated_by_name}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setDetail(row)
                                                    }
                                                >
                                                    <Eye size={14} /> Detail
                                                </Button>
                                                {permissions.canUpdate &&
                                                    row.can_edit && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                editRow(row)
                                                            }
                                                        >
                                                            <Edit3 size={14} />{" "}
                                                            Edit
                                                        </Button>
                                                    )}
                                                {permissions.canDelete &&
                                                    row.can_delete && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="text-red-600"
                                                            onClick={() =>
                                                                window.confirm(
                                                                    "Hapus jadwal?",
                                                                ) &&
                                                                router.delete(
                                                                    `${baseUrl}/${row.id}`,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 size={14} />
                                                        </Button>
                                                    )}
                                                {permissions.canUpdate &&
                                                    row.can_lock && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/lock`,
                                                                )
                                                            }
                                                        >
                                                            <Lock size={14} />{" "}
                                                            Lock
                                                        </Button>
                                                    )}
                                                {permissions.canUnlock &&
                                                    row.record_status ===
                                                        "locked" && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/unlock`,
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
                                            colSpan={8}
                                        >
                                            Belum ada jadwal lapangan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>
            <Modal
                size="full"
                open={Boolean(detail)}
                onClose={() => setDetail(null)}
                title={
                    detail ? `Detail ${detail.kode_jadwal}` : "Detail Jadwal"
                }
                footer={
                    <div className="flex gap-2">
                        <Button
                            as="a"
                            variant="outline"
                            href={detail?.pdf_url ?? "#"}
                            download={
                                detail
                                    ? `${detail.kode_jadwal || "time-schedule"}.pdf`
                                    : undefined
                            }
                            onClick={(event) => {
                                if (!detail?.pdf_url) event.preventDefault();
                            }}
                        >
                            Export PDF
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDetail(null)}
                        >
                            Tutup
                        </Button>
                    </div>
                }
            >
                {detail && (
                    <div className="grid gap-4 text-sm">
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                                <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                                    PEK
                                </p>
                                <p className="mt-2 font-bold">
                                    {detail.nama_pekerjaan}
                                </p>
                                <p className="text-xs text-ink-soft">
                                    {detail.tahapan}
                                </p>
                            </div>
                            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                                <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                                    Kontrak Kerja
                                </p>
                                <p className="mt-2 font-bold">
                                    {detail.spk || "-"}
                                </p>
                                <p className="text-xs text-ink-soft">
                                    {detail.perumahan} - {detail.unit}
                                </p>
                            </div>
                        </div>
                        <div className="grid gap-3 md:grid-cols-4">
                            <div className="rounded-lg border border-silver-deep/60 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5">
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    Periode
                                </p>
                                <p className="mt-1 font-bold">
                                    {detail.tanggal_mulai} s/d{" "}
                                    {detail.tanggal_target}
                                </p>
                            </div>
                            <div className="rounded-lg border border-silver-deep/60 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5">
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    Target
                                </p>
                                <p className="mt-1 font-bold">
                                    {detail.target_progress}%
                                </p>
                            </div>
                            <div className="rounded-lg border border-silver-deep/60 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5">
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    Realisasi
                                </p>
                                <p className="mt-1 font-bold">
                                    {detail.realisasi_progress}%
                                </p>
                            </div>
                            <div className="rounded-lg border border-silver-deep/60 bg-white/70 p-3 dark:border-white/10 dark:bg-white/5">
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    Status
                                </p>
                                <p className="mt-1 font-bold">
                                    {detail.terlambat
                                        ? "Terlambat"
                                        : detail.status}
                                </p>
                            </div>
                        </div>
                        <div className="overflow-x-auto rounded-lg border border-silver-deep/60 dark:border-white/10">
                            <table className="min-w-full border-collapse text-xs">
                                <thead>
                                    <tr className="bg-lime-500/90 text-ink">
                                        <th
                                            className="border border-ink/30 px-3 py-2"
                                            rowSpan={2}
                                        >
                                            NO
                                        </th>
                                        <th
                                            className="min-w-72 border border-ink/30 px-3 py-2"
                                            rowSpan={2}
                                        >
                                            JENIS PEK
                                        </th>
                                        <th
                                            className="min-w-28 border border-ink/30 px-3 py-2"
                                            rowSpan={2}
                                        >
                                            BOBOT (%)
                                        </th>
                                        <th
                                            className="min-w-32 border border-ink/30 px-3 py-2"
                                            rowSpan={2}
                                        >
                                            REALISASI
                                        </th>
                                        {detailMonthGroups.map((group) => (
                                            <th
                                                className="border border-ink/30 px-3 py-2 text-center"
                                                colSpan={group.count}
                                                key={group.month}
                                            >
                                                BULAN {group.month}
                                            </th>
                                        ))}
                                    </tr>
                                    <tr className="bg-lime-500/90 text-ink">
                                        {detailPeriodColumns.map((column) => (
                                            <th
                                                className="min-w-20 border border-ink/30 px-2 py-2 text-center"
                                                key={column.periode}
                                            >
                                                {column.week}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {detailPlanRows.map((planRow, index) => (
                                        <tr
                                            key={`${planRow.urut ?? index}-${planRow.nama_tahap_pekerjaan ?? index}`}
                                        >
                                            <td className="border border-ink/30 px-3 py-2 text-center">
                                                {planRow.urut ?? index + 1}
                                            </td>
                                            <td className="border border-ink/30 px-3 py-2">
                                                <div className="font-extrabold">
                                                    {planRow.nama_tahap_pekerjaan ||
                                                        "-"}
                                                </div>
                                            </td>
                                            <td className="border border-ink/30 px-3 py-2 text-right">
                                                {numberFormat.format(
                                                    Number(
                                                        planRow.target_progress ||
                                                            0,
                                                    ),
                                                )}
                                            </td>
                                            <td className="border border-ink/30 px-3 py-2 text-right">
                                                {numberFormat.format(
                                                    Number(
                                                        detail.realisasi_progress ||
                                                            0,
                                                    ),
                                                )}
                                            </td>
                                            {detailPeriodColumns.map(
                                                (column) => {
                                                    const planned =
                                                        getPlanWeekValue(
                                                            planRow,
                                                            column.periode,
                                                        );

                                                    return (
                                                        <td
                                                            className="border border-ink/30 px-2 py-2 text-center"
                                                            key={`${planRow.urut ?? index}-${column.periode}`}
                                                        >
                                                            {planned > 0
                                                                ? numberFormat.format(
                                                                      Number(
                                                                          planned,
                                                                      ),
                                                                  )
                                                                : ""}
                                                        </td>
                                                    );
                                                },
                                            )}
                                        </tr>
                                    ))}
                                    <tr className="bg-yellow-300 font-extrabold text-ink">
                                        <td
                                            className="border border-ink/30 px-3 py-2 text-center"
                                            colSpan={2}
                                        >
                                            TOTAL
                                        </td>
                                        <td className="border border-ink/30 px-3 py-2 text-right">
                                            {numberFormat.format(
                                                Number(
                                                    detail.target_progress || 0,
                                                ),
                                            )}
                                        </td>
                                        <td className="border border-ink/30 px-3 py-2 text-right">
                                            {numberFormat.format(
                                                Number(
                                                    detail.realisasi_progress ||
                                                        0,
                                                ),
                                            )}
                                        </td>
                                        {detailWeeklyTotals.map(
                                            (value, index) => (
                                                <td
                                                    className="border border-ink/30 px-2 py-2 text-center"
                                                    key={`weekly-${index}`}
                                                >
                                                    {value > 0
                                                        ? numberFormat.format(
                                                              value,
                                                          )
                                                        : ""}
                                                </td>
                                            ),
                                        )}
                                    </tr>
                                    <tr className="bg-sky-200 text-ink">
                                        <td
                                            className="border border-ink/30 px-3 py-2 text-right font-extrabold"
                                            colSpan={4}
                                        >
                                            RENCANA PRESTASI MINGGUAN
                                        </td>
                                        {detailWeeklyTotals.map(
                                            (value, index) => (
                                                <td
                                                    className="border border-ink/30 px-2 py-2 text-center font-bold"
                                                    key={`summary-${index}`}
                                                >
                                                    {numberFormat.format(
                                                        value || 0,
                                                    )}
                                                </td>
                                            ),
                                        )}
                                    </tr>
                                    <tr className="bg-sky-100 text-ink">
                                        <td
                                            className="border border-ink/30 px-3 py-2 text-right font-extrabold"
                                            colSpan={4}
                                        >
                                            RENCANA PRESTASI KOMULATIF
                                        </td>
                                        {detailCumulativeTotals.map(
                                            (value, index) => (
                                                <td
                                                    className="border border-ink/30 px-2 py-2 text-center font-bold"
                                                    key={`cum-${index}`}
                                                >
                                                    {numberFormat.format(value)}
                                                </td>
                                            ),
                                        )}
                                    </tr>
                                    <tr className="bg-emerald-100 text-emerald-900">
                                        <td
                                            className="border border-ink/30 px-3 py-2 text-right font-extrabold"
                                            colSpan={4}
                                        >
                                            REALISASI KOMULATIF OTOMATIS
                                        </td>
                                        {detailCumulativeTotals.map(
                                            (_, index) => (
                                                <td
                                                    className="border border-ink/30 px-2 py-2 text-center font-bold"
                                                    key={`realisasi-${index}`}
                                                >
                                                    {numberFormat.format(
                                                        Number(
                                                            detail.realisasi_progress ||
                                                                0,
                                                        ),
                                                    )}
                                                </td>
                                            ),
                                        )}
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div className="grid gap-3 md:grid-cols-2">
                            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    Kendala
                                </p>
                                <p className="mt-1">{detail.kendala || "-"}</p>
                            </div>
                            <div className="rounded-lg border border-silver-deep/60 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5">
                                <p className="text-xs font-bold uppercase text-ink-soft">
                                    Catatan
                                </p>
                                <p className="mt-1">{detail.catatan || "-"}</p>
                            </div>
                        </div>
                    </div>
                )}
            </Modal>
            <Modal
                open={Boolean(editing)}
                onClose={() => setEditing(null)}
                title={editing ? `Edit ${editing.kode_jadwal}` : "Ubah Jadwal"}
                footer={
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setEditing(null)}
                        >
                            <X size={14} /> Batal
                        </Button>
                        <Button
                            type="button"
                            disabled={editForm.processing}
                            onClick={submitEdit}
                        >
                            <CalendarClock size={14} /> Simpan
                        </Button>
                    </>
                }
            >
                {editing && (
                    <form className="grid gap-4" onSubmit={submitEdit}>
                        <Input
                            label="Nama PEK"
                            value={editForm.data.nama_pekerjaan}
                            onChange={(event) =>
                                editForm.setData(
                                    "nama_pekerjaan",
                                    event.target.value,
                                )
                            }
                        />
                        <div className="grid gap-4 md:grid-cols-3">
                            <Input
                                label="Tanggal Mulai"
                                type="date"
                                value={editForm.data.tanggal_mulai}
                                onChange={(event) =>
                                    editForm.setData(
                                        "tanggal_mulai",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="Tanggal Target"
                                type="date"
                                value={editForm.data.tanggal_target}
                                onChange={(event) =>
                                    editForm.setData(
                                        "tanggal_target",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                label="Persentase Kemajuan %"
                                type="text"
                                inputMode="decimal"
                                value={editForm.data.target_progress}
                                onChange={(event) =>
                                    editForm.setData(
                                        "target_progress",
                                        normalizeDecimalInput(
                                            event.target.value,
                                        ),
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Status
                            </span>
                            <Dropdown
                                value={editForm.data.status}
                                options={[
                                    {
                                        value: "direncanakan",
                                        label: "Direncanakan",
                                    },
                                    { value: "berjalan", label: "Berjalan" },
                                    { value: "terlambat", label: "Terlambat" },
                                    { value: "tertahan", label: "Tertahan" },
                                    { value: "selesai", label: "Selesai" },
                                ]}
                                onChange={(value) =>
                                    editForm.setData("status", value)
                                }
                            />
                        </div>
                        <Textarea
                            label="Kendala"
                            value={editForm.data.kendala}
                            onChange={(event) =>
                                editForm.setData("kendala", event.target.value)
                            }
                        />
                        <Textarea
                            label="Catatan"
                            value={editForm.data.catatan}
                            onChange={(event) =>
                                editForm.setData("catatan", event.target.value)
                            }
                        />
                    </form>
                )}
            </Modal>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Jadwal Lapangan"}>
        {page}
    </AdminLayout>
);
