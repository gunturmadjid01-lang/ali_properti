import { Head, Link, useForm } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Form({ title, options = {}, row = null }) {
    const [duplicates, setDuplicates] = useState([]);
    const [checkingDuplicates, setCheckingDuplicates] = useState(false);
    const form = useForm({
        name: row?.name || "",
        phone: row?.phone || "",
        email: row?.email || "",
        identity_no: row?.identity_no || "",
        lead_source_id: row?.lead_source_id || "",
        marketing_campaign_id: row?.marketing_campaign_id || "",
        source_channel: row?.source_channel || "direct",
        consent_status: row?.consent_status || "unknown",
        consent_channels: row?.consent_channels || [],
        perumahan_id: row?.perumahan_id || "",
        unit_type_interest: row?.unit_type_interest || "",
        detail_rumah_id: row?.detail_rumah_id || "",
        interest_level: row?.interest_level || "cold",
        preferred_payment_method: row?.preferred_payment_method || "",
        notes: row?.notes || "",
        duplicate_acknowledged_id: "",
        duplicate_override_reason: "",
    });
    const housing = options.perumahans?.find(
        (item) => Number(item.id) === Number(form.data.perumahan_id),
    );
    const housingUnits = (options.units || []).filter(
        (item) => Number(item.perumahan_id) === Number(form.data.perumahan_id),
    );
    const unitTypes = [
        ...new Set(housingUnits.map((item) => item.tipe_rumah).filter(Boolean)),
    ];
    const filteredUnits = housingUnits.filter(
        (item) =>
            !form.data.unit_type_interest ||
            item.tipe_rumah === form.data.unit_type_interest,
    );
    const campaigns = (options.campaigns || []).filter(
        (item) =>
            !item.perumahan_id ||
            Number(item.perumahan_id) === Number(form.data.perumahan_id),
    );
    const field = (name, label, type = "text") => (
        <label className="grid gap-1">
            <b className="text-sm">{label}</b>
            <input
                className="rounded-xl border p-3"
                type={type}
                value={form.data[name]}
                onChange={(event) => form.setData(name, event.target.value)}
            />
            <small className="text-red-600">{form.errors[name]}</small>
        </label>
    );
    const toggleChannel = (channel) =>
        form.setData(
            "consent_channels",
            form.data.consent_channels.includes(channel)
                ? form.data.consent_channels.filter((item) => item !== channel)
                : [...form.data.consent_channels, channel],
        );
    const checkDuplicates = async () => {
        setCheckingDuplicates(true);
        form.clearErrors("duplicate_override_reason");
        try {
            const query = new URLSearchParams({
                phone: form.data.phone,
                email: form.data.email,
                identity_no: form.data.identity_no,
                exclude_id: row?.id || "",
            });
            const response = await fetch(
                `/admin/marketing/leads/check-duplicates?${query}`,
                { headers: { Accept: "application/json" } },
            );
            const payload = await response.json();
            setDuplicates(payload.duplicates || []);
            form.setData("duplicate_acknowledged_id", "");
        } finally {
            setCheckingDuplicates(false);
        }
    };

    return (
        <>
            <Head title={title} />
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    row
                        ? form.put(`/admin/marketing/leads/${row.id}`)
                        : form.post("/admin/marketing/leads");
                }}
                className="mx-auto grid max-w-3xl gap-5 rounded-3xl border bg-white p-6"
            >
                <div>
                    <h1 className="text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-ink-soft">
                        Lead adalah data prospek awal. Data administratif
                        Customer baru dibuat setelah Lead qualified dan
                        diverifikasi Admin Sales.
                    </p>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    {field("name", "Nama *")}
                    {field("phone", "Telepon *")}
                    {field("email", "Email", "email")}
                    {field("identity_no", "NIK / Identitas")}
                    <label className="grid gap-1">
                        <b>Sumber Lead (opsional)</b>
                        <span className="text-xs text-ink-soft">
                            Asal spesifik prospek, misalnya nama referensi,
                            program promo, atau sumber canvassing tertentu.
                            Pilih jika sudah diketahui.
                        </span>
                        <select
                            className="rounded-xl border p-3"
                            value={form.data.lead_source_id}
                            onChange={(event) =>
                                form.setData(
                                    "lead_source_id",
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">Belum diketahui</option>
                            {options.sources?.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.nama_sumber}
                                </option>
                            ))}
                        </select>
                        <small className="text-red-600">
                            {form.errors.lead_source_id}
                        </small>
                    </label>
                    <label className="grid gap-1">
                        <b>Kanal Lead *</b>
                        <span className="text-xs text-ink-soft">
                            Media pertama prospek masuk, misalnya Canvassing,
                            WhatsApp, Website, Event, atau Referensi.
                        </span>
                        <select
                            className="rounded-xl border p-3"
                            value={form.data.source_channel}
                            onChange={(event) =>
                                form.setData(
                                    "source_channel",
                                    event.target.value,
                                )
                            }
                        >
                            {[
                                "direct",
                                "canvassing",
                                "whatsapp",
                                "website",
                                "social_media",
                                "event",
                                "referral",
                                "office",
                            ].map((item) => (
                                <option key={item} value={item}>
                                    {item.replaceAll("_", " ")}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1">
                        <b>Perumahan diminati</b>
                        <select
                            className="rounded-xl border p-3"
                            value={form.data.perumahan_id}
                            onChange={(event) => {
                                form.setData((data) => ({
                                    ...data,
                                    perumahan_id: event.target.value,
                                    unit_type_interest: "",
                                    detail_rumah_id: "",
                                    marketing_campaign_id: "",
                                }));
                            }}
                        >
                            <option value="">Belum diketahui</option>
                            {options.perumahans?.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.nama_perusahaan}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1">
                        <b>Cabang</b>
                        <input
                            className="rounded-xl border bg-slate-50 p-3"
                            readOnly
                            value={
                                housing?.cabang?.nama_cabang ||
                                "Mengikuti perumahan"
                            }
                        />
                    </label>
                    <label className="grid gap-1">
                        <b>Tipe unit diminati</b>
                        <select
                            className="rounded-xl border p-3"
                            value={form.data.unit_type_interest}
                            disabled={!form.data.perumahan_id}
                            onChange={(event) =>
                                form.setData((data) => ({
                                    ...data,
                                    unit_type_interest: event.target.value,
                                    detail_rumah_id: "",
                                }))
                            }
                        >
                            <option value="">Belum menentukan tipe</option>
                            {unitTypes.map((item) => (
                                <option key={item} value={item}>
                                    {item}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1">
                        <b>Unit diminati</b>
                        <select
                            className="rounded-xl border p-3"
                            value={form.data.detail_rumah_id}
                            disabled={!form.data.perumahan_id}
                            onChange={(event) =>
                                form.setData(
                                    "detail_rumah_id",
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">Belum menentukan unit</option>
                            {filteredUnits.map((item) => (
                                <option key={item.id} value={item.id}>
                                    Blok {item.kode_nlok || "-"} /{" "}
                                    {item.nomor_rumah || "-"} · Tipe{" "}
                                    {item.tipe_rumah || "-"}
                                </option>
                            ))}
                        </select>
                        <small className="text-red-600">
                            {form.errors.detail_rumah_id}
                        </small>
                    </label>
                    <label className="grid gap-1">
                        <b>Campaign promosi</b>
                        <select
                            className="rounded-xl border p-3"
                            value={form.data.marketing_campaign_id}
                            onChange={(event) =>
                                form.setData(
                                    "marketing_campaign_id",
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">Tanpa campaign</option>
                            {campaigns.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.nama_campaign} · {item.kanal || "-"}
                                </option>
                            ))}
                        </select>
                        <small className="text-red-600">
                            {form.errors.marketing_campaign_id}
                        </small>
                    </label>
                    <label className="grid gap-1">
                        <b>Tingkat minat</b>
                        <select
                            className="rounded-xl border p-3"
                            value={form.data.interest_level}
                            onChange={(event) =>
                                form.setData(
                                    "interest_level",
                                    event.target.value,
                                )
                            }
                        >
                            {["cold", "warm", "hot"].map((item) => (
                                <option key={item}>{item}</option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1">
                        <b>Rencana pembayaran</b>
                        <select
                            className="rounded-xl border p-3"
                            value={form.data.preferred_payment_method}
                            onChange={(event) =>
                                form.setData(
                                    "preferred_payment_method",
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">Belum diketahui</option>
                            <option value="cash">Cash</option>
                            <option value="cash_installment">
                                Cash Bertahap
                            </option>
                            <option value="kpr">KPR</option>
                        </select>
                    </label>
                    <label className="grid gap-1">
                        <b>Persetujuan dihubungi</b>
                        <select
                            className="rounded-xl border p-3"
                            value={form.data.consent_status}
                            onChange={(event) =>
                                form.setData(
                                    "consent_status",
                                    event.target.value,
                                )
                            }
                        >
                            <option value="unknown">Belum dikonfirmasi</option>
                            <option value="granted">Bersedia dihubungi</option>
                            <option value="denied">Tidak bersedia</option>
                        </select>
                    </label>
                </div>
                <section className="grid gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <b>Periksa duplikasi Lead</b>
                            <p className="text-sm text-ink-soft">
                                Pemeriksaan memakai telepon, email, dan NIK
                                sebelum data disimpan.
                            </p>
                        </div>
                        <button
                            type="button"
                            className="rounded-xl border bg-white px-4 py-2 font-bold"
                            disabled={checkingDuplicates || !form.data.phone}
                            onClick={checkDuplicates}
                        >
                            {checkingDuplicates
                                ? "Memeriksa..."
                                : "Periksa Sekarang"}
                        </button>
                    </div>
                    {duplicates.length === 0 ? (
                        <p className="text-sm text-ink-soft">
                            Belum ada kandidat duplikat dari pemeriksaan
                            terakhir.
                        </p>
                    ) : (
                        <div className="grid gap-2">
                            <p className="font-bold text-amber-900">
                                Ditemukan {duplicates.length} kandidat serupa.
                                Gunakan data lama atau pilih satu kandidat dan
                                jelaskan mengapa ini orang yang berbeda.
                            </p>
                            {duplicates.map((item) => (
                                <label
                                    className="grid gap-1 rounded-xl border bg-white p-3"
                                    key={item.id}
                                >
                                    <span className="flex items-start gap-2">
                                        <input
                                            type="radio"
                                            name="duplicate_acknowledged_id"
                                            value={item.id}
                                            checked={
                                                Number(
                                                    form.data
                                                        .duplicate_acknowledged_id,
                                                ) === item.id
                                            }
                                            onChange={() =>
                                                form.setData(
                                                    "duplicate_acknowledged_id",
                                                    item.id,
                                                )
                                            }
                                        />
                                        <span>
                                            <b>
                                                {item.lead_no} · {item.name}
                                            </b>
                                            <span className="block text-sm">
                                                {item.phone || "-"} ·{" "}
                                                {item.email || "-"} ·{" "}
                                                {item.marketing ||
                                                    "Belum dibagi"}{" "}
                                                · {item.stage}
                                            </span>
                                        </span>
                                    </span>
                                    <Link
                                        className="w-fit text-sm font-bold text-gold-deep"
                                        href={item.url}
                                    >
                                        Buka Lead lama
                                    </Link>
                                </label>
                            ))}
                            <textarea
                                className="min-h-24 rounded-xl border p-3"
                                placeholder="Alasan data ini benar-benar orang/prospek berbeda *"
                                value={form.data.duplicate_override_reason}
                                onChange={(event) =>
                                    form.setData(
                                        "duplicate_override_reason",
                                        event.target.value,
                                    )
                                }
                            />
                            <small className="text-red-600">
                                {form.errors.duplicate_override_reason}
                            </small>
                        </div>
                    )}
                </section>
                {form.data.consent_status === "granted" && (
                    <fieldset className="rounded-xl border p-4">
                        <legend className="px-2 font-bold">
                            Kanal yang disetujui
                        </legend>
                        <div className="flex flex-wrap gap-4">
                            {[
                                ["phone", "Telepon"],
                                ["whatsapp", "WhatsApp"],
                                ["email", "Email"],
                            ].map(([value, label]) => (
                                <label
                                    className="flex items-center gap-2"
                                    key={value}
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.consent_channels.includes(
                                            value,
                                        )}
                                        onChange={() => toggleChannel(value)}
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                )}
                <label className="grid gap-1">
                    <b>Catatan awal</b>
                    <textarea
                        className="min-h-28 rounded-xl border p-3"
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData("notes", event.target.value)
                        }
                    />
                </label>
                <div className="flex gap-2">
                    <Link
                        href="/admin/marketing/leads"
                        className="rounded-xl border px-4 py-3 font-bold"
                    >
                        Batal
                    </Link>
                    <button
                        disabled={form.processing}
                        className="rounded-xl bg-ink px-4 py-3 font-bold text-white"
                    >
                        Simpan Lead
                    </button>
                </div>
            </form>
        </>
    );
}

Form.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Lead"}>{page}</AdminLayout>
);
