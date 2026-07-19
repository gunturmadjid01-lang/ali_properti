import { Head, router } from "@inertiajs/react";
import { Check, Edit3, Lock, Plus, RotateCcw, Search, Trash2, X } from "lucide-react";
import { useState } from "react";
import Pagination from "../../../../Components/Pagination";
import { Button, Input, TableActions } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

export default function BankPageShell({
    title,
    description,
    baseUrl,
    rows,
    filters = {},
    columns,
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="flex flex-col gap-4 rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p className="text-xs font-black uppercase tracking-wider text-ink-soft">
                            Master Kredit Bank
                        </p>
                        <h1 className="mt-1 text-2xl font-black">{title}</h1>
                        <p className="mt-1 text-sm text-ink-soft">
                            {description}
                        </p>
                    </div>
                    {permissions.create && (
                        <Button
                            onClick={() => router.visit(`${baseUrl}/create`)}
                        >
                            <Plus size={16} /> Tambah Data
                        </Button>
                    )}
                </section>
                <section className="rounded-xl border border-white/80 bg-white/80 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex justify-end border-b p-5">
                        <form
                            className="flex items-end gap-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(
                                    baseUrl,
                                    { search },
                                    { preserveState: true, replace: true },
                                );
                            }}
                        >
                            <Input
                                label="Pencarian"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                            <Button type="submit">
                                <Search size={16} /> Cari
                            </Button>
                        </form>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm">
                            <thead>
                                <tr>
                                    {columns.map((c) => (
                                        <th
                                            className="whitespace-nowrap px-4 py-3 text-left text-xs font-black uppercase"
                                            key={c.key}
                                        >
                                            {c.label}
                                        </th>
                                    ))}
                                    <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-black uppercase">Finalisasi</th>
                                    {(permissions.update ||
                                        permissions.delete || permissions.submit) && (
                                        <th className="w-16 px-4 py-3 text-right">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {(rows.data ?? []).map((row) => (
                                    <tr key={row.id}>
                                        {columns.map((c) => (
                                            <td
                                                className="max-w-sm px-4 py-3"
                                                key={c.key}
                                            >
                                                {c.render
                                                    ? c.render(row)
                                                    : String(row[c.key] ?? "-")}
                                            </td>
                                        ))}
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <div className="font-bold">{row.record_status === "locked" ? "Final / Locked" : "Draf"}</div>
                                            <div className="text-xs text-ink-soft">
                                                {row.approval_status ? `Approval: ${row.approval_status}` : "Belum diajukan"}
                                                {row.approval_stage ? ` · Tahap ${row.approval_stage}` : ""}
                                            </div>
                                        </td>
                                        {(permissions.update ||
                                            permissions.delete || permissions.submit) && (
                                            <td className="w-16 px-4 py-3">
                                                <TableActions>
                                                    {permissions.update && row.record_status !== "locked" && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.visit(
                                                                    `${baseUrl}/${row.id}/edit`,
                                                                )
                                                            }
                                                        >
                                                            <Edit3 size={14} />
                                                        </Button>
                                                    )}
                                                    {permissions.delete && row.record_status !== "locked" && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="text-red-600"
                                                            onClick={() =>
                                                                window.confirm(
                                                                    "Hapus data ini?",
                                                                ) &&
                                                                router.delete(
                                                                    `${baseUrl}/${row.id}`,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 size={14} />
                                                        </Button>
                                                    )}
                                                    {permissions.submit && row.record_status !== "locked" && (
                                                        <Button size="sm" onClick={() => window.confirm("Finalisasi data ini? Setelah di-lock data dapat dipakai modul lain dan tidak dapat diedit.") && router.post(`${baseUrl}/${row.id}/lock`)}>
                                                            <Lock size={14} /> Finalisasi
                                                        </Button>
                                                    )}
                                                    {permissions.update && row.record_status === "locked" && row.approval_status !== "approved" && (
                                                        <Button size="sm" variant="outline" onClick={() => window.confirm("Kembalikan data menjadi draf? Data akan hilang dari pilihan modul lain.") && router.post(`${baseUrl}/${row.id}/unlock`)}>
                                                            <RotateCcw size={14} /> Buka Lock
                                                        </Button>
                                                    )}
                                                    {row.can_review && (
                                                        <>
                                                            <Button size="sm" onClick={() => router.post(`${baseUrl}/${row.id}/review/approve`)}><Check size={14} /> Setujui</Button>
                                                            <Button size="sm" variant="outline" className="text-red-600" onClick={() => { const note = window.prompt("Alasan penolakan:"); if (note?.trim()) router.post(`${baseUrl}/${row.id}/review/reject`, { note }); }}><X size={14} /> Tolak</Button>
                                                        </>
                                                    )}
                                                </TableActions>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {!(rows.data ?? []).length && (
                                    <tr>
                                        <td
                                            className="px-4 py-10 text-center text-ink-soft"
                                            colSpan={columns.length + 2}
                                        >
                                            Belum ada data.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links ?? []} />
                </section>
            </div>
        </>
    );
}

BankPageShell.layout = (page) => (
    <AdminLayout title={page?.props?.title}>{page}</AdminLayout>
);
