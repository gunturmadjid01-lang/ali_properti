import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { useEffect, useMemo, useRef, useState } from "react";
import { Circle, CircleMarker, MapContainer, TileLayer } from "react-leaflet";
import {
    AlertTriangle,
    BadgeCheck,
    Camera,
    Clock3,
    Crosshair,
    LogOut,
    MapPin,
    RefreshCw,
    ShieldCheck,
    X,
} from "lucide-react";
import "leaflet/dist/leaflet.css";

const distanceMeters = (aLat, aLng, bLat, bLng) => {
    const r = 6371000,
        p = Math.PI / 180;
    const x =
        Math.sin(((bLat - aLat) * p) / 2) ** 2 +
        Math.cos(aLat * p) *
            Math.cos(bLat * p) *
            Math.sin(((bLng - aLng) * p) / 2) ** 2;
    return r * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
};

function EvidenceMap({ item, branch }) {
    const point = [item.latitude, item.longitude];
    return (
        <div className="mt-4 h-52 overflow-hidden rounded-2xl border border-slate-200">
            <MapContainer
                center={point}
                zoom={17}
                className="h-full w-full"
                scrollWheelZoom={false}
            >
                <TileLayer
                    attribution="&copy; OpenStreetMap"
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                <CircleMarker
                    center={point}
                    radius={9}
                    pathOptions={{
                        color: item.within_radius ? "#059669" : "#dc2626",
                        fillOpacity: 1,
                    }}
                />
                {branch?.latitude && (
                    <Circle
                        center={[branch.latitude, branch.longitude]}
                        radius={branch.radius}
                        pathOptions={{ color: "#d97706", fillOpacity: 0.1 }}
                    />
                )}
            </MapContainer>
        </div>
    );
}

