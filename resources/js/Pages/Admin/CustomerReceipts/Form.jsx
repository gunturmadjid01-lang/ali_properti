import { Head, useForm } from "@inertiajs/react";
import { useEffect } from "react";
import { Button, CurrencyInput, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
const purposes = {
    booking_fee: "Booking Fee",
    down_payment: "Uang Muka / DP",
    invoice_payment: "Bayar Tagihan",
    accelerated_payment: "Percepatan Tagihan",
    overpayment: "Pembayaran Lebih",
    other: "Penerimaan Lainnya",
};

export default function Form({
    title,
    transactions,
    banks,
    pettyCashAccounts,
    defaults = {},
    storeUrl,
}) {
    const form = useForm({
        sales_transaction_id: defaults.transaction || "",
        master_bank_id: "",
        petty_cash_account_id: "",
        payment_date: new Date().toISOString().slice(0, 10),
        amount: "",
        payment_method: "transfer",
        receipt_purpose: defaults.purpose || "invoice_payment",
        bank_reference: "",
        sender_bank: "",
        sender_name: "",
        proof: null,
        notes: "",
        allocations: [],
    });
    const transaction = transactions.find(
        (item) => item.value === String(form.data.sales_transaction_id),
    );
    const today = form.data.payment_date;
    const schedulePurpose = (schedule) =>
        schedule.purpose === "invoice_payment" && schedule.due_date > today
            ? "accelerated_payment"
            : schedule.purpose;
    const eligibleSchedules = (transaction?.schedules || []).filter(
        (schedule) => schedulePurpose(schedule) === form.data.receipt_purpose,
    );
    const availablePurposes = Object.entries(purposes).filter(
        ([value]) =>
            ["overpayment", "other"].includes(value) ||
            (transaction?.schedules || []).some(
                (schedule) => schedulePurpose(schedule) === value,
            ),
    );
    const allocated = form.data.allocations.reduce(
        (sum, item) => sum + Number(item.amount || 0),
        0,
    );
    const updateTotalReceived = (value) => {
        form.clearErrors("allocations", "amount");
        let remaining = Math.max(0, Number(value || 0));
        const allocations = form.data.allocations.map((item) => {
            const schedule = eligibleSchedules.find(
                (row) => row.value === item.payment_schedule_id,
            );
            const amount = Math.min(
                remaining,
                Number(schedule?.available || 0),
            );
            remaining -= amount;
            return { ...item, amount: String(amount) };
        });
        form.setData({ ...form.data, amount: value, allocations });
    };
    const updateAllocationAmount = (index, value) => {
        form.clearErrors("allocations");
        const allocatedElsewhere = form.data.allocations.reduce(
            (sum, item, i) =>
                i === index ? sum : sum + Number(item.amount || 0),
            0,
        );
        const schedule = eligibleSchedules.find(
            (row) =>
                row.value === form.data.allocations[index]?.payment_schedule_id,
        );
        const maximum = Math.min(
            Number(schedule?.available || 0),
            Math.max(0, Number(form.data.amount || 0) - allocatedElsewhere),
        );
        form.setData(
            "allocations",
            form.data.allocations.map((item, i) =>
                i === index
                    ? {
                          ...item,
                          amount: String(Math.min(Number(value || 0), maximum)),
                      }
                    : item,
            ),
        );
    };
    const selectSchedule = (index, value) => {
        const schedule = eligibleSchedules.find((item) => item.value === value);
        const allocatedElsewhere = form.data.allocations.reduce(
            (sum, item, i) =>
                i === index ? sum : sum + Number(item.amount || 0),
            0,
        );
        const allocatable = Math.min(
            Number(schedule?.available || 0),
            Math.max(0, Number(form.data.amount || 0) - allocatedElsewhere),
        );
        const allocations = form.data.allocations.map((item, i) =>
            i === index
                ? {
                      ...item,
                      payment_schedule_id: value,
                      amount: schedule ? String(allocatable) : "",
                  }
                : item,
        );
        form.setData("allocations", allocations);
    };

    useEffect(() => {
        if (!transaction) return;
        const validPurpose = availablePurposes.some(
            ([value]) => value === form.data.receipt_purpose,
        );
        if (!validPurpose)
            form.setData(
                "receipt_purpose",
                availablePurposes[0]?.[0] || "other",
            );
    }, [form.data.sales_transaction_id]);

    useEffect(() => {
        if (
            !transaction ||
            ["overpayment", "other"].includes(form.data.receipt_purpose)
        ) {
            form.setData("allocations", []);
            return;
        }
        const schedule = eligibleSchedules[0];
        if (!schedule) return;
        form.setData({
            ...form.data,
            amount: String(schedule.available),
            allocations: [
                {
                    payment_schedule_id: schedule.value,
                    amount: String(schedule.available),
                },
            ],
        });
    }, [
        form.data.receipt_purpose,
        form.data.payment_date,
        form.data.sales_transaction_id,
    ]);

    return (
        <>
            <Head title={title} />
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(storeUrl, { forceFormData: true });
                }}
                className="grid gap-6"
            >
                <header className="rounded-xl border bg-white/80 p-6 dark:bg-white/8">
                    <h1 className="text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-ink-soft">
                        Satu pintu untuk Booking Fee, DP, pembayaran tagihan,
                        percepatan, dan pembayaran lebih. Piutang dan jurnal
                        baru berubah setelah persetujuan akhir.
                    </p>
                </header>
                <section className="grid gap-4 rounded-xl border bg-white/80 p-6 md:grid-cols-2 dark:bg-white/8">
                    <label>
                        Transaksi
                        <select
                            className="mt-1 w-full rounded-lg border p-3"
                            value={form.data.sales_transaction_id}
                            onChange={(e) =>
                                form.setData({
                                    ...form.data,
                                    sales_transaction_id: e.target.value,
                                    allocations: [],
                                    amount: "",
                                })
                            }
                        >
                            <option value="">
                                Pilih kode dan nama pelanggan
                            </option>
                            {transactions.map((item) => (
                                <option value={item.value} key={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Jenis Penerimaan
                        <select
                            className="mt-1 w-full rounded-lg border p-3"
                            value={form.data.receipt_purpose}
                            onChange={(e) =>
                                form.setData("receipt_purpose", e.target.value)
                            }
                        >
                            {availablePurposes.map(([value, label]) => (
                                <option value={value} key={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </label>
                    {form.data.payment_method === "transfer" && (
                        <label>
                            Rekening Penerima{" "}
                            <span className="text-red-600">*</span>
                            <select
                                className="mt-1 w-full rounded-lg border p-3"
                                required
                                value={form.data.master_bank_id}
                                onChange={(e) =>
                                    form.setData(
                                        "master_bank_id",
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">Pilih rekening tujuan</option>
                                {banks.map((item) => (
                                    <option value={item.value} key={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    {form.data.payment_method === "cash" && (
                        <label>
                            Kas Kecil Penerima{" "}
                            <span className="text-red-600">*</span>
                            <select
                                className="mt-1 w-full rounded-lg border p-3"
                                required
                                value={form.data.petty_cash_account_id}
                                onChange={(e) =>
                                    form.setData(
                                        "petty_cash_account_id",
                                        e.target.value,
                                    )
                                }
                            >
                                <option value="">Pilih Kas Kecil</option>
                                {pettyCashAccounts.map((item) => (
                                    <option value={item.value} key={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    <label>
                        Tanggal
                        <Input
                            type="date"
                            value={form.data.payment_date}
                            onChange={(e) =>
                                form.setData("payment_date", e.target.value)
                            }
                        />
                    </label>
                    <CurrencyInput
                        label="Total Diterima"
                        value={form.data.amount}
                        onChange={updateTotalReceived}
                    />
                    <label>
                        Metode
                        <select
                            className="mt-1 w-full rounded-lg border p-3"
                            value={form.data.payment_method}
                            onChange={(e) =>
                                form.setData("payment_method", e.target.value)
                            }
                        >
                            {["transfer", "cash"].map((value) => (
                                <option key={value}>{value}</option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Referensi Bank
                        <Input
                            value={form.data.bank_reference}
                            onChange={(e) =>
                                form.setData("bank_reference", e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Bank Pengirim
                        <Input
                            value={form.data.sender_bank}
                            onChange={(e) =>
                                form.setData("sender_bank", e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Nama Pengirim
                        <Input
                            value={form.data.sender_name}
                            onChange={(e) =>
                                form.setData("sender_name", e.target.value)
                            }
                        />
                    </label>
                    <label>
                        Bukti Transfer
                        <Input
                            type="file"
                            accept="image/*,.pdf"
                            onChange={(e) =>
                                form.setData("proof", e.target.files[0])
                            }
                        />
                    </label>
                    <label className="md:col-span-2">
                        Catatan
                        <Input
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData("notes", e.target.value)
                            }
                        />
                    </label>
                </section>
                {transaction && (
                    <section className="rounded-xl border bg-white/80 p-6 dark:bg-white/8">
                        <div className="flex justify-between">
                            <div>
                                <h2 className="text-xl font-black">
                                    Alokasi Tagihan Otomatis
                                </h2>
                                <p className="text-sm text-ink-soft">
                                    Tagihan lunas atau yang sedang diproses
                                    tidak ditampilkan. Nominal boleh diperkecil
                                    untuk pembayaran sebagian.
                                </p>
                            </div>
                        </div>
                        <div className="mt-4 grid gap-3">
                            {form.data.allocations.map((allocation, index) => (
                                <div
                                    className="grid gap-2 md:grid-cols-[1fr_220px]"
                                    key={index}
                                >
                                    <select
                                        className="rounded-lg border p-3"
                                        value={allocation.payment_schedule_id}
                                        onChange={(e) =>
                                            selectSchedule(
                                                index,
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Pilih tagihan</option>
                                        {eligibleSchedules
                                            .filter(
                                                (schedule) =>
                                                    schedule.value ===
                                                        allocation.payment_schedule_id ||
                                                    !form.data.allocations.some(
                                                        (item) =>
                                                            item.payment_schedule_id ===
                                                            schedule.value,
                                                    ),
                                            )
                                            .map((schedule) => (
                                                <option
                                                    value={schedule.value}
                                                    key={schedule.value}
                                                >
                                                    {schedule.label} — Sisa{" "}
                                                    {money(schedule.remaining)}
                                                    {schedule.penalty > 0
                                                        ? ` + Denda ${money(schedule.penalty)}`
                                                        : ""}
                                                </option>
                                            ))}
                                    </select>
                                    <CurrencyInput
                                        value={allocation.amount}
                                        onChange={(value) =>
                                            updateAllocationAmount(index, value)
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="mt-5 rounded-lg bg-slate-100 p-4 font-bold">
                            Dialokasikan {money(allocated)} · Deposit belum
                            teralokasi{" "}
                            {money(
                                Math.max(
                                    0,
                                    Number(form.data.amount || 0) - allocated,
                                ),
                            )}
                        </div>
                    </section>
                )}
                <div>
                    {Object.values(form.errors).map((error, index) => (
                        <p className="text-sm text-red-600" key={index}>
                            {error}
                        </p>
                    ))}
                    <Button disabled={form.processing} className="mt-3">
                        Simpan Draf
                    </Button>
                </div>
            </form>
        </>
    );
}
Form.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Input Penerimaan Pelanggan"}>
        {page}
    </AdminLayout>
);
