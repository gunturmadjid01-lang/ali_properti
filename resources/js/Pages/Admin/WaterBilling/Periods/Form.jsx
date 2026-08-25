import { Head, router, useForm } from "@inertiajs/react";
import { ArrowLeft, Save } from "lucide-react";
import { Button, Dropdown, Input } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Form({ title, actionUrl, method, housing, row }) {
    const form = useForm({
        perumahan_id: String(row?.perumahan_id ?? ""),
        period_name: row?.period_name ?? "",
        period_start: row?.period_start?.slice?.(0, 10) ?? "",
        period_end: row?.period_end?.slice?.(0, 10) ?? "",
        due_date: row?.due_date?.slice?.(0, 10) ?? "",
        amount: row?.amount ?? "",
        is_active: row?.is_active ?? true,
    });
    const submit = (e) => {
        e.preventDefault();
        form[method](actionUrl);
    };
    return (
        <>
            <Head title={title} />
            <section className="rounded-xl border border-white/80 bg-white/85 p-6 shadow-soft dark:border-white/10 dark:bg-white/7">
                <p className="text-xs font-black uppercase tracking-widest text-ink-soft">
                    Pengelolaan Air / Periode
                </p>
                <h1 className="mt-2 text-3xl font-black">{title}</h1>
                <p className="mt-2 text-sm text-ink-soft">
                    Tetapkan periode, jatuh tempo, dan tarif air untuk satu
                    perumahan.
                </p>
                <form
                    className="mt-6 grid gap-5 md:grid-cols-2"
                    onSubmit={submit}
                >
                    <label className="grid gap-2 text-sm font-bold md:col-span-2">
                        <span>Perumahan</span>
                        <Dropdown
                            value={form.data.perumahan_id}
                            options={housing}
                            onChange={(v) => form.setData("perumahan_id", v)}
                        />
                        <small className="text-red-600">
                            {form.errors.perumahan_id}
                        </small>
                    </label>
                    <Input
                        label="Nama Periode"
                        value={form.data.period_name}
                        error={form.errors.period_name}
                        onChange={(e) =>
                            form.setData("period_name", e.target.value)
                        }
                    />
                    <Input
                        label="Nominal Tagihan (Rp)"
                        type="number"
                        value={form.data.amount}
                        error={form.errors.amount}
                        onChange={(e) => form.setData("amount", e.target.value)}
                    />
                    <Input
                        label="Tanggal Mulai Periode"
                        type="date"
                        value={form.data.period_start}
                        error={form.errors.period_start}
                        onChange={(e) =>
                            form.setData("period_start", e.target.value)
                        }
                    />
                    <Input
                        label="Tanggal Akhir Periode"
                        type="date"
                        value={form.data.period_end}
                        error={form.errors.period_end}
                        onChange={(e) =>
                            form.setData("period_end", e.target.value)
                        }
                    />
                    <Input
                        label="Tanggal Jatuh Tempo"
                        type="date"
                        value={form.data.due_date}
                        error={form.errors.due_date}
                        onChange={(e) =>
                            form.setData("due_date", e.target.value)
                        }
                    />
                    <label className="flex items-center gap-3 rounded-lg border border-silver-deep/60 px-4 py-3 font-bold">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) =>
                                form.setData("is_active", e.target.checked)
                            }
                        />{" "}
                        Periode aktif
                    </label>
                    <div className="flex justify-end gap-2 border-t pt-5 md:col-span-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.visit("/admin/periode-tagihan-air")
                            }
                        >
                            <ArrowLeft size={16} /> Kembali
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <Save size={16} /> Simpan
                        </Button>
                    </div>
                </form>
            </section>
        </>
    );
}
Form.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Periode Tagihan Air"}>
        {page}
    </AdminLayout>
);
