import { Head, Link, router, useForm } from "@inertiajs/react";
import { ArrowLeft, FileText, MapPin, UserPlus } from "lucide-react";
import { Button } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const readable = (value) => {
    if (Array.isArray(value)) {
        return value
            .map(
                (item) =>
                    `${item.name}: ${item.status}${item.required ? " (wajib)" : ""}${item.file_name ? ` — file: ${item.file_name}` : ""}${item.note ? ` — ${item.note}` : ""}`,
            )
            .join("\n");
    }

    if (value === null || value === undefined || value === "") return "-";

    return String(value).replaceAll("_", " ");
};

const ChecklistItems = ({ items }) => (
    <div className="grid gap-3">
        {items.map((item, index) => (
            <div
                className="rounded-xl border border-silver-deep/60 bg-white/70 p-4 dark:border-white/10 dark:bg-white/5"
                key={`${item.customer_document_id ?? item.name}-${index}`}
            >
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="font-extrabold">
                            {item.name || "Dokumen"}
                        </p>
                        <p className="mt-1 text-xs font-bold uppercase tracking-wider text-ink-soft">
                            {item.required ? "Wajib" : "Opsional"} ·{" "}
                            {readable(item.status)}
                        </p>
                    </div>
                    {item.document_url && (
                        <Button
                            as="a"
                            href={item.document_url}
                            target="_blank"
                            rel="noreferrer"
                            variant="outline"
                            size="sm"
                        >
                            <FileText size={15} /> Lihat Berkas
                        </Button>
                    )}
                </div>
                <div className="mt-3 grid gap-2 text-sm font-semibold text-ink-soft md:grid-cols-2">
                    <p>
                        Pihak:{" "}
                        <span className="text-ink">
                            {item.party_scope === "spouse"
                                ? "Pasangan"
                                : item.party_scope === "both"
                                  ? "Customer dan Pasangan"
                                  : "Customer"}
                        </span>
                    </p>
                    <p>
                        Sumber:{" "}
                        <span className="text-ink">
                            {item.source || "Dokumen khusus"}
                        </span>
                    </p>
                    <p>
                        File:{" "}
                        <span className="text-ink">
                            {item.file_name || "Belum upload"}
                        </span>
                    </p>
                    <p>
                        Masa berlaku:{" "}
                        <span className="text-ink">
                            {item.expires_at || "-"}
                        </span>
                    </p>
                </div>
                {item.note && (
                    <p className="mt-3 whitespace-pre-wrap rounded-lg bg-silver-soft px-3 py-2 text-sm font-semibold text-ink-soft">
                        {item.note}
                    </p>
                )}
            </div>
        ))}
    </div>
);

