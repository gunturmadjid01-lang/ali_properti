import { Head, Link, router } from "@inertiajs/react";
import { Eye, Filter, Plus, Settings2 } from "lucide-react";
import { useState } from "react";
import { Button, Dropdown } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Index({
    title,
    rows,
    filters,
    options = {},
    canManage,
}) {
    const [form, setForm] = useState(filters);
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <header className="flex flex-wrap items-center justify-between gap-4 rounded-3xl border bg-white/85 p-6 shadow-soft dark:border-white/10 dark:bg-white/7">
                    <div>
                        <p className="text-xs font-black uppercase tracking-widest text-gold-deep">
                            Kinerja berbasis data
                        </p>
                        <h1 className="mt-2 text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-sm text-ink-soft">
                            Nilai dihitung dari SLA, kualitas follow-up,
                            kunjungan terverifikasi, perkembangan customer,
                            administrasi, dan hasil penjualan.
                        </p>
                    </div>
                    {canManage && (
                        <div className="flex gap-2">
                            <Button
                                as={Link}
                                variant="outline"
                                href="/admin/marketing/evaluasi-marketing/pengaturan"
                            >
                                <Settings2 size={16} /> Bobot
                            </Button>
                            <Button
                                as={Link}
                                href="/admin/marketing/evaluasi-marketing/create"
                            >
                                <Plus size={16} /> Buat Evaluasi
                            </Button>
                        </div>
                    )}
                </header>
                <form
                    className="grid gap-3 rounded-2xl border bg-white/85 p-5 md:grid-cols-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            "/admin/marketing/evaluasi-marketing",
                            form,
                            { preserveState: true },
                        );
                    }}
                >
                    <Dropdown
                        label="Marketing"
                        value={String(form.marketing_id || "")}
                        options={[
                            { value: "", label: "Semua Marketing" },
                            ...(options.marketings || []),
                        ]}
                        onChange={(v) => setForm({ ...form, marketing_id: v })}
                    />
                    <Dropdown
                        label="Perumahan"
                        value={String(form.perumahan_id || "")}
                        options={[
                            { value: "", label: "Semua Perumahan" },
                            ...(options.perumahans || []),
                        ]}
                        onChange={(v) => setForm({ ...form, perumahan_id: v })}
                    />
                    <Button className="self-end" type="submit">
                        <Filter size={16} /> Terapkan
                    </Button>
                </form>
                <section className="overflow-x-auto rounded-2xl border bg-white/85">
                    <table className="min-w-full text-sm">
                        <thead className="bg-silver-soft text-left text-xs uppercase">
                            <tr>
                                <th className="p-4">Nomor</th>
                                <th className="p-4">Marketing</th>
                                <th className="p-4">Periode</th>
                                <th className="p-4">Nilai</th>
                                <th className="p-4">Rating</th>
                                <th className="p-4">Status</th>
                                <th className="p-4"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.data.map((row) => (
                                <tr key={row.id}>
                                    <td className="p-4 font-bold">
                                        {row.evaluation_no}
                                    </td>
                                    <td className="p-4">
                                        <b>{row.marketing}</b>
                                        <br />
                                        <span className="text-xs text-ink-soft">
                                            {row.perumahan || "Semua perumahan"}
                                        </span>
                                    </td>
                                    <td className="p-4">
                                        {row.period_start} – {row.period_end}
                                    </td>
                                    <td className="p-4 text-xl font-black">
                                        {row.total_score}
                                    </td>
                                    <td className="p-4">
                                        {row.rating?.replaceAll("_", " ")}
                                    </td>
                                    <td className="p-4">
                                        {row.record_status} ·{" "}
                                        {row.approval_status ||
                                            "belum diajukan"}
                                    </td>
                                    <td className="p-4">
                                        <Button
                                            as={Link}
                                            size="sm"
                                            variant="outline"
                                            href={`/admin/marketing/evaluasi-marketing/${row.id}`}
                                        >
                                            <Eye size={15} /> Detail
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {!rows.data.length && (
                                <tr>
                                    <td
                                        colSpan="7"
                                        className="p-10 text-center text-ink-soft"
                                    >
                                        Belum ada evaluasi.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Evaluasi Marketing"}>
        {page}
    </AdminLayout>
);
