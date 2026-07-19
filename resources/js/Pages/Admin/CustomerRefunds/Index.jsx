import { useState } from "react";
import { Head, router, useForm } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";
import { FinanceChart } from "@/Components/Finance/FinanceChart";
import {
    Button,
    CurrencyInput,
    Dropdown,
    Input,
    Modal,
    Textarea,
    TableActions,
} from "@/Components/UI";

const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value || 0));

export default function Index({ title, rows, banks }) {
    const [editing, setEditing] = useState(null);
    const form = useForm({
        master_bank_id: "",
        eligible_amount: 0,
        penalty_amount: 0,
        refund_amount: 0,
        refund_date: "",
        recipient_name: "",
        recipient_bank: "",
        recipient_account: "",
        transfer_reference: "",
        proof: null,
        notes: "",
    });
    const open = (row) => {
        setEditing(row);
        form.setData({
            master_bank_id: "",
            eligible_amount: row.eligible_amount,
            penalty_amount: row.penalty_amount,
            refund_amount: row.refund_amount,
            refund_date: row.refund_date || "",
            recipient_name: row.recipient_name || row.customer || "",
            recipient_bank: row.recipient_bank || "",
            recipient_account: row.recipient_account || "",
            transfer_reference: row.transfer_reference || "",
            proof: null,
            notes: row.notes || "",
        });
    };
    const submit = (event) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: "put" })).post(
            `/admin/keuangan/refund-customer/${editing.id}`,
            { forceFormData: true, onSuccess: () => setEditing(null) },
        );
    };
    const post = (url, data = {}) =>
        router.post(url, data, { preserveScroll: true });

    return (
        <AdminLayout>
            <Head title={title} />
            <section className="space-y-5 p-4 md:p-6">
                <div>
                    <p className="text-xs font-black uppercase tracking-[0.16em] text-ink-soft">
                        Keuangan Customer
                    </p>
                    <h1 className="mt-1 text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-sm text-ink-soft">
                        Draf dibuat otomatis setelah penutupan penjualan dengan
                        perlakuan dana Refund disetujui.
                    </p>
                </div>
                <FinanceChart
                    title="Ringkasan Refund Booking Fee dan DP"
                    subtitle="Dihitung dari daftar refund yang tampil pada halaman ini."
                    items={[
                        {
                            label: "Dana Tersedia",
                            value: rows.data.reduce(
                                (sum, row) =>
                                    sum + Number(row.eligible_amount || 0),
                                0,
                            ),
                            tone: "bg-blue-500",
                        },
                        {
                            label: "Potongan",
                            value: rows.data.reduce(
                                (sum, row) =>
                                    sum + Number(row.penalty_amount || 0),
                                0,
                            ),
                            tone: "bg-amber-500",
                        },
                        {
                            label: "Dikembalikan",
                            value: rows.data.reduce(
                                (sum, row) =>
                                    sum + Number(row.refund_amount || 0),
                                0,
                            ),
                        },
                    ]}
                />
                <div className="overflow-x-auto rounded-2xl border border-silver-deep/50 bg-white shadow-soft">
                    <table className="min-w-full text-sm">
                        <thead className="bg-silver-soft/70 text-left">
                            <tr>
                                {[
                                    "Refund",
                                    "Transaksi / Customer",
                                    "Dana Tersedia",
                                    "Potongan",
                                    "Dikembalikan",
                                    "Status",
                                    "Aksi",
                                ].map((label) => (
                                    <th
                                        key={label}
                                        className="px-4 py-3 font-black"
                                    >
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((row) => (
                                <tr
                                    key={row.id}
                                    className="border-t border-silver-deep/40"
                                >
                                    <td className="px-4 py-4">
                                        <p className="font-black">
                                            {row.refund_no}
                                        </p>
                                        <p className="text-xs text-ink-soft">
                                            {row.resolution_no}
                                        </p>
                                    </td>
                                    <td className="px-4 py-4">
                                        <p className="font-bold">
                                            {row.transaction}
                                        </p>
                                        <p>{row.customer}</p>
                                        <p className="text-xs text-ink-soft">
                                            {row.housing}
                                        </p>
                                    </td>
                                    <td className="px-4 py-4 font-bold">
                                        {money(row.eligible_amount)}
                                    </td>
                                    <td className="px-4 py-4">
                                        {money(row.penalty_amount)}
                                    </td>
                                    <td className="px-4 py-4 font-black text-emerald-700">
                                        {money(row.refund_amount)}
                                    </td>
                                    <td className="px-4 py-4">
                                        <p className="font-bold">
                                            {row.status?.replaceAll("_", " ")}
                                        </p>
                                        <p className="text-xs text-ink-soft">
                                            {row.approval_stage ||
                                                row.approval_status}
                                        </p>
                                        {row.journal_no && (
                                            <p className="text-xs">
                                                {row.journal_no}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-4">
                                        <TableActions>
                                            {row.can_edit && (
                                                <Button
                                                    type="button"
                                                    onClick={() => open(row)}
                                                >
                                                    Lengkapi
                                                </Button>
                                            )}
                                            {row.can_lock && (
                                                <Button
                                                    type="button"
                                                    onClick={() =>
                                                        post(
                                                            `/admin/keuangan/refund-customer/${row.id}/lock`,
                                                        )
                                                    }
                                                >
                                                    Ajukan
                                                </Button>
                                            )}
                                            {row.can_unlock && (
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    onClick={() =>
                                                        post(
                                                            `/admin/keuangan/refund-customer/${row.id}/unlock`,
                                                        )
                                                    }
                                                >
                                                    Unlock
                                                </Button>
                                            )}
                                            {row.can_review && (
                                                <>
                                                    <Button
                                                        type="button"
                                                        onClick={() =>
                                                            post(
                                                                `/admin/keuangan/refund-customer/${row.id}/review/approve`,
                                                            )
                                                        }
                                                    >
                                                        Approve
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="danger"
                                                        onClick={() => {
                                                            const note =
                                                                window.prompt(
                                                                    "Alasan penolakan",
                                                                );
                                                            if (note)
                                                                post(
                                                                    `/admin/keuangan/refund-customer/${row.id}/review/reject`,
                                                                    { note },
                                                                );
                                                        }}
                                                    >
                                                        Reject
                                                    </Button>
                                                </>
                                            )}
                                            {row.proof_url && (
                                                <a
                                                    className="font-bold text-primary"
                                                    href={row.proof_url}
                                                >
                                                    Bukti
                                                </a>
                                            )}
                                        </TableActions>
                                    </td>
                                </tr>
                            ))}
                            {!rows.data.length && (
                                <tr>
                                    <td
                                        colSpan="7"
                                        className="px-4 py-12 text-center font-bold text-ink-soft"
                                    >
                                        Belum ada pengajuan refund.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
            <Modal
                show={!!editing}
                onClose={() => setEditing(null)}
                title="Lengkapi Refund Customer"
            >
                <form
                    onSubmit={submit}
                    className="grid gap-4 p-1 md:grid-cols-2"
                >
                    <div className="md:col-span-2 rounded-xl bg-silver-soft p-4">
                        <p className="text-xs font-bold text-ink-soft">
                            Dana Booking Fee/DP tersedia
                        </p>
                        <p className="text-xl font-black">
                            {money(form.data.eligible_amount)}
                        </p>
                    </div>
                    <Dropdown
                        label="Rekening Sumber Perusahaan"
                        value={String(form.data.master_bank_id)}
                        options={banks}
                        onChange={(value) =>
                            form.setData("master_bank_id", value)
                        }
                    />
                    <Input
                        label="Tanggal Refund"
                        type="date"
                        value={form.data.refund_date}
                        onChange={(e) =>
                            form.setData("refund_date", e.target.value)
                        }
                    />
                    <CurrencyInput
                        label="Potongan/Penalti"
                        value={form.data.penalty_amount}
                        onChange={(value) => {
                            form.setData("penalty_amount", value);
                            form.setData(
                                "refund_amount",
                                Math.max(
                                    0,
                                    Number(form.data.eligible_amount) -
                                        Number(value),
                                ),
                            );
                        }}
                    />
                    <CurrencyInput
                        label="Nominal Dikembalikan"
                        value={form.data.refund_amount}
                        onChange={(value) =>
                            form.setData("refund_amount", value)
                        }
                    />
                    <Input
                        label="Nama Penerima"
                        value={form.data.recipient_name}
                        onChange={(e) =>
                            form.setData("recipient_name", e.target.value)
                        }
                    />
                    <Input
                        label="Bank Tujuan"
                        value={form.data.recipient_bank}
                        onChange={(e) =>
                            form.setData("recipient_bank", e.target.value)
                        }
                    />
                    <Input
                        label="Nomor Rekening Tujuan"
                        value={form.data.recipient_account}
                        onChange={(e) =>
                            form.setData("recipient_account", e.target.value)
                        }
                    />
                    <Input
                        label="Referensi Transfer"
                        value={form.data.transfer_reference}
                        onChange={(e) =>
                            form.setData("transfer_reference", e.target.value)
                        }
                    />
                    <Input
                        className="md:col-span-2"
                        label="Bukti Transfer"
                        type="file"
                        accept="image/*,.pdf"
                        onChange={(e) =>
                            form.setData("proof", e.target.files?.[0] || null)
                        }
                    />
                    <Textarea
                        className="md:col-span-2"
                        label="Catatan dan Dasar Potongan"
                        value={form.data.notes}
                        onChange={(e) => form.setData("notes", e.target.value)}
                    />
                    <div className="md:col-span-2 flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setEditing(null)}
                        >
                            Batal
                        </Button>
                        <Button disabled={form.processing}>Simpan Draf</Button>
                    </div>
                </form>
            </Modal>
        </AdminLayout>
    );
}
