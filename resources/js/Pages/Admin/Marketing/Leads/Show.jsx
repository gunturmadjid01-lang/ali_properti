import { Head, Link, useForm } from "@inertiajs/react";
import AdminLayout from "../../../../Layouts/AdminLayout";

const box = "rounded-2xl border bg-white p-5";
const stageLabels = {
    new: "Lead Baru",
    contacted: "Sudah Dihubungi",
    nurturing: "Dalam Follow-up",
    qualified: "Diajukan sebagai Qualified",
    postponed: "Ditunda",
    lost: "Tidak Potensial",
    converted: "Menjadi Customer",
};

export default function Show({
    title,
    lead,
    logs = [],
    duplicates = [],
    options = {},
    canEdit,
    canQualify,
    canConvert,
    canVerify,
    canAssign,
}) {
    const stageForm = useForm({
        stage: lead.stage === "converted" ? "qualified" : lead.stage,
        qualification_note: lead.qualification_note || "",
        perumahan_id: lead.perumahan_id || "",
        preferred_payment_method: lead.preferred_payment_method || "",
        interest_level: lead.interest_level || "cold",
        budget_min: lead.budget_min || "",
        budget_max: lead.budget_max || "",
        purchase_timeline: lead.purchase_timeline || "unknown",
        decision_maker: lead.decision_maker || "",
        financing_readiness: lead.financing_readiness || "needs_assessment",
        needs_summary: lead.needs_summary || "",
        main_objection: lead.main_objection || "",
        next_action_at: lead.next_action_at?.slice(0, 16) || "",
        recycle_at: lead.recycle_at?.slice(0, 10) || "",
    });
    const verifyForm = useForm({
        status:
            lead.verification_status === "verified" ? "verified" : "verified",
        note: lead.verification_note || "",
    });
    const assignForm = useForm({
        marketing_id: lead.marketing_id || "",
        response_hours: 2,
        reason: "",
    });
    const mergeForm = useForm({ target_lead_id: "", reason: "" });
    const recycleForm = useForm({ reason: "", next_action_at: "" });
    const consentForm = useForm({
        consent_status: lead.consent_status || "unknown",
        consent_channels: lead.consent_channels || [],
        note: "",
    });
    const timeline = [
        ...(logs || []).map((item) => ({
            id: `log-${item.id}`,
            at: item.created_at,
            title: item.event?.replaceAll("_", " "),
            detail: `${item.user?.name || "Sistem"} · ${item.old_status || "-"} → ${item.new_status || "-"} · ${item.reason || "-"}`,
        })),
        ...(lead.assignments || []).map((item) => ({
            id: `assignment-${item.id}`,
            at: item.assigned_at,
            title: "Penugasan Lead",
            detail: `${item.from_marketing?.name || "Belum dibagi"} → ${item.to_marketing?.name || "-"} · ${item.status} · ${item.reason}`,
        })),
        ...(lead.follow_ups || []).map((item) => ({
            id: `follow-${item.id}`,
            at: item.tanggal_follow_up,
            title: `Follow-up ${item.metode_follow_up || ""}`,
            detail: `${item.user?.name || "-"} · ${item.catatan || "-"}`,
        })),
    ].sort((a, b) => String(b.at || "").localeCompare(String(a.at || "")));

    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="rounded-3xl border bg-white p-6">
                    <p className="text-xs font-bold uppercase text-ink-soft">
                        {lead.ownership_type === "company"
                            ? "Lead Perusahaan"
                            : "Lead Marketing"}
                    </p>
                    <h1 className="text-3xl font-black">
                        {lead.lead_no} · {lead.name}
                    </h1>
                    <p className="mt-2">
                        {lead.phone || "-"} · {lead.email || "-"}
                    </p>
                    <div className="mt-4 flex flex-wrap gap-2">
                        {canEdit && (
                            <Link
                                className="rounded-xl border px-4 py-2 font-bold"
                                href={`/admin/marketing/leads/${lead.id}/edit`}
                            >
                                Edit Data Lead
                            </Link>
                        )}
                        <span className="rounded-full bg-silver px-3 py-1 text-sm font-bold">
                            Skor {lead.qualification_score || 0}/100
                        </span>
                        <span className="rounded-full bg-silver px-3 py-1 text-sm font-bold">
                            {stageLabels[lead.stage] || lead.stage}
                        </span>
                        <span className="rounded-full bg-silver px-3 py-1 text-sm font-bold">
                            Verifikasi: {lead.verification_status}
                        </span>
                        {lead.do_not_contact && (
                            <span className="rounded-full bg-red-100 px-3 py-1 text-sm font-bold text-red-700">
                                Jangan dihubungi
                            </span>
                        )}
                    </div>
                </header>
                <section className={`${box} grid gap-3 md:grid-cols-4`}>
                    {[
                        ["Marketing", lead.marketing?.name],
                        [
                            "Sumber",
                            lead.source?.nama_sumber || lead.source_channel,
                        ],
                        ["Perumahan", lead.perumahan?.nama_perusahaan],
                        ["Cabang", lead.branch?.nama_cabang],
                        ["Tipe diminati", lead.unit_type_interest],
                        [
                            "Unit diminati",
                            lead.unit
                                ? `Blok ${lead.unit.kode_nlok || "-"} / ${lead.unit.nomor_rumah || "-"}`
                                : null,
                        ],
                        ["Campaign", lead.campaign?.nama_campaign],
                        ["Minat", lead.interest_level],
                        ["Pembayaran", lead.preferred_payment_method],
                        [
                            "Anggaran",
                            lead.budget_max
                                ? `Rp ${Number(lead.budget_min || 0).toLocaleString("id-ID")}–${Number(lead.budget_max).toLocaleString("id-ID")}`
                                : "-",
                        ],
                        ["Target beli", lead.purchase_timeline],
                        ["Consent", lead.consent_status],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <p className="text-xs font-bold uppercase text-ink-soft">
                                {label}
                            </p>
                            <p className="mt-1 font-bold">{value || "-"}</p>
                        </div>
                    ))}
                </section>
                {canQualify && (
                    <form
                        className={`${box} grid gap-4`}
                        onSubmit={(event) => {
                            event.preventDefault();
                            consentForm.post(
                                `/admin/marketing/leads/${lead.id}/consent`,
                            );
                        }}
                    >
                        <div>
                            <h2 className="text-xl font-black">
                                Consent Komunikasi
                            </h2>
                            <p className="text-sm text-ink-soft">
                                Penolakan menghentikan follow-up, reminder, SLA,
                                dan recycle sampai consent diberikan kembali.
                            </p>
                        </div>
                        <select
                            className="rounded-xl border p-3"
                            value={consentForm.data.consent_status}
                            onChange={(event) =>
                                consentForm.setData(
                                    "consent_status",
                                    event.target.value,
                                )
                            }
                        >
                            <option value="unknown">Belum dikonfirmasi</option>
                            <option value="granted">Diizinkan</option>
                            <option value="denied">Ditolak</option>
                        </select>
                        {consentForm.data.consent_status === "granted" && (
                            <div className="flex flex-wrap gap-4">
                                {["phone", "whatsapp", "email"].map(
                                    (channel) => (
                                        <label
                                            className="flex items-center gap-2"
                                            key={channel}
                                        >
                                            <input
                                                type="checkbox"
                                                checked={consentForm.data.consent_channels.includes(
                                                    channel,
                                                )}
                                                onChange={(event) =>
                                                    consentForm.setData(
                                                        "consent_channels",
                                                        event.target.checked
                                                            ? [
                                                                  ...consentForm
                                                                      .data
                                                                      .consent_channels,
                                                                  channel,
                                                              ]
                                                            : consentForm.data.consent_channels.filter(
                                                                  (item) =>
                                                                      item !==
                                                                      channel,
                                                              ),
                                                    )
                                                }
                                            />
                                            <span className="capitalize">
                                                {channel}
                                            </span>
                                        </label>
                                    ),
                                )}
                            </div>
                        )}
                        <textarea
                            className="rounded-xl border p-3"
                            placeholder="Sumber, waktu, dan bukti persetujuan atau penolakan"
                            value={consentForm.data.note}
                            onChange={(event) =>
                                consentForm.setData("note", event.target.value)
                            }
                        />
                        {(consentForm.errors.consent_channels ||
                            consentForm.errors.note) && (
                            <p className="text-sm text-red-600">
                                {consentForm.errors.consent_channels ||
                                    consentForm.errors.note}
                            </p>
                        )}
                        <button
                            className="w-fit rounded-xl bg-ink px-4 py-3 font-bold text-white"
                            disabled={consentForm.processing}
                        >
                            Simpan Consent
                        </button>
                    </form>
                )}
                {lead.customer ? (
                    <section className="rounded-2xl border border-green-300 bg-green-50 p-5">
                        <b>Sudah menjadi Customer</b>
                        <p>
                            {lead.customer.kode_costumer} · {lead.customer.nama}{" "}
                            · {lead.customer.customer_stage}
                        </p>
                        <Link
                            className="font-bold text-gold-deep"
                            href={`/admin/marketing/calon-konsumen/${lead.customer.id}`}
                        >
                            Buka Customer
                        </Link>
                    </section>
                ) : (
                    canQualify && (
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                stageForm.post(
                                    `/admin/marketing/leads/${lead.id}/stage`,
                                );
                            }}
                            className={`${box} grid gap-4`}
                        >
                            <div>
                                <h2 className="text-xl font-black">
                                    Checklist Kualifikasi Lead
                                </h2>
                                <p className="text-sm text-ink-soft">
                                    Minimum skor 80 untuk diajukan ke Admin
                                    Sales. Qualified belum berarti Customer.
                                </p>
                            </div>
                            <div className="grid gap-3 md:grid-cols-2">
                                <select
                                    className="rounded-xl border p-3"
                                    value={stageForm.data.stage}
                                    onChange={(event) =>
                                        stageForm.setData(
                                            "stage",
                                            event.target.value,
                                        )
                                    }
                                >
                                    {Object.entries(stageLabels)
                                        .filter(
                                            ([value]) => value !== "converted",
                                        )
                                        .map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                </select>
                                <select
                                    className="rounded-xl border p-3"
                                    value={stageForm.data.interest_level}
                                    onChange={(event) =>
                                        stageForm.setData(
                                            "interest_level",
                                            event.target.value,
                                        )
                                    }
                                >
                                    {["cold", "warm", "hot"].map((item) => (
                                        <option key={item}>{item}</option>
                                    ))}
                                </select>
                                <select
                                    className="rounded-xl border p-3"
                                    value={stageForm.data.perumahan_id}
                                    onChange={(event) =>
                                        stageForm.setData(
                                            "perumahan_id",
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Pilih perumahan</option>
                                    {options.perumahans?.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.nama_perusahaan}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    className="rounded-xl border p-3"
                                    value={
                                        stageForm.data.preferred_payment_method
                                    }
                                    onChange={(event) =>
                                        stageForm.setData(
                                            "preferred_payment_method",
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Rencana pembayaran</option>
                                    <option value="cash">Cash</option>
                                    <option value="cash_installment">
                                        Cash Bertahap
                                    </option>
                                    <option value="kpr">KPR</option>
                                </select>
                                <input
                                    className="rounded-xl border p-3"
                                    type="number"
                                    placeholder="Anggaran minimum"
                                    value={stageForm.data.budget_min}
                                    onChange={(event) =>
                                        stageForm.setData(
                                            "budget_min",
                                            event.target.value,
                                        )
                                    }
                                />
                                <input
                                    className="rounded-xl border p-3"
                                    type="number"
                                    placeholder="Anggaran maksimum"
                                    value={stageForm.data.budget_max}
                                    onChange={(event) =>
                                        stageForm.setData(
                                            "budget_max",
                                            event.target.value,
                                        )
                                    }
                                />
                                <select
                                    className="rounded-xl border p-3"
                                    value={stageForm.data.purchase_timeline}
                                    onChange={(event) =>
                                        stageForm.setData(
                                            "purchase_timeline",
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="unknown">
                                        Target waktu belum diketahui
                                    </option>
                                    <option value="0_3_months">
                                        0–3 bulan
                                    </option>
                                    <option value="3_6_months">
                                        3–6 bulan
                                    </option>
                                    <option value="6_12_months">
                                        6–12 bulan
                                    </option>
                                    <option value="over_12_months">
                                        Lebih dari 12 bulan
                                    </option>
                                </select>
                                <input
                                    className="rounded-xl border p-3"
                                    placeholder="Pengambil keputusan"
                                    value={stageForm.data.decision_maker}
                                    onChange={(event) =>
                                        stageForm.setData(
                                            "decision_maker",
                                            event.target.value,
                                        )
                                    }
                                />
                                <select
                                    className="rounded-xl border p-3"
                                    value={stageForm.data.financing_readiness}
                                    onChange={(event) =>
                                        stageForm.setData(
                                            "financing_readiness",
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="ready">
                                        Pembiayaan siap
                                    </option>
                                    <option value="needs_assessment">
                                        Perlu asesmen
                                    </option>
                                    <option value="not_ready">
                                        Belum siap
                                    </option>
                                </select>
                                <input
                                    className="rounded-xl border p-3"
                                    type="datetime-local"
                                    value={stageForm.data.next_action_at}
                                    onChange={(event) =>
                                        stageForm.setData(
                                            "next_action_at",
                                            event.target.value,
                                        )
                                    }
                                />
                                {stageForm.data.stage === "postponed" && (
                                    <input
                                        className="rounded-xl border p-3"
                                        type="date"
                                        value={stageForm.data.recycle_at}
                                        onChange={(event) =>
                                            stageForm.setData(
                                                "recycle_at",
                                                event.target.value,
                                            )
                                        }
                                        title="Tanggal aktivasi kembali"
                                    />
                                )}
                            </div>
                            <textarea
                                className="rounded-xl border p-3"
                                placeholder="Kebutuhan rumah, lokasi, tipe, dan alasan membeli"
                                value={stageForm.data.needs_summary}
                                onChange={(event) =>
                                    stageForm.setData(
                                        "needs_summary",
                                        event.target.value,
                                    )
                                }
                            />
                            <textarea
                                className="rounded-xl border p-3"
                                placeholder="Keberatan atau hambatan utama"
                                value={stageForm.data.main_objection}
                                onChange={(event) =>
                                    stageForm.setData(
                                        "main_objection",
                                        event.target.value,
                                    )
                                }
                            />
                            <textarea
                                className="rounded-xl border p-3"
                                placeholder="Catatan/bukti kualifikasi wajib"
                                value={stageForm.data.qualification_note}
                                onChange={(event) =>
                                    stageForm.setData(
                                        "qualification_note",
                                        event.target.value,
                                    )
                                }
                            />
                            <small className="text-red-600">
                                {stageForm.errors.stage ||
                                    stageForm.errors.qualification_note}
                            </small>
                            <button className="rounded-xl bg-ink p-3 font-bold text-white">
                                {stageForm.data.stage === "qualified"
                                    ? "Ajukan Qualified ke Admin Sales"
                                    : "Simpan Tahap"}
                            </button>
                        </form>
                    )
                )}
                {lead.qualification_status === "submitted" &&
                    lead.verification_status === "pending" && (
                        <section className="rounded-2xl border border-blue-300 bg-blue-50 p-5">
                            <b>Menunggu verifikasi Admin Sales</b>
                            <p>
                                Marketing tidak dapat mengonversi Lead ini
                                sebelum pemeriksaan selesai.
                            </p>
                        </section>
                    )}
                {canVerify && lead.qualification_status === "submitted" && (
                    <form
                        className={`${box} grid gap-3`}
                        onSubmit={(event) => {
                            event.preventDefault();
                            verifyForm.post(
                                `/admin/admin-sales/lead/${lead.id}/verify`,
                            );
                        }}
                    >
                        <h2 className="font-black">
                            Gerbang Verifikasi Admin Sales
                        </h2>
                        <select
                            className="rounded-xl border p-3"
                            value={verifyForm.data.status}
                            onChange={(event) =>
                                verifyForm.setData("status", event.target.value)
                            }
                        >
                            {[
                                "verified",
                                "duplicate",
                                "spam",
                                "needs_revision",
                            ].map((item) => (
                                <option key={item}>{item}</option>
                            ))}
                        </select>
                        <textarea
                            className="rounded-xl border p-3"
                            placeholder="Keputusan dan catatan wajib"
                            value={verifyForm.data.note}
                            onChange={(event) =>
                                verifyForm.setData("note", event.target.value)
                            }
                        />
                        <button className="rounded-xl bg-ink p-3 font-bold text-white">
                            Simpan Keputusan
                        </button>
                    </form>
                )}
                {canVerify &&
                    duplicates.length > 0 &&
                    !lead.merged_into_lead_id && (
                        <form
                            className={`${box} grid gap-3 border-red-200`}
                            onSubmit={(event) => {
                                event.preventDefault();
                                mergeForm.post(
                                    `/admin/admin-sales/leads/${lead.id}/merge`,
                                );
                            }}
                        >
                            <div>
                                <h2 className="font-black">
                                    Kandidat Duplikat
                                </h2>
                                <p className="text-sm text-ink-soft">
                                    Follow-up, kunjungan, assignment, dan tugas
                                    akan dipindahkan ke Lead utama.
                                </p>
                            </div>
                            <select
                                className="rounded-xl border p-3"
                                value={mergeForm.data.target_lead_id}
                                onChange={(event) =>
                                    mergeForm.setData(
                                        "target_lead_id",
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Pilih Lead utama</option>
                                {duplicates.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.lead_no} · {item.name} ·{" "}
                                        {item.stage}
                                    </option>
                                ))}
                            </select>
                            <textarea
                                className="rounded-xl border p-3"
                                placeholder="Alasan dan bukti duplikat wajib"
                                value={mergeForm.data.reason}
                                onChange={(event) =>
                                    mergeForm.setData(
                                        "reason",
                                        event.target.value,
                                    )
                                }
                            />
                            <button className="rounded-xl bg-red-700 p-3 font-bold text-white">
                                Gabungkan ke Lead Utama
                            </button>
                        </form>
                    )}
                {canAssign &&
                    ["lost", "postponed"].includes(lead.stage) &&
                    !lead.merged_into_lead_id && (
                        <form
                            className={`${box} grid gap-3`}
                            onSubmit={(event) => {
                                event.preventDefault();
                                recycleForm.post(
                                    `/admin/admin-sales/leads/${lead.id}/recycle`,
                                );
                            }}
                        >
                            <div>
                                <h2 className="font-black">Recycle Lead</h2>
                                <p className="text-sm text-ink-soft">
                                    Aktifkan kembali ke nurturing dengan jadwal
                                    tindak lanjut baru.
                                </p>
                            </div>
                            <input
                                className="rounded-xl border p-3"
                                type="datetime-local"
                                value={recycleForm.data.next_action_at}
                                onChange={(event) =>
                                    recycleForm.setData(
                                        "next_action_at",
                                        event.target.value,
                                    )
                                }
                            />
                            <textarea
                                className="rounded-xl border p-3"
                                placeholder="Alasan aktivasi kembali"
                                value={recycleForm.data.reason}
                                onChange={(event) =>
                                    recycleForm.setData(
                                        "reason",
                                        event.target.value,
                                    )
                                }
                            />
                            <button className="rounded-xl bg-ink p-3 font-bold text-white">
                                Aktifkan Kembali
                            </button>
                        </form>
                    )}
                {lead.ownership_type === "company" &&
                    lead.verification_status === "verified" &&
                    canAssign && (
                        <form
                            className={`${box} grid gap-3`}
                            onSubmit={(event) => {
                                event.preventDefault();
                                assignForm.post(
                                    `/admin/admin-sales/leads/${lead.id}/assign`,
                                );
                            }}
                        >
                            <h2 className="font-black">
                                Bagikan kepada Marketing
                            </h2>
                            <select
                                className="rounded-xl border p-3"
                                value={assignForm.data.marketing_id}
                                onChange={(event) =>
                                    assignForm.setData(
                                        "marketing_id",
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Pilih Marketing</option>
                                {options.marketings?.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </select>
                            <input
                                className="rounded-xl border p-3"
                                type="number"
                                min="1"
                                max="72"
                                value={assignForm.data.response_hours}
                                onChange={(event) =>
                                    assignForm.setData(
                                        "response_hours",
                                        event.target.value,
                                    )
                                }
                            />
                            <textarea
                                className="rounded-xl border p-3"
                                placeholder="Alasan pembagian Lead"
                                value={assignForm.data.reason}
                                onChange={(event) =>
                                    assignForm.setData(
                                        "reason",
                                        event.target.value,
                                    )
                                }
                            />
                            <button className="rounded-xl bg-ink p-3 font-bold text-white">
                                Tawarkan Lead
                            </button>
                        </form>
                    )}
                {!lead.customer &&
                    lead.stage === "qualified" &&
                    lead.verification_status === "verified" &&
                    canConvert && (
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                stageForm.post(
                                    `/admin/marketing/leads/${lead.id}/convert`,
                                );
                            }}
                            className="rounded-2xl border border-amber-300 bg-amber-50 p-5"
                        >
                            <h2 className="font-black">
                                Siap menjadi Customer
                            </h2>
                            <p className="my-2">
                                Lead telah qualified dan lolos verifikasi Admin
                                Sales.
                            </p>
                            <button className="rounded-xl bg-amber-700 px-4 py-3 font-bold text-white">
                                Konversi Menjadi Customer
                            </button>
                        </form>
                    )}
                <section className={box}>
                    <h2 className="text-xl font-black">Timeline CRM</h2>
                    {timeline.map((item) => (
                        <div className="border-b py-3 text-sm" key={item.id}>
                            <b className="capitalize">{item.title}</b>
                            <p>{item.detail}</p>
                            <small className="text-ink-soft">
                                {item.at || "-"}
                            </small>
                        </div>
                    ))}
                    {!timeline.length && (
                        <p className="py-5 text-ink-soft">
                            Belum ada aktivitas tercatat.
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

Show.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Detail Lead"}>
        {page}
    </AdminLayout>
);
