import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    Edit3,
    Eye,
    KeyRound,
    Lock,
    PlusCircle,
    RotateCcw,
    Search,
    Unlock,
    UserMinus,
} from "lucide-react";
import { useMemo, useState } from "react";
import {
    Button,
    Dropdown,
    Input,
    Modal,
    Textarea,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

const emptyForm = {
    detail_rumah_id: "",
    costumer_id: "",
    owner_name: "",
    identity_type: "KTP",
    identity_number: "",
    phone: "",
    email: "",
    address: "",
    spouse_name: "",
    acquisition_method: "data_lama",
    acquired_at: new Date().toISOString().slice(0, 10),
    document_number: "",
    notes: "",
    attachment: null,
};

function Pagination({ links = [] }) {
    if (links.length <= 3) return null;
    return (
        <div className="flex flex-wrap justify-end gap-2 border-t border-silver-deep/60 px-5 py-4 dark:border-white/10">
            {links.map((link, index) => (
                <Button
                    as={Link}
                    className={
                        !link.url ? "pointer-events-none opacity-45" : ""
                    }
                    dangerouslySetInnerHTML={{ __html: link.label }}
                    href={link.url ?? "#"}
                    key={`${link.label}-${index}`}
                    preserveScroll
                    size="sm"
                    variant={link.active ? "dark" : "outline"}
                />
            ))}
        </div>
    );
}

export default function Index({
    title,
    description,
    baseUrl,
    rows,
    filters,
    options,
    permissions,
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [status, setStatus] = useState(filters.status ?? "active");
    const [branchId, setBranchId] = useState(filters.cabang_id ?? "");
    const [projectId, setProjectId] = useState(filters.perumahan_id ?? "");
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyForm);
    const projectOptions = useMemo(
        () => [
            { value: "", label: "Semua Perumahan" },
            ...options.projects.filter(
                (project) => !branchId || project.cabang_id === branchId,
            ),
        ],
        [branchId, options.projects],
    );

    const selectedUnit = options.units.find(
        (unit) => unit.value === form.data.detail_rumah_id,
    );

    const openCreate = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
        setModalOpen(true);
    };

    const openEdit = (row) => {
        setEditing(row);
        form.setData({
            detail_rumah_id: row.detail_rumah_id ?? "",
            costumer_id: row.costumer_id ?? "",
            owner_name: row.owner_name ?? "",
            identity_type: row.identity_type ?? "KTP",
            identity_number: row.identity_number ?? "",
            phone: row.phone ?? "",
            email: row.email ?? "",
            address: row.address ?? "",
            spouse_name: row.spouse_name ?? "",
            acquisition_method: row.acquisition_method ?? "data_lama",
            acquired_at: row.acquired_at ?? "",
            document_number: row.document_number ?? "",
            notes: row.notes ?? "",
            attachment: null,
        });
        form.clearErrors();
        setModalOpen(true);
    };

    const selectCustomer = (value, customer) => {
        form.setData({
            ...form.data,
            costumer_id: value,
            owner_name: customer?.owner_name ?? form.data.owner_name,
            identity_type: customer?.identity_type ?? form.data.identity_type,
            identity_number:
                customer?.identity_number ?? form.data.identity_number,
            phone: customer?.phone ?? "",
            email: customer?.email ?? "",
            address: customer?.address ?? "",
            spouse_name: customer?.spouse_name ?? "",
        });
    };

    const submit = (event) => {
        event.preventDefault();
        if (editing) {
            form.transform((data) => ({ ...data, _method: "put" }));
            form.post(`${baseUrl}/${editing.id}`, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setModalOpen(false),
                onFinish: () => form.transform((data) => data),
            });
            return;
        }
        form.post(baseUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setModalOpen(false),
        });
    };

    const submitFilters = (event) => {
        event.preventDefault();
        router.get(
            baseUrl,
            {
                search,
                status,
                cabang_id: branchId,
                perumahan_id: projectId,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const deactivate = (row) => {
        if (
            !window.confirm(
                `Nonaktifkan kepemilikan ${row.owner_name} pada ${row.unit}? Riwayat tetap disimpan.`,
            )
        )
            return;
        router.post(
            `${baseUrl}/${row.id}/deactivate`,
            {},
            { preserveScroll: true },
        );
    };

    const toggleLock = (row) =>
        router.post(
            `${baseUrl}/${row.id}/${row.record_status === "locked" ? "unlock" : "lock"}`,
            {},
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="flex flex-col gap-4 border-b border-silver-deep/60 pb-5 dark:border-white/10 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-xs font-extrabold uppercase tracking-[0.16em] text-ink-soft">
                            Master Kepemilikan
                        </p>
                        <h2 className="mt-2 font-display text-3xl font-extrabold">
                            {title}
                        </h2>
                        <p className="mt-2 max-w-4xl leading-7 text-ink-soft dark:text-white/60">
                            {description}
                        </p>
                    </div>
                    {permissions.canCreate && (
                        <Button type="button" onClick={openCreate}>
                            <PlusCircle size={17} /> Tambah Pemilik Unit
                        </Button>
                    )}
                </section>

                <section className="grid gap-px overflow-hidden rounded-lg border border-silver-deep/60 bg-silver-deep/60 dark:border-white/10 dark:bg-white/10">
                    {[["Jumlah data pemilik", rows.total ?? 0]].map(
                        ([label, value]) => (
                            <div
                                className="bg-white px-5 py-4 dark:bg-graphite"
                                key={label}
                            >
                                <p className="text-xs font-extrabold uppercase text-ink-soft dark:text-white/50">
                                    {label}
                                </p>
                                <p className="mt-2 text-2xl font-extrabold">
                                    {value}
                                </p>
                            </div>
                        ),
                    )}
                </section>

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-[1.5fr_1fr_1fr_1fr_auto_auto] xl:items-end"
                        onSubmit={submitFilters}
                    >
                        <Input
                            label="Pencarian"
                            value={search}
                            placeholder="Cari pemilik, NIK, dokumen, proyek, atau unit..."
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Cabang Perusahaan</span>
                            <Dropdown
                                value={branchId}
                                options={[
                                    { value: "", label: "Semua Cabang" },
                                    ...options.branches,
                                ]}
                                onChange={(value) => {
                                    setBranchId(value);
                                    setProjectId("");
                                }}
                            />
                        </label>
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Perumahan</span>
                            <Dropdown
                                value={projectId}
                                options={projectOptions}
                                onChange={setProjectId}
                            />
                        </label>
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Status</span>
                            <Dropdown
                                value={status}
                                options={options.statuses}
                                searchable={false}
                                onChange={setStatus}
                            />
                        </label>
                        <Button type="submit">
                            <Search size={17} /> Cari
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.get(baseUrl)}
                        >
                            <RotateCcw size={17} /> Atur Ulang
                        </Button>
                    </form>

                    <div className="overflow-x-auto border-t border-silver-deep/60 dark:border-white/10">
                        <table className="min-w-[1200px] w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {[
                                        "Proyek / Unit",
                                        "Pemilik",
                                        "Identitas",
                                        "Kontak",
                                        "Mulai / Berakhir",
                                        "Dokumen",
                                        "Status",
                                        "Audit",
                                        "Aksi",
                                    ].map((column) => (
                                        <th
                                            className="px-4 py-4 font-extrabold"
                                            key={column}
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-4">
                                            <strong>{row.project}</strong>
                                            <p className="mt-1 text-xs font-bold text-ink-soft">
                                                {row.branch} · {row.unit}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4">
                                            <strong>{row.owner_name}</strong>
                                            {row.spouse_name && (
                                                <p className="mt-1 text-xs text-ink-soft">
                                                    Pasangan: {row.spouse_name}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-4 font-semibold">
                                            {row.identity_type} ·{" "}
                                            {row.identity_number}
                                        </td>
                                        <td className="px-4 py-4">
                                            <p>{row.phone || "-"}</p>
                                            <p className="text-xs text-ink-soft">
                                                {row.email || "-"}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 font-semibold">
                                            <p>{row.acquired_at}</p>
                                            <p className="text-xs text-ink-soft">
                                                {row.ended_at
                                                    ? `s.d. ${row.ended_at}`
                                                    : "Masih aktif"}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4">
                                            <p className="font-semibold">
                                                {row.document_number || "-"}
                                            </p>
                                            {row.attachment_url && (
                                                <a
                                                    className="mt-1 inline-flex items-center gap-1 text-xs font-bold text-emerald-600 underline"
                                                    href={row.attachment_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Eye size={13} /> Buka
                                                    lampiran
                                                </a>
                                            )}
                                        </td>
                                        <td className="px-4 py-4">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-extrabold ${row.is_active ? "bg-emerald-100 text-emerald-800" : "bg-slate-200 text-slate-600"}`}
                                            >
                                                {row.is_active
                                                    ? "Pemilik Aktif"
                                                    : "Riwayat"}
                                            </span>
                                            <p className="mt-2 text-xs font-bold text-ink-soft">
                                                {row.record_status === "locked"
                                                    ? "Locked"
                                                    : "Draf"}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 text-xs">
                                            <p>Dibuat: {row.created_by}</p>
                                            <p className="mt-1 text-ink-soft">
                                                Diubah: {row.updated_by}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4">
                                            <TableActions>
                                                {row.can_edit && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            openEdit(row)
                                                        }
                                                    >
                                                        <Edit3 size={14} /> Edit
                                                    </Button>
                                                )}
                                                {row.can_deactivate && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            deactivate(row)
                                                        }
                                                    >
                                                        <UserMinus size={14} />{" "}
                                                        Nonaktifkan
                                                    </Button>
                                                )}
                                                {row.source_type === "legacy" &&
                                                    (row.record_status !==
                                                        "locked" ||
                                                        permissions.canUnlock) && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                toggleLock(row)
                                                            }
                                                        >
                                                            {row.record_status ===
                                                            "locked" ? (
                                                                <Unlock
                                                                    size={14}
                                                                />
                                                            ) : (
                                                                <Lock
                                                                    size={14}
                                                                />
                                                            )}
                                                            {row.record_status ===
                                                            "locked"
                                                                ? "Buka Kunci"
                                                                : "Kunci"}
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
                                            colSpan={9}
                                        >
                                            Belum ada data kepemilikan sesuai
                                            filter.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <Pagination links={rows.links} />
                </section>
            </div>

            <Modal
                open={modalOpen}
                title={
                    editing ? "Ubah Data Pemilik" : "Input Data Pemilik Unit"
                }
                onClose={() => setModalOpen(false)}
            >
                <form className="grid gap-5" onSubmit={submit}>
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="grid gap-2 text-sm font-extrabold md:col-span-2">
                            <span>Unit Rumah</span>
                            <Dropdown
                                value={form.data.detail_rumah_id}
                                options={options.units}
                                disabled={Boolean(editing)}
                                onChange={(value) =>
                                    form.setData("detail_rumah_id", value)
                                }
                            />
                        </label>
                        {form.errors.detail_rumah_id && (
                            <p className="text-xs font-bold text-red-600 md:col-span-2">
                                {form.errors.detail_rumah_id}
                            </p>
                        )}
                        {selectedUnit?.current_owner &&
                            String(selectedUnit.value) !==
                                String(editing?.detail_rumah_id) && (
                                <p className="rounded-lg bg-amber-50 p-3 text-sm font-bold text-amber-800 md:col-span-2">
                                    Unit saat ini tercatat atas nama{" "}
                                    {selectedUnit.current_owner}. Menyimpan data
                                    baru akan memindahkannya ke riwayat.
                                </p>
                            )}
                        <label className="grid gap-2 text-sm font-extrabold md:col-span-2">
                            <span>
                                Pilih Pelanggan yang Sudah Ada (Opsional)
                            </span>
                            <Dropdown
                                label="Kosongkan bila pembeli belum terdaftar"
                                value={form.data.costumer_id}
                                options={[
                                    {
                                        value: "",
                                        label: "Buat pelanggan baru dari data di bawah",
                                    },
                                    ...options.customers,
                                ]}
                                onChange={selectCustomer}
                            />
                        </label>
                        <Input
                            label="Nama Pemilik"
                            value={form.data.owner_name}
                            error={form.errors.owner_name}
                            onChange={(event) =>
                                form.setData("owner_name", event.target.value)
                            }
                        />
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Jenis Identitas</span>
                            <Dropdown
                                value={form.data.identity_type}
                                options={options.identityTypes}
                                searchable={false}
                                onChange={(value) =>
                                    form.setData("identity_type", value)
                                }
                            />
                        </label>
                        <Input
                            label="Nomor Identitas / NIK"
                            value={form.data.identity_number}
                            error={form.errors.identity_number}
                            onChange={(event) =>
                                form.setData(
                                    "identity_number",
                                    event.target.value,
                                )
                            }
                        />
                        <Input
                            label="Nomor Telepon"
                            value={form.data.phone}
                            error={form.errors.phone}
                            onChange={(event) =>
                                form.setData("phone", event.target.value)
                            }
                        />
                        <Input
                            label="Email"
                            type="email"
                            value={form.data.email}
                            error={form.errors.email}
                            onChange={(event) =>
                                form.setData("email", event.target.value)
                            }
                        />
                        <Input
                            label="Nama Pasangan / Pemilik Bersama"
                            value={form.data.spouse_name}
                            error={form.errors.spouse_name}
                            onChange={(event) =>
                                form.setData("spouse_name", event.target.value)
                            }
                        />
                        <Textarea
                            className="md:col-span-2"
                            label="Alamat Pemilik"
                            value={form.data.address}
                            error={form.errors.address}
                            onChange={(event) =>
                                form.setData("address", event.target.value)
                            }
                        />
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Cara Perolehan</span>
                            <Dropdown
                                value={form.data.acquisition_method}
                                options={options.methods}
                                searchable={false}
                                onChange={(value) =>
                                    form.setData("acquisition_method", value)
                                }
                            />
                        </label>
                        <Input
                            label="Tanggal Mulai Kepemilikan"
                            type="date"
                            value={form.data.acquired_at}
                            error={form.errors.acquired_at}
                            onChange={(event) =>
                                form.setData("acquired_at", event.target.value)
                            }
                        />
                        <Input
                            label="Nomor Akad / Sertifikat / Dokumen"
                            value={form.data.document_number}
                            error={form.errors.document_number}
                            onChange={(event) =>
                                form.setData(
                                    "document_number",
                                    event.target.value,
                                )
                            }
                        />
                        <label className="grid gap-2 text-sm font-extrabold">
                            <span>Lampiran Dokumen</span>
                            <input
                                className="min-h-11 rounded-lg border border-silver-deep/70 bg-white px-3 py-2 dark:border-white/10 dark:bg-white/8"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx"
                                onChange={(event) =>
                                    form.setData(
                                        "attachment",
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </label>
                        <Textarea
                            className="md:col-span-2"
                            label="Catatan"
                            value={form.data.notes}
                            error={form.errors.notes}
                            onChange={(event) =>
                                form.setData("notes", event.target.value)
                            }
                        />
                    </div>
                    <div className="flex justify-end gap-2 border-t border-silver-deep/60 pt-4 dark:border-white/10">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            <KeyRound size={17} />{" "}
                            {form.processing
                                ? "Menyimpan..."
                                : "Simpan Pemilik"}
                        </Button>
                    </div>
                </form>
            </Modal>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Data Pemilik Unit"}>
        {page}
    </AdminLayout>
);
