import { Link } from "@inertiajs/react";
import { ShieldCheck, Sparkles } from "lucide-react";
import { assets } from "../Data/site";

export default function AuthLayout({ children, title = "Login" }) {
    return (
        <div className="auth-theme-scope relative min-h-screen overflow-hidden bg-[radial-gradient(circle_at_18%_16%,rgba(255,241,191,0.55),transparent_24rem),radial-gradient(circle_at_82%_22%,rgba(216,186,114,0.18),transparent_28rem),linear-gradient(135deg,#eff3f7_0%,#dde4ea_56%,#f7efe0_100%)] text-ink">
            <div className="absolute inset-0 -z-10 bg-[linear-gradient(115deg,transparent_0_68%,rgba(31,37,43,0.04)_68%_68.35%,transparent_68.35%_100%)]" />

            <div className="mx-auto grid min-h-screen w-[min(1180px,calc(100%-32px))] items-center gap-8 py-8 lg:grid-cols-[0.92fr_1.08fr] lg:py-12">
                <section className="relative overflow-hidden rounded-[28px] border border-graphite bg-[linear-gradient(180deg,rgba(16,21,27,0.96),rgba(26,33,40,0.94))] p-8 text-white">
                    <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_18%_16%,rgba(216,186,114,0.34),transparent_18rem),radial-gradient(circle_at_82%_20%,rgba(255,255,255,0.10),transparent_14rem)]" />
                    <Link href="/" className="inline-flex items-center gap-3">
                        <img
                            className="h-14 w-14 rounded-2xl border border-champagne/60 object-cover"
                            src={assets.logo}
                            alt="Logo Sidratul Muntaha"
                        />
                        <span className="font-display text-2xl font-extrabold leading-none">
                            Sidratul Muntaha
                            <small className="mt-1 block font-sans text-xs font-bold tracking-[0.16em] text-champagne/90 uppercase">
                                PT Ali Properti Indonesia
                            </small>
                        </span>
                    </Link>

                    <div className="mt-14 max-w-xl">
                        <span className="inline-flex min-h-9 items-center gap-2 rounded-full border border-champagne/30 bg-white/6 px-4 text-xs font-extrabold uppercase tracking-[0.16em] text-champagne">
                            <Sparkles size={15} /> Akses Internal Properti
                        </span>
                    </div>

                    <div className="mt-10 grid gap-3 sm:grid-cols-2">
                        {[
                            "Owner",
                            "Manajer / Pimpro",
                            "Keuangan",
                            "Marketing",
                            "Legal",
                            "Gudang / Logistik",
                        ].map((item) => (
                            <div
                                className="rounded-2xl border border-white/10 bg-white/6 px-4 py-3 text-sm font-bold text-white/80 backdrop-blur"
                                key={item}
                            >
                                {item}
                            </div>
                        ))}
                    </div>
                </section>

                <section className="flex justify-center">
                    <div className="w-full max-w-[520px] rounded-[28px] border border-ink/70 bg-white/88 p-6 backdrop-blur-xl md:p-8">
                        <div className="mb-6">
                            <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-ink-soft">
                                {title}
                            </p>
                            <h2 className="mt-2 text-2xl font-extrabold tracking-tight">
                                Masuk ke sistem
                            </h2>
                            <p className="mt-2 text-sm leading-6 text-ink-soft">
                                Gunakan email dan password akun internal untuk
                                melanjutkan.
                            </p>
                        </div>

                        {children}
                    </div>
                </section>
            </div>
        </div>
    );
}
