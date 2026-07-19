import { Head, useForm } from "@inertiajs/react";
import { useRef } from "react";
import {
    ArrowRight,
    Building2,
    Fingerprint,
    ShieldCheck,
    UserRound,
} from "lucide-react";

export default function Login() {
    const form = useForm({ name: "", pin: "" });
    const otpRefs = useRef([]);
    const digits = Array.from({ length: 8 }, (_, i) => form.data.pin[i] ?? "");

    const setDigit = (index, value) => {
        const clean = value.replace(/\D/g, "").slice(-1);
        const next = [...digits];
        next[index] = clean;
        form.setData("pin", next.join(""));
        if (clean && index < 7) otpRefs.current[index + 1]?.focus();
    };
    const onKeyDown = (index, event) => {
        if (event.key === "Backspace" && !digits[index] && index > 0)
            otpRefs.current[index - 1]?.focus();
        if (event.key === "ArrowLeft" && index > 0)
            otpRefs.current[index - 1]?.focus();
        if (event.key === "ArrowRight" && index < 7)
            otpRefs.current[index + 1]?.focus();
    };
    const onPaste = (event) => {
        const pasted = event.clipboardData
            .getData("text")
            .replace(/\D/g, "")
            .slice(0, 8);
        if (!pasted) return;
        event.preventDefault();
        form.setData("pin", pasted);
        otpRefs.current[Math.min(pasted.length, 7)]?.focus();
    };
    const submit = (event) => {
        event.preventDefault();
        if (form.data.pin.length !== 8)
            return form.setError("pin", "PIN absensi harus lengkap 8 digit.");
        form.post("/absensi/check");
    };

    return (
        <div className="relative min-h-screen overflow-hidden bg-slate-950 text-white">
            <Head title="Portal Absensi Pegawai" />
            <div
                className="absolute inset-0 bg-cover bg-center"
                style={{
                    backgroundImage: "url('/media/image/background_absensi.jpeg')",
                }}
            />
            <div className="absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/72 to-slate-950/20" />
            <div className="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-slate-950/20" />
            <main className="relative mx-auto grid min-h-screen max-w-7xl items-center gap-10 px-5 py-10 lg:grid-cols-[1.05fr_.95fr] lg:px-10">
                <section className="hidden max-w-xl lg:block">
                    <div className="inline-flex items-center gap-2 rounded-full border border-amber-300/25 bg-amber-300/10 px-4 py-2 text-xs font-black uppercase tracking-[.18em] text-amber-200">
                        <Building2 size={15} /> PT Ali Properti Indonesia
                    </div>
                    <h1 className="mt-7 text-5xl font-black leading-[1.08] xl:text-6xl">
                        Kehadiran yang
                        <br />
                        <span className="text-amber-300">
                            mudah dan terpercaya.
                        </span>
                    </h1>
                    <p className="mt-6 max-w-lg text-lg leading-8 text-white/60">
                        Verifikasi identitas, ambil foto langsung, dan catat
                        lokasi kerja dalam satu alur yang aman.
                    </p>
                    <div className="mt-9 grid grid-cols-2 gap-3 text-sm font-bold text-white/75">
                        <div className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                            <ShieldCheck className="text-emerald-300" /> PIN
                            terenkripsi
                        </div>
                        <div className="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur">
                            <Fingerprint className="text-amber-300" /> Bukti GPS
                            & foto
                        </div>
                    </div>
                </section>
                <section className="mx-auto w-full max-w-lg rounded-[2rem] border border-white/25 bg-white/92 p-6 text-slate-950 shadow-[0_35px_100px_rgba(0,0,0,.45)] backdrop-blur-xl sm:p-8">
                    <div className="flex items-start justify-between">
                        <div className="grid h-14 w-14 place-items-center rounded-2xl bg-slate-950 text-amber-300 shadow-lg">
                            <Fingerprint size={30} />
                        </div>
                        <span className="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700">
                            Portal Aktif
                        </span>
                    </div>
                    <p className="mt-7 text-xs font-black uppercase tracking-[.18em] text-amber-700">
                        Verifikasi Pegawai
                    </p>
                    <h2 className="mt-2 text-3xl font-black">Mulai absensi</h2>
                    <p className="mt-2 text-sm leading-6 text-slate-500">
                        Masukkan nama lengkap dan PIN 8 digit. Kamera serta peta
                        akan muncul setelah data ditemukan.
                    </p>
                    <form className="mt-7 space-y-6" onSubmit={submit}>
                        <label className="block text-sm font-black">
                            Nama Lengkap
                            <div className="relative mt-2">
                                <UserRound
                                    className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"
                                    size={19}
                                />
                                <input
                                    autoFocus
                                    autoComplete="name"
                                    className="min-h-14 w-full rounded-2xl border border-slate-200 bg-slate-50 pl-12 pr-4 font-bold outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData("name", e.target.value)
                                    }
                                    placeholder="Sesuai Data Pegawai"
                                />
                            </div>
                        </label>
                        <fieldset>
                            <legend className="text-sm font-black">
                                PIN Absensi 8 Digit
                            </legend>
                            <div
                                className="mt-2 grid grid-cols-8 gap-1.5 sm:gap-2"
                                onPaste={onPaste}
                            >
                                {digits.map((digit, index) => (
                                    <input
                                        key={index}
                                        ref={(el) =>
                                            (otpRefs.current[index] = el)
                                        }
                                        value={digit}
                                        onChange={(e) =>
                                            setDigit(index, e.target.value)
                                        }
                                        onKeyDown={(e) => onKeyDown(index, e)}
                                        onFocus={(e) => e.target.select()}
                                        inputMode="numeric"
                                        pattern="[0-9]*"
                                        maxLength={1}
                                        autoComplete={
                                            index === 0
                                                ? "one-time-code"
                                                : "off"
                                        }
                                        aria-label={`Digit PIN ${index + 1}`}
                                        className="aspect-square min-w-0 rounded-xl border border-slate-200 bg-slate-50 text-center text-lg font-black outline-none transition focus:border-amber-400 focus:bg-white focus:ring-4 focus:ring-amber-100 sm:text-xl"
                                    />
                                ))}
                            </div>
                            <p className="mt-2 text-xs font-medium text-slate-400">
                                Bisa tempel delapan digit sekaligus.
                            </p>
                        </fieldset>
                        {Object.values(form.errors)[0] && (
                            <p className="rounded-2xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-red-700">
                                {Object.values(form.errors)[0]}
                            </p>
                        )}
                        <button
                            disabled={form.processing}
                            className="flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 font-black text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:opacity-60"
                        >
                            {form.processing
                                ? "Memeriksa Pegawai..."
                                : "Cek Pegawai"}
                            <ArrowRight size={19} />
                        </button>
                    </form>
                </section>
            </main>
        </div>
    );
}
