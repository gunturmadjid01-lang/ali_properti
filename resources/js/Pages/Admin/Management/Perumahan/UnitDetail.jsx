import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    Banknote,
    Building2,
    CalendarDays,
    ExternalLink,
    FileCheck2,
    HardHat,
    Home,
    PackageOpen,
    ShieldCheck,
    UserRound,
} from "lucide-react";
import AdminLayout from "../../../../Layouts/AdminLayout";

const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

const humanize = (value) => String(value ?? "-").replaceAll("_", " ");
const showDate = (value) => value || "-";

function Status({ children }) {
    const value = String(children ?? "").toLowerCase();
    const positive = ["approved", "disetujui", "selesai", "aktif", "tersedia"].some((item) =>
        value.includes(item),
    );
    const negative = ["reject", "ditolak", "batal", "terlambat"].some((item) =>
        value.includes(item),
    );
    const color = positive
        ? "border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200"
        : negative
          ? "border-red-200 bg-red-50 text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200"
          : "border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200";

    return (
        <span className={`inline-flex rounded-full border px-2.5 py-1 text-[11px] font-black capitalize ${color}`}>
            {humanize(children)}
        </span>
    );
}

function Section({ id, eyebrow, title, description, icon: Icon, children }) {
    return (
        <section
            id={id}
            className="scroll-mt-28 border-b border-silver-deep/60 py-10 last:border-b-0 dark:border-white/10"
        >
            <div className="mb-7 flex items-start gap-4">
                <div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-ink text-gold dark:bg-white dark:text-ink">
                    <Icon size={20} />
                </div>
                <div>
                    <p className="text-[10px] font-black uppercase tracking-[0.2em] text-gold-deep dark:text-gold">
                        {eyebrow}
                    </p>
                    <h2 className="mt-1 font-display text-2xl font-black text-ink dark:text-white">
                        {title}
                    </h2>
                    {description && (
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-ink-soft dark:text-white/60">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            {children}
        </section>
    );
}

function Facts({ items }) {
    return (
        <dl className="grid border-y border-silver-deep/60 sm:grid-cols-2 lg:grid-cols-4 dark:border-white/10">
            {items.map(([name, value]) => (
                <div
                    className="border-b border-silver-deep/50 px-1 py-5 sm:px-5 sm:[&:nth-child(2n)]:border-l lg:[&:nth-child(2n)]:border-l-0 lg:[&:not(:nth-child(4n+1))]:border-l dark:border-white/10"
                    key={name}
                >
                    <dt className="text-[10px] font-black uppercase tracking-[0.16em] text-ink-soft">
                        {name}
                    </dt>
                    <dd className="mt-2 text-sm font-extrabold capitalize text-ink dark:text-white">
                        {value ?? "-"}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function Empty({ text = "Belum ada data yang tercatat untuk unit ini." }) {
    return (
        <div className="border-l-2 border-silver-deep py-3 pl-5 text-sm font-bold text-ink-soft dark:border-white/20">
            {text}
        </div>
    );
}

function DetailLink({ href }) {
    if (!href) return null;
    return (
        <a
            className="inline-flex items-center gap-1.5 text-xs font-black text-gold-deep hover:underline dark:text-gold"
            href={href}
        >
            Buka detail <ExternalLink size={13} />
        </a>
    );
}

function Table({ columns, rows, empty }) {
    if (!rows?.length) return <Empty text={empty} />;

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
                <thead>
                    <tr className="border-y border-silver-deep/70 text-left text-[10px] uppercase tracking-[0.14em] text-ink-soft dark:border-white/15">
                        {columns.map((column) => (
                            <th className="whitespace-nowrap px-4 py-3 font-black first:pl-0" key={column.label}>
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            className="border-b border-silver-deep/50 align-top transition hover:bg-silver-soft/50 dark:border-white/10 dark:hover:bg-white/5"
                            key={row.id}
                        >
                            {columns.map((column) => (
                                <td className="px-4 py-4 first:pl-0" key={column.label}>
                                    {column.render ? column.render(row) : (row[column.key] ?? "-")}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function Timeline({ rows }) {
    if (!rows?.length) return <Empty text="Belum ada progress pembangunan." />;
    return (
        <div className="relative ml-2 border-l border-silver-deep dark:border-white/15">
            {rows.map((row) => (
                <article className="relative pb-8 pl-7 last:pb-0" key={row.id}>
                    <span className="absolute -left-[5px] top-1.5 h-2.5 w-2.5 rounded-full bg-gold ring-4 ring-white dark:ring-[#171717]" />
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="font-black text-ink dark:text-white">{row.tahapan}</p>
                            <p className="mt-1 text-xs font-bold text-ink-soft">
                                {showDate(row.tanggal)} · Input {row.input_oleh ?? "-"} · Approval {row.approved_by ?? "-"}
                            </p>
                        </div>
                        <Status>{row.status_label}</Status>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm font-extrabold">
                        <span>Progress tahap {row.persentase}%</span>
                        <span>Kontribusi unit {Number(row.persentase_total ?? 0).toFixed(2)}%</span>
                    </div>
                    <p className="mt-2 whitespace-pre-line text-sm leading-6 text-ink-soft dark:text-white/65">
                        {row.keterangan ?? "-"}
                    </p>
                    {row.foto_url && (
                        <a
                            className="mt-2 inline-flex text-xs font-black text-gold-deep hover:underline dark:text-gold"
                            href={row.foto_url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            Lihat dokumentasi
                        </a>
                    )}
                </article>
            ))}
        </div>
    );
}

export default function UnitDetail({
    title,
    baseUrl,
    perumahan = {},
    rumah = {},
    visibility = {},
    progressRows = [],
    materialRows = [],
    logistikRows = [],
    hpp = null,
    salesRows = [],
    transactionRows = [],
    reservationRows = [],
    spkRows = [],
    scheduleRows = [],
    reportRows = [],
    qualityRows = [],
}) {
    const navigation = [
        ["overview", "Unit", Home, true],
        ["commercial", "Komersial", Banknote, visibility.hpp || visibility.reservations || visibility.sales],
        ["construction", "Konstruksi", HardHat, visibility.progress || visibility.spk || visibility.schedules],
        ["control", "Kontrol Mutu", ShieldCheck, visibility.reports || visibility.quality],
        ["logistics", "Material", PackageOpen, visibility.materials || visibility.logistics],
    ].filter((item) => item[3]);

    const commercialRows = [
        ...reservationRows.map((row) => ({ ...row, kind: "Reservasi", amount: row.booking_fee, date: row.reserved_at })),
        ...salesRows.map((row) => ({ ...row, kind: "SPR", amount: row.sale_price })),
        ...transactionRows.map((row) => ({ ...row, kind: "Transaksi", amount: row.sale_price })),
    ];

    return (
        <>
            <Head title={`${title} ${rumah.kode_nlok ?? ""} ${rumah.nomor_rumah ?? ""}`.trim()} />

            <div className="mx-auto max-w-[1500px]">
                <header className="relative overflow-hidden rounded-2xl bg-ink px-6 py-7 text-white shadow-soft sm:px-8 lg:px-10 lg:py-9">
                    <div className="absolute -right-16 -top-24 h-72 w-72 rounded-full border-[48px] border-white/[0.035]" />
                    <div className="absolute bottom-0 right-1/3 h-24 w-48 -skew-x-12 bg-gold/[0.06]" />
                    <div className="relative">
                        <Link
                            className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.12em] text-white/60 transition hover:text-white"
                            href={baseUrl}
                        >
                            <ArrowLeft size={15} /> Kembali ke daftar unit
                        </Link>

                        <div className="mt-7 flex flex-col justify-between gap-7 lg:flex-row lg:items-end">
                            <div>
                                <div className="flex items-center gap-2 text-xs font-black uppercase tracking-[0.2em] text-gold">
                                    <Building2 size={15} /> {perumahan.nama_perusahaan}
                                </div>
                                <h1 className="mt-3 font-display text-4xl font-black tracking-tight sm:text-5xl">
                                    Blok {rumah.kode_nlok ?? "-"}
                                    <span className="text-white/35"> / </span>
                                    No. {rumah.nomor_rumah ?? "-"}
                                </h1>
                                <p className="mt-3 max-w-2xl text-sm leading-6 text-white/60">
                                    {perumahan.cabang ?? "-"} · {perumahan.alamat ?? "-"}
                                </p>
                            </div>

                            <div className="flex flex-wrap items-end gap-x-8 gap-y-4 lg:justify-end">
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-[0.16em] text-white/45">Status Penjualan</p>
                                    <p className="mt-1 text-lg font-black capitalize">{humanize(rumah.status_penjualan)}</p>
                                </div>
                                {visibility.price && (
                                    <div>
                                        <p className="text-[10px] font-black uppercase tracking-[0.16em] text-white/45">Harga Jual</p>
                                        <p className="mt-1 text-lg font-black text-gold">{money(rumah.harga_jual)}</p>
                                    </div>
                                )}
                                {visibility.progress && (
                                    <div>
                                        <p className="text-[10px] font-black uppercase tracking-[0.16em] text-white/45">Progress</p>
                                        <p className="mt-1 text-lg font-black">{Number(rumah.progress_terakhir ?? 0).toFixed(2)}%</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </header>

                <nav className="sticky top-2 z-20 mt-5 overflow-x-auto rounded-xl border border-white/80 bg-white/90 px-3 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-[#171717]/90">
                    <div className="flex min-w-max">
                        {navigation.map(([id, name, Icon]) => (
                            <a
                                className="group flex items-center gap-2 border-b-2 border-transparent px-4 py-4 text-xs font-black text-ink-soft transition hover:border-gold hover:text-ink dark:hover:text-white"
                                href={`#${id}`}
                                key={id}
                            >
                                <Icon className="text-gold-deep" size={15} /> {name}
                            </a>
                        ))}
                    </div>
                </nav>

                <main className="mt-5 rounded-2xl border border-white/80 bg-white/80 px-6 shadow-soft backdrop-blur dark:border-white/10 dark:bg-white/[0.04] sm:px-8 lg:px-10">
                    <Section
                        id="overview"
                        eyebrow="Data Induk"
                        title="Profil dan spesifikasi unit"
                        description="Informasi teknis, status, dan identitas unit dalam satu tampilan tetap."
                        icon={Home}
                    >
                        <Facts
                            items={[
                                ["Model / Tipe", `${rumah.model_unit ?? "-"} / ${rumah.tipe_rumah ?? "-"}`],
                                ["Luas Tanah", rumah.luas_tanah ? `${rumah.luas_tanah} m²` : "-"],
                                ["Luas Bangunan", rumah.luas_bangunan ? `${rumah.luas_bangunan} m²` : "-"],
                                ["Jumlah Lantai", rumah.jumlah_lantai],
                                ["Kamar", `${rumah.kamar_tidur ?? "-"} tidur / ${rumah.kamar_mandi ?? "-"} mandi`],
                                ["Daya Listrik", rumah.daya_listrik],
                                ["Sumber Air", rumah.sumber_air],
                                ["Carport", rumah.carport],
                                ["Arah Hadap", rumah.arah_hadap],
                                ["Posisi Unit", rumah.posisi_unit],
                                ["Status Pembangunan", humanize(rumah.status_pembangunan)],
                                ["Mulai / Selesai", `${showDate(rumah.tanggal_mulai_bangun)} / ${showDate(rumah.tanggal_selesai_bangun)}`],
                            ]}
                        />

                        {(rumah.spesifikasi || rumah.catatan) && (
                            <div className="mt-7 grid gap-7 lg:grid-cols-2">
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-[0.16em] text-ink-soft">Spesifikasi Tambahan</p>
                                    <p className="mt-2 whitespace-pre-line text-sm leading-7 text-ink dark:text-white">{rumah.spesifikasi ?? "-"}</p>
                                </div>
                                <div>
                                    <p className="text-[10px] font-black uppercase tracking-[0.16em] text-ink-soft">Catatan Unit</p>
                                    <p className="mt-2 whitespace-pre-line text-sm leading-7 text-ink dark:text-white">{rumah.catatan ?? "-"}</p>
                                </div>
                            </div>
                        )}

                        {visibility.ownership && (
                            <div className="mt-9 border-t border-silver-deep/60 pt-7 dark:border-white/10">
                                <div className="mb-5 flex items-center gap-2">
                                    <UserRound className="text-gold-deep" size={18} />
                                    <h3 className="font-black">Pemilik Aktif</h3>
                                </div>
                                {rumah.pemilik ? (
                                    <Facts items={[["Nama", rumah.pemilik.nama], ["Identitas", `${rumah.pemilik.jenis_identitas ?? "-"} / ${rumah.pemilik.nomor_identitas ?? "-"}`], ["Kontak", `${rumah.pemilik.telepon ?? "-"} / ${rumah.pemilik.email ?? "-"}`], ["Sumber", rumah.pemilik.sumber], ["Sejak", rumah.pemilik.tanggal_mulai], ["Dokumen", rumah.pemilik.nomor_dokumen], ["Alamat", rumah.pemilik.alamat]]} />
                                ) : <Empty text="Belum ada pemilik aktif yang tercatat." />}
                            </div>
                        )}
                    </Section>

                    {(visibility.hpp || visibility.reservations || visibility.sales) && (
                        <Section id="commercial" eyebrow="Komersial" title="Harga, RAB, dan riwayat penjualan" description="Anggaran pembangunan dan jejak komersial unit ditampilkan sesuai kewenangan akun." icon={Banknote}>
                            {visibility.hpp && (
                                <div className="mb-10">
                                    <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                                        <div>
                                            <h3 className="font-black">RAB dan Realisasi Unit</h3>
                                            <p className="mt-1 text-sm text-ink-soft">RAB {money(hpp?.total_rab)} · Realisasi {money(hpp?.total_realisasi)} · Sisa {money((hpp?.total_rab ?? 0) - (hpp?.total_realisasi ?? 0))}</p>
                                        </div>
                                        <DetailLink href={hpp?.url} />
                                    </div>
                                    <Table rows={hpp?.rows} empty="RAB unit belum disusun." columns={[
                                        { label: "Pekerjaan", render: (row) => <><p className="font-extrabold">{row.nama_pekerjaan}</p><p className="mt-1 text-xs text-ink-soft">{row.kelompok_hpp_nama}</p></> },
                                        { label: "Tahapan", key: "tahapan_nama" },
                                        { label: "Volume", render: (row) => `${row.volume} ${row.satuan ?? ""}` },
                                        { label: "RAB", render: (row) => <span className="font-extrabold">{money(row.jumlah_rab)}</span> },
                                        { label: "Realisasi", render: (row) => money(row.jumlah_realisasi) },
                                        { label: "Sisa", render: (row) => money(row.sisa_anggaran) },
                                    ]} />
                                </div>
                            )}
                            {(visibility.reservations || visibility.sales) && (
                                <div>
                                    <h3 className="mb-5 font-black">Jejak Reservasi dan Penjualan</h3>
                                    <Table rows={commercialRows} empty="Belum ada reservasi atau transaksi penjualan." columns={[
                                        { label: "Jenis", render: (row) => <span className="font-black">{row.kind}</span> },
                                        { label: "Nomor", render: (row) => <><p className="font-extrabold">{row.number}</p><DetailLink href={row.url} /></> },
                                        { label: "Customer", key: "customer" },
                                        { label: "Tanggal", render: (row) => showDate(row.date) },
                                        { label: "Nilai", render: (row) => <span className="font-extrabold">{money(row.amount)}</span> },
                                        { label: "Status", render: (row) => <Status>{row.status}</Status> },
                                    ]} />
                                </div>
                            )}
                        </Section>
                    )}

                    {(visibility.progress || visibility.spk || visibility.schedules) && (
                        <Section id="construction" eyebrow="Pelaksanaan" title="Konstruksi dan pekerjaan lapangan" description="Perkembangan fisik unit, kontrak kerja, dan jadwal pelaksanaan." icon={HardHat}>
                            {visibility.progress && <div className="mb-10"><h3 className="mb-5 font-black">Riwayat Progress</h3><Timeline rows={progressRows} /></div>}
                            {visibility.spk && <div className="mb-10"><h3 className="mb-5 font-black">SPK Kontraktor</h3><Table rows={spkRows} columns={[
                                { label: "SPK", render: (row) => <><p className="font-extrabold">{row.number}</p><DetailLink href={row.url} /></> },
                                { label: "Pekerjaan", render: (row) => <><p className="font-bold">{row.title}</p><p className="mt-1 text-xs text-ink-soft">{row.contractor}</p></> },
                                { label: "Periode", render: (row) => `${showDate(row.start_date)} — ${showDate(row.end_date)}` },
                                { label: "Nilai", render: (row) => <span className="font-extrabold">{money(row.value)}</span> },
                                { label: "Status", render: (row) => <Status>{row.status}</Status> },
                            ]} /></div>}
                            {visibility.schedules && <div><h3 className="mb-5 font-black">Jadwal Pekerjaan</h3><Table rows={scheduleRows} columns={[
                                { label: "Jadwal", render: (row) => <><p className="font-extrabold">{row.number}</p><p className="mt-1 text-xs text-ink-soft">{row.stage}</p></> },
                                { label: "Pekerjaan", key: "work" },
                                { label: "Periode", render: (row) => `${showDate(row.start_date)} — ${showDate(row.target_date)}` },
                                { label: "Target", render: (row) => `${row.target}%` },
                                { label: "Realisasi", render: (row) => <span className="font-extrabold">{row.actual}%</span> },
                                { label: "Status", render: (row) => <Status>{row.status}</Status> },
                            ]} /></div>}
                        </Section>
                    )}

                    {(visibility.reports || visibility.quality) && (
                        <Section id="control" eyebrow="Pengendalian" title="Laporan dan kontrol mutu" description="Catatan lapangan dan hasil inspeksi untuk menjaga mutu penyelesaian unit." icon={ShieldCheck}>
                            {visibility.reports && <div className="mb-10"><h3 className="mb-5 flex items-center gap-2 font-black"><CalendarDays size={17} className="text-gold-deep" /> Laporan Lapangan</h3><Table rows={reportRows} columns={[
                                { label: "Laporan", render: (row) => <><p className="font-extrabold">{row.number}</p><p className="mt-1 text-xs text-ink-soft">{humanize(row.type)}</p></> },
                                { label: "Tanggal", render: (row) => showDate(row.date) },
                                { label: "Tahapan", key: "stage" },
                                { label: "Pekerja", key: "workers" },
                                { label: "Pekerjaan Selesai", key: "completed" },
                                { label: "Status", render: (row) => <Status>{row.status}</Status> },
                            ]} /></div>}
                            {visibility.quality && <div><h3 className="mb-5 flex items-center gap-2 font-black"><FileCheck2 size={17} className="text-gold-deep" /> Inspeksi Mutu</h3><Table rows={qualityRows} columns={[
                                { label: "Inspeksi", render: (row) => <><p className="font-extrabold">{row.number}</p><p className="mt-1 text-xs text-ink-soft">{showDate(row.date)}</p></> },
                                { label: "Tahapan", key: "stage" },
                                { label: "Hasil", key: "result" },
                                { label: "Temuan", key: "finding" },
                                { label: "Tindakan / Target", render: (row) => <><p>{row.corrective_action ?? "-"}</p><p className="mt-1 text-xs text-ink-soft">{showDate(row.target_date)}</p></> },
                                { label: "Status", render: (row) => <Status>{row.status}</Status> },
                            ]} /></div>}
                        </Section>
                    )}

                    {(visibility.materials || visibility.logistics) && (
                        <Section id="logistics" eyebrow="Operasional" title="Material dan logistik unit" description="Permintaan dan arus material yang secara langsung terhubung dengan unit." icon={PackageOpen}>
                            {visibility.materials && <div className="mb-10"><h3 className="mb-5 font-black">Permintaan Material</h3><Table rows={materialRows} columns={[
                                { label: "Permintaan", render: (row) => <><p className="font-extrabold">{row.kode_request}</p><p className="mt-1 text-xs text-ink-soft">{showDate(row.tanggal)}</p></> },
                                { label: "Gudang", key: "gudang" },
                                { label: "Tahapan", key: "tahapan" },
                                { label: "Material", key: "items_text" },
                                { label: "Keterangan", key: "keterangan" },
                                { label: "Status", render: (row) => <Status>{row.status_label}</Status> },
                            ]} /></div>}
                            {visibility.logistics && <div><h3 className="mb-5 font-black">Transaksi Logistik</h3><Table rows={logistikRows} columns={[
                                { label: "Transaksi", render: (row) => <><p className="font-extrabold">{row.kode_transaksi}</p><p className="mt-1 text-xs text-ink-soft">{showDate(row.tanggal)}</p></> },
                                { label: "Jenis", render: (row) => humanize(row.jenis) },
                                { label: "Gudang", key: "gudang" },
                                { label: "Material", key: "items_text" },
                                { label: "Keterangan", key: "keterangan" },
                            ]} /></div>}
                        </Section>
                    )}

                    <footer className="flex flex-wrap items-center justify-between gap-3 py-7 text-xs font-bold text-ink-soft">
                        <span>Dibuat oleh {rumah.created_by ?? "-"} · Terakhir diubah oleh {rumah.updated_by ?? "-"}</span>
                        <span>Bagian yang tidak memiliki permission tidak dimuat.</span>
                    </footer>
                </main>
            </div>
        </>
    );
}

UnitDetail.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail Unit Rumah"}>{page}</AdminLayout>
);
