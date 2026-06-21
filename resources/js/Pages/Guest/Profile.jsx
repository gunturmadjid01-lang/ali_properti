import { Head } from "@inertiajs/react";
import { Building2, Trees } from "lucide-react";
import SectionTitle from "../../Components/Guest/SectionTitle";
import { assets, highlights } from "../../Data/site";
import GuestLayout from "../../Layouts/GuestLayout";

function Profile() {
    return (
        <>
            <Head title="Profil Perumahan" />
            <section className="relative overflow-hidden bg-graphite py-24 text-white">
                <img
                    className="absolute inset-0 h-full w-full object-cover opacity-45"
                    src={assets.hero}
                    alt="Profil Sidratul Muntaha"
                />
                <div className="absolute inset-0 bg-gradient-to-r from-graphite via-graphite/72 to-transparent" />
                <div className="relative mx-auto w-[min(1180px,calc(100%-32px))]">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-champagne">
                        Profil Kawasan
                    </p>
                    <h1 className="mt-4 max-w-4xl font-display text-5xl font-extrabold leading-tight md:text-7xl">
                        Sidratul Muntaha, hunian keluarga dari PT Ali Properti
                        Indonesia.
                    </h1>
                    <p className="mt-6 max-w-2xl text-lg leading-8 text-white/78">
                        Profil perumahan ini menampilkan foto rumah, brosur,
                        denah, site plan, dan video promosi agar pembeli dapat
                        mengenal unit sebelum survei.
                    </p>
                </div>
            </section>
            <section className="py-20">
                <div className="mx-auto grid w-[min(1180px,calc(100%-32px))] gap-5 md:grid-cols-2">
                    {[
                        [
                            "Konsep Perumahan",
                            "Sidratul Muntaha dipresentasikan sebagai rumah tapak bernuansa modern dengan visual elegan silver dan champagne gold.",
                            Building2,
                        ],
                        [
                            "Lingkungan Hunian",
                            "Galeri rumah dan site plan membantu pembeli memahami suasana kawasan serta posisi unit yang tersedia.",
                            Trees,
                        ],
                    ].map(([title, desc, Icon]) => (
                        <article
                            className="rounded-lg border border-white/80 bg-white/75 p-7 shadow-soft"
                            key={title}
                        >
                            <span className="grid h-12 w-12 place-items-center rounded-lg bg-gradient-to-br from-champagne to-gold text-gold-deep">
                                <Icon size={22} />
                            </span>
                            <h2 className="mt-5 font-display text-3xl font-extrabold">
                                {title}
                            </h2>
                            <p className="mt-3 leading-8 text-ink-soft">
                                {desc}
                            </p>
                        </article>
                    ))}
                </div>
            </section>
            <section className="py-20">
                <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                    <SectionTitle
                        eyebrow="Nilai Utama"
                        title="Alasan Sidratul Muntaha layak masuk daftar survei calon pembeli."
                    />
                    <div className="grid gap-5 md:grid-cols-3">
                        {highlights.map(({ title, desc, icon: Icon }) => (
                            <article
                                className="rounded-lg border border-white/80 bg-white/75 p-7 shadow-soft"
                                key={title}
                            >
                                <Icon className="text-gold-deep" size={24} />
                                <h3 className="mt-4 font-display text-2xl font-extrabold">
                                    {title}
                                </h3>
                                <p className="mt-3 leading-7 text-ink-soft">
                                    {desc}
                                </p>
                            </article>
                        ))}
                    </div>
                </div>
            </section>
        </>
    );
}

Profile.layout = (page) => <GuestLayout>{page}</GuestLayout>;

export default Profile;
