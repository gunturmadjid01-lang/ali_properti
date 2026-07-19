import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Save } from "lucide-react";
import {
    Button,
    CurrencyInput,
    Dropdown,
    Input,
    Textarea,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
export default function FormPage({
    title,
    baseUrl,
    actionUrl,
    method,
    initialData,
    employees,
    perumahans = [],
    banks = [],
}) {
    const f = useForm(initialData);
    const submit = (e) => {
        e.preventDefault();
        method === "put" ? f.put(actionUrl) : f.post(actionUrl);
    };
    return (
        <>
            <Head title={title} />
            <div className="mx-auto grid max-w-4xl gap-5">
                <section className="flex justify-between rounded-2xl bg-ink p-6 text-white">
                    <h1 className="text-3xl font-black">{title}</h1>
                    <Button as={Link} href={baseUrl} variant="outline">
                        <ArrowLeft size={16} /> Kembali
                    </Button>
                </section>
                <form
                    onSubmit={submit}
                    className="grid gap-4 rounded-2xl border bg-white p-5"
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <span className="mb-2 block font-bold">
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
                            <span className="mb-2 block font-bold">
                                Rekening Sumber Perusahaan *
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
                    </div>
                    <div>
                        <span className="mb-2 block font-bold">Pegawai *</span>
                        <Dropdown
                            value={f.data.user_id}
                            onChange={(v) => f.setData("user_id", v)}
                            options={employees}
                        />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Input
                            type="date"
                            label="Tanggal Pengambilan"
                            value={f.data.advance_date}
                            onChange={(e) =>
                                f.setData("advance_date", e.target.value)
                            }
                        />
                        <Input
                            type="month"
                            label="Periode Potong Gaji"
                            value={f.data.deduction_period}
                            onChange={(e) =>
                                f.setData("deduction_period", e.target.value)
                            }
                        />
                    </div>
                    <CurrencyInput
                        label="Nominal Panjar"
                        value={f.data.amount}
                        onChange={(v) => f.setData("amount", v)}
                    />
                    <Textarea
                        label="Keperluan Panjar"
                        value={f.data.purpose}
                        onChange={(e) => f.setData("purpose", e.target.value)}
                    />
                    <div className="flex justify-end">
                        <Button disabled={f.processing}>
                            <Save size={16} /> Simpan Draft
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
FormPage.layout = (p) => <AdminLayout title={p?.props?.title}>{p}</AdminLayout>;
