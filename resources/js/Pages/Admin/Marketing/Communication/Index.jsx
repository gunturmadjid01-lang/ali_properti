import { Head, router, useForm } from "@inertiajs/react";
import { MessageCircle, Send } from "lucide-react";
import AdminLayout from "../../../../Layouts/AdminLayout";

const channelLabel = { whatsapp: "WhatsApp", email: "Email", sms: "SMS" };

export default function Index({ title, threads, selected, filters = {} }) {
    const form = useForm({ body: "", template_code: "" });
    const submitSearch = (event) => {
        event.preventDefault();
        router.get("/admin/marketing/komunikasi", { search: event.currentTarget.search.value }, { preserveState: true, replace: true });
    };
    const send = (event) => {
        event.preventDefault();
        if (!selected) return;
        form.post(`/admin/marketing/komunikasi/${selected.id}/kirim`, { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return <>
        <Head title={title} />
        <div className="grid gap-5">
            <header className="rounded-3xl border bg-white p-6"><p className="text-xs font-black uppercase tracking-widest text-gold-deep">Marketing</p><h1 className="mt-1 text-3xl font-black">{title}</h1><p className="mt-2 text-ink-soft">Satu tempat untuk melihat percakapan Lead dan Customer. Pesan keluar tetap mengikuti persetujuan komunikasi.</p></header>
            <div className="grid gap-5 lg:grid-cols-[minmax(260px,360px)_1fr]">
                <section className="overflow-hidden rounded-2xl border bg-white"><form onSubmit={submitSearch} className="border-b p-4"><input name="search" defaultValue={filters.search} placeholder="Cari nama, nomor, atau alamat" className="w-full rounded-lg border p-3" /></form><div className="divide-y">{threads.data.map((thread) => <button type="button" key={thread.id} onClick={() => router.get("/admin/marketing/komunikasi", { thread: thread.id, search: filters.search || undefined }, { preserveState: true, replace: true })} className={`w-full p-4 text-left hover:bg-slate-50 ${selected?.id === thread.id ? "bg-amber-50" : ""}`}><div className="flex items-start justify-between gap-3"><div><p className="font-black">{thread.contact_name}</p><p className="text-xs text-ink-soft">{channelLabel[thread.channel] || thread.channel} · {thread.contact_address || "alamat belum tersedia"}</p></div><span className="text-[11px] text-ink-soft">{thread.last_message_at || "-"}</span></div><p className="mt-2 text-xs text-ink-soft">PIC: {thread.assigned_to || "Belum dibagikan"}</p></button>)}{!threads.data.length && <p className="p-8 text-center text-ink-soft">Belum ada percakapan.</p>}</div></section>
                <section className="flex min-h-[520px] flex-col rounded-2xl border bg-white">{selected ? <><div className="border-b p-5"><p className="text-xs font-black uppercase tracking-widest text-gold-deep">{channelLabel[selected.channel] || selected.channel}</p><h2 className="mt-1 text-2xl font-black">{selected.contact_name || "Kontak"}</h2><p className="text-sm text-ink-soft">{selected.contact_address || "Alamat belum tersedia"}</p></div><div className="flex-1 space-y-3 overflow-y-auto p-5">{selected.messages.map((message) => <div key={message.id} className={`max-w-[80%] rounded-2xl p-3 text-sm ${message.direction === "keluar" ? "ml-auto bg-ink text-white" : "bg-slate-100 text-ink"}`}><p className="whitespace-pre-wrap">{message.body || "(pesan tanpa teks)"}</p><p className="mt-2 text-[11px] opacity-70">{message.at} · {message.status}</p></div>)}{!selected.messages.length && <p className="text-center text-ink-soft">Belum ada pesan.</p>}</div><form onSubmit={send} className="border-t p-4"><textarea value={form.data.body} onChange={(event) => form.setData("body", event.target.value)} placeholder="Tulis pesan..." className="min-h-24 w-full rounded-lg border p-3" required /><div className="mt-3 flex justify-end"><button disabled={form.processing} className="inline-flex items-center gap-2 rounded-lg bg-ink px-4 py-2 font-bold text-white"><Send size={16} /> Masukkan ke antrean</button></div></form></> : <div className="grid flex-1 place-items-center p-8 text-center text-ink-soft"><MessageCircle className="mx-auto mb-3" size={42} /><p>Pilih percakapan untuk melihat pesan.</p></div>}</section>
            </div>
        </div>
    </>;
}
Index.layout = (page) => <AdminLayout title={page?.props?.title ?? "Inbox Komunikasi CRM"}>{page}</AdminLayout>;
