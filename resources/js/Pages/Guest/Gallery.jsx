import { Head } from "@inertiajs/react";
import { useState } from "react";
import SectionTitle from "../../Components/Guest/SectionTitle";
import { assets } from "../../Data/site";
import GuestLayout from "../../Layouts/GuestLayout";

function Gallery() {
    const [activeVideo, setActiveVideo] = useState(0);

    return (
        <>
            <Head title="Galeri Rumah dan Video" />
            <section className="py-20">
                <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                    <SectionTitle
                        eyebrow="Galeri Rumah"
                        title="Galeri lengkap rumah, brosur, denah, site plan, dan video promosi."
                    />
                    <div className="grid auto-rows-[230px] gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {assets.house.map((src, index) => (
                            <figure
                                className={`relative m-0 overflow-hidden rounded-lg shadow-soft ${index === 0 ? "lg:row-span-2" : ""}`}
                                key={src}
                            >
                                <img
                                    className="h-full w-full object-cover"
                                    src={src}
                                    alt={`Foto rumah Sidratul Muntaha ${index + 1}`}
                                />
                                <figcaption className="absolute inset-x-4 bottom-4 rounded-lg bg-graphite/55 p-3 font-extrabold text-white backdrop-blur">
                                    Foto Rumah {index + 1}
                                </figcaption>
                            </figure>
                        ))}
                    </div>
                </div>
            </section>
            <section className="py-20">
                <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                    <SectionTitle
                        eyebrow="Video Promosi"
                        title="Video promosi tersusun untuk ditonton secara penuh."
                    />
                    <div className="mx-auto w-[min(980px,100%)] overflow-hidden rounded-lg bg-graphite shadow-gold">
                        <video
                            className="aspect-video w-full object-cover"
                            key={assets.videos[activeVideo]}
                            src={assets.videos[activeVideo]}
                            controls
                            autoPlay
                            muted
                            loop
                            playsInline
                        />
                    </div>
                    <div className="mx-auto mt-5 grid w-[min(980px,100%)] gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {assets.videos.map((src, index) => (
                            <button
                                className={`relative overflow-hidden rounded-lg border ${activeVideo === index ? "border-gold ring-4 ring-champagne/70" : "border-white/80"}`}
                                type="button"
                                onClick={() => setActiveVideo(index)}
                                key={src}
                            >
                                <video
                                    className="aspect-video w-full object-cover"
                                    src={src}
                                    muted
                                    playsInline
                                    preload="metadata"
                                />
                                <span className="absolute bottom-3 left-3 rounded-full bg-champagne/90 px-3 py-1 text-xs font-extrabold">
                                    Video {index + 1}
                                </span>
                            </button>
                        ))}
                    </div>
                </div>
            </section>
            <section className="py-20">
                <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                    <SectionTitle
                        eyebrow="Brosur Marketing"
                        title="Brosur dipisahkan dari denah agar tampil sebagai materi promosi."
                    />
                    <div className="grid gap-5 lg:grid-cols-[1.05fr_0.95fr]">
                        <a
                            className="overflow-hidden rounded-lg border border-white/80 bg-white shadow-soft"
                            href={assets.brochures[0]}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <img
                                className="aspect-[4/3] w-full object-cover"
                                src={assets.brochures[0]}
                                alt="Brosur utama Sidratul Muntaha"
                            />
                        </a>
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-1">
                            {assets.brochures.slice(1).map((src, index) => (
                                <a
                                    className="overflow-hidden rounded-lg border border-white/80 bg-white shadow-soft"
                                    href={src}
                                    target="_blank"
                                    rel="noreferrer"
                                    key={src}
                                >
                                    <img
                                        className="aspect-[3/4] w-full object-cover object-top"
                                        src={src}
                                        alt={`Brosur portrait Sidratul Muntaha ${index + 1}`}
                                    />
                                </a>
                            ))}
                        </div>
                    </div>
                </div>
            </section>
            <section className="py-20">
                <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                    <SectionTitle
                        eyebrow="Denah & Site Plan"
                        title="Denah dan site plan tampil lebar agar detailnya mudah dibaca."
                    />
                    <div className="grid gap-5">
                        <a
                            className="overflow-hidden rounded-lg border border-white/80 bg-white p-4 shadow-soft md:p-6"
                            href={assets.plans[0]}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <img
                                className="max-h-[640px] w-full object-contain"
                                src={assets.plans[0]}
                                alt="Site plan Sidratul Muntaha"
                            />
                        </a>
                        <div className="grid gap-5 md:grid-cols-2">
                            {assets.plans.slice(1).map((src, index) => (
                                <a
                                    className="overflow-hidden rounded-lg border border-white/80 bg-white p-4 shadow-soft md:p-5"
                                    href={src}
                                    target="_blank"
                                    rel="noreferrer"
                                    key={src}
                                >
                                    <img
                                        className="h-[420px] w-full object-contain"
                                        src={src}
                                        alt={`Denah Sidratul Muntaha ${index + 1}`}
                                    />
                                </a>
                            ))}
                        </div>
                    </div>
                </div>
            </section>
        </>
    );
}

Gallery.layout = (page) => <GuestLayout>{page}</GuestLayout>;

export default Gallery;
