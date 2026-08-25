import { Head, Link, router } from "@inertiajs/react";
import { Edit3, Lock, LockOpen, Plus, Trash2 } from "lucide-react";
import Pagination from "../../../../Components/Pagination";
import { Button, Dropdown } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function Index({
    title,
    rows,
    categories,
    category,
    permissions,
}) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <header className="flex flex-wrap items-end justify-between gap-4 rounded-3xl border bg-white/85 p-6">
                    <div>
                        <p className="text-xs font-black uppercase tracking-widest text-gold-deep">
                            Referensi terpusat
                        </p>
                        <h1 className="mt-2 text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-sm text-ink-soft">
                            Pilihan aktif dipakai bersama oleh form marketing
                            agar status dan hasil tidak berbeda-beda.
                        </p>
                    </div>
                    {permissions.create && (
                        <Button
                            as={Link}
                            href={`/admin/marketing/master-pilihan/create?category=${category}`}
                        >
                            <Plus size={16} /> Tambah Pilihan
                        </Button>
                    )}
                </header>
                <div className="max-w-md">
                    <Dropdown
                        label="Kategori"
                        value={category}
                        options={categories}
                        onChange={(value) =>
                            router.get("/admin/marketing/master-pilihan", {
                                category: value,
                            })
                        }
                    />
                </div>
                <section className="overflow-hidden rounded-2xl border bg-white/90">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft text-left text-xs uppercase">
                                <tr>
                                    <th className="p-4">Urutan</th>
                                    <th className="p-4">Kode</th>
                                    <th className="p-4">Label</th>
                                    <th className="p-4">Status</th>
                                    <th className="p-4">Approval</th>
                                    <th className="p-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="p-4">
                                            {row.sort_order}
                                        </td>
                                        <td className="p-4 font-mono text-xs">
                                            {row.code}
                                        </td>
                                        <td className="p-4">
                                            <b>{row.label}</b>
                                            <p className="text-xs text-ink-soft">
                                                {row.description}
                                            </p>
                                        </td>
                                        <td className="p-4">
                                            {row.is_active
                                                ? "Aktif"
                                                : "Nonaktif"}{" "}
                                            · {row.record_status}
                                        </td>
                                        <td className="p-4">
                                            {row.approval_status || "-"}
                                        </td>
                                        <td className="p-4">
                                            <div className="flex flex-wrap gap-2">
                                                {permissions.update &&
                                                    row.record_status ===
                                                        "draft" && (
                                                        <Button
                                                            as={Link}
                                                            size="sm"
                                                            variant="outline"
                                                            href={`/admin/marketing/master-pilihan/${row.id}/edit`}
                                                        >
                                                            <Edit3 size={14} />{" "}
                                                            Edit
                                                        </Button>
                                                    )}
                                                {permissions.lock &&
                                                    row.record_status ===
                                                        "draft" && (
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admin/marketing/master-pilihan/${row.id}/lock`,
                                                                )
                                                            }
                                                        >
                                                            <Lock size={14} />{" "}
                                                            Finalisasi
                                                        </Button>
                                                    )}
                                                {permissions.unlock &&
                                                    row.record_status ===
                                                        "locked" && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admin/marketing/master-pilihan/${row.id}/unlock`,
                                                                )
                                                            }
                                                        >
                                                            <LockOpen
                                                                size={14}
                                                            />{" "}
                                                            Unlock
                                                        </Button>
                                                    )}
                                                {permissions.delete &&
                                                    row.record_status ===
                                                        "draft" && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                confirm(
                                                                    "Hapus draft pilihan ini?",
                                                                ) &&
                                                                router.delete(
                                                                    `/admin/marketing/master-pilihan/${row.id}`,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 size={14} />
                                                        </Button>
                                                    )}
                                                {row.can_review && (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admin/marketing/master-pilihan/${row.id}/review/approve`,
                                                                )
                                                            }
                                                        >
                                                            Setujui
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(
                                                                    `/admin/marketing/master-pilihan/${row.id}/review/reject`,
                                                                )
                                                            }
                                                        >
                                                            Tolak
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>
        </>
    );
}
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Referensi Marketing"}>{page}</AdminLayout>
);
