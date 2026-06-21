import { Head } from "@inertiajs/react";
import { CheckCircle2 } from "lucide-react";
import SectionTitle from "../../Components/Guest/SectionTitle";
import { specs, units } from "../../Data/site";
import GuestLayout from "../../Layouts/GuestLayout";

function Units() {
    return (
        <>
            <Head title="Tipe Rumah" />
            <section className="py-20">
                <div className="mx-auto w-[min(1180px,calc(100%-32px))]">
                    <SectionTitle
                        eyebrow="Tipe Rumah"
                        title="Pilihan rumah Sidratul Muntaha untuk keluarga dan investasi."
                    >
                        Detail unit dirangkum untuk memudahkan perbandingan
                        kebutuhan ruang, fungsi, dan nilai aset.
                    </SectionTitle>
                    <div className="grid gap-5 lg:grid-cols-3">
                        {units.map((unit) => (
                            <article
                                className="overflow-hidden rounded-lg border border-white/80 bg-white/75 shadow-soft"
                                key={unit.name}
                            >
                                <img
                                    className="aspect-[16/10] w-full object-cover"
                                    src={unit.image}
                                    alt={unit.name}
                                />
                                <div className="p-7">
                                    <h2 className="font-display text-2xl font-extrabold">
                                        {unit.name}
                                    </h2>
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
                        ))}
                    </div>
                </div>
            </section>
            <section className="py-20">
                <div className="mx-auto grid w-[min(1180px,calc(100%-32px))] gap-5 md:grid-cols-2 lg:grid-cols-3">
                    {specs.map(([title, desc, Icon]) => (
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
            </section>
        </>
    );
}

Units.layout = (page) => <GuestLayout>{page}</GuestLayout>;

export default Units;
