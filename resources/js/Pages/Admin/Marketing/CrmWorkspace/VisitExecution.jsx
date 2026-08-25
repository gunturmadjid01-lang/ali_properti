import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Camera, MapPin } from "lucide-react";
import { Button, Input, Textarea } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function VisitExecution({
    title,
    phase,
    visit,
    actionUrl,
    backUrl,
    options = {},
}) {
    const interestOptions = options.interestOptions || [
        { value: "cold", label: "Dingin" },
        { value: "warm", label: "Hangat" },
        { value: "hot", label: "Panas" },
    ];
    const form = useForm({
        latitude: "",
        longitude: "",
        accuracy: "",
        photo: null,
        device_info: navigator.userAgent,
        result: "",
        customer_response: "",
        objections: "",
        interest_level: "",
        next_action: "",
        next_action_at: "",
    });
    const locate = () =>
        navigator.geolocation?.getCurrentPosition(
            (position) =>
                form.setData((data) => ({
                    ...data,
                    latitude: String(position.coords.latitude),
                    longitude: String(position.coords.longitude),
                    accuracy: String(Math.round(position.coords.accuracy)),
                })),
            () =>
                alert(
                    "Lokasi tidak dapat diambil. Aktifkan izin lokasi dan coba lagi.",
                ),
            { enableHighAccuracy: true, timeout: 15000 },
        );
    const submit = (event) => {
        event.preventDefault();
        form.post(actionUrl, { forceFormData: true });
    };
    const checkingOut = phase === "check-out";

    return (
        <>
            <Head title={title} />
            <div className="mx-auto grid max-w-4xl gap-6">
                <section className="rounded-3xl bg-gradient-to-br from-[#20262d] to-[#11161c] p-6 text-white">
                    <p className="text-xs font-black uppercase tracking-widest text-amber-300">
                        Kunjungan {visit.visit_no}
                    </p>
                    <h1 className="mt-2 text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-sm text-white/65">
                        {visit.customer} -{" "}
                        {visit.location || "Lokasi belum ditulis"}
                    </p>
                </section>
                <form
                    className="grid gap-5 rounded-3xl border bg-white/85 p-6 shadow-soft dark:border-white/10 dark:bg-white/7"
                    onSubmit={submit}
                >
                    <div className="rounded-2xl bg-amber-50 p-4 text-sm text-amber-900">
                        <b>Waktu dicatat otomatis dari server.</b> Koordinat
                        tidak dapat diketik manual; ambil lokasi saat berada di
                        tempat kunjungan.
                    </div>
                    <Button type="button" variant="outline" onClick={locate}>
                        <MapPin size={17} /> Ambil Lokasi Saat Ini
                    </Button>
                    <div className="rounded-2xl bg-silver-soft p-4 text-sm font-bold">
                        {form.data.latitude
                            ? `${form.data.latitude}, ${form.data.longitude} - akurasi +- ${form.data.accuracy} meter`
                            : "Lokasi belum diambil"}
                    </div>
                    {form.errors.latitude && (
                        <p className="text-sm font-bold text-red-600">
                            {form.errors.latitude}
                        </p>
                    )}
                    <Input
                        type="file"
                        accept="image/*"
                        capture="environment"
                        label={
                            checkingOut
                                ? "Foto dokumentasi / check-out"
                                : "Foto check-in"
                        }
                        error={form.errors.photo}
                        onChange={(event) =>
                            form.setData(
                                "photo",
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    {checkingOut && (
                        <>
                            <Textarea
                                label="Hasil kunjungan *"
                                value={form.data.result}
                                error={form.errors.result}
                                onChange={(event) =>
                                    form.setData("result", event.target.value)
                                }
                            />
                            <Textarea
                                label="Respons customer"
                                value={form.data.customer_response}
                                onChange={(event) =>
                                    form.setData(
                                        "customer_response",
                                        event.target.value,
                                    )
                                }
                            />
                            <Textarea
                                label="Kendala / keberatan"
                                value={form.data.objections}
                                onChange={(event) =>
                                    form.setData(
                                        "objections",
                                        event.target.value,
                                    )
                                }
                            />
                            <label className="grid gap-2 text-sm font-extrabold">
                                Tingkat minat
                                <select
                                    className="rounded-xl border bg-white px-3 py-2 dark:bg-[#171c23]"
                                    value={form.data.interest_level}
                                    onChange={(event) =>
                                        form.setData(
                                            "interest_level",
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Pilih</option>
                                    {interestOptions.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Textarea
                                label="Tindak lanjut berikutnya *"
                                value={form.data.next_action}
                                error={form.errors.next_action}
                                onChange={(event) =>
                                    form.setData(
                                        "next_action",
                                        event.target.value,
                                    )
                                }
                            />
                            <Input
                                type="datetime-local"
                                label="Jadwal tindak lanjut"
                                value={form.data.next_action_at}
                                error={form.errors.next_action_at}
                                onChange={(event) =>
                                    form.setData(
                                        "next_action_at",
                                        event.target.value,
                                    )
                                }
                            />
                        </>
                    )}
                    <div className="flex justify-between gap-3">
                        <Button as={Link} href={backUrl} variant="outline">
                            <ArrowLeft size={16} /> Kembali
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.latitude ||
                                !form.data.photo
                            }
                        >
                            <Camera size={16} />{" "}
                            {checkingOut
                                ? "Selesaikan Kunjungan"
                                : "Mulai Kunjungan"}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

VisitExecution.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Kunjungan"}>{page}</AdminLayout>
);
