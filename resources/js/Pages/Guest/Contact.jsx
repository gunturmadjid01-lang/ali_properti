import { Head } from "@inertiajs/react";
import { ArrowRight, CheckCircle2, Phone } from "lucide-react";
import GuestLayout from "../../Layouts/GuestLayout";

function Contact() {
    return (
        <>
            <Head title="Kontak Marketing" />
            <section className="py-20">
                <div className="mx-auto grid w-[min(1180px,calc(100%-32px))] gap-7 lg:grid-cols-[0.9fr_1.1fr]">
                    <div>
                        <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-gold-deep">
                            Kontak Marketing
                        </p>
                        <h1 className="mt-4 font-display text-5xl font-extrabold leading-tight">
                            Tanyakan unit Sidratul Muntaha dan jadwalkan survei.
                        </h1>
                        <p className="mt-5 leading-8 text-ink-soft">
                            Kontak ini menyediakan akses brosur digital,
                            marketing, dan informasi ketersediaan unit.
                        </p>
                        <ul className="mt-6 grid gap-3 font-extrabold text-ink/75">
                            {[
                                "Tanya harga dan promo",
                                "Minta brosur digital",
                                "Atur jadwal survei lokasi",
                            ].map((item) => (
                                <li
                                    className="flex items-center gap-2"
                                    key={item}
                                >
                                    <CheckCircle2
                                        size={18}
                                        className="text-gold-deep"
                                    />{" "}
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <form className="grid gap-4 rounded-lg border border-white/80 bg-white/75 p-7 shadow-soft">
                        <label className="grid gap-2 font-extrabold text-ink/75">
                            Nama Lengkap
                            <input
                                className="rounded-lg border border-silver-deep/70 bg-white px-4 py-3 font-semibold outline-none focus:border-gold"
                                type="text"
                                placeholder="Nama calon pembeli"
                            />
                        </label>
                        <label className="grid gap-2 font-extrabold text-ink/75">
                            Nomor WhatsApp
                            <input
                                className="rounded-lg border border-silver-deep/70 bg-white px-4 py-3 font-semibold outline-none focus:border-gold"
                                type="tel"
                                placeholder="08xxxxxxxxxx"
                            />
                        </label>
                        <label className="grid gap-2 font-extrabold text-ink/75">
                            Kebutuhan
                            <textarea
                                className="min-h-36 rounded-lg border border-silver-deep/70 bg-white px-4 py-3 font-semibold outline-none focus:border-gold"
                                placeholder="Saya ingin bertanya tentang unit Sidratul Muntaha..."
                            />
                        </label>
                        <button
                            className="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-gradient-to-br from-champagne via-gold to-gold-deep px-6 font-extrabold text-[#241a08] shadow-gold"
                            type="button"
                        >
                            Kirim Minat <ArrowRight size={18} />
                        </button>
                        <p className="flex items-center gap-2 text-sm font-bold text-ink-soft">
                            <Phone size={16} /> Nomor marketing resmi akan
                            tersedia setelah data final terverifikasi.
                        </p>
                    </form>
                </div>
            </section>
        </>
    );
}

Contact.layout = (page) => <GuestLayout>{page}</GuestLayout>;

export default Contact;
