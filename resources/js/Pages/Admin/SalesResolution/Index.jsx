import { Head, router, useForm } from "@inertiajs/react";
import { AlertTriangle, Lock, Plus, RotateCcw, Unlock } from "lucide-react";
import { useState } from "react";
import {
    Button,
    Dropdown,
    Input,
    Modal,
    Textarea,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const actionLabels = {
    retry_stage: "Ulangi Tahap",
    change_payment_method: "Alihkan Metode Pembayaran",
    close_lost: "Tutup sebagai Gagal",
};
const methodLabels = {
    cash: "Tunai",
    cash_bertahap: "Tunai Bertahap",
    kpr_bank: "KPR Bank",
    kpr_developer: "KPR Developer",
};

export default function Index({ title, baseUrl, rows, transactions = [] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        sales_transaction_id: "",
        action: "retry_stage",
        failed_stage: "",
        failure_category: "",
        failure_reason: "",
        proposed_payment_method: "",
        restart_stage: "",
        financial_treatment: "review_required",
        resolution_notes: "",
    });
    const selected = transactions.find(
        (item) => String(item.value) === String(form.data.sales_transaction_id),
    );
    const submit = (event) => {
        event.preventDefault();
        form.post(baseUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <>
            <Head title={title} />
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title="Buat Usulan Tindak Lanjut"
                size="lg"
                footer={
                    <>
                        <Button
                            variant="outline"
                            type="button"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" form="resolution-form">
                            Simpan Draf
                        </Button>
                    </>
                }
            >
                <form
                    id="resolution-form"
                    className="grid gap-4"
                    onSubmit={submit}
                >
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-400/25 dark:bg-amber-400/10 dark:text-amber-100">
                        Usulan ini belum mengubah transaksi. Perubahan baru
                        diterapkan setelah dikunci dan disetujui melalui
                        Pengaturan Persetujuan.
                    </div>
                    <div className="grid gap-2">
                        <span className="text-sm font-extrabold">
                            Transaksi Penjualan
                        </span>
                        <Dropdown
                            value={form.data.sales_transaction_id}
                            options={transactions}
                            onChange={(value) => {
                                const trx = transactions.find(
                                    (item) =>
                                        String(item.value) === String(value),
                                );
                                form.setData({
                                    ...form.data,
                                    sales_transaction_id: value,
                                    failed_stage: trx?.stage ?? "",
                                    restart_stage: trx?.stage ?? "",
                                });
                            }}
                        />
                        {form.errors.sales_transaction_id && (
                            <small className="text-red-600">
                                {form.errors.sales_transaction_id}
                            </small>
                        )}
                    </div>
                    {selected && (
                        <p className="rounded-lg bg-silver-soft p-3 text-sm font-bold">
                            Metode saat ini:{" "}
                            {methodLabels[selected.payment_method] ??
                                selected.payment_method}{" "}
                            · Tahap aktif: {selected.stage ?? "-"}
                        </p>
                    )}
                    <div className="grid gap-2">
                        <span className="text-sm font-extrabold">Tindakan</span>
                        <Dropdown
                            value={form.data.action}
                            options={Object.entries(actionLabels).map(
                                ([value, label]) => ({ value, label }),
                            )}
                            onChange={(value) => form.setData("action", value)}
                        />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Input
                            label="Tahap yang Gagal"
                            value={form.data.failed_stage}
                            onChange={(e) =>
                                form.setData("failed_stage", e.target.value)
                            }
                        />
                        <Input
                            label="Kategori Penyebab"
                            required
                            value={form.data.failure_category}
                            error={form.errors.failure_category}
                            onChange={(e) =>
                                form.setData("failure_category", e.target.value)
                            }
                        />
                    </div>
                    <Textarea
                        label="Alasan / Bukti Kegagalan"
                        required
                        value={form.data.failure_reason}
                        error={form.errors.failure_reason}
                        onChange={(e) =>
                            form.setData("failure_reason", e.target.value)
                        }
                    />
                    {form.data.action === "change_payment_method" && (
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Metode Pembayaran Baru
                            </span>
                            <Dropdown
                                value={form.data.proposed_payment_method}
                                options={Object.entries(methodLabels).map(
                                    ([value, label]) => ({ value, label }),
                                )}
                                onChange={(value) =>
                                    form.setData(
                                        "proposed_payment_method",
                                        value,
                                    )
                                }
                            />
                            {form.errors.proposed_payment_method && (
                                <small className="text-red-600">
                                    {form.errors.proposed_payment_method}
                                </small>
                            )}
                        </div>
                    )}
                    {form.data.action === "retry_stage" && (
                        <Input
                            label="Mulai Kembali dari Tahap"
                            value={form.data.restart_stage}
                            onChange={(e) =>
                                form.setData("restart_stage", e.target.value)
                            }
                        />
                    )}
                    <div className="grid gap-2">
                        <span className="text-sm font-extrabold">
                            Perlakuan Dana yang Sudah Masuk
                        </span>
                        <Dropdown
                            value={form.data.financial_treatment}
                            options={[
                                {
                                    value: "review_required",
                                    label: "Wajib Ditinjau Keuangan",
                                },
                                {
                                    value: "carry_forward",
                                    label: "Dialihkan ke Pengajuan Baru",
                                },
                                {
                                    value: "refund",
                                    label: "Proses Pengembalian Dana",
                                },
                                {
                                    value: "forfeit",
                                    label: "Tidak Dikembalikan Sesuai Ketentuan",
                                },
                            ]}
                            onChange={(value) =>
                                form.setData("financial_treatment", value)
                            }
                        />
                    </div>
                    <Textarea
                        label="Rencana Penyelesaian"
                        value={form.data.resolution_notes}
                        onChange={(e) =>
                            form.setData("resolution_notes", e.target.value)
                        }
                    />
                </form>
            </Modal>
            <div className="grid gap-5">
                <section className="rounded-2xl bg-gradient-to-br from-ink to-graphite p-6 text-white">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-black uppercase tracking-widest text-champagne">
                                Kontrol Pengecualian Penjualan
                            </p>
                            <h1 className="mt-2 text-2xl font-black">
                                {title}
                            </h1>
                            <p className="mt-2 text-sm text-white/65">
                                Tangani tahap ditolak, pengalihan metode
                                pembayaran, atau pelanggan yang benar-benar
                                tidak dapat dilanjutkan secara terlacak.
                            </p>
                        </div>
                        <Button type="button" onClick={() => setOpen(true)}>
                            <Plus size={17} /> Buat Tindak Lanjut
                        </Button>
                    </div>
                </section>
                <section className="overflow-hidden rounded-2xl border border-silver-deep/60 bg-white">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft text-left">
                                <tr>
                                    {[
                                        "Nomor",
                                        "Transaksi / Pelanggan",
                                        "Marketing",
                                        "Tindakan",
                                        "Penyebab",
                                        "Status",
                                        "Aksi",
                                    ].map((x) => (
                                        <th className="px-4 py-3" key={x}>
                                            {x}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.data.map((row) => (
                                    <tr className="border-t" key={row.id}>
                                        <td className="px-4 py-4 font-black">
                                            {row.request_no}
                                        </td>
                                        <td className="px-4 py-4">
                                            <b>{row.transaction}</b>
                                            <br />
                                            <span>{row.customer}</span>
                                            <br />
                                            <small>{row.spr}</small>
                                        </td>
                                        <td className="px-4 py-4">
                                            {row.marketing}
                                        </td>
                                        <td className="px-4 py-4 font-bold">
                                            {actionLabels[row.action]}
                                            {row.proposed_payment_method && (
                                                <small className="block">
                                                    ke{" "}
                                                    {
                                                        methodLabels[
                                                            row
                                                                .proposed_payment_method
                                                        ]
                                                    }
                                                </small>
                                            )}
                                        </td>
                                        <td className="max-w-xs px-4 py-4">
                                            <b>{row.failure_category}</b>
                                            <small className="block">
                                                {row.failed_stage ?? "-"}
                                            </small>
                                            <span>{row.failure_reason}</span>
                                        </td>
                                        <td className="px-4 py-4">
                                            <b>{row.status}</b>
                                            <small className="block">
                                                Persetujuan:{" "}
                                                {row.approval_status ?? "-"}{" "}
                                                {row.approval_stage ?? ""}
                                            </small>
                                        </td>
                                        <td className="px-4 py-4">
                                            <TableActions>
                                                {row.can_lock && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(
                                                                `${baseUrl}/${row.id}/lock`,
                                                            )
                                                        }
                                                    >
                                                        <Lock size={15} />{" "}
                                                        Ajukan Persetujuan
                                                    </Button>
                                                )}
                                                {row.can_review && (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admin/approval/${row.approval_id}/approve`,
                                                                )
                                                            }
                                                        >
                                                            Setujui
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => {
                                                                const note =
                                                                    window.prompt(
                                                                        "Alasan penolakan",
                                                                    );
                                                                if (note)
                                                                    router.post(
                                                                        `/admin/approval/${row.approval_id}/reject`,
                                                                        {
                                                                            note,
                                                                        },
                                                                    );
                                                            }}
                                                        >
                                                            Tolak
                                                        </Button>
                                                    </>
                                                )}
                                                {row.can_unlock && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `${baseUrl}/${row.id}/unlock`,
                                                            )
                                                        }
                                                    >
                                                        <Unlock size={15} />{" "}
                                                        Buka Draf
                                                    </Button>
                                                )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {!rows.data.length && (
                                    <tr>
                                        <td
                                            className="px-4 py-10 text-center"
                                            colSpan="7"
                                        >
                                            Belum ada kasus yang dibuat.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Penanganan Proses Gagal"}>
        {page}
    </AdminLayout>
);
