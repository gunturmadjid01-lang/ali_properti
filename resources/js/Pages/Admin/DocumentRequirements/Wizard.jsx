import { Head, Link, useForm } from "@inertiajs/react";
import { useState } from "react";
import { Button, Dropdown, Input, Textarea } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
const jobs = [
    ["pns", "PNS / ASN"],
    ["tni_polri", "TNI / Polri"],
    ["bumn", "BUMN/BUMD"],
    ["pegawai_swasta", "Pegawai Swasta"],
    ["wiraswasta", "Wiraswasta"],
    ["profesional", "Profesional"],
    ["pensiunan", "Pensiunan"],
    ["lainnya", "Lainnya"],
];
const marital = [
    ["belum menikah", "Belum Menikah"],
    ["menikah", "Menikah"],
    ["cerai", "Cerai"],
];
const processStages = [
    { value: "", label: "Tahap otomatis sesuai metode" },
    { value: "document_collection", label: "KPR Bank - Pengumpulan Dokumen" },
    { value: "document_validation", label: "KPR Developer - Validasi Dokumen" },
    { value: "contract_review", label: "Cash Bertahap - Pemeriksaan Kontrak" },
    { value: "contract_signing", label: "Penandatanganan Kontrak" },
    { value: "contract_preparation", label: "Persiapan Akad" },
    { value: "customer_handover", label: "Serah Terima Customer" },
];
const Toggle = ({ label, checked, onChange }) => (
    <label className="flex gap-2 rounded-lg border border-ink/50 bg-white/75 p-3 transition hover:border-amber-500 dark:border-white/35 dark:bg-white/6">
        <input
            type="checkbox"
            checked={checked}
            onChange={(e) => onChange(e.target.checked)}
        />
        {label}
    </label>
);
const Multi = ({ title, options, values, onChange }) => (
    <div>
        <b>{title}</b>
        <div className="mt-2 grid gap-2 md:grid-cols-2">
            {options.map((x) => (
                <Toggle
                    key={x.value}
                    label={x.label}
                    checked={values.map(String).includes(String(x.value))}
                    onChange={(yes) =>
                        onChange(
                            yes
                                ? [...values, String(x.value)]
                                : values.filter(
                                      (v) => String(v) !== String(x.value),
                                  ),
                        )
                    }
                />
            ))}
        </div>
    </div>
);
export default function Wizard({
    title,
    indexUrl,
    actionUrl,
    method,
    row,
    options,
}) {
    const [page, setPage] = useState(0);
    const steps = [
        "Identitas Paket",
        "Daftar Dokumen & Kondisi",
        "Cakupan Penerapan",
        "Pratinjau & Simpan",
    ];
    const f = useForm({
        code: row?.code ?? "",
        name: row?.name ?? "",
        description: row?.description ?? "",
        status: row?.status ?? "aktif",
        application_types: row?.application_types ?? [],
        bank_ids: (row?.bank_ids ?? []).map(String),
        product_ids: (row?.product_ids ?? []).map(String),
        housing_ids: (row?.housing_ids ?? []).map(String),
        company_ids: (row?.company_ids ?? []).map(String),
        partnership_ids: (row?.partnership_ids ?? []).map(String),
        items: row?.items ?? [],
    });
    const setItem = (i, k, v) =>
        f.setData(
            "items",
            f.data.items.map((x, n) => (n === i ? { ...x, [k]: v } : x)),
        );
    const submit = (e) => {
        e.preventDefault();
        f[method](actionUrl);
    };
    return (
        <>
            <Head title={title} />
            <div className="mx-auto grid max-w-6xl gap-5">
                <header className="admin-page-hero flex justify-between rounded-xl border p-5">
                    <div>
                        <p className="text-xs font-black uppercase text-ink-soft">
                            Panduan Repositori Dokumen
                        </p>
                        <h1 className="text-2xl font-black">{title}</h1>
                    </div>
                    <Button as={Link} href={indexUrl} variant="outline">
                        Kembali
                    </Button>
                </header>
                <nav className="flex gap-2 overflow-auto rounded-xl border border-ink/60 bg-white/90 p-3 dark:border-white/40 dark:bg-white/7">
                    {steps.map((x, i) => (
                        <Button
                            key={x}
                            variant={page === i ? "primary" : "ghost"}
                            onClick={() => setPage(i)}
                        >
                            {i + 1}. {x}
                        </Button>
                    ))}
                </nav>
                <form
                    onSubmit={submit}
                    className="rounded-xl border border-ink/60 bg-white/90 p-6 dark:border-white/40 dark:bg-white/7"
                >
                    {page === 0 && (
                        <div className="grid gap-4 md:grid-cols-2">
                            <Input
                                label="Kode Paket"
                                value={f.data.code}
                                onChange={(e) =>
                                    f.setData("code", e.target.value)
                                }
                            />
                            <Input
                                label="Nama Paket"
                                value={f.data.name}
                                onChange={(e) =>
                                    f.setData("name", e.target.value)
                                }
                            />
                            <Textarea
                                className="md:col-span-2"
                                label="Keterangan"
                                value={f.data.description}
                                onChange={(e) =>
                                    f.setData("description", e.target.value)
                                }
                            />
                            <Multi
                                title="Digunakan Pada"
                                values={f.data.application_types}
                                onChange={(v) =>
                                    f.setData("application_types", v)
                                }
                                options={[
                                    ["spr", "SPR"],
                                    ["cash_bertahap", "Tunai Bertahap"],
                                    ["kpr_developer", "KPR Developer"],
                                    ["kpr_bank", "KPR Bank"],
                                ].map(([value, label]) => ({ value, label }))}
                            />
                        </div>
                    )}
                    {page === 1 && (
                        <div className="grid gap-4">
                            <p className="rounded-lg border border-blue-200 bg-blue-50 p-4 text-blue-900 dark:border-blue-400/25 dark:bg-blue-400/10 dark:text-blue-100">
                                Pilih dari Jenis Dokumen Pelanggan. Kondisi
                                kosong berarti berlaku untuk semua
                                pekerjaan/status perkawinan.
                            </p>
                            {f.data.items.map((item, i) => (
                                <div
                                    className="grid gap-3 rounded-xl border p-4"
                                    key={i}
                                >
                                    <div className="grid gap-3 md:grid-cols-4">
                                        <Dropdown
                                            value={String(
                                                item.dokumen_costumer_id,
                                            )}
                                            options={options.documents}
                                            onChange={(v) =>
                                                setItem(
                                                    i,
                                                    "dokumen_costumer_id",
                                                    v,
                                                )
                                            }
                                        />
                                        <Dropdown
                                            value={
                                                item.party_scope ?? "customer"
                                            }
                                            options={[
                                                {
                                                    value: "customer",
                                                    label: "Pelanggan",
                                                },
                                                {
                                                    value: "spouse",
                                                    label: "Pasangan",
                                                },
                                                {
                                                    value: "both",
                                                    label: "Pelanggan dan Pasangan",
                                                },
                                            ]}
                                            onChange={(v) =>
                                                setItem(i, "party_scope", v)
                                            }
                                        />
                                        <Dropdown
                                            value={item.process_stage_code ?? ""}
                                            options={processStages}
                                            onChange={(v) => setItem(i, "process_stage_code", v)}
                                        />
                                        <Toggle
                                            label="Wajib"
                                            checked={!!item.is_required}
                                            onChange={(v) =>
                                                setItem(i, "is_required", v)
                                            }
                                        />
                                    </div>
                                    <Multi
                                        title="Khusus Kategori Pekerjaan"
                                        values={
                                            item.employment_categories ?? []
                                        }
                                        onChange={(v) =>
                                            setItem(
                                                i,
                                                "employment_categories",
                                                v,
                                            )
                                        }
                                        options={jobs.map(([value, label]) => ({
                                            value,
                                            label,
                                        }))}
                                    />
                                    <Multi
                                        title="Khusus Status Perkawinan"
                                        values={item.marital_statuses ?? []}
                                        onChange={(v) =>
                                            setItem(i, "marital_statuses", v)
                                        }
                                        options={marital.map(
                                            ([value, label]) => ({
                                                value,
                                                label,
                                            }),
                                        )}
                                    />
                                    <Button
                                        type="button"
                                        variant="danger"
                                        onClick={() =>
                                            f.setData(
                                                "items",
                                                f.data.items.filter(
                                                    (_, n) => n !== i,
                                                ),
                                            )
                                        }
                                    >
                                        Hapus Dokumen
                                    </Button>
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    f.setData("items", [
                                        ...f.data.items,
                                        {
                                            dokumen_costumer_id: "",
                                            employment_categories: [],
                                            marital_statuses: [],
                                            party_scope: "customer",
                                            is_required: true,
                                            validity_days: "",
                                            notes: "",
                                        },
                                    ])
                                }
                            >
                                Tambah dari Repositori
                            </Button>
                        </div>
                    )}
                    {page === 2 && (
                        <div className="grid gap-6">
                            <p className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900 dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-100">
                                Kosongkan suatu kelompok jika paket berlaku
                                untuk semuanya. Pilihan dalam kelompok yang sama
                                bersifat ATAU; antarkelompok bersifat DAN.
                            </p>
                            <Multi
                                title="Perusahaan / Cabang"
                                options={options.companies}
                                values={f.data.company_ids}
                                onChange={(v) => f.setData("company_ids", v)}
                            />
                            <Multi
                                title="Perumahan"
                                options={options.housings}
                                values={f.data.housing_ids}
                                onChange={(v) => f.setData("housing_ids", v)}
                            />
                            <Multi
                                title="Bank"
                                options={options.banks}
                                values={f.data.bank_ids}
                                onChange={(v) => f.setData("bank_ids", v)}
                            />
                            <Multi
                                title="Produk Kredit"
                                options={options.products}
                                values={f.data.product_ids}
                                onChange={(v) => f.setData("product_ids", v)}
                            />
                            <Multi
                                title="Kontrak Kerja Sama Bank"
                                options={options.partnerships}
                                values={f.data.partnership_ids}
                                onChange={(v) =>
                                    f.setData("partnership_ids", v)
                                }
                            />
                        </div>
                    )}
                    {page === 3 && (
                        <div className="grid gap-3">
                            <h2 className="text-xl font-black">
                                {f.data.code} — {f.data.name}
                            </h2>
                            <p>
                                {f.data.items.length} dokumen ·{" "}
                                {f.data.application_types.length} proses ·{" "}
                                {f.data.housing_ids.length} perumahan ·{" "}
                                {f.data.bank_ids.length} bank ·{" "}
                                {f.data.partnership_ids.length} kontrak kerja
                                sama
                            </p>
                            {Object.values(f.errors).map((e, i) => (
                                <p className="text-red-600" key={i}>
                                    {e}
                                </p>
                            ))}
                        </div>
                    )}
                    <footer className="mt-6 flex justify-between border-t pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={page === 0}
                            onClick={() => setPage(page - 1)}
                        >
                            Sebelumnya
                        </Button>
                        {page < 3 ? (
                            <Button
                                type="button"
                                onClick={() => setPage(page + 1)}
                            >
                                Selanjutnya
                            </Button>
                        ) : (
                            <Button type="submit" disabled={f.processing}>
                                Simpan Paket
                            </Button>
                        )}
                    </footer>
                </form>
            </div>
        </>
    );
}
Wizard.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Panduan Dokumen"}>
        {page}
    </AdminLayout>
);