export default function Show({ title, baseUrl, row, fields = [], contacts = [], contactStoreUrl = null }) {
    const contactForm = useForm({ name: "", phone: "", email: "", organization: "", outcome: "information_only", interest_level: "cold", notes: "" });
    const visibleFields = fields.filter(
        (field) =>
            !["check_in_photo_path", "check_out_photo_path"].includes(field),
    );

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.visit(baseUrl)}
                        >
                            <ArrowLeft size={16} /> Kembali
                        </Button>
                        {row.check_in_url && (
                            <Button as={Link} href={row.check_in_url}>
                                Mulai Kunjungan
                            </Button>
                        )}
                        {row.check_out_url && (
                            <Button as={Link} href={row.check_out_url}>
                                Selesaikan Kunjungan
                            </Button>
                        )}
                        {row.convert_customer_url && (
                            <Button as={Link} href={row.convert_customer_url}>
                                <UserPlus size={16} /> Konversi Menjadi Lead
                                Customer
                            </Button>
                        )}
                        {row.map_url && (
                            <Button
                                as="a"
                                target="_blank"
                                variant="outline"
                                href={row.map_url}
                            >
                                <MapPin size={16} /> Buka Map
                            </Button>
                        )}
                        {row.check_in_evidence_url && (
                            <Button
                                as="a"
                                target="_blank"
                                variant="outline"
                                href={row.check_in_evidence_url}
                            >
                                Foto Check-in
                            </Button>
                        )}
                        {row.check_out_evidence_url && (
                            <Button
                                as="a"
                                target="_blank"
                                variant="outline"
                                href={row.check_out_evidence_url}
                            >
                                Foto Check-out
                            </Button>
                        )}
                    </div>
                    <h1 className="mt-5 text-3xl font-extrabold">{title}</h1>
                </section>

                <section className="grid gap-4 rounded-lg border bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8 md:grid-cols-2">
                    {visibleFields.map((field) => (
                        <div
                            className={
                                Array.isArray(row[field]) ||
                                String(row[field] ?? "").length > 100
                                    ? "md:col-span-2"
                                    : ""
                            }
                            key={field}
                        >
                            <p className="text-xs font-extrabold uppercase tracking-wider text-ink-soft">
                                {field.replaceAll("_", " ")}
                            </p>
                            {field === "items" && Array.isArray(row[field]) ? (
                                <div className="mt-2">
                                    <ChecklistItems items={row[field]} />
                                </div>
                            ) : (
                                <p className="mt-2 whitespace-pre-wrap font-semibold">
                                    {readable(row[field])}
                                </p>
                            )}
                        </div>
                    ))}
                </section>
                {contactStoreUrl && <section className="grid gap-4 rounded-lg border bg-white/80 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div><h2 className="text-xl font-extrabold">Kontak yang Ditemui</h2><p className="text-sm text-ink-soft">Orang tanpa kontak tetap dihitung sebagai hasil aktivitas. Hanya orang yang memberikan telepon/email yang dapat dijadikan Lead.</p></div>
                    <div className="grid gap-3">{contacts.map(contact => <div key={contact.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"><div><b>{contact.name || "Kontak tanpa nama"}</b><p className="text-sm">{contact.phone || contact.email || "Tidak memberikan kontak"} · {contact.outcome}</p>{contact.notes && <p className="text-sm text-ink-soft">{contact.notes}</p>}</div>{contact.lead ? <Link className="font-bold text-gold-deep" href={`/admin/marketing/leads/${contact.lead.id}`}>{contact.lead.lead_no} · {contact.lead.stage}</Link> : (contact.phone || contact.email) && <button className="rounded-lg bg-ink px-3 py-2 text-sm font-bold text-white" onClick={() => router.post(`/admin/marketing/aktivitas-lapangan/contacts/${contact.id}/convert`)}>Jadikan Lead</button>}</div>)}</div>
                    <form onSubmit={e=>{e.preventDefault();contactForm.post(contactStoreUrl,{preserveScroll:true,onSuccess:()=>contactForm.reset()})}} className="grid gap-3 rounded-xl bg-silver-soft p-4 md:grid-cols-2"><input className="rounded-lg border p-3" placeholder="Nama (opsional)" value={contactForm.data.name} onChange={e=>contactForm.setData('name',e.target.value)}/><input className="rounded-lg border p-3" placeholder="Telepon" value={contactForm.data.phone} onChange={e=>contactForm.setData('phone',e.target.value)}/><input className="rounded-lg border p-3" placeholder="Email" value={contactForm.data.email} onChange={e=>contactForm.setData('email',e.target.value)}/><input className="rounded-lg border p-3" placeholder="Instansi/komunitas" value={contactForm.data.organization} onChange={e=>contactForm.setData('organization',e.target.value)}/><select className="rounded-lg border p-3" value={contactForm.data.outcome} onChange={e=>contactForm.setData('outcome',e.target.value)}>{[['no_contact','Tidak memperoleh kontak'],['information_only','Menerima informasi'],['interested','Tertarik'],['request_follow_up','Meminta dihubungi'],['request_survey','Meminta survei'],['not_interested','Tidak tertarik']].map(([v,l])=><option value={v} key={v}>{l}</option>)}</select><select className="rounded-lg border p-3" value={contactForm.data.interest_level} onChange={e=>contactForm.setData('interest_level',e.target.value)}>{['cold','warm','hot'].map(x=><option key={x}>{x}</option>)}</select><textarea className="rounded-lg border p-3 md:col-span-2" placeholder="Catatan hasil percakapan" value={contactForm.data.notes} onChange={e=>contactForm.setData('notes',e.target.value)}/>{Object.values(contactForm.errors).map(x=><small className="text-red-600" key={x}>{x}</small>)}<button className="rounded-lg bg-ink p-3 font-bold text-white md:col-span-2">Tambah Kontak Hasil Aktivitas</button></form>
                </section>}
            </div>
        </>
    );
}

Show.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail CRM"}>{page}</AdminLayout>
);
