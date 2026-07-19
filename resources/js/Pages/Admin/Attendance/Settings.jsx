import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, CalendarClock, Save } from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";

const days = [
    ["1", "Sen"],
    ["2", "Sel"],
    ["3", "Rab"],
    ["4", "Kam"],
    ["5", "Jum"],
    ["6", "Sab"],
    ["7", "Min"],
];
export default function Settings({ branches, settings }) {
    const form = useForm({
        cabang_perusahaan_id: "",
        check_in_time: "08:00",
        check_out_time: "17:00",
        late_tolerance_minutes: 15,
        checkout_tolerance_minutes: 15,
        work_days: [1, 2, 3, 4, 5, 6],
        is_active: true,
    });
    const selectSetting = (id) => {
        const s = settings.find((x) => x.id === id);
        if (s)
            form.setData({
                cabang_perusahaan_id: s.branch_id,
                check_in_time: s.check_in_time,
                check_out_time: s.check_out_time,
                late_tolerance_minutes: s.late_tolerance_minutes,
                checkout_tolerance_minutes: s.checkout_tolerance_minutes,
                work_days: s.work_days.map(Number),
                is_active: s.is_active,
            });
    };
    const submit = (e) => {
        e.preventDefault();
        form.post("/admin/pengaturan-absensi", { preserveScroll: true });
    };
    return (
        <>
            <Head title="Pengaturan Jam Absensi" />
            <div className="mx-auto max-w-5xl space-y-5">
                <section className="flex items-center justify-between rounded-3xl bg-slate-950 p-6 text-white">
                    <div>
                        <p className="text-xs font-black uppercase tracking-wider text-amber-300">
                            Konfigurasi Terpisah
                        </p>
                        <h1 className="mt-2 text-3xl font-black">
                            Pengaturan Jam Absensi
                        </h1>
                        <p className="mt-2 text-sm text-white/55">
                            Atur jadwal per perusahaan tanpa mencampurnya dengan
                            data lokasi cabang.
                        </p>
                    </div>
                    <Link
                        href="/admin/absensi-pegawai"
                        className="rounded-xl border border-white/20 p-3"
                    >
                        <ArrowLeft />
                    </Link>
                </section>
                <div className="grid gap-5 lg:grid-cols-[.8fr_1.2fr]">
                    <section className="rounded-2xl border bg-white p-4">
                        <h2 className="font-black">Jadwal Tersimpan</h2>
                        <div className="mt-3 space-y-2">
                            {settings.map((s) => (
                                <button
                                    type="button"
                                    onClick={() => selectSetting(s.id)}
                                    key={s.id}
                                    className="w-full rounded-xl border p-3 text-left hover:border-amber-500"
                                >
                                    <p className="font-black">{s.branch}</p>
                                    <p className="text-xs text-slate-500">
                                        {s.check_in_time}–{s.check_out_time} ·
                                        toleransi {s.late_tolerance_minutes}{" "}
                                        menit
                                    </p>
                                    <span className="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase">
                                        {s.record_status}
                                    </span>
                                </button>
                            ))}
                        </div>
                    </section>
                    <form
                        onSubmit={submit}
                        className="space-y-4 rounded-2xl border bg-white p-5"
                    >
                        <div className="flex items-center gap-2">
                            <CalendarClock className="text-amber-600" />
                            <h2 className="text-xl font-black">
                                Form Jadwal Kerja
                            </h2>
                        </div>
                        <label className="block text-sm font-black">
                            Perusahaan
                            <select
                                value={form.data.cabang_perusahaan_id}
                                onChange={(e) =>
                                    form.setData(
                                        "cabang_perusahaan_id",
                                        e.target.value,
                                    )
                                }
                                className="mt-1 w-full rounded-xl border p-3"
                            >
                                <option value="">Pilih perusahaan</option>
                                {branches.map((b) => (
                                    <option key={b.value} value={b.value}>
                                        {b.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="text-sm font-black">
                                Jam Masuk
                                <input
                                    type="time"
                                    value={form.data.check_in_time}
                                    onChange={(e) =>
                                        form.setData(
                                            "check_in_time",
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 w-full rounded-xl border p-3"
                                />
                            </label>
                            <label className="text-sm font-black">
                                Jam Pulang
                                <input
                                    type="time"
                                    value={form.data.check_out_time}
                                    onChange={(e) =>
                                        form.setData(
                                            "check_out_time",
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 w-full rounded-xl border p-3"
                                />
                            </label>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <label className="block text-sm font-black">
                                Toleransi Masuk (menit)
                                <input
                                    type="number"
                                    min="0"
                                    max="180"
                                    value={form.data.late_tolerance_minutes}
                                    onChange={(e) =>
                                        form.setData(
                                            "late_tolerance_minutes",
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 w-full rounded-xl border p-3"
                                />
                            </label>
                            <label className="block text-sm font-black">
                                Toleransi Pulang ± (menit)
                                <input
                                    type="number"
                                    min="0"
                                    max="180"
                                    value={form.data.checkout_tolerance_minutes}
                                    onChange={(e) =>
                                        form.setData(
                                            "checkout_tolerance_minutes",
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 w-full rounded-xl border p-3"
                                />
                            </label>
                        </div>
                        <div>
                            <p className="text-sm font-black">Hari Kerja</p>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {days.map(([value, label]) => {
                                    const n = Number(value),
                                        active =
                                            form.data.work_days.includes(n);
                                    return (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() =>
                                                form.setData(
                                                    "work_days",
                                                    active
                                                        ? form.data.work_days.filter(
                                                              (x) => x !== n,
                                                          )
                                                        : [
                                                              ...form.data
                                                                  .work_days,
                                                              n,
                                                          ],
                                                )
                                            }
                                            className={`h-11 w-11 rounded-xl font-black ${active ? "bg-slate-950 text-white" : "bg-slate-100 text-slate-500"}`}
                                        >
                                            {label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                        {Object.values(form.errors)[0] && (
                            <p className="rounded-xl bg-red-50 p-3 text-sm font-bold text-red-700">
                                {Object.values(form.errors)[0]}
                            </p>
                        )}
                        <button
                            disabled={form.processing}
                            className="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-amber-400 font-black"
                        >
                            <Save size={18} />
                            Simpan & Ajukan
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
Settings.layout = (page) => (
    <AdminLayout title="Pengaturan Absensi">{page}</AdminLayout>
);
