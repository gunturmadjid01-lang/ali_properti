import { Head, Link, router } from "@inertiajs/react";
import {
    ArrowLeft,
    CalendarPlus,
    Edit3,
    MessageCircle,
    PhoneCall,
} from "lucide-react";
import { Button } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Show({
    title,
    baseUrl,
    row,
    fields = [],
    canEdit,
    quickActions = {},
    timeline = [],
}) {
    const fixed = [
        ["Kode Pelanggan", row.kode_costumer],
        ["Perumahan", row.perumahan],
        ["Sumber Lead", row.sumber_lead],
        ["Kampanye", row.campaign],
        ["Status Data", row.record_status],
    ];
    const groups = [
        ["profile", "Profil Pelanggan"],
        ["pekerjaan", "Pekerjaan"],
        ["pasangan", "Pasangan"],
    ];
    const installments = Array.isArray(row.daftar_cicilan)
        ? row.daftar_cicilan
        : [];
    const unitInterests = Array.isArray(row.unit_interests)
        ? row.unit_interests
        : [];
    const whatsapp = String(quickActions.phone ?? "")
        .replace(/\D/g, "")
        .replace(/^0/, "62");

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-widest text-ink-soft">
                        Marketing / Pelanggan / Detail
                    </p>
                    <h1 className="mt-2 text-3xl font-extrabold">{title}</h1>
                    <div className="mt-5 flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.visit(baseUrl)}
                        >
                            <ArrowLeft size={16} /> Kembali
                        </Button>
                        {canEdit && (
                            <Button
                                onClick={() =>
                                    router.visit(`${baseUrl}/${row.id}/edit`)
                                }
                            >
                                <Edit3 size={16} /> Ubah
                            </Button>
                        )}
                        {quickActions.followUpUrl && (
                            <Button as={Link} href={quickActions.followUpUrl}>
                                <MessageCircle size={16} /> Catat Follow-up
                            </Button>
                        )}
                        {quickActions.visitUrl && (
                            <Button
                                as={Link}
                                href={quickActions.visitUrl}
                                variant="outline"
                            >
                                <CalendarPlus size={16} /> Jadwalkan Kunjungan
                            </Button>
                        )}
                        {quickActions.phone && (
                            <a
                                className="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-extrabold"
                                href={`tel:${quickActions.phone}`}
                            >
                                <PhoneCall size={16} /> Telepon
                            </a>
                        )}
                        {whatsapp && (
                            <a
                                className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-extrabold text-white"
                                href={`https://wa.me/${whatsapp}`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <MessageCircle size={16} /> WhatsApp
                            </a>
                        )}
                    </div>
                </section>
                <section className="grid gap-4 rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft md:grid-cols-2 dark:border-white/10 dark:bg-white/8">
                    {fixed.map(([label, value]) => (
                        <Item key={label} label={label} value={value} />
                    ))}
                </section>
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <h2 className="text-lg font-extrabold">
                        Timeline Customer
                    </h2>
                    <p className="mt-1 text-sm text-ink-soft">
                        Follow-up, kunjungan, perubahan status, action plan,
                        reminder, dan dokumen dalam satu kronologi.
                    </p>
                    <div className="mt-5 grid gap-3">
                        {timeline.map((item, index) => (
                            <article
                                key={`${item.type}-${item.at}-${index}`}
                                className="border-l-4 border-gold bg-silver-soft/70 px-4 py-3 dark:bg-white/5"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <strong>{item.title}</strong>
                                    <span className="text-xs font-bold text-ink-soft">
                                        {dateTime(item.at)}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm text-ink-soft">
                                    {item.description || "Tanpa catatan"}
                                </p>
                                <p className="mt-2 text-xs font-extrabold uppercase text-gold-deep">
                                    {item.status || item.type}
                                    {item.user ? ` · ${item.user}` : ""}
                                </p>
                            </article>
                        ))}
                        {timeline.length === 0 && (
                            <p className="text-sm font-semibold text-ink-soft">
                                Belum ada aktivitas customer.
                            </p>
                        )}
                    </div>
                </section>
                {groups.map(([key, label]) => (
                    <section
                        className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8"
                        key={key}
                    >
                        <h2 className="text-lg font-extrabold">{label}</h2>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            {fields
                                .filter((field) => field.group === key)
                                .map((field) => (
                                    <Item
                                        key={field.name}
                                        label={field.label}
                                        value={row[field.name]}
                                    />
                                ))}
                        </div>
                    </section>
                ))}
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <h2 className="text-lg font-extrabold">Cicilan Berjalan</h2>
                    <div className="mt-4 grid gap-3">
                        {installments.length === 0 ? (
                            <p className="text-sm font-semibold text-ink-soft">
                                Tidak ada cicilan yang dicatat.
                            </p>
                        ) : (
                            installments.map((item, index) => (
                                <div
                                    className="grid gap-3 rounded-lg border border-silver-deep/70 p-4 md:grid-cols-3 dark:border-white/10"
                                    key={index}
                                >
                                    <Item
                                        label="Pemilik"
                                        value={item.pemilik}
                                    />
                                    <Item label="Jenis" value={item.jenis} />
                                    <Item
                                        label="Kreditur"
                                        value={item.kreditur}
                                    />
                                    <Item
                                        label="Angsuran Bulanan"
                                        value={money(item.angsuran_bulanan)}
                                    />
                                    <Item
                                        label="Sisa Pokok"
                                        value={money(item.sisa_pokok)}
                                    />
                                    <Item
                                        label="Tanggal Selesai"
                                        value={item.tanggal_selesai}
                                    />
                                </div>
                            ))
                        )}
                    </div>
                </section>
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <h2 className="text-lg font-extrabold">Minat Unit</h2>
                    <div className="mt-4 grid gap-3">
                        {unitInterests.length === 0 ? (
                            <p className="text-sm font-semibold text-ink-soft">
                                Belum ada unit atau perumahan yang dicatat
                                sebagai minat.
                            </p>
                        ) : (
                            unitInterests.map((item, index) => (
                                <div
                                    className="grid gap-3 rounded-lg border border-silver-deep/70 p-4 md:grid-cols-3 dark:border-white/10"
                                    key={index}
                                >
                                    <Item
                                        label="Unit"
                                        value={
                                            item.unit_label ||
                                            "Belum pilih unit"
                                        }
                                    />
                                    <Item
                                        label="Perumahan"
                                        value={item.perumahan}
                                    />
                                    <Item
                                        label="Tingkat Minat"
                                        value={item.interest_level}
                                    />
                                    <Item
                                        label="Rencana Bayar"
                                        value={item.payment_plan}
                                    />
                                    <Item
                                        label="Budget Min"
                                        value={money(item.budget_min)}
                                    />
                                    <Item
                                        label="Budget Max"
                                        value={money(item.budget_max)}
                                    />
                                    <div className="md:col-span-3">
                                        <Item
                                            label="Catatan"
                                            value={item.notes}
                                        />
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

function Item({ label, value }) {
    return (
        <div>
            <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft">
                {label}
            </p>
            <p className="mt-1 whitespace-pre-wrap font-semibold">
                {value === null || value === "" || value === undefined
                    ? "-"
                    : String(value)}
            </p>
        </div>
    );
}
function money(value) {
    if (value === null || value === "" || value === undefined) return "-";
    return `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
}
function dateTime(value) {
    return value
        ? new Intl.DateTimeFormat("id-ID", {
              dateStyle: "medium",
              timeStyle: "short",
          }).format(new Date(value))
        : "-";
}
Show.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail Customer"}>
        {page}
    </AdminLayout>
);
