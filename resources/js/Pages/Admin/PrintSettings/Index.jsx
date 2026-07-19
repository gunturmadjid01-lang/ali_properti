import { Head, useForm } from "@inertiajs/react";
import { Plus, Save, Trash2 } from "lucide-react";
import AdminLayout from "../../../Layouts/AdminLayout";
import { Button, Dropdown, Input } from "../../../Components/UI";

const blank = {
    name: "",
    paper_size: "a4",
    orientation: "portrait",
    custom_width_mm: "",
    custom_height_mm: "",
    margin_top_mm: 15,
    margin_right_mm: 15,
    margin_bottom_mm: 15,
    margin_left_mm: 15,
};

function Index({ title, baseUrl, templates, targets }) {
    const form = useForm({ templates, targets });
    const setTemplate = (i, k, v) =>
        form.setData(
            "templates",
            form.data.templates.map((x, n) => (n === i ? { ...x, [k]: v } : x)),
        );
    const remove = (i) => {
        const id = form.data.templates[i].id;
        form.setData({
            templates: form.data.templates.filter((_, n) => n !== i),
            targets: form.data.targets.map((t) =>
                String(t.template_id) === String(id)
                    ? { ...t, template_id: "" }
                    : t,
            ),
        });
    };
    const submit = (e) => {
        e.preventDefault();
        form.put(baseUrl, { preserveScroll: true });
    };
    return (
        <>
            <Head title={title} />
            <form className="grid gap-5" onSubmit={submit}>
                <section className="flex flex-col gap-3 rounded-lg border bg-white/80 p-5 dark:border-white/10 dark:bg-white/8 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 className="text-xl font-extrabold">{title}</h2>
                        <p className="text-sm text-ink-soft">
                            Buat beberapa template lalu tetapkan template
                            berbeda untuk setiap fungsi cetak.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                form.setData("templates", [
                                    ...form.data.templates,
                                    { ...blank },
                                ])
                            }
                        >
                            <Plus size={16} />
                            Tambah Template
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <Save size={16} />
                            Simpan
                        </Button>
                    </div>
                </section>
                <section className="grid gap-4">
                    {form.data.templates.map((t, i) => (
                        <article
                            className="rounded-lg border bg-white/80 p-5 dark:border-white/10 dark:bg-white/8"
                            key={t.id ?? `new-${i}`}
                        >
                            <div className="mb-4 flex justify-between">
                                <h3 className="font-extrabold">
                                    Template {i + 1}
                                </h3>
                                {form.data.templates.length > 1 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => remove(i)}
                                    >
                                        <Trash2 size={16} />
                                        Hapus
                                    </Button>
                                )}
                            </div>
                            <div className="grid gap-3 md:grid-cols-4">
                                <Input
                                    label="Nama Template"
                                    value={t.name}
                                    onChange={(e) =>
                                        setTemplate(i, "name", e.target.value)
                                    }
                                />
                                <label className="grid gap-2 text-sm font-bold">
                                    Ukuran Kertas
                                    <Dropdown
                                        searchable={false}
                                        value={t.paper_size}
                                        options={[
                                            { value: "a4", label: "A4" },
                                            { value: "legal", label: "Legal" },
                                            {
                                                value: "custom",
                                                label: "Ukuran Khusus",
                                            },
                                        ]}
                                        onChange={(value) =>
                                            setTemplate(i, "paper_size", value)
                                        }
                                    />
                                </label>
                                <label className="grid gap-2 text-sm font-bold">
                                    Orientasi
                                    <Dropdown
                                        searchable={false}
                                        value={t.orientation}
                                        options={[
                                            {
                                                value: "portrait",
                                                label: "Tegak",
                                            },
                                            {
                                                value: "landscape",
                                                label: "Mendatar",
                                            },
                                        ]}
                                        onChange={(value) =>
                                            setTemplate(i, "orientation", value)
                                        }
                                    />
                                </label>
                                {t.paper_size === "custom" && (
                                    <>
                                        <Input
                                            label="Lebar (mm)"
                                            type="number"
                                            value={t.custom_width_mm}
                                            onChange={(e) =>
                                                setTemplate(
                                                    i,
                                                    "custom_width_mm",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <Input
                                            label="Tinggi (mm)"
                                            type="number"
                                            value={t.custom_height_mm}
                                            onChange={(e) =>
                                                setTemplate(
                                                    i,
                                                    "custom_height_mm",
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </>
                                )}{" "}
                                {["top", "right", "bottom", "left"].map(
                                    (side) => (
                                        <Input
                                            key={side}
                                            label={`Margin ${{ top: "Atas", right: "Kanan", bottom: "Bawah", left: "Kiri" }[side]} (mm)`}
                                            type="number"
                                            value={t[`margin_${side}_mm`]}
                                            onChange={(e) =>
                                                setTemplate(
                                                    i,
                                                    `margin_${side}_mm`,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    ),
                                )}
                            </div>
                        </article>
                    ))}
                </section>
                <section className="rounded-lg border bg-white/80 p-5 dark:border-white/10 dark:bg-white/8">
                    <h3 className="mb-4 font-extrabold">
                        Penugasan Template ke Fungsi Cetak
                    </h3>
                    <div className="grid gap-3 md:grid-cols-2">
                        {form.data.targets.map((target, i) => (
                            <label
                                className="grid gap-2 rounded-lg border p-3 text-sm font-bold"
                                key={target.key}
                            >
                                {target.label}
                                <Dropdown
                                    value={target.template_id}
                                    label="Bawaan A4"
                                    options={[
                                        { value: "", label: "Bawaan A4" },
                                        ...form.data.templates.map((t, n) => ({
                                            value: String(t.id ?? `new:${n}`),
                                            label:
                                                t.name || `Template ${n + 1}`,
                                        })),
                                    ]}
                                    onChange={(value) =>
                                        form.setData(
                                            "targets",
                                            form.data.targets.map((x, n) =>
                                                n === i
                                                    ? {
                                                          ...x,
                                                          template_id: value,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                            </label>
                        ))}
                    </div>
                </section>
            </form>
        </>
    );
}
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Pengaturan Cetak"}>
        {page}
    </AdminLayout>
);
export default Index;
