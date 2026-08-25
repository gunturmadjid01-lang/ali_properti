import { Head, router, useForm } from "@inertiajs/react";
import {
    AlertCircle,
    CheckCircle2,
    Download,
    FilePlus2,
    RefreshCw,
    Search,
} from "lucide-react";
import { useState } from "react";
import {
    Button,
    Dropdown,
    Input,
    Modal,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const fieldLabels = {
    costumer_id: "Pelanggan",
    dokumen_costumer_id: "Jenis dokumen",
    label: "Nama dokumen",
    party_scope: "Pemilik",
    file: "Berkas",
    document_date: "Tanggal dokumen",
    expires_at: "Masa berlaku",
    keterangan: "Catatan",
};

function ResponseModal({ response, close }) {
    return (
        <Modal
            open={Boolean(response)}
            onClose={close}
            title={
                response?.type === "success"
                    ? "Dokumen Berhasil Disimpan"
                    : "Dokumen Gagal Disimpan"
            }
            size="sm"
            footer={
                <Button type="button" onClick={close}>
                    Tutup
                </Button>
            }
        >
            <div
                className={`flex items-start gap-3 rounded-lg p-4 ${response?.type === "success" ? "bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200" : "bg-red-50 text-red-800 dark:bg-red-500/10 dark:text-red-200"}`}
            >
                {response?.type === "success" ? (
                    <CheckCircle2 className="mt-0.5 shrink-0" size={22} />
                ) : (
                    <AlertCircle className="mt-0.5 shrink-0" size={22} />
                )}
                <p className="text-sm font-bold leading-6">
                    {response?.message}
                </p>
            </div>
        </Modal>
    );
}

function ValidationModal({ errors, close }) {
    const entries = Object.entries(errors ?? {});
    return (
        <Modal
            open={entries.length > 0}
            onClose={close}
            title="Dokumen Belum Dapat Disimpan"
            size="sm"
            footer={
                <Button type="button" onClick={close}>
                    Tutup dan Perbaiki
                </Button>
            }
        >
            <div className="grid gap-3">
                <div className="flex gap-3 rounded-lg bg-red-50 p-4 text-red-800 dark:bg-red-500/10 dark:text-red-200">
                    <AlertCircle className="mt-0.5 shrink-0" size={21} />
                    <p className="text-sm font-bold">
                        Periksa kembali {entries.length} bagian yang belum
                        valid.
                    </p>
                </div>
                {entries.map(([field, message]) => (
                    <div
                        className="rounded-lg border border-red-200 px-4 py-3 dark:border-red-500/20"
                        key={field}
                    >
                        <p className="text-sm font-extrabold text-red-800 dark:text-red-200">
                            {fieldLabels[field] ?? field}
                        </p>
                        <p className="mt-1 text-sm text-red-700 dark:text-red-300">
                            {message}
                        </p>
                    </div>
                ))}
            </div>
        </Modal>
    );
}

export default function Index({
    title,
    baseUrl,
    rows,
    customers,
    types,
    filters,
    canManage,
}) {
    const [open, setOpen] = useState(false);
    const [replace, setReplace] = useState(null);
    const [validationOpen, setValidationOpen] = useState(false);
    const [response, setResponse] = useState(null);
    const form = useForm({
        costumer_id: filters.customer || "",
        dokumen_costumer_id: "",
        label: "",
        party_scope: "customer",
        file: null,
        document_date: "",
        expires_at: "",
        keterangan: "",
    });

    const save = (event) => {
        event.preventDefault();
        form.post(baseUrl, {
            forceFormData: true,
            preserveScroll: true,
            onError: () => setValidationOpen(true),
            onSuccess: (page) => {
                setOpen(false);
                form.reset();
                const error = page?.props?.flash?.error;
                setResponse({
                    type: error ? "error" : "success",
                    message:
                        error ??
                        page?.props?.flash?.success ??
                        "Dokumen berhasil disimpan ke repository customer.",
                });
            },
        });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="flex flex-col gap-4 rounded-xl border bg-white/85 p-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p className="text-xs font-black uppercase text-ink-soft">
                            Sumber Berkas Tunggal
                        </p>
                        <h1 className="text-2xl font-black">{title}</h1>
                        <p className="text-sm text-ink-soft">
                            Unggah sekali, lalu gunakan pada SPR dan seluruh
                            tahapan penjualan.
                        </p>
                    </div>
                    {canManage && (
                        <Button
                            onClick={() => {
                                form.clearErrors();
                                setOpen(true);
                            }}
                        >
                            <FilePlus2 size={16} />
                            Unggah Dokumen
                        </Button>
                    )}
                </header>
                <section className="rounded-xl border bg-white/85 p-4">
                    <form
                        className="flex gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(baseUrl, {
                                customer: event.currentTarget.customer.value,
                            });
                        }}
                    >
                        <div className="flex-1">
                            <Dropdown
                                name="customer"
                                value={filters.customer || ""}
                                options={[
                                    { value: "", label: "Semua Pelanggan" },
                                    ...customers,
                                ]}
                                onChange={(value) =>
                                    router.get(baseUrl, { customer: value })
                                }
                            />
                        </div>
                        <Button>
                            <Search size={16} />
                            Tampilkan
                        </Button>
                    </form>
                </section>
                <section className="overflow-x-auto rounded-xl border bg-white/85">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="p-4">Pelanggan</th>
                                <th>Jenis Dokumen</th>
                                <th>Berkas / Versi</th>
                                <th>Status</th>
                                <th className="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((row) => (
                                <tr className="border-b" key={row.id}>
                                    <td className="p-4">
                                        <b>{row.customer}</b>
                                        <p>{row.customer_code}</p>
                                    </td>
                                    <td>
                                        <b>{row.document_type}</b>
                                        <p>
                                            {row.document_code ||
                                                row.party_scope}
                                        </p>
                                    </td>
                                    <td>
                                        <b>{row.file_name}</b>
                                        <p>
                                            Versi {row.version}
                                            {row.expires_at
                                                ? ` · Berlaku ${row.expires_at}`
                                                : ""}
                                        </p>
                                    </td>
                                    <td>{row.status}</td>
                                    <td className="p-4">
                                        <TableActions>
                                            <Button
                                                as="a"
                                                href={`/media/${row.path}`}
                                                target="_blank"
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Download size={14} />
                                                Lihat
                                            </Button>
                                            {canManage &&
                                                row.status === "active" && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setReplace(row)
                                                        }
                                                    >
                                                        <RefreshCw size={14} />
                                                        Ganti
                                                    </Button>
                                                )}
                                        </TableActions>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
            <Modal
                open={open}
                onClose={() => setOpen(false)}
                title="Unggah ke Repositori Pelanggan"
            >
                <form className="grid gap-4" onSubmit={save}>
                    <label className="grid gap-2 text-sm font-bold">
                        Pelanggan
                        <Dropdown
                            value={form.data.costumer_id}
                            options={customers}
                            onChange={(value) =>
                                form.setData("costumer_id", value)
                            }
                        />
                        {form.errors.costumer_id && (
                            <span className="text-xs text-red-600">
                                {form.errors.costumer_id}
                            </span>
                        )}
                    </label>
                    <label className="grid gap-2 text-sm font-bold">
                        Jenis Dokumen
                        <Dropdown
                            value={form.data.dokumen_costumer_id}
                            options={types}
                            onChange={(value) =>
                                form.setData("dokumen_costumer_id", value)
                            }
                        />
                        {form.errors.dokumen_costumer_id && (
                            <span className="text-xs text-red-600">
                                {form.errors.dokumen_costumer_id}
                            </span>
                        )}
                    </label>
                    <Input
                        label="Nama dokumen lain (jika tidak ada di master)"
                        value={form.data.label}
                        error={form.errors.label}
                        onChange={(event) =>
                            form.setData("label", event.target.value)
                        }
                    />
                    <label className="grid gap-2 text-sm font-bold">
                        Pemilik
                        <Dropdown
                            value={form.data.party_scope}
                            options={[
                                { value: "customer", label: "Pelanggan" },
                                { value: "spouse", label: "Pasangan" },
                                { value: "both", label: "Keduanya" },
                            ]}
                            onChange={(value) =>
                                form.setData("party_scope", value)
                            }
                        />
                        {form.errors.party_scope && (
                            <span className="text-xs text-red-600">
                                {form.errors.party_scope}
                            </span>
                        )}
                    </label>
                    <Input
                        type="file"
                        label="Berkas"
                        error={form.errors.file}
                        onChange={(event) =>
                            form.setData(
                                "file",
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <div className="flex justify-end">
                        <Button disabled={form.processing}>
                            {form.processing
                                ? "Mengunggah..."
                                : "Simpan ke Repositori"}
                        </Button>
                    </div>
                </form>
            </Modal>
            <ValidationModal
                errors={validationOpen ? form.errors : {}}
                close={() => setValidationOpen(false)}
            />
            <ResponseModal
                response={response}
                close={() => setResponse(null)}
            />
            {replace && (
                <Replace
                    row={replace}
                    close={() => setReplace(null)}
                    baseUrl={baseUrl}
                    setResponse={setResponse}
                />
            )}
        </>
    );
}

