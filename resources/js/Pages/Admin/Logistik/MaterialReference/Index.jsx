import { Head, router, useForm } from "@inertiajs/react";
import { Edit3, Plus, Search, Trash2, X } from "lucide-react";
import { useState } from "react";
import {
    Button,
    Dropdown,
    Form,
    Input,
    Textarea,
    TableActions,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { SectionCard, WarehousePage } from "../components/WarehouseShell";

function Index({
    title,
    baseUrl,
    rows,
    search = "",
    usesSymbol = false,
    permissions = {},
}) {
    const [query, setQuery] = useState(search);
    const [editing, setEditing] = useState(null);
    const form = useForm({
        name: "",
        symbol: "",
        description: "",
        status: "aktif",
    });
    const reset = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
    };
    const edit = (row) => {
        setEditing(row);
        form.setData({
            name: row.name ?? "",
            symbol: row.symbol ?? "",
            description: row.description ?? "",
            status: row.status ?? "aktif",
        });
    };
    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        editing
            ? form.put(`${baseUrl}/${editing.id}`, options)
            : form.post(baseUrl, options);
    };

    return (
        <>
            <Head title={title} />
            <WarehousePage
                eyebrow="Referensi Material"
                title={title}
                description="Master ini dipakai pada form material dan dapat diberikan kepada role lain melalui Peran & Hak Akses."
            >
                {(permissions.create || (editing && permissions.update)) && (
                    <Form
                        title={editing ? `Edit ${title}` : `Tambah ${title}`}
                        onSubmit={submit}
                        actions={
                            <div className="flex gap-2">
                                {editing && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={reset}
                                    >
                                        <X size={16} /> Batal
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    <Plus size={16} />{" "}
                                    {editing ? "Simpan Perubahan" : "Tambah"}
                                </Button>
                            </div>
                        }
                    >
                        <div
                            className={`grid gap-4 ${usesSymbol ? "md:grid-cols-3" : "md:grid-cols-2"}`}
                        >
                            <Input
                                label="Nama"
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(event) =>
                                    form.setData("name", event.target.value)
                                }
                            />
                            {usesSymbol && (
                                <Input
                                    label="Simbol"
                                    placeholder="Contoh: Dus, Pcs, Kg"
                                    value={form.data.symbol}
                                    error={form.errors.symbol}
                                    onChange={(event) =>
                                        form.setData(
                                            "symbol",
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Status
                                </span>
                                <Dropdown
                                    value={form.data.status}
                                    options={[
                                        { value: "aktif", label: "Aktif" },
                                        {
                                            value: "nonaktif",
                                            label: "Nonaktif",
                                        },
                                    ]}
                                    onChange={(value) =>
                                        form.setData("status", value)
                                    }
                                />
                            </div>
                        </div>
                        <Textarea
                            label="Keterangan"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData("description", event.target.value)
                            }
                        />
                    </Form>
                )}
                <SectionCard
                    title={`Daftar ${title}`}
                    actions={
                        <form
                            className="flex gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get(
                                    baseUrl,
                                    { search: query },
                                    { preserveState: true, replace: true },
                                );
                            }}
                        >
                            <Input
                                value={query}
                                placeholder="Cari..."
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                            />
                            <Button type="submit">
                                <Search size={16} /> Cari
                            </Button>
                        </form>
                    }
                >
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-xs">
                            <thead>
                                <tr>
                                    {[
                                        "Kode",
                                        "Nama",
                                        ...(usesSymbol ? ["Simbol"] : []),
                                        "Keterangan",
                                        "Status",
                                        "Aksi",
                                    ].map((column) => (
                                        <th
                                            className="px-4 py-3 text-left font-extrabold"
                                            key={column}
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-3 font-bold">
                                            {row.code}
                                        </td>
                                        <td className="px-4 py-3 font-extrabold">
                                            {row.name}
                                        </td>
                                        {usesSymbol && (
                                            <td className="px-4 py-3">
                                                {row.symbol}
                                            </td>
                                        )}
                                        <td className="px-4 py-3">
                                            {row.description ?? "-"}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.status}
                                        </td>
                                        <td className="px-4 py-3">
                                            <TableActions>
                                                {permissions.update && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        type="button"
                                                        onClick={() =>
                                                            edit(row)
                                                        }
                                                    >
                                                        <Edit3 size={14} />
                                                    </Button>
                                                )}
                                                {permissions.delete && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="text-red-600"
                                                        type="button"
                                                        onClick={() =>
                                                            window.confirm(
                                                                `Hapus ${row.name}?`,
                                                            ) &&
                                                            router.delete(
                                                                `${baseUrl}/${row.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Trash2 size={14} />
                                                    </Button>
                                                )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </SectionCard>
            </WarehousePage>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Referensi Material"}>
        {page}
    </AdminLayout>
);
export default Index;
