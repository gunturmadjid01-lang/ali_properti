import { Head, Link } from "@inertiajs/react";
import {
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    CheckCircle2,
    MapPin,
    Phone,
    Play,
    Sparkles,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import SectionTitle from "../../Components/Guest/SectionTitle";
import { assets, highlights, units } from "../../Data/site";
import GuestLayout from "../../Layouts/GuestLayout";

function GoldSection({ children, className = "", ...props }) {
    return (
        <section
            className={`relative overflow-hidden py-14 md:py-16 before:absolute before:inset-0 before:-z-10 before:bg-[radial-gradient(circle_at_14%_12%,rgba(255,241,191,0.55),transparent_24rem),radial-gradient(circle_at_88%_22%,rgba(216,186,114,0.18),transparent_26rem),linear-gradient(115deg,transparent_0_68%,rgba(216,186,114,0.10)_68%_68.35%,transparent_68.35%_100%)] ${className}`}
            {...props}
        >
            {children}
        </section>
    );
}

function Reveal({ children, delay = 0, className = "" }) {
    const ref = useRef(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const node = ref.current;

        if (!node) {
            return undefined;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    observer.unobserve(entry.target);
                }
            },
            { rootMargin: "0px 0px -10% 0px", threshold: 0.12 },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    return (
        <div
            ref={ref}
            className={`transition duration-700 ease-out will-change-transform ${
                visible
                    ? "translate-y-0 opacity-100 blur-0"
                    : "translate-y-8 opacity-0 blur-[2px]"
            } ${className}`}
            style={{ transitionDelay: visible ? `${delay}ms` : "0ms" }}
        >
            {children}
        </div>
    );
}

function Hero() {
    return (
        <section className="relative isolate min-h-[calc(100vh-80px)] overflow-hidden bg-graphite text-white">
            <img
                className="absolute inset-0 -z-20 h-full w-full animate-soft-zoom object-cover"
                src={assets.hero}
                alt="Fasad rumah Sidratul Muntaha"
            />
            <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_20%_32%,rgba(216,186,114,0.32),transparent_24rem),linear-gradient(90deg,rgba(15,17,19,0.9),rgba(20,22,25,0.62)_52%,rgba(20,22,25,0.22)),linear-gradient(0deg,rgba(238,241,244,0.95),transparent_23%)]" />

            <div className="mx-auto flex min-h-[calc(100vh-80px)] w-[min(1180px,calc(100%-32px))] items-center py-20">
                <div className="max-w-4xl">
                    <span className="inline-flex min-h-9 animate-fade-up items-center gap-2 rounded-full border border-champagne/45 bg-black/25 px-4 text-xs font-extrabold uppercase tracking-[0.14em] text-champagne">
                        <Sparkles size={15} /> Perumahan Sidratul Muntaha
                    </span>
                    <h1 className="mt-6 animate-fade-up font-display text-4xl font-extrabold leading-[1.02] [animation-delay:120ms] md:text-6xl lg:text-7xl">
                        Hunian keluarga dengan tampilan elegan dan lingkungan
                        tertata.
                    </h1>
                    <p className="mt-6 max-w-2xl animate-fade-up text-lg leading-8 text-white/82 [animation-delay:220ms] md:text-xl">
                        Sidratul Muntaha menghadirkan rumah tapak bernuansa
                        modern untuk keluarga yang ingin tinggal nyaman,
                        memiliki aset bernilai, dan mudah menjangkau kebutuhan
                        harian.
                    </p>
                    <div className="mt-9 flex animate-fade-up flex-wrap gap-3 [animation-delay:320ms]">
                        <Link
                            className="inline-flex min-h-12 items-center gap-2 rounded-lg bg-gradient-to-br from-champagne via-gold to-gold-deep px-6 font-extrabold text-[#241a08] shadow-gold transition hover:-translate-y-0.5"
                            href="/kontak"
                        >
                            Jadwalkan Survei <ArrowRight size={18} />
                        </Link>
                        <a
                            className="inline-flex min-h-12 items-center gap-2 rounded-lg border border-white/20 bg-white/85 px-6 font-extrabold text-ink transition hover:bg-white"
                            href="#video-promo"
                        >
                            <Play size={18} /> Lihat Video Rumah
                        </a>
                    </div>
                    <div className="mt-12 grid max-w-4xl animate-fade-up gap-3 [animation-delay:420ms] sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            ["9+", "Foto rumah asli"],
                            ["8", "Video promosi"],
                            ["3", "Brosur resmi"],
                            ["1", "Site plan kawasan"],
                        ].map(([value, label]) => (
                            <div
                                className="rounded-lg border border-white/20 bg-white/12 p-5 shadow-[0_18px_46px_rgba(0,0,0,0.10)] backdrop-blur"
                                key={label}
                            >
                                <strong className="block text-3xl text-champagne">
                                    {value}
                                </strong>
                                <span className="text-sm font-bold text-white/78">
                                    {label}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}

function VisualSlider() {
    const slides = [
        [
            "Fasad Rumah",
            "Tampilan depan rumah yang bersih dan mudah menarik perhatian calon pembeli.",
            assets.house[0],
        ],
        [
            "Kondisi Unit",
            "Foto unit asli membantu pengunjung melihat suasana rumah secara lebih jujur.",
            assets.house[3],
        ],
        [
            "Visual 3D",
            "Materi visual 3D membantu menjelaskan rencana bentuk dan penataan hunian.",
            assets.plans[2],
        ],
        [
            "Site Plan",
            "Site plan menjadi acuan penting saat calon pembeli memilih posisi unit.",
            assets.plans[0],
        ],
    ];
    const [active, setActive] = useState(0);

    useEffect(() => {
        const timer = window.setInterval(
            () => setActive((current) => (current + 1) % slides.length),
            5200,
        );
        return () => window.clearInterval(timer);
    }, [slides.length]);

    const move = (step) =>
        setActive(
            (current) => (current + step + slides.length) % slides.length,
        );

    return (
        <GoldSection>
            <Reveal className="mx-auto grid w-[min(1180px,calc(100%-32px))] gap-5 rounded-lg border border-white/80 bg-white/75 p-3 shadow-soft backdrop-blur lg:grid-cols-[0.72fr_1.28fr] lg:p-4">
                <div className="flex flex-col justify-center p-5 lg:p-7">
                    <span className="inline-flex min-h-9 w-fit items-center gap-2 rounded-full border border-gold/30 bg-champagne/60 px-4 text-xs font-extrabold uppercase tracking-[0.14em] text-gold-deep">
                        <Sparkles size={15} /> Sorotan Utama
                    </span>
                    <h2 className="mt-5 font-display text-3xl font-extrabold leading-[1.08] md:text-4xl">
                        Etalase visual rumah, denah, dan site plan.
                    </h2>
                    <p className="mt-4 text-sm leading-7 text-ink-soft md:text-base">
                        Materi utama ini membangun kepercayaan calon pembeli
                        lewat foto rumah asli, visual 3D, dan rencana kawasan.
                    </p>
                    <div className="mt-8 flex gap-3">
                        <button
                            className="grid h-12 w-12 place-items-center rounded-lg border border-gold/35 bg-white text-gold-deep shadow-[0_10px_22px_rgba(31,37,43,0.06)]"
                            type="button"
                            onClick={() => move(-1)}
                            aria-label="Slide sebelumnya"
                        >
                            <ChevronLeft size={20} />
                        </button>
                        <button
                            className="grid h-12 w-12 place-items-center rounded-lg border border-gold/35 bg-white text-gold-deep shadow-[0_10px_22px_rgba(31,37,43,0.06)]"
                            type="button"
                            onClick={() => move(1)}
                            aria-label="Slide berikutnya"
                        >
                            <ChevronRight size={20} />
                        </button>
                    </div>
                </div>
                <div className="relative min-h-[300px] overflow-hidden rounded-lg bg-graphite md:min-h-[460px]">
                    <div
                        className="flex h-full transition-transform duration-700 ease-out"
                        style={{ transform: `translateX(-${active * 100}%)` }}
                    >
                        {slides.map(([label, title, image]) => (
                            <figure
                                className="relative m-0 min-w-full overflow-hidden"
                                key={label}
                            >
                                <img
                                    className="h-full min-h-[300px] w-full object-cover md:min-h-[460px]"
                                    src={image}
                                    alt={label}
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-graphite/80 to-transparent" />
                                <figcaption className="absolute inset-x-6 bottom-6 text-white">
                                    <span className="font-extrabold text-champagne">
                                        {label}
                                    </span>
                                    <strong className="mt-2 block max-w-2xl font-display text-2xl leading-tight md:text-3xl">
                                        {title}
                                    </strong>
                                </figcaption>
                            </figure>
                        ))}
                    </div>
                    <div className="absolute right-5 top-5 flex gap-2">
                        {slides.map(([label], index) => (
                            <button
                                className={`h-1.5 rounded-full transition ${active === index ? "w-9 bg-champagne" : "w-6 bg-white/45"}`}
                                type="button"
                                onClick={() => setActive(index)}
                                aria-label={`Slide ${index + 1}`}
                                key={label}
                            />
                        ))}
                    </div>
                </div>
            </Reveal>
        </GoldSection>
    );
}

function HighlightSection() {
    return (
        <GoldSection className="bg-white/40">
            <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                <SectionTitle
                    eyebrow="Keunggulan Kawasan"
                    title="Materi pemasaran yang menampilkan nilai rumah secara lebih meyakinkan."
                >
                    Sorotan ini menampilkan kualitas unit, suasana kawasan, dan
                    informasi penting sebelum survei lokasi.
                </SectionTitle>
                <div className="grid gap-5 md:grid-cols-3">
                    {highlights.map(({ title, desc, icon: Icon }, index) => (
                        <Reveal delay={index * 100} key={title}>
                            <article className="group relative flex min-h-56 flex-col justify-between overflow-hidden rounded-lg border border-white/80 bg-white/72 p-6 shadow-soft backdrop-blur transition hover:-translate-y-1 hover:border-gold/40 hover:shadow-gold">
                                <div className="flex items-center justify-between">
                                    <span className="grid h-12 w-12 place-items-center rounded-lg bg-gradient-to-br from-champagne to-gold text-gold-deep">
                                        <Icon size={22} />
                                    </span>
                                    <span className="font-display text-3xl font-extrabold text-gold/35">
                                        {String(index + 1).padStart(2, "0")}
                                    </span>
                                </div>
                                <div>
                                    <h3 className="font-display text-xl font-extrabold">
                                        {title}
                                    </h3>
                                    <p className="mt-3 text-sm leading-7 text-ink-soft">
                                        {desc}
                                    </p>
                                </div>
                            </article>
                        </Reveal>
                    ))}
                </div>
            </div>
        </GoldSection>
    );
}

function GalleryPreview() {
    return (
        <GoldSection>
            <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                <SectionTitle
                    eyebrow="Galeri Rumah"
                    title="Foto rumah asli sebagai bukti visual untuk calon pembeli."
                >
                    Galeri luas memudahkan penilaian fasad, progres, dan
                    karakter unit secara nyaman.
                </SectionTitle>
                <div className="grid auto-rows-[230px] gap-4 md:grid-cols-2 lg:grid-cols-[1.2fr_0.8fr_0.8fr]">
                    {assets.house.slice(0, 5).map((src, index) => (
                        <Reveal
                            className={index === 0 ? "lg:row-span-2" : ""}
                            delay={index * 80}
                            key={src}
                        >
                            <figure className="group relative m-0 h-full overflow-hidden rounded-lg bg-silver-deep shadow-soft">
                                <img
                                    className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                    src={src}
                                    alt={`Foto rumah Sidratul Muntaha ${index + 1}`}
                                    loading="lazy"
                                    decoding="async"
                                />
                                <figcaption className="absolute inset-x-4 bottom-4 rounded-lg bg-graphite/55 p-3 font-extrabold text-white backdrop-blur">
                                    Foto Rumah {index + 1}
                                </figcaption>
                            </figure>
                        </Reveal>
                    ))}
                </div>
            </div>
        </GoldSection>
    );
}

function VideoPromo() {
    const [activeVideo, setActiveVideo] = useState(0);

    return (
        <GoldSection className="bg-white/35" id="video-promo">
            <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                <SectionTitle
                    eyebrow="Video Promosi"
                    title="Satu video utama besar, pilihan video lain tersedia di bawahnya."
                >
                    Video promosi menampilkan rumah dan perkembangan kawasan
                    secara detail.
                </SectionTitle>
                <div className="grid gap-5">
                    <Reveal className="mx-auto w-[min(980px,100%)]">
                        <div className="overflow-hidden rounded-lg border border-champagne/60 bg-graphite shadow-[0_24px_62px_rgba(31,37,43,0.12),0_0_0_8px_rgba(255,241,191,0.14)]">
                            <video
                                key={assets.videos[activeVideo]}
                                className="aspect-video w-full object-cover"
                                src={assets.videos[activeVideo]}
                                controls
                                autoPlay
                                muted
                                loop
                                playsInline
                                preload="metadata"
                                poster={
                                    assets.house[
                                        (activeVideo + 2) % assets.house.length
                                    ]
                                }
                            />
                        </div>
                    </Reveal>
                    <div className="mx-auto grid w-[min(980px,100%)] gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {assets.videos.map((src, index) => (
                            <Reveal delay={index * 55} key={src}>
                                <button
                                    className={`group relative overflow-hidden rounded-lg border bg-white/70 shadow-soft transition hover:-translate-y-0.5 hover:border-gold/70 hover:shadow-gold ${
                                        activeVideo === index
                                            ? "border-gold ring-4 ring-champagne/70"
                                            : "border-white/80"
                                    }`}
                                    type="button"
                                    onClick={() => setActiveVideo(index)}
                                >
                                    <img
                                        className="aspect-video w-full object-cover transition duration-500 group-hover:scale-105"
                                        src={
                                            assets.house[
                                                (index + 3) %
                                                    assets.house.length
                                            ]
                                        }
                                        alt={`Thumbnail video promosi ${index + 1}`}
                                        loading="lazy"
                                        decoding="async"
                                    />
                                    <span className="absolute bottom-3 left-3 rounded-full bg-champagne/90 px-3 py-1 text-xs font-extrabold text-ink">
                                        Video {index + 1}
                                    </span>
                                </button>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </div>
        </GoldSection>
    );
}

function UnitSection() {
    return (
        <GoldSection>
            <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                <SectionTitle
                    eyebrow="Pilihan Unit"
                    title="Rumah tapak untuk keluarga dan aset jangka panjang."
                >
                    Informasi unit tersaji ringkas sehingga perbandingan
                    karakter setiap pilihan rumah lebih cepat.
                </SectionTitle>
                <div className="grid gap-5 lg:grid-cols-[1.14fr_0.86fr]">
                    {units.map((unit, index) => (
                        <Reveal
                            className={index === 0 ? "lg:row-span-2" : ""}
                            delay={index * 100}
                            key={unit.name}
                        >
                            <article
                                className={`h-full overflow-hidden rounded-lg border border-white/80 bg-white/76 shadow-soft backdrop-blur transition hover:-translate-y-1 hover:shadow-gold ${index === 0 ? "" : "lg:grid lg:grid-cols-[0.95fr_1.05fr]"}`}
                            >
                                <img
                                    className={`w-full object-cover ${index === 0 ? "aspect-video" : "h-full min-h-64"}`}
                                    src={unit.image}
                                    alt={unit.name}
                                    loading="lazy"
                                    decoding="async"
                                />
                                <div className="flex flex-col justify-center p-7">
                                    <h3 className="font-display text-2xl font-extrabold">
                                        {unit.name}
                                    </h3>
                                    <p className="mt-3 leading-7 text-ink-soft">
                                        {unit.desc}
                                    </p>
                                    <ul className="mt-5 grid gap-3 text-sm font-extrabold text-ink/75">
                                        {unit.specs.map((spec) => (
                                            <li
                                                className="flex items-center gap-2"
                                                key={spec}
                                            >
                                                <CheckCircle2
                                                    size={17}
                                                    className="text-gold-deep"
                                                />{" "}
                                                {spec}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </article>
                        </Reveal>
                    ))}
                </div>
            </div>
        </GoldSection>
    );
}

function BrochureSection() {
    return (
        <GoldSection className="bg-white/35">
            <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                <SectionTitle
                    eyebrow="Brosur Marketing"
                    title="Brosur resmi Sidratul Muntaha untuk materi promosi."
                >
                    Brosur ditata terpisah agar tampil sebagai materi promosi
                    yang lebih profesional.
                </SectionTitle>
                <div className="grid gap-5 lg:grid-cols-[1.05fr_0.95fr]">
                    <Reveal>
                        <a
                            className="block overflow-hidden rounded-lg border border-white/80 bg-white shadow-soft transition hover:-translate-y-0.5 hover:shadow-gold"
                            href={assets.brochures[0]}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <img
                                className="aspect-[4/3] w-full object-cover"
                                src={assets.brochures[0]}
                                alt="Brosur utama Sidratul Muntaha"
                                loading="lazy"
                                decoding="async"
                            />
                        </a>
                    </Reveal>
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-1">
                        {assets.brochures.slice(1).map((src, index) => (
                            <Reveal delay={(index + 1) * 100} key={src}>
                                <a
                                    className="block overflow-hidden rounded-lg border border-white/80 bg-white shadow-soft transition hover:-translate-y-0.5 hover:shadow-gold"
                                    href={src}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <img
                                        className="aspect-[3/4] w-full object-cover object-top"
                                        src={src}
                                        alt={`Brosur portrait Sidratul Muntaha ${index + 1}`}
                                        loading="lazy"
                                        decoding="async"
                                    />
                                </a>
                            </Reveal>
                        ))}
                    </div>
                </div>
            </div>
        </GoldSection>
    );
}

function PlanSection() {
    return (
        <GoldSection>
            <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                <SectionTitle
                    eyebrow="Denah & Site Plan"
                    title="Denah unit dan site plan dibuat terpisah agar mudah dibaca."
                >
                    Denah dan site plan ditampilkan lebar sehingga ukuran,
                    posisi, dan rencana kawasan lebih mudah dipahami.
                </SectionTitle>
                <div className="grid gap-5">
                    <Reveal>
                        <a
                            className="block overflow-hidden rounded-lg border border-white/80 bg-white p-4 shadow-soft transition hover:shadow-gold md:p-6"
                            href={assets.plans[0]}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <img
                                className="max-h-[640px] w-full object-contain"
                                src={assets.plans[0]}
                                alt="Site plan Sidratul Muntaha"
                                loading="lazy"
                                decoding="async"
                            />
                        </a>
                    </Reveal>
                    <div className="grid gap-5 md:grid-cols-2">
                        <Reveal>
                            <a
                                className="block overflow-hidden rounded-lg border border-white/80 bg-white p-4 shadow-soft transition hover:shadow-gold md:p-5"
                                href={assets.plans[1]}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <img
                                    className="h-[420px] w-full object-contain"
                                    src={assets.plans[1]}
                                    alt="Denah perumahan Sidratul Muntaha"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </a>
                        </Reveal>
                        <Reveal delay={100}>
                            <a
                                className="block overflow-hidden rounded-lg border border-white/80 bg-white p-4 shadow-soft transition hover:shadow-gold md:p-5"
                                href={assets.plans[2]}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <img
                                    className="h-[420px] w-full object-contain"
                                    src={assets.plans[2]}
                                    alt="Visual 3D denah Sidratul Muntaha"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </a>
                        </Reveal>
                    </div>
                </div>
            </div>
        </GoldSection>
    );
}

function ContactCta() {
    return (
        <GoldSection>
            <Reveal className="mx-auto grid w-[min(1180px,calc(100%-32px))] gap-7 lg:grid-cols-[0.9fr_1.1fr]">
                <div>
                    <SectionTitle
                        eyebrow="Konsultasi Pembelian"
                        title="Siapkan jadwal survei ke Sidratul Muntaha."
                    />
                    <p className="max-w-xl leading-8 text-ink-soft">
                        Tim marketing siap menjelaskan pilihan unit, brosur,
                        site plan, dan jadwal kunjungan.
                    </p>
                    <ul className="mt-6 grid gap-3 font-extrabold text-ink/75">
                        <li className="flex items-center gap-2">
                            <Phone size={18} className="text-gold-deep" />{" "}
                            Konsultasi marketing
                        </li>
                        <li className="flex items-center gap-2">
                            <MapPin size={18} className="text-gold-deep" />{" "}
                            Jadwal survei lokasi
                        </li>
                    </ul>
                </div>
                <div className="rounded-lg border border-white/80 bg-white/75 p-7 shadow-soft backdrop-blur">
                    <h3 className="font-display text-3xl font-extrabold">
                        Mau lihat unit secara langsung?
                    </h3>
                    <p className="mt-3 leading-8 text-ink-soft">
                        Hubungi marketing untuk mendapatkan arahan unit yang
                        sesuai dengan kebutuhan keluarga.
                    </p>
                    <Link
                        className="mt-6 inline-flex min-h-12 items-center gap-2 rounded-lg bg-gradient-to-br from-champagne via-gold to-gold-deep px-6 font-extrabold text-[#241a08] shadow-gold"
                        href="/kontak"
                    >
                        Buka Kontak <ArrowRight size={18} />
                    </Link>
                </div>
            </Reveal>
        </GoldSection>
    );
}

function Home() {
    return (
        <>
            <Head title="Perumahan Elegan" />
            <Hero />
            <VisualSlider />
            <HighlightSection />
            <GalleryPreview />
            <VideoPromo />
            <UnitSection />
            <BrochureSection />
            <PlanSection />
            <ContactCta />
        </>
    );
}

Home.layout = (page) => <GuestLayout>{page}</GuestLayout>;

export default Home;
