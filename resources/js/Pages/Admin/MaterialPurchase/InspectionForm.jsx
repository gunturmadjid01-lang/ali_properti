import { Head, router, useForm } from "@inertiajs/react";
import { ArrowLeft, Save } from "lucide-react";
import { Button, Dropdown, Input, Textarea } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

function ItemForm({ purchase, item, submitBaseUrl }) {
    const form = useForm({
        status: "sesuai",
        tanggal_barang_masuk: purchase.tanggal_barang_masuk,
        qty_faktur: item.qty_faktur ?? item.qty,
        invoice_unit_price: item.harga_satuan ?? 0,
        qty_fisik_tiba: item.qty_fisik_tiba ?? item.qty,
        qty_diterima: item.qty_diterima || item.qty,
        qty_cacat: item.qty_cacat ?? 0,
        qty_ditolak: item.qty_ditolak ?? 0,
        kondisi_fisik: "baik",
        alasan_selisih: "",
        catatan: item.inspection_note ?? "",
    });
    const submit = (event) => {
        event.preventDefault();
        form.post(`${submitBaseUrl}/${purchase.id}/item/${item.id}`, {
            preserveScroll: true,
        });
    };
    return (
        <form
            onSubmit={submit}
            className="rounded-xl border border-silver-deep/60 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/6"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-black">{item.material}</h3>
                    <p className="text-sm font-semibold text-ink-soft">
                        Dipesan {item.qty} {item.satuan} · Status{" "}
                        {item.inspection_status}
                    </p>
                </div>
                {item.inspection_status !== "pending" && (
                    <span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-700">
                        Sudah diperiksa
                    </span>
                )}
            </div>
            {item.inspection_status === "pending" && (
                <>
                    <div className="mt-4 grid gap-3 md:grid-cols-4">
                        <Input
                            label="Qty Faktur"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.qty_faktur}
                            onChange={(e) =>
                                form.setData("qty_faktur", e.target.value)
                            }
                        />
                        <Input
                            label="Harga Faktur/Unit"
                            type="number"
                            min="0"
                            value={form.data.invoice_unit_price}
                            onChange={(e) =>
                                form.setData(
                                    "invoice_unit_price",
                                    e.target.value,
                                )
                            }
                        />
                        <Input
                            label="Fisik Tiba"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.qty_fisik_tiba}
                            onChange={(e) =>
                                form.setData("qty_fisik_tiba", e.target.value)
                            }
                        />
                        <Input
                            label="Diterima Baik"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.qty_diterima}
                            onChange={(e) =>
                                form.setData("qty_diterima", e.target.value)
                            }
                        />
                        <Input
                            label="Cacat"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.qty_cacat}
                            onChange={(e) =>
                                form.setData("qty_cacat", e.target.value)
                            }
                        />
                        <Input
                            label="Ditolak"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.data.qty_ditolak}
                            onChange={(e) =>
                                form.setData("qty_ditolak", e.target.value)
                            }
                        />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Kondisi Fisik
                            </span>
                            <Dropdown
                                value={form.data.kondisi_fisik}
                                searchable={false}
                                options={[
                                    { value: "baik", label: "Baik" },
                                    {
                                        value: "layak_pakai",
                                        label: "Layak Pakai",
                                    },
                                    { value: "cacat", label: "Cacat" },
                                    { value: "rusak", label: "Rusak" },
                                ]}
                                onChange={(value) =>
                                    form.setData("kondisi_fisik", value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Keputusan
                            </span>
                            <Dropdown
                                value={form.data.status}
                                searchable={false}
                                options={[
                                    { value: "sesuai", label: "Sesuai" },
                                    {
                                        value: "tidak_sesuai",
                                        label: "Ada Selisih",
                                    },
                                ]}
                                onChange={(value) =>
                                    form.setData("status", value)
                                }
                            />
                        </div>
                    </div>
                    <div className="mt-3 grid gap-3 md:grid-cols-2">
                        <Textarea
                            label="Alasan Selisih"
                            value={form.data.alasan_selisih}
                            onChange={(e) =>
                                form.setData("alasan_selisih", e.target.value)
                            }
                        />
                        <Textarea
                            label="Catatan Pemeriksaan"
                            value={form.data.catatan}
                            onChange={(e) =>
                                form.setData("catatan", e.target.value)
                            }
                        />
                    </div>
                    <div className="mt-4 flex justify-end">
                        <Button type="submit" disabled={form.processing}>
                            <Save size={16} /> Simpan Pemeriksaan Item
                        </Button>
                    </div>
                </>
            )}
        </form>
    );
}

export default function InspectionForm({
    title,
    indexUrl,
    submitBaseUrl,
    purchase,
}) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-black">{title}</h1>
                        <p className="text-sm font-semibold text-ink-soft">
                            {purchase.supplier} · {purchase.gudang} · Faktur{" "}
                            {purchase.nomor_faktur || "-"} · Surat Jalan{" "}
                            {purchase.nomor_surat_jalan || "-"}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => router.visit(indexUrl)}
                    >
                        <ArrowLeft size={16} /> Kembali
                    </Button>
                </div>
                <section className="rounded-xl border border-white/80 bg-white/75 p-4 dark:border-white/10 dark:bg-white/5">
                    <Input
                        label="Tanggal Barang Masuk"
                        type="date"
                        value={purchase.tanggal_barang_masuk}
                        readOnly
                    />
                </section>
                {purchase.items.map((item) => (
                    <ItemForm
                        key={item.id}
                        purchase={purchase}
                        item={item}
                        submitBaseUrl={submitBaseUrl}
                    />
                ))}
            </div>
        </>
    );
}

InspectionForm.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Pemeriksaan Barang Masuk"}>
        {page}
    </AdminLayout>
);