function Replace({ row, close, baseUrl, setResponse }) {
    const form = useForm({ file: null, keterangan: "" });
    const [validationOpen, setValidationOpen] = useState(false);
    const save = (event) => {
        event.preventDefault();
        form.post(`${baseUrl}/${row.id}/replace`, {
            forceFormData: true,
            preserveScroll: true,
            onError: () => setValidationOpen(true),
            onSuccess: (page) => {
                close();
                const error = page?.props?.flash?.error;
                setResponse({
                    type: error ? "error" : "success",
                    message:
                        error ??
                        page?.props?.flash?.success ??
                        "Versi dokumen berhasil diganti.",
                });
            },
        });
    };
    return (
        <>
            <Modal open onClose={close} title={`Ganti ${row.document_type}`}>
                <form className="grid gap-4" onSubmit={save}>
                    <p>
                        File lama tetap disimpan sebagai versi {row.version}.
                        Semua penggunaan aktif akan diarahkan ke versi baru.
                    </p>
                    <Input
                        type="file"
                        label="Berkas Pengganti"
                        error={form.errors.file}
                        onChange={(event) =>
                            form.setData(
                                "file",
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <Input
                        label="Catatan"
                        value={form.data.keterangan}
                        error={form.errors.keterangan}
                        onChange={(event) =>
                            form.setData("keterangan", event.target.value)
                        }
                    />
                    <Button disabled={form.processing}>
                        {form.processing
                            ? "Mengunggah..."
                            : "Simpan Versi Baru"}
                    </Button>
                </form>
            </Modal>
            <ValidationModal
                errors={validationOpen ? form.errors : {}}
                close={() => setValidationOpen(false)}
            />
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Berkas Pelanggan"}>
        {page}
    </AdminLayout>
);
