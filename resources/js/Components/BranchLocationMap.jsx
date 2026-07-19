import { useEffect, useMemo, useState } from "react";
import {
    Circle,
    CircleMarker,
    MapContainer,
    TileLayer,
    useMap,
    useMapEvents,
} from "react-leaflet";
import { Crosshair, MapPin } from "lucide-react";
import "leaflet/dist/leaflet.css";

const DEFAULT_CENTER = [-2.5489, 118.0149];

function MapPicker({ position, onPick }) {
    const map = useMap();
    useMapEvents({
        click: (event) => onPick(event.latlng.lat, event.latlng.lng),
    });
    useEffect(() => {
        if (position) map.flyTo(position, Math.max(map.getZoom(), 16));
    }, [map, position]);
    return position ? (
        <CircleMarker
            center={position}
            radius={9}
            pathOptions={{
                color: "#b7791f",
                fillColor: "#f59e0b",
                fillOpacity: 1,
            }}
        />
    ) : null;
}

export default function BranchLocationMap({
    latitude,
    longitude,
    radius,
    errors = {},
    onChange,
}) {
    const [locating, setLocating] = useState(false);
    const position = useMemo(() => {
        const lat = Number(latitude);
        const lng = Number(longitude);
        return Number.isFinite(lat) &&
            Number.isFinite(lng) &&
            latitude !== "" &&
            longitude !== ""
            ? [lat, lng]
            : null;
    }, [latitude, longitude]);
    const pick = (lat, lng) => {
        onChange("latitude", lat.toFixed(7));
        onChange("longtitude", lng.toFixed(7));
    };
    const autoLocate = () => {
        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                pick(coords.latitude, coords.longitude);
                setLocating(false);
            },
            () => {
                setLocating(false);
                window.alert(
                    "Lokasi tidak dapat dibaca. Izinkan akses lokasi pada browser lalu coba lagi.",
                );
            },
            { enableHighAccuracy: true, timeout: 15000 },
        );
    };

    return (
        <section className="md:col-span-2 overflow-hidden rounded-2xl border border-silver-deep/70 bg-silver-soft/60">
            <div className="flex flex-wrap items-center justify-between gap-3 p-4">
                <div>
                    <p className="font-extrabold">Titik & Radius Absensi</p>
                    <p className="text-xs font-semibold text-ink-soft">
                        Klik peta untuk memilih manual, atau gunakan GPS
                        perangkat.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={autoLocate}
                    disabled={locating}
                    className="inline-flex min-h-10 items-center gap-2 rounded-xl bg-ink px-4 text-sm font-extrabold text-white disabled:opacity-60"
                >
                    <Crosshair size={17} />{" "}
                    {locating ? "Mencari lokasi..." : "Gunakan Lokasi Saat Ini"}
                </button>
            </div>
            <div className="h-80 border-y border-silver-deep/70">
                <MapContainer
                    center={position ?? DEFAULT_CENTER}
                    zoom={position ? 16 : 5}
                    className="h-full w-full"
                    scrollWheelZoom
                >
                    <TileLayer
                        attribution="&copy; OpenStreetMap contributors"
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />
                    <MapPicker position={position} onPick={pick} />
                    {position && (
                        <Circle
                            center={position}
                            radius={Math.max(10, Number(radius) || 100)}
                            pathOptions={{
                                color: "#d97706",
                                fillColor: "#fbbf24",
                                fillOpacity: 0.16,
                            }}
                        />
                    )}
                </MapContainer>
            </div>
            <div className="grid gap-3 p-4 sm:grid-cols-3">
                <label className="text-xs font-extrabold">
                    Latitude
                    <input
                        className="mt-1 w-full rounded-xl border border-silver-deep bg-white px-3 py-2"
                        value={latitude ?? ""}
                        onChange={(e) => onChange("latitude", e.target.value)}
                    />
                </label>
                <label className="text-xs font-extrabold">
                    Longitude
                    <input
                        className="mt-1 w-full rounded-xl border border-silver-deep bg-white px-3 py-2"
                        value={longitude ?? ""}
                        onChange={(e) => onChange("longtitude", e.target.value)}
                    />
                </label>
                <label className="text-xs font-extrabold">
                    Radius (meter)
                    <input
                        type="number"
                        min="10"
                        max="10000"
                        className="mt-1 w-full rounded-xl border border-silver-deep bg-white px-3 py-2"
                        value={radius ?? 100}
                        onChange={(e) =>
                            onChange("attendance_radius_meters", e.target.value)
                        }
                    />
                </label>
            </div>
            {(errors.latitude ||
                errors.longtitude ||
                errors.attendance_radius_meters) && (
                <p className="px-4 pb-4 text-sm font-bold text-red-600">
                    {errors.latitude ||
                        errors.longtitude ||
                        errors.attendance_radius_meters}
                </p>
            )}
        </section>
    );
}
