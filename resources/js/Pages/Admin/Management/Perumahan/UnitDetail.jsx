import { Head, Link, router } from "@inertiajs/react";
import {
    CheckCircle2,
    ChevronRight,
    PackageSearch,
    Search,
} from "lucide-react";
import { useMemo, useState } from "react";
import Accordion from "../../../../Components/UI/Accordion";
import { Button, Input } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

function money(value) {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));
}

function badgeClass(status) {
    if (status === "approved" || status === "selesai") {
        return "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200";
    }
    if (status === "diajukan" || status === "menunggu_approval_manager") {
        return "bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200";
    }
    if (status === "ditolak") {
        return "bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-200";
    }
    return "bg-silver-soft text-ink-soft dark:bg-white/10 dark:text-white/70";
}

export default function UnitDetail({
    title,
    baseUrl,
    perumahan = {},
    rumah = {},
    progressRows = [],
    materialRows = [],
    logistikRows = [],
    filters = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");

    const keyword = search.trim().toLowerCase();

    const filteredProgress = useMemo(() => {
        if (!keyword) return progressRows;
        return progressRows.filter((row) =>
            [
                row.tahapan,
                row.keterangan,
                row.status_label,
                row.input_oleh,
                row.approved_by,
            ].some((value) =>
                String(value ?? "")
                    .toLowerCase()
                    .includes(keyword),
            ),
        );
    }, [keyword, progressRows]);

    const filteredRequests = useMemo(() => {
        if (!keyword) return materialRows;
        return materialRows.filter((row) =>
            [
                row.kode_request,
                row.gudang,
                row.tahapan,
                row.status_label,
                row.items_text,
                row.keterangan,
            ].some((value) =>
                String(value ?? "")
                    .toLowerCase()
                    .includes(keyword),
            ),
        );
    }, [keyword, materialRows]);

    const filteredLogistik = useMemo(() => {
        if (!keyword) return logistikRows;
        return logistikRows.filter((row) =>
            [
                row.kode_transaksi,
                row.gudang,
                row.keterangan,
                row.items_text,
                row.jenis,
            ].some((value) =>
                String(value ?? "")
                    .toLowerCase()
                    .includes(keyword),
            ),
        );
    }, [keyword, logistikRows]);

    const tahapanSummary = useMemo(() => {
        const grouped = {};

        filteredProgress.forEach((row) => {
            const key = row.tahapan_id ?? row.tahapan;
            if (!grouped[key]) {
                grouped[key] = {
                    tahapan_id: row.tahapan_id ?? row.tahapan,
                    tahapan: row.tahapan,
                    bobot_tahapan: Number(row.bobot_tahapan ?? 0),
                    total_input: 0,
                    total_kontribusi: 0,
                    rows: [],
                };
            }

            grouped[key].total_input += Number(row.persentase ?? 0);
            grouped[key].total_kontribusi += Number(row.persentase_total ?? 0);
            grouped[key].rows.push(row);
        });

        return Object.values(grouped);
    }, [filteredProgress]);

    const requestApprove = (row) => {
        router.post(row.approve_url, {}, { preserveScroll: true });
    };

    const profileAccordion = {
        title: "Profile Unit",
        content: (
            <div className="grid gap-4 lg:grid-cols-2">
                {[
                    {
                        title: "Identitas Unit",
                        items: [
                            ["Perumahan", perumahan.nama_perusahaan],
                            ["Cabang", perumahan.cabang ?? "-"],
                            [
                                "Blok / Nomor",
                                `${rumah.kode_nlok ?? "-"} / ${rumah.nomor_rumah ?? "-"}`,
                            ],
                            ["Model Unit", rumah.model_unit ?? "-"],
                        ],
                    },
                    {
                        title: "Lokasi & Ukuran",
                        items: [
                            ["Alamat", perumahan.alamat ?? "-"],
                            ["Tipe", rumah.tipe_rumah ?? "-"],
                            ["Luas Tanah", rumah.luas_tanah ?? "-"],
                            ["Luas Bangunan", rumah.luas_bangunan ?? "-"],
                        ],
                    },
                    {
                        title: "Status Bangunan",
                        items: [
                            ["Status Bangun", rumah.status_pembangunan ?? "-"],
                            [
                                "Progress Terakhir",
                                `${rumah.progress_terakhir ?? 0}%`,
                            ],
                            ["Status Penjualan", rumah.status_penjualan ?? "-"],
                            ["Harga Jual", money(rumah.harga_jual)],
                        ],
                    },
                    {
                        title: "Pemilik Aktif",
                        items: rumah.pemilik
                            ? [
                                  ["Nama Pemilik", rumah.pemilik.nama],
                                  [
                                      "Identitas",
                                      `${rumah.pemilik.jenis_identitas ?? "-"} / ${rumah.pemilik.nomor_identitas ?? "-"}`,
                                  ],
                                  ["Telepon", rumah.pemilik.telepon ?? "-"],
                                  ["Email", rumah.pemilik.email ?? "-"],
                                  ["Sumber", rumah.pemilik.sumber ?? "-"],
                                  ["Sejak", rumah.pemilik.tanggal_mulai ?? "-"],
                                  [
                                      "Nomor Dokumen",
                                      rumah.pemilik.nomor_dokumen ?? "-",
                                  ],
                              ]
                            : [["Pemilik", "Belum tercatat"]],
                        note: rumah.pemilik?.alamat ?? null,
                    },
                    {
                        title: "Audit & Catatan",
                        items: [
                            [
                                "Audit",
                                `Dibuat: ${rumah.created_by ?? "-"} | Diubah: ${rumah.updated_by ?? "-"}`,
                            ],
                            [
                                "Tanggal Mulai",
                                rumah.tanggal_mulai_bangun ?? "-",
                            ],
                            [
                                "Tanggal Selesai",
                                rumah.tanggal_selesai_bangun ?? "-",
                            ],
                        ],
                        note: rumah.catatan ?? "-",
                    },
                ].map((group) => (
                    <div
                        className="rounded-lg border border-silver-deep/50 bg-silver-soft/40 p-4 dark:border-white/10 dark:bg-white/5"
                        key={group.title}
                    >
                        <p className="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                            {group.title}
                        </p>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            {group.items.map(([label, value]) => (
                                <div
                                    className="rounded-lg bg-white/70 px-4 py-3 dark:bg-white/5"
                                    key={label}
                                >
                                    <p className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-sm font-bold text-ink dark:text-white">
                                        {value}
                                    </p>
                                </div>
                            ))}
                        </div>
                        {group.note && (
                            <div className="mt-3 rounded-lg bg-white/70 px-4 py-3 dark:bg-white/5">
                                <p className="text-[10px] font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                                    Catatan
                                </p>
                                <p className="mt-1 whitespace-pre-line text-sm font-semibold text-ink dark:text-white">
                                    {group.note}
                                </p>
                            </div>
                        )}
                    </div>
                ))}
            </div>
        ),
    };

    const progressAccordion = {
        title: "Detail Progress Pembangunan",
        content: (
            <div className="grid gap-4">
                {tahapanSummary.length === 0 ? (
                    <p className="text-sm font-bold text-ink-soft">
                        Belum ada progress pembangunan.
                    </p>
                ) : (
                    tahapanSummary.map((group) => (
                        <div
                            className="rounded-lg border border-silver-deep/50 p-4 dark:border-white/10"
                            key={group.tahapan_id}
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="text-sm font-extrabold text-ink dark:text-white">
                                        {group.tahapan}
                                    </p>
                                    <p className="text-xs font-bold text-ink-soft">
                                        Total input: {group.total_input}% dari
                                        100% | Kontribusi ke unit:{" "}
                                        {group.total_kontribusi.toFixed(2)}%
                                    </p>
                                </div>
                                <div className="rounded-full bg-ink px-3 py-1 text-xs font-extrabold text-white dark:bg-white dark:text-ink">
                                    Bobot Tahap{" "}
                                    {Number(group.bobot_tahapan ?? 0)}%
                                </div>
                            </div>
                            <div className="mt-4 grid gap-3">
                                {group.rows.map((row) => (
                                    <div
                                        className="rounded-lg bg-silver-soft/70 p-3 dark:bg-white/5"
                                        key={row.id}
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-sm font-bold text-ink dark:text-white">
                                                {row.tanggal} | {row.persentase}
                                                %
                                            </p>
                                            <span
                                                className={`rounded-full px-3 py-1 text-[11px] font-extrabold ${badgeClass(row.status)}`}
                                            >
                                                {row.status_label}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-xs font-semibold text-ink-soft">
                                            Kontribusi:{" "}
                                            {row.persentase_total.toFixed(2)}% |
                                            Input oleh: {row.input_oleh} |
                                            Approve: {row.approved_by} | Lock:{" "}
                                            {row.record_status}
                                        </p>
                                        <p className="mt-2 whitespace-pre-line text-sm font-semibold text-ink dark:text-white">
                                            {row.keterangan}
                                        </p>
                                        {row.foto_url && (
                                            <a
                                                className="mt-2 inline-flex font-bold text-emerald-600 underline decoration-dotted underline-offset-4 dark:text-emerald-300"
                                                href={row.foto_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                Lihat Foto
                                            </a>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))
                )}
            </div>
        ),
    };

    const logistikAccordion = {
        title: "Detail Pengambilan Logistik",
        content: (
            <div className="grid gap-5">
                <div>
                    <h4 className="mb-3 text-sm font-extrabold text-ink dark:text-white">
                        Permintaan Material
                    </h4>
                    <div className="grid gap-3">
                        {filteredRequests.length === 0 ? (
                            <p className="text-sm font-bold text-ink-soft">
                                Belum ada permintaan material untuk unit ini.
                            </p>
                        ) : (
                            filteredRequests.map((row) => (
                                <div
                                    className="rounded-lg border border-silver-deep/50 p-4 dark:border-white/10"
                                    key={row.id}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-extrabold text-ink dark:text-white">
                                                {row.kode_request}
                                            </p>
                                            <p className="text-xs font-bold text-ink-soft">
                                                {row.tanggal} | {row.gudang} |{" "}
                                                {row.tahapan}
                                            </p>
                                        </div>
                                        <span
                                            className={`rounded-full px-3 py-1 text-[11px] font-extrabold ${badgeClass(row.status)}`}
                                        >
                                            {row.status_label}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm font-semibold text-ink dark:text-white">
                                        {row.items_text}
                                    </p>
                                    <p className="mt-1 text-sm font-semibold text-ink-soft">
                                        {row.keterangan ?? "-"}
                                    </p>
                                    <p className="mt-1 text-xs font-bold text-ink-soft">
                                        Disetujui oleh: {row.approved_by}
                                    </p>
                                    {row.can_approve &&
                                        row.status === "diajukan" && (
                                            <div className="mt-3">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    onClick={() =>
                                                        requestApprove(row)
                                                    }
                                                >
                                                    <CheckCircle2 size={15} />
                                                    Approve Permintaan
                                                </Button>
                                            </div>
                                        )}
                                </div>
                            ))
                        )}
                    </div>
                </div>

                <div>
                    <h4 className="mb-3 text-sm font-extrabold text-ink dark:text-white">
                        Transaksi Logistik
                    </h4>
                    <div className="grid gap-3">
                        {filteredLogistik.length === 0 ? (
                            <p className="text-sm font-bold text-ink-soft">
                                Belum ada transaksi logistik untuk unit ini.
                            </p>
                        ) : (
                            filteredLogistik.map((row) => (
                                <div
                                    className="rounded-lg border border-silver-deep/50 p-4 dark:border-white/10"
                                    key={row.id}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-extrabold text-ink dark:text-white">
                                                {row.kode_transaksi}
                                            </p>
                                            <p className="text-xs font-bold text-ink-soft">
                                                {row.tanggal} | {row.gudang} |{" "}
                                                {row.jenis}
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-silver-soft px-3 py-1 text-[11px] font-extrabold text-ink-soft dark:bg-white/10 dark:text-white/70">
                                            <PackageSearch
                                                size={13}
                                                className="inline-block -translate-y-[1px]"
                                            />{" "}
                                            Logistik
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm font-semibold text-ink dark:text-white">
                                        {row.items_text}
                                    </p>
                                    <p className="mt-1 text-sm font-semibold text-ink-soft">
                                        {row.keterangan ?? "-"}
                                    </p>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        ),
    };

    const items = [profileAccordion, progressAccordion, logistikAccordion];

    return (
        <>
            <Head
                title={`${title} ${rumah.kode_nlok ?? ""} ${rumah.nomor_rumah ?? ""}`.trim()}
            />
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
                                <ChevronRight
                                    size={16}
                                    className="rotate-180"
                                />{" "}
                                Kembali
                            </Button>
                            <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                                Detail Unit Rumah
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
                                    Progress Unit
                                </p>
                                <p className="mt-1 text-xl font-extrabold">
                                    {Number(
                                        rumah.progress_terakhir ?? 0,
                                    ).toFixed(2)}
                                    %
                                </p>
                            </div>
                            <div className="rounded-lg bg-silver-soft p-4 dark:bg-white/8">
                                <p className="text-xs font-extrabold uppercase tracking-[0.12em] text-ink-soft">
                                    Harga Jual
                                </p>
                                <p className="mt-1 text-xl font-extrabold">
                                    {money(rumah.harga_jual)}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                window.location.pathname,
                                { search },
                                {
                                    preserveScroll: true,
                                    preserveState: true,
                                    replace: true,
                                },
                            );
                        }}
                    >
                        <Input
                            className="md:max-w-md"
                            label="Search"
                            value={search}
                            placeholder="Cari tahapan, keterangan, permintaan, atau logistik..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Button type="submit">
                            <Search size={17} />
                            Cari
                        </Button>
                    </form>
                </section>

                <Accordion items={items} defaultOpen={0} />
            </div>
        </>
    );
}

UnitDetail.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail Unit Rumah"}>
        {page}
    </AdminLayout>
);