export default function Index({ employee, branch, today = [], schedule }) {
    const { auth, flash } = usePage().props;
    const videoRef = useRef(null),
        canvasRef = useRef(null),
        streamRef = useRef(null),
        submissionTimerRef = useRef(null);
    const [cameraError, setCameraError] = useState(""),
        [preview, setPreview] = useState(""),
        [locating, setLocating] = useState(false);
    const [outsideWarning, setOutsideWarning] = useState(null);
    const [feedback, setFeedback] = useState(() =>
        flash?.success
            ? { type: "success", message: flash.success }
            : flash?.error
              ? { type: "error", message: flash.error }
              : null,
    );
    const form = useForm({
        type: "",
        latitude: "",
        longitude: "",
        accuracy_meters: "",
        photo: null,
        outside_radius_confirmed: false,
    });
    const completed = useMemo(() => new Set(today.map((x) => x.type)), [today]);

    useEffect(() => {
        if (flash?.success) {
            setFeedback({ type: "success", message: flash.success });
        } else if (flash?.error) {
            setFeedback({ type: "error", message: flash.error });
        }
    }, [flash?.id, flash?.success, flash?.error]);

    const startCamera = async () => {
        setCameraError("");
        try {
            streamRef.current?.getTracks().forEach((t) => t.stop());
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: "user",
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
                audio: false,
            });
            streamRef.current = stream;
            if (videoRef.current) videoRef.current.srcObject = stream;
        } catch {
            const message =
                "Kamera tidak dapat dibuka. Gunakan HTTPS dan izinkan akses kamera pada browser.";
            setCameraError(message);
            setFeedback({ type: "error", message });
        }
    };
    useEffect(() => {
        startCamera();
        return () => {
            streamRef.current?.getTracks().forEach((t) => t.stop());
            window.clearTimeout(submissionTimerRef.current);
        };
    }, []);
    const capture = () => {
        const video = videoRef.current,
            canvas = canvasRef.current;
        if (!video?.videoWidth) return;
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext("2d").drawImage(video, 0, 0);
        canvas.toBlob(
            (blob) => {
                if (!blob) return;
                form.setData(
                    "photo",
                    new File([blob], `absensi-${Date.now()}.jpg`, {
                        type: "image/jpeg",
                    }),
                );
                setPreview(URL.createObjectURL(blob));
            },
            "image/jpeg",
            0.86,
        );
    };
    const locate = () =>
        new Promise((resolve, reject) => {
            setLocating(true);
            navigator.geolocation.getCurrentPosition(
                ({ coords }) => {
                    form.setData((d) => ({
                        ...d,
                        latitude: coords.latitude,
                        longitude: coords.longitude,
                        accuracy_meters: coords.accuracy,
                    }));
                    setLocating(false);
                    resolve(coords);
                },
                (e) => {
                    setLocating(false);
                    reject(e);
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
            );
        });
    useEffect(() => {
        if (branch?.ready) locate().catch(() => {});
    }, []);
    const send = (type, confirmed = false) => {
        setFeedback({
            type: "loading",
            message: `${type === "check_in" ? "Absen masuk" : "Absen pulang"} sedang disimpan...`,
        });
        window.clearTimeout(submissionTimerRef.current);
        submissionTimerRef.current = window.setTimeout(() => {
            form.cancel();
            setFeedback({
                type: "error",
                message:
                    "Server tidak merespons dalam 30 detik. Data belum tersimpan; periksa koneksi lalu coba kembali.",
            });
        }, 30000);

        form.transform((d) => ({
            ...d,
            type,
            outside_radius_confirmed: confirmed,
        }));

        form.post("/absensi", {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                form.reset("photo");
                setPreview("");
                setOutsideWarning(null);
                setFeedback({
                    type: "success",
                    message:
                        page.props.flash?.success ??
                        `${type === "check_in" ? "Absen masuk" : "Absen pulang"} berhasil direkam.`,
                });
                startCamera();
            },
            onError: (errors) => {
                const message =
                    Object.values(errors ?? {}).find(Boolean) ??
                    "Absensi gagal disimpan. Periksa foto, lokasi, dan koneksi lalu coba kembali.";
                setFeedback({ type: "error", message: String(message) });
            },
            onFinish: () => {
                window.clearTimeout(submissionTimerRef.current);
                submissionTimerRef.current = null;
            },
        });
    };
    const prepare = async (type) => {
        if (!branch) {
            setFeedback({
                type: "error",
                message:
                    "Pegawai belum ditautkan ke perusahaan atau kantor cabang. Hubungi admin.",
            });
            return;
        }
        if (!branch.ready) {
            setFeedback({
                type: "error",
                message:
                    "Lokasi kantor belum aktif, belum final, atau koordinatnya belum lengkap. Hubungi admin.",
            });
            return;
        }
        if (type === "check_out" && !completed.has("check_in")) {
            setFeedback({
                type: "error",
                message:
                    "Absen masuk harus dilakukan terlebih dahulu sebelum absen pulang.",
            });
            return;
        }
        if (locating) {
            setFeedback({
                type: "error",
                message:
                    "Lokasi GPS sedang dibaca. Tunggu beberapa saat lalu tekan kembali tombol absensi.",
            });
            return;
        }
        if (!form.data.photo) {
            setFeedback({
                type: "error",
                message:
                    "Tekan Ambil Foto terlebih dahulu sebelum melakukan absensi.",
            });
            return;
        }
        let coords;
        try {
            coords = await locate();
        } catch {
            setFeedback({
                type: "error",
                message:
                    "GPS tidak dapat dibaca. Aktifkan lokasi dan izinkan akses GPS.",
            });
            return;
        }
        const distance = distanceMeters(
            branch.latitude,
            branch.longitude,
            coords.latitude,
            coords.longitude,
        );
        if (distance > branch.radius)
            return setOutsideWarning({ type, distance: Math.round(distance) });
        send(type, false);
    };

    return (
        <div className="relative min-h-screen bg-slate-100 text-slate-950">
            <div
                className="fixed inset-0 bg-cover bg-center opacity-[.16]"
                style={{
                    backgroundImage:
                        "url('/media/image/background_absensi.jpeg')",
                }}
            />
            <div className="fixed inset-0 bg-gradient-to-b from-slate-100/75 via-slate-100/95 to-slate-100" />
            <Head title="Absensi Pegawai" />
            {feedback && (
                <div className="fixed inset-x-4 top-4 z-[1200] mx-auto max-w-xl">
                    <div
                        className={`flex items-start gap-3 rounded-2xl border p-4 shadow-2xl backdrop-blur-xl ${
                            feedback.type === "success"
                                ? "border-emerald-200 bg-emerald-50/95 text-emerald-900"
                                : feedback.type === "loading"
                                  ? "border-blue-200 bg-blue-50/95 text-blue-900"
                                  : "border-red-200 bg-red-50/95 text-red-900"
                        }`}
                        role="alert"
                    >
                        <span
                            className={`mt-0.5 h-3 w-3 shrink-0 rounded-full ${
                                feedback.type === "success"
                                    ? "bg-emerald-500"
                                    : feedback.type === "loading"
                                      ? "animate-pulse bg-blue-500"
                                      : "bg-red-500"
                            }`}
                        />
                        <div className="flex-1">
                            <p className="font-black">
                                {feedback.type === "success"
                                    ? "Absensi Berhasil"
                                    : feedback.type === "loading"
                                      ? "Memproses Absensi"
                                      : "Absensi Belum Berhasil"}
                            </p>
                            <p className="mt-1 text-sm font-semibold leading-5">
                                {feedback.message}
                            </p>
                        </div>
                        {feedback.type !== "loading" && (
                            <button
                                type="button"
                                onClick={() => setFeedback(null)}
                                aria-label="Tutup pemberitahuan"
                            >
                                <X size={19} />
                            </button>
                        )}
                    </div>
                </div>
            )}
            <header className="relative z-10 mx-auto flex max-w-5xl items-center justify-between px-4 py-5">
                <div className="flex items-center gap-3">
                    <div className="grid h-11 w-11 place-items-center rounded-2xl bg-slate-950 text-amber-400">
                        <ShieldCheck />
                    </div>
                    <div>
                        <p className="text-[10px] font-black uppercase tracking-[.2em] text-amber-700">
                            Portal Kehadiran
                        </p>
                        <h1 className="font-black">Absensi Pegawai</h1>
                    </div>
                </div>
                <button
                    onClick={() => router.post("/absensi/keluar")}
                    className="rounded-xl border border-slate-300 bg-white p-3 shadow-sm"
                >
                    <LogOut size={18} />
                </button>
            </header>
            <main className="relative z-10 mx-auto grid max-w-5xl gap-5 px-4 pb-12 lg:grid-cols-[.8fr_1.2fr]">
                <aside className="space-y-4">
                    <section className="overflow-hidden rounded-[1.75rem] bg-slate-950 p-6 text-white shadow-xl">
                        <p className="text-xs font-bold text-amber-300">
                            IDENTITAS TERVERIFIKASI
                        </p>
                        <h2 className="mt-2 text-2xl font-black">
                            {employee.name}
                        </h2>
                        <p className="text-sm text-white/55">
                            {employee.employee_number}
                        </p>
                        <div className="mt-5 border-t border-white/10 pt-5">
                            <p className="flex items-center gap-2 font-black">
                                <MapPin className="text-amber-400" size={18} />
                                {branch?.name}
                            </p>
                            <p className="mt-2 text-sm leading-6 text-white/55">
                                {branch?.address}
                            </p>
                            <p className="mt-3 inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-bold">
                                Radius kantor {branch?.radius} meter
                            </p>
                        </div>
                    </section>
                    {schedule && (
                        <section className="rounded-3xl border border-white bg-white/85 p-5 shadow-lg">
                            <p className="text-xs font-black uppercase tracking-wider text-slate-500">
                                Jadwal Hari Kerja
                            </p>
                            <div className="mt-3 grid grid-cols-2 gap-3">
                                <div className="rounded-2xl bg-emerald-50 p-4">
                                    <p className="text-xs font-bold text-emerald-700">
                                        Jam masuk
                                    </p>
                                    <p className="text-2xl font-black">
                                        {schedule.check_in_time?.slice(0, 5)}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-amber-50 p-4">
                                    <p className="text-xs font-bold text-amber-700">
                                        Jam pulang
                                    </p>
                                    <p className="text-2xl font-black">
                                        {schedule.check_out_time?.slice(0, 5)}
                                    </p>
                                </div>
                            </div>
                        </section>
                    )}
                    {flash?.success && (
                        <div className="rounded-2xl bg-emerald-100 p-4 text-sm font-bold text-emerald-800">
                            {flash.success}
                        </div>
                    )}
                    {Object.values(form.errors)[0] && (
                        <div className="rounded-2xl bg-red-100 p-4 text-sm font-bold text-red-700">
                            {Object.values(form.errors)[0]}
                        </div>
                    )}
                </aside>
                <section className="rounded-[2rem] border border-white bg-white/90 p-5 shadow-2xl">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-xs font-black uppercase tracking-wider text-amber-700">
                                Bukti Wajah Langsung
                            </p>
                            <h2 className="text-xl font-black">
                                Ambil foto dari kamera
                            </h2>
                        </div>
                        <Camera />
                    </div>
                    <div className="relative mt-4 aspect-[4/3] overflow-hidden rounded-3xl bg-slate-950">
                        {preview ? (
                            <img
                                src={preview}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <video
                                ref={videoRef}
                                autoPlay
                                playsInline
                                muted
                                className="h-full w-full scale-x-[-1] object-cover"
                            />
                        )}
                        {cameraError && (
                            <div className="absolute inset-0 grid place-items-center p-8 text-center text-sm font-bold text-red-200">
                                {cameraError}
                            </div>
                        )}
                        <div className="pointer-events-none absolute inset-6 rounded-[45%] border-2 border-dashed border-white/45" />
                    </div>
                    <canvas ref={canvasRef} className="hidden" />
                    <div className="mt-4 flex gap-3">
                        <button
                            type="button"
                            onClick={capture}
                            className="flex min-h-12 flex-1 items-center justify-center gap-2 rounded-2xl bg-slate-950 font-black text-white"
                        >
                            <Camera size={18} />
                            {preview ? "Foto Ulang" : "Ambil Foto"}
                        </button>
                        <button
                            type="button"
                            onClick={startCamera}
                            className="rounded-2xl border border-slate-300 px-4"
                        >
                            <RefreshCw size={18} />
                        </button>
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-3">
                        <button
                            disabled={
                                completed.has("check_in") || form.processing
                            }
                            onClick={() => prepare("check_in")}
                            className="min-h-20 rounded-2xl bg-emerald-600 p-3 font-black text-white disabled:opacity-40"
                        >
                            <BadgeCheck className="mx-auto mb-1" />
                            {completed.has("check_in")
                                ? "Sudah Masuk"
                                : "Absen Masuk"}
                        </button>
                        <button
                            disabled={
                                completed.has("check_out") || form.processing
                            }
                            onClick={() => prepare("check_out")}
                            className="min-h-20 rounded-2xl bg-amber-400 p-3 font-black disabled:opacity-40"
                        >
                            <Clock3 className="mx-auto mb-1" />
                            {completed.has("check_out")
                                ? "Sudah Pulang"
                                : "Absen Pulang"}
                        </button>
                    </div>
                    {(locating || form.processing) && (
                        <p className="mt-4 flex justify-center gap-2 text-sm font-bold">
                            <Crosshair className="animate-pulse" size={18} />
                            {locating
                                ? "Memeriksa radius GPS..."
                                : "Menyimpan absensi..."}
                        </p>
                    )}
                </section>
                {!!today.length && (
                    <section className="space-y-4 lg:col-span-2">
                        <div>
                            <p className="text-xs font-black uppercase tracking-wider text-amber-700">
                                Laporan Hari Ini
                            </p>
                            <h2 className="text-2xl font-black">
                                Bukti absensi lengkap
                            </h2>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            {today.map((item) => (
                                <article
                                    key={item.id}
                                    className="rounded-3xl border border-white bg-white/90 p-5 shadow-lg"
                                >
                                    <div className="flex gap-4">
                                        <img
                                            src={item.photo_url}
                                            className="h-24 w-24 rounded-2xl object-cover"
                                        />
                                        <div>
                                            <p className="font-black">
                                                {item.type === "check_in"
                                                    ? "Absen Masuk"
                                                    : "Absen Pulang"}
                                            </p>
                                            <p className="text-2xl font-black">
                                                {item.time}
                                            </p>
                                            <span
                                                className={`mt-2 inline-flex rounded-full px-3 py-1 text-xs font-black ${item.within_radius ? "bg-emerald-100 text-emerald-700" : "bg-red-100 text-red-700"}`}
                                            >
                                                {item.within_radius
                                                    ? "Dalam radius"
                                                    : `Di luar radius · ${item.distance} m`}
                                            </span>
                                            <p className="mt-2 text-xs font-black text-slate-600">
                                                {{
                                                    on_time: "Waktu wajar",
                                                    late: "Terlambat masuk",
                                                    early_leave:
                                                        "Pulang terlalu cepat",
                                                    late_leave:
                                                        "Pulang terlambat",
                                                }[item.time_status] ??
                                                    item.time_status}
                                            </p>
                                        </div>
                                    </div>
                                    <EvidenceMap item={item} branch={branch} />
                                </article>
                            ))}
                        </div>
                    </section>
                )}
                {form.data.latitude && form.data.longitude && (
                    <section className="rounded-3xl border border-white bg-white/85 p-5 shadow-lg">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs font-black uppercase tracking-wider text-slate-500">
                                    Lokasi Anda Saat Ini
                                </p>
                                <p className="mt-1 text-sm font-bold">
                                    Akurasi GPS ±
                                    {Math.round(
                                        Number(form.data.accuracy_meters) || 0,
                                    )}{" "}
                                    meter
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() =>
                                    locate().catch(() =>
                                        window.alert("GPS tidak dapat dibaca."),
                                    )
                                }
                                className="rounded-xl border p-2"
                            >
                                <RefreshCw size={17} />
                            </button>
                        </div>
                        <div className="mt-3 h-60 overflow-hidden rounded-2xl">
                            <MapContainer
                                center={[
                                    Number(form.data.latitude),
                                    Number(form.data.longitude),
                                ]}
                                zoom={17}
                                className="h-full w-full"
                                scrollWheelZoom={false}
                            >
                                <TileLayer
                                    attribution="&copy; OpenStreetMap"
                                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                />
                                <CircleMarker
                                    center={[
                                        Number(form.data.latitude),
                                        Number(form.data.longitude),
                                    ]}
                                    radius={9}
                                    pathOptions={{
                                        color: "#2563eb",
                                        fillOpacity: 1,
                                    }}
                                />
                                <Circle
                                    center={[branch.latitude, branch.longitude]}
                                    radius={branch.radius}
                                    pathOptions={{
                                        color: "#d97706",
                                        fillOpacity: 0.1,
                                    }}
                                />
                            </MapContainer>
                        </div>
                    </section>
                )}
                {auth?.user && (
                    <Link
                        href="/admin/dashboard"
                        className="text-center text-sm font-bold underline lg:col-span-2"
                    >
                        Kembali ke dashboard
                    </Link>
                )}
            </main>
            {outsideWarning && (
                <div className="fixed inset-0 z-[1000] grid place-items-center bg-slate-950/70 p-4 backdrop-blur-sm">
                    <section className="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                        <div className="flex items-start justify-between">
                            <div className="grid h-12 w-12 place-items-center rounded-2xl bg-red-100 text-red-600">
                                <AlertTriangle />
                            </div>
                            <button onClick={() => setOutsideWarning(null)}>
                                <X />
                            </button>
                        </div>
                        <h2 className="mt-5 text-2xl font-black">
                            Anda di luar radius
                        </h2>
                        <p className="mt-2 text-sm leading-6 text-slate-600">
                            Jarak Anda sekitar{" "}
                            <b>{outsideWarning.distance} meter</b> dari kantor,
                            sedangkan batasnya {branch.radius} meter. Absensi
                            tetap dapat dilanjutkan dan akan ditandai “Di luar
                            radius” pada laporan admin.
                        </p>
                        <div className="mt-6 grid grid-cols-2 gap-3">
                            <button
                                onClick={() => setOutsideWarning(null)}
                                className="min-h-12 rounded-xl border border-slate-300 font-black"
                            >
                                Batal
                            </button>
                            <button
                                onClick={() => send(outsideWarning.type, true)}
                                className="min-h-12 rounded-xl bg-red-600 font-black text-white"
                            >
                                Tetap Lanjutkan
                            </button>
                        </div>
                    </section>
                </div>
            )}
        </div>
    );
}
