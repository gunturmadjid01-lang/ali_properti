import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Plus, Save, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import {
    Button,
    CurrencyInput,
    Dropdown,
    Input,
    Textarea,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
const blank = {
    user_id: "",
    basic_salary: 0,
    fixed_allowance: 0,
    other_allowance: 0,
    deductions: 0,
    advance_deduction: 0,
    notes: "",
};
const rp = (v) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(v || 0));
export default function FormPage({
    title,
    baseUrl,
    actionUrl,
    method,
    initialData,
    employees,
    salaryLookupUrl,
    perumahans = [],
    banks = [],
}) {
    const f = useForm(initialData);
    const add = () => f.setData("items", [...f.data.items, { ...blank }]);
    const set = (i, k, v) =>
        f.setData(
            "items",
            f.data.items.map((x, n) => (n === i ? { ...x, [k]: v } : x)),
        );
    const remove = (i) =>
        f.setData(
            "items",
            f.data.items.filter((_, n) => n !== i),
        );
    const [salaryStatus, setSalaryStatus] = useState("");
    useEffect(() => {
        const ids = f.data.items.map((x) => x.user_id).filter(Boolean);
        if (!ids.length || !f.data.period || !f.data.perumahan_id) return;
        const controller = new AbortController();
        const params = new URLSearchParams({
            period: f.data.period,
            perumahan_id: f.data.perumahan_id,
        });
        ids.forEach((id) => params.append("user_ids[]", id));
        setSalaryStatus("Memuat gaji aktif...");
        fetch(`${salaryLookupUrl}?${params}`, {
            headers: { Accept: "application/json" },
            signal: controller.signal,
        })
            .then((r) => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(({ salaries }) => {
                f.setData(
                    "items",
                    f.data.items.map((x) =>
                        salaries[String(x.user_id)]
                            ? { ...x, ...salaries[String(x.user_id)] }
                            : x,
                    ),
                );
                setSalaryStatus("Gaji aktif sesuai periode sudah dimuat.");
            })
            .catch((e) => {
                if (e.name !== "AbortError")
                    setSalaryStatus("Gagal memuat daftar gaji aktif.");
            });
        return () => controller.abort();
    }, [
        f.data.period,
        f.data.perumahan_id,
        f.data.items.map((x) => x.user_id).join(","),
    ]);
    const choose = (i, id) => {
        const e = employees.find((x) => String(x.value) === String(id));
        set(i, "user_id", id);
        if (e) {
            const next = f.data.items.map((x, n) =>
                n === i
                    ? {
                          ...x,
                          user_id: id,
                          basic_salary: e.basic_salary,
                          fixed_allowance: e.fixed_allowance,
                      }
                    : x,
            );
            f.setData("items", next);
        }
    };
    const total = f.data.items.reduce(
        (s, x) =>
            s +
            Number(x.basic_salary || 0) +
            Number(x.fixed_allowance || 0) +
            Number(x.other_allowance || 0) -
            Number(x.deductions || 0) -
            Number(x.advance_deduction || 0),
        0,
    );
    const submit = (e) => {
        e.preventDefault();
        method === "put" ? f.put(actionUrl) : f.post(actionUrl);
    };
    return (
        <>
            <Head title={title} />
            <div className="mx-auto grid max-w-7xl gap-5">
                <section className="flex items-center justify-between rounded-2xl bg-ink p-6 text-white">
                    <div>
                        <p className="text-xs font-black uppercase tracking-widest text-champagne">
                            Transaksi Batch
                        </p>
                        <h1 className="mt-2 text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-sm text-white/60">
                            Hanya pegawai aktif yang sudah memiliki jabatan yang
                            dapat dipilih.
                        </p>
                    </div>
                    <Button as={Link} href={baseUrl} variant="outline">
                        <ArrowLeft size={16} /> Kembali
                    </Button>
                </section>
                <form onSubmit={submit} className="grid gap-5">
                    <section className="grid gap-4 rounded-2xl border bg-white p-5 md:grid-cols-2 dark:border-white/10 dark:bg-white/5">
                        <div>
                            <span className="mb-2 block text-sm font-bold">
                                Perumahan Pembebanan *
                            </span>
                            <Dropdown
                                value={f.data.perumahan_id}
                                onChange={(value) => {
                                    f.setData("perumahan_id", value);
                                    f.setData("master_bank_id", "");
                                }}
                                options={perumahans}
                            />
                            {f.errors.perumahan_id && (
                                <p className="mt-1 text-xs text-red-600">
                                    {f.errors.perumahan_id}
                                </p>
                            )}
                        </div>
                        <div>
                            <span className="mb-2 block text-sm font-bold">
                                Rekening Pembayaran Gaji *
                            </span>
                            <Dropdown
                                value={f.data.master_bank_id}
                                onChange={(value) =>
                                    f.setData("master_bank_id", value)
                                }
                                options={banks.filter(
                                    (bank) =>
                                        String(bank.perumahan_id) ===
                                        String(f.data.perumahan_id),
                                )}
                            />
                            {f.errors.master_bank_id && (
                                <p className="mt-1 text-xs text-red-600">
                                    {f.errors.master_bank_id}
                                </p>
                            )}
                        </div>
                        <Input
                            type="month"
                            required
                            label="Periode Gaji"
                            value={f.data.period}
                            error={f.errors.period}
                            onChange={(e) =>
                                f.setData("period", e.target.value)
                            }
                        />
                        <Input
                            type="date"
                            required
                            label="Tanggal Pembayaran"
                            value={f.data.payment_date}
                            error={f.errors.payment_date}
                            onChange={(e) =>
                                f.setData("payment_date", e.target.value)
                            }
                        />
                        <p className="text-xs font-bold text-emerald-700 md:col-span-2">
                            {salaryStatus}
                        </p>
                        <div className="md:col-span-2">
                            <Textarea
                                label="Catatan Transaksi"
                                value={f.data.notes}
                                onChange={(e) =>
                                    f.setData("notes", e.target.value)
                                }
                            />
                        </div>
                    </section>
                    <section className="overflow-hidden rounded-2xl border bg-white dark:border-white/10 dark:bg-white/5">
                        <header className="flex items-center justify-between border-b p-5">
                            <div>
                                <h2 className="font-black">Daftar Slip Gaji</h2>
                                <p className="text-xs text-ink-soft">
                                    Tambahkan banyak pegawai dalam satu
                                    transaksi.
                                </p>
                            </div>
                            <Button type="button" onClick={add}>
                                <Plus size={16} /> Tambah Pegawai
                            </Button>
                        </header>
                        <div className="grid gap-4 p-5">
                            {f.data.items.map((x, i) => {
                                const emp = employees.find(
                                    (e) =>
                                        String(e.value) === String(x.user_id),
                                );
                                const net =
                                    Number(x.basic_salary || 0) +
                                    Number(x.fixed_allowance || 0) +
                                    Number(x.other_allowance || 0) -
                                    Number(x.deductions || 0);
                                return (
                                    <article
                                        key={i}
                                        className="rounded-xl border p-4"
                                    >
                                        <div className="mb-4 flex items-start justify-between">
                                            <div className="min-w-0 flex-1">
                                                <span className="mb-2 block text-sm font-extrabold">
                                                    Pegawai *
                                                </span>
                                                <Dropdown
                                                    value={x.user_id}
                                                    onChange={(v) =>
                                                        choose(i, v)
                                                    }
                                                    options={employees
                                                        .filter(
                                                            (e) =>
                                                                !f.data.items.some(
                                                                    (it, n) =>
                                                                        n !==
                                                                            i &&
                                                                        String(
                                                                            it.user_id,
                                                                        ) ===
                                                                            String(
                                                                                e.value,
                                                                            ),
                                                                ),
                                                        )
                                                        .map((e) => ({
                                                            value: e.value,
                                                            label: `${e.employee_number ? e.employee_number + " · " : ""}${e.label} · ${e.job_position}`,
                                                        }))}
                                                />
                                                {emp && (
                                                    <p className="mt-2 text-xs text-ink-soft">
                                                        {emp.job_position}
                                                        {emp.branch
                                                            ? ` · ${emp.branch}`
                                                            : ""}
                                                    </p>
                                                )}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="danger"
                                                size="sm"
                                                className="ml-3"
                                                onClick={() => remove(i)}
                                            >
                                                <Trash2 size={15} />
                                            </Button>
                                        </div>
                                        <div className="grid gap-3 md:grid-cols-4">
                                            <CurrencyInput
                                                label="Gaji Pokok"
                                                value={x.basic_salary}
                                                onChange={(v) =>
                                                    set(i, "basic_salary", v)
                                                }
                                            />
                                            <CurrencyInput
                                                label="Tunjangan Tetap"
                                                value={x.fixed_allowance}
                                                onChange={(v) =>
                                                    set(i, "fixed_allowance", v)
                                                }
                                            />
                                            <CurrencyInput
                                                label="Tunjangan Lain"
                                                value={x.other_allowance}
                                                onChange={(v) =>
                                                    set(i, "other_allowance", v)
                                                }
                                            />
                                            <CurrencyInput
                                                label="Potongan"
                                                value={x.deductions}
                                                onChange={(v) =>
                                                    set(i, "deductions", v)
                                                }
                                            />
                                        </div>
                                        <div className="mt-3 grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
                                            <Textarea
                                                label="Catatan Slip"
                                                value={x.notes}
                                                onChange={(e) =>
                                                    set(
                                                        i,
                                                        "notes",
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <div className="rounded-xl bg-emerald-50 px-5 py-4 text-right">
                                                <p className="text-xs font-bold text-emerald-700">
                                                    GAJI BERSIH
                                                </p>
                                                <p className="text-xl font-black text-emerald-800">
                                                    {rp(Math.max(0, net))}
                                                </p>
                                            </div>
                                        </div>
                                    </article>
                                );
                            })}
                            {!f.data.items.length && (
                                <div className="rounded-xl border border-dashed p-10 text-center font-bold text-ink-soft">
                                    Klik “Tambah Pegawai” untuk mengisi
                                    transaksi.
                                </div>
                            )}
                        </div>
                    </section>
                    <footer className="sticky bottom-3 flex items-center justify-between rounded-2xl border bg-white/95 p-4 shadow-lg dark:border-white/10 dark:bg-graphite">
                        <div>
                            <p className="text-xs font-bold uppercase text-ink-soft">
                                Total bersih batch
                            </p>
                            <p className="text-2xl font-black text-emerald-700">
                                {rp(Math.max(0, total))}
                            </p>
                        </div>
                        <Button
                            type="submit"
                            disabled={f.processing || !f.data.items.length}
                        >
                            <Save size={17} /> Simpan Draft
                        </Button>
                    </footer>
                </form>
            </div>
        </>
    );
}
FormPage.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Penggajian"}>
        {page}
    </AdminLayout>
);
