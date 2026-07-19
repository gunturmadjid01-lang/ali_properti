import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, Clock3, MapPin, Navigation, UserRound } from "lucide-react";
import {
    Circle,
    CircleMarker,
    MapContainer,
    Polyline,
    TileLayer,
} from "react-leaflet";
import "leaflet/dist/leaflet.css";
import AdminLayout from "../../../Layouts/AdminLayout";

export default function Show({ record }) {
    const employeePoint = [record.latitude, record.longitude],
        officePoint = [record.office_latitude, record.office_longitude];
    return (
        <>
            <Head title={`Detail Absensi ${record.employee}`} />
            <div className="mx-auto max-w-6xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-xs font-black uppercase tracking-wider text-amber-700">
                            Bukti Kehadiran
                        </p>
                        <h1 className="text-3xl font-black">Detail Absensi</h1>
                    </div>
                    <Link
                        href="/admin/absensi-pegawai"
                        className="inline-flex items-center gap-2 rounded-xl border bg-white px-4 py-2 font-black"
                    >
                        <ArrowLeft size={17} />
                        Kembali
                    </Link>
                </div>
                <section className="grid gap-5 lg:grid-cols-[360px_1fr]">
                    <div className="space-y-4">
                        <img
                            src={record.photo_url}
                            className="aspect-[3/4] w-full rounded-3xl object-cover shadow-xl"
                        />
                        <div className="rounded-3xl bg-slate-950 p-5 text-white">
                            <p className="flex items-center gap-2 text-xs font-bold text-amber-300">
                                <UserRound size={16} />
                                PEGAWAI
                            </p>
                            <h2 className="mt-2 text-2xl font-black">
                                {record.employee}
                            </h2>
                            <p className="text-white/55">
                                {record.employee_number} · {record.job_title}
                            </p>
                            <div className="mt-5 grid grid-cols-2 gap-3">
                                <div className="rounded-2xl bg-white/10 p-3">
                                    <p className="text-xs text-white/50">
                                        Jenis
                                    </p>
                                    <p className="font-black">
                                        {record.type === "check_in"
                                            ? "Masuk"
                                            : "Pulang"}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-white/10 p-3">
                                    <p className="text-xs text-white/50">
                                        Waktu
                                    </p>
                                    <p className="font-black">{record.time}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="space-y-4">
                        <div className="h-[520px] overflow-hidden rounded-3xl border bg-white shadow-xl">
                            <MapContainer
                                center={employeePoint}
                                zoom={16}
                                className="h-full w-full"
                            >
                                <TileLayer
                                    attribution="&copy; OpenStreetMap"
                                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                />
                                <Circle
                                    center={officePoint}
                                    radius={record.radius}
                                    pathOptions={{
                                        color: "#d97706",
                                        fillOpacity: 0.1,
                                    }}
                                />
                                <CircleMarker
                                    center={officePoint}
                                    radius={8}
                                    pathOptions={{
                                        color: "#d97706",
                                        fillOpacity: 1,
                                    }}
                                />
                                <CircleMarker
                                    center={employeePoint}
                                    radius={10}
                                    pathOptions={{
                                        color: record.within_radius
                                            ? "#059669"
                                            : "#dc2626",
                                        fillOpacity: 1,
                                    }}
                                />
                                <Polyline
                                    positions={[officePoint, employeePoint]}
                                    pathOptions={{
                                        color: "#334155",
                                        dashArray: "8 8",
                                    }}
                                />
                            </MapContainer>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div className="rounded-2xl border bg-white p-4">
                                <MapPin
                                    className={
                                        record.within_radius
                                            ? "text-emerald-600"
                                            : "text-red-600"
                                    }
                                />
                                <p className="mt-2 text-xs text-slate-500">
                                    Status radius
                                </p>
                                <p className="font-black">
                                    {record.within_radius
                                        ? "Dalam radius"
                                        : "Di luar radius"}
                                </p>
                            </div>
                            <div className="rounded-2xl border bg-white p-4">
                                <Navigation className="text-blue-600" />
                                <p className="mt-2 text-xs text-slate-500">
                                    Jarak kantor
                                </p>
                                <p className="font-black">
                                    {record.distance} meter
                                </p>
                            </div>
                            <div className="rounded-2xl border bg-white p-4">
                                <Clock3 className="text-amber-600" />
                                <p className="mt-2 text-xs text-slate-500">
                                    Status waktu
                                </p>
                                <p className="font-black">
                                    {
                                        {
                                            on_time: "Tepat waktu",
                                            late: "Terlambat",
                                            early_leave: "Pulang cepat",
                                            late_leave: "Pulang terlambat",
                                        }[record.time_status]
                                    }
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}
Show.layout = (page) => (
    <AdminLayout title="Detail Absensi">{page}</AdminLayout>
);
