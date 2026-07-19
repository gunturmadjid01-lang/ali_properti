import { Head, router, useForm } from "@inertiajs/react";
import {
    Edit3,
    Eye,
    Lock,
    PlusCircle,
    Search,
    Trash2,
    Unlock,
    X,
} from "lucide-react";
import { useState } from "react";
import Pagination from "../../../../Components/Pagination";
import {
    Button,
    Dropdown,
    Form,
    Input,
    Modal,
    Textarea,
    TableActions,
} from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

function ErrorSummary({ errors }) {
    const messages = Object.values(errors ?? {}).filter(Boolean);
    if (!messages.length) return null;
    return (
        <div className="rounded-lg border border-red-300 bg-red-50 p-4 text-sm font-bold text-red-700">
            {messages.map((message) => (
                <p key={message}>{message}</p>
            ))}
        </div>
    );
}

export default function Index({
    title,
    description,
    baseUrl,
    rows = { data: [], links: [] },
    filters = {},
    options = {},
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [editing, setEditing] = useState(null);
    const [detail, setDetail] = useState(null);
    const form = useForm({
        nama_sumber: "",
        kategori: "",
        keterangan: "",
        status: "aktif",
    });

    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        form.setData("status", "aktif");
    };

    const editRow = (row) => {
        setEditing(row);
        form.clearErrors();
        form.setData({
            nama_sumber: row.nama_sumber ?? "",
            kategori: row.kategori === "-" ? "" : (row.kategori ?? ""),
            keterangan: row.keterangan ?? "",
            status: row.status ?? "aktif",
        });
        window.scrollTo({ top: 0, behavior: "smooth" });
    };

    const submit = (event) => {
        event.preventDefault();
        const requestOptions = { preserveScroll: true, onSuccess: resetForm };

        if (editing) {
            form.put(`${baseUrl}/${editing.id}`, requestOptions);
            return;
        }

        form.post(baseUrl, requestOptions);
    };
    const showForm = false;

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                        Marketing
                    </p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">
                        {title}
                    </h2>
                    <p className="mt-2 max-w-3xl leading-7 text-ink-soft dark:text-white/60">
                        {description}
                    </p>
                    {permissions.canCreate && (
                        <Button
                            className="mt-4"
                            type="button"
                            onClick={() => router.visit(`${baseUrl}/create`)}
                        >
                            <PlusCircle size={17} /> Tambah Sumber Lead
                        </Button>
                    )}
                </section>

                {showForm && (
                    <Form
                        collapsible
                        title={
                            editing
                                ? `Edit ${editing.kode_sumber}`
                                : "Tambah Sumber Lead"
                        }
                        description="Contoh: Facebook Ads, Instagram, Referral, Spanduk, Walk-in, Pameran, atau Agen."
                        onSubmit={submit}
                        actions={
                            <>
                                {editing && permissions.canUpdate && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={resetForm}
                                    >
                                        <X size={15} /> Batal
                                    </Button>
                                )}
                                {((editing && permissions.canUpdate) ||
                                    (!editing && permissions.canCreate)) && (
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        <PlusCircle size={17} />{" "}
                                        {editing
                                            ? "Simpan Perubahan"
                                            : "Simpan Sumber"}
                                    </Button>
                                )}
                            </>
                        }
                    >
                        <ErrorSummary errors={form.errors} />
                        <div className="grid gap-4 md:grid-cols-3">
                            <Input
                                label="Nama Sumber Lead"
                                value={form.data.nama_sumber}
                                error={form.errors.nama_sumber}
                                onChange={(event) =>
                                    form.setData(
                                        "nama_sumber",
                                        event.target.value,
                                    )
                                }
                            />
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Kategori
                                </span>
                                <Dropdown
                                    label="Pilih Kategori"
                                    value={form.data.kategori}
                                    options={options.kategoriOptions ?? []}
                                    onChange={(value) =>
                                        form.setData("kategori", value)
                                    }
                                />
                                {form.errors.kategori && (
                                    <span className="text-xs font-bold text-red-600">
                                        {form.errors.kategori}
                                    </span>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Status
                                </span>
                                <Dropdown
                                    label="Pilih Status"
                                    value={form.data.status}
                                    options={options.statusOptions ?? []}
                                    onChange={(value) =>
                                        form.setData("status", value)
                                    }
                                />
                                {form.errors.status && (
                                    <span className="text-xs font-bold text-red-600">
                                        {form.errors.status}
                                    </span>
                                )}
                            </div>
                        </div>
                        <Textarea
                            label="Keterangan"
                            value={form.data.keterangan}
                            error={form.errors.keterangan}
                            onChange={(event) =>
                                form.setData("keterangan", event.target.value)
                            }
                        />
                    </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 md:grid-cols-[1fr_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                baseUrl,
                                { search },
                                { preserveState: true, replace: true },
                            );
                        }}
                    >
                        <Input
                            label="Cari Kode / Nama / Kategori"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="flex items-end">
                            <Button type="submit">
                                <Search size={16} /> Cari
                            </Button>
                        </div>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                <tr>
                                    {[
                                        "Kode",
                                        "Nama Sumber",
                                        "Kategori",
                                        "Pelanggan",
                                        "Status",
                                        "Audit",
                                        "Kunci",
                                        "Aksi",
                                    ].map((label) => (
                                        <th className="px-5 py-4" key={label}>
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">
                                            {row.kode_sumber}
                                        </td>
                                        <td className="px-5 py-4 font-extrabold">
                                            {row.nama_sumber}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.kategori}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.jumlah_customer}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.status}
                                        </td>
                                        <td className="min-w-44 px-5 py-4 text-xs">
                                            <span className="font-bold">
                                                Dibuat:
                                            </span>{" "}
                                            {row.created_by_name}
                                            <br />
                                            <span className="font-bold">
                                                Diubah:
                                            </span>{" "}
                                            {row.updated_by_name}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.record_status}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.visit(
                                                            `${baseUrl}/${row.id}`,
                                                        )
                                                    }
                                                >
                                                    <Eye size={14} /> Detail
                                                </Button>
                                                {permissions.canUpdate &&
                                                    row.can_edit && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.visit(
                                                                    `${baseUrl}/${row.id}/edit`,
                                                                )
                                                            }
                                                        >
                                                            <Edit3 size={14} />{" "}
                                                            Ubah
                                                        </Button>
                                                    )}
                                                {permissions.canDelete &&
                                                    row.can_delete && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            className="text-red-600"
                                                            onClick={() =>
                                                                window.confirm(
                                                                    "Hapus sumber lead ini?",
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
                                                {row.can_lock && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `${baseUrl}/${row.id}/lock`,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Lock size={14} /> Kunci
                                                    </Button>
                                                )}
                                                {row.can_unlock &&
                                                    permissions.canUnlock && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/unlock`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <Unlock size={14} />{" "}
                                                            Unlock
                                                        </Button>
                                                    )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {rows.data.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center font-bold text-ink-soft"
                                            colSpan={8}
                                        >
                                            Belum ada sumber lead.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

            {false && (
                <Modal
                    open={Boolean(detail)}
                    onClose={() => setDetail(null)}
                    title={
                        detail
                            ? `Detail ${detail.kode_sumber}`
                            : "Detail Sumber Lead"
                    }
                    footer={
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDetail(null)}
                        >
                            Tutup
                        </Button>
                    }
                >
                    {detail && (
                        <div className="grid gap-3 text-sm">
                            <p>
                                <b>Nama:</b> {detail.nama_sumber}
                            </p>
                            <p>
                                <b>Kategori:</b> {detail.kategori}
                            </p>
                            <p>
                                <b>Status:</b> {detail.status}
                            </p>
                            <p>
                                <b>Jumlah Pelanggan:</b>{" "}
                                {detail.jumlah_customer}
                            </p>
                            <p>
                                <b>Keterangan:</b> {detail.keterangan || "-"}
                            </p>
                        </div>
                    )}
                </Modal>
            )}
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Sumber Lead"}>
        {page}
    </AdminLayout>
);
