import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Save } from "lucide-react";
import { Button, Dropdown, Input, Textarea } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Form({ title, row, options = {}, actionUrl, method }) {
    const form = useForm({
        marketing_id: String(row?.marketing_id || ""),
        perumahan_id: String(row?.perumahan_id || ""),
        period_start: row?.period_start?.slice?.(0, 10) || "",
        period_end: row?.period_end?.slice?.(0, 10) || "",
        manager_note: row?.manager_note || "",
        coaching_plan: row?.coaching_plan || "",
    });
    const submit = (event) => {
        event.preventDefault();
        form[method](actionUrl);
    };

    return (
        <>
            <Head title={title} />
            <form onSubmit={submit} className="mx-auto grid max-w-4xl gap-6">
                <header className="rounded-3xl bg-[#171d24] p-6 text-white">
                    <p className="text-xs font-black uppercase tracking-widest text-amber-300">
                        Evaluasi berbasis transaksi aktual
                    </p>
                    <h1 className="mt-2 text-3xl font-black">{title}</h1>
                    <p className="mt-2 text-sm text-white/65">
                        Nilai dihitung otomatis; manager hanya mengisi periode,
                        catatan, dan rencana pembinaan.
                    </p>
                </header>
                <section className="grid gap-5 rounded-3xl border bg-white/85 p-6 md:grid-cols-2">
                    <Dropdown
                        label="Marketing *"
                        value={form.data.marketing_id}
                        options={[
                            { value: "", label: "Pilih Marketing" },
                            ...(options.marketings || []),
                        ]}
                        error={form.errors.marketing_id}
                        onChange={(value) =>
                            form.setData("marketing_id", value)
                        }
                    />
                    <Dropdown
                        label="Perumahan"
                        value={form.data.perumahan_id}
                        options={[
                            { value: "", label: "Semua Perumahan" },
                            ...(options.perumahans || []),
                        ]}
                        error={form.errors.perumahan_id}
                        onChange={(value) =>
                            form.setData("perumahan_id", value)
                        }
                    />
                    <Input
                        type="date"
                        label="Periode mulai *"
                        value={form.data.period_start}
                        error={form.errors.period_start}
                        onChange={(event) =>
                            form.setData("period_start", event.target.value)
                        }
                    />
                    <Input
                        type="date"
                        label="Periode selesai *"
                        value={form.data.period_end}
                        error={form.errors.period_end}
                        onChange={(event) =>
                            form.setData("period_end", event.target.value)
                        }
                    />
                    <Textarea
                        className="md:col-span-2"
                        label="Catatan manager"
                        value={form.data.manager_note}
                        error={form.errors.manager_note}
                        onChange={(event) =>
                            form.setData("manager_note", event.target.value)
                        }
                    />
                    <Textarea
                        className="md:col-span-2"
                        label="Rencana coaching / tindak lanjut"
                        value={form.data.coaching_plan}
                        error={form.errors.coaching_plan}
                        onChange={(event) =>
                            form.setData("coaching_plan", event.target.value)
                        }
                    />
                    <div className="flex justify-between md:col-span-2">
                        <Button
                            as={Link}
                            href="/admin/marketing/evaluasi-marketing"
                            variant="outline"
                        >
                            <ArrowLeft size={16} /> Kembali
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <Save size={16} /> Hitung dan Simpan
                        </Button>
                    </div>
                </section>
            </form>
        </>
    );
}

Form.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Evaluasi Marketing"}>
        {page}
    </AdminLayout>
);
