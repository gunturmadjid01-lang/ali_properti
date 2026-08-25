import { Head, Link, useForm } from "@inertiajs/react";
import { ArrowLeft, Save } from "lucide-react";
import { Button, Dropdown, Input, Textarea } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
export default function Form({
    title,
    row,
    categories,
    defaultCategory,
    actionUrl,
    method,
}) {
    const form = useForm({
        category: row?.category || defaultCategory,
        code: row?.code || "",
        label: row?.label || "",
        description: row?.description || "",
        sort_order: row?.sort_order ?? 0,
        is_active: row?.is_active ?? true,
    });
    const submit = (e) => {
        e.preventDefault();
        form[method](actionUrl);
    };
    return (
        <>
            <Head title={title} />
            <form onSubmit={submit} className="mx-auto grid max-w-3xl gap-6">
                <header>
                    <Button
                        as={Link}
                        variant="ghost"
                        href={`/admin/marketing/master-pilihan?category=${form.data.category}`}
                    >
                        <ArrowLeft size={16} /> Kembali
                    </Button>
                    <h1 className="mt-3 text-3xl font-black">{title}</h1>
                </header>
                <section className="grid gap-5 rounded-2xl border bg-white/90 p-6 md:grid-cols-2">
                    <Dropdown
                        label="Kategori"
                        value={form.data.category}
                        options={categories}
                        onChange={(v) => form.setData("category", v)}
                        error={form.errors.category}
                    />
                    <Input
                        label="Kode"
                        value={form.data.code}
                        onChange={(e) => form.setData("code", e.target.value)}
                        error={form.errors.code}
                    />
                    <Input
                        label="Label"
                        value={form.data.label}
                        onChange={(e) => form.setData("label", e.target.value)}
                        error={form.errors.label}
                    />
                    <Input
                        type="number"
                        min="0"
                        label="Urutan"
                        value={form.data.sort_order}
                        onChange={(e) =>
                            form.setData("sort_order", e.target.value)
                        }
                        error={form.errors.sort_order}
                    />
                    <div className="md:col-span-2">
                        <Textarea
                            label="Keterangan"
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData("description", e.target.value)
                            }
                            error={form.errors.description}
                        />
                    </div>
                    <label className="flex items-center gap-3 font-bold">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) =>
                                form.setData("is_active", e.target.checked)
                            }
                        />{" "}
                        Aktif digunakan pada form
                    </label>
                </section>
                <div className="flex justify-end">
                    <Button disabled={form.processing}>
                        <Save size={16} /> Simpan Draft
                    </Button>
                </div>
            </form>
        </>
    );
}
Form.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Form Referensi Marketing"}>{page}</AdminLayout>
);
