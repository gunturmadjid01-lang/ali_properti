import { Head, router, useForm } from "@inertiajs/react";
import {
    CheckCircle2,
    Edit3,
    Eye,
    Lock,
    Search,
    ShieldCheck,
    Trash2,
    Unlock,
    X,
} from "lucide-react";
import { useMemo, useState } from "react";
import Pagination from "../../../Components/Pagination";
import {
    Button,
    Dropdown,
    Form,
    Input,
    Modal,
    Textarea,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { scopedTahapanOptions } from "../../../Utils/tahapanOptions";

export default function Index({
    title,
    baseUrl,
    rows = { data: [], links: [] },
    filters = {},
    options = {},
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const [filterPerumahan, setFilterPerumahan] = useState(
        filters.perumahan_id ?? "",
    );
    const [filterUnit, setFilterUnit] = useState(filters.detail_rumah_id ?? "");
    const [editing, setEditing] = useState(null);
    const [detail, setDetail] = useState(null);
    const form = useForm({
        tanggal: new Date().toISOString().slice(0, 10),
        perumahan_id: "",
        detail_rumah_id: "",
        tahapan_pembangunan_id: "",
        site_schedule_id: "",
        progress_pembangunan_id: "",
        hasil: "sesuai",
        item_pemeriksaan: "",
        temuan: "",
        tindakan_perbaikan: "",
        target_selesai: "",
        status: "terbuka",
        foto: null,
    });
    const perumahans = options.perumahans ?? [];
    const detailRumahs = options.detailRumahs ?? [];
    const tahapanPembangunansUnit =
        options.tahapanPembangunansUnit ?? options.tahapanPembangunans ?? [];
    const tahapanPembangunansKawasan =
        options.tahapanPembangunansKawasan ?? options.tahapanPembangunans ?? [];
    const resolveScopedValue = (selectedValue, fallbackValue) =>
        selectedValue !== undefined &&
        selectedValue !== null &&
        selectedValue !== ""
            ? selectedValue
            : fallbackValue;
    const tahapanPembangunans = useMemo(
        () =>
            scopedTahapanOptions(
                form.data.detail_rumah_id
                    ? tahapanPembangunansUnit
                    : tahapanPembangunansKawasan,
                form.data.perumahan_id,
                form.data.detail_rumah_id,
            ),
        [
            form.data.detail_rumah_id,
            form.data.perumahan_id,
            tahapanPembangunansKawasan,
            tahapanPembangunansUnit,
        ],
    );
    const unitOptions = useMemo(
        () =>
            detailRumahs.filter(
                (row) =>
                    !form.data.perumahan_id ||
                    row.perumahan_id === String(form.data.perumahan_id),
            ),
        [form.data.perumahan_id, detailRumahs],
    );
    const scheduleOptions = useMemo(
        () =>
            (options.siteSchedules ?? []).filter(
                (row) =>
                    (!form.data.perumahan_id ||
                        row.perumahan_id === String(form.data.perumahan_id)) &&
                    (!form.data.detail_rumah_id ||
                        row.detail_rumah_id ===
                            String(form.data.detail_rumah_id)),
            ),
        [
            form.data.perumahan_id,
            form.data.detail_rumah_id,
            options.siteSchedules,
        ],
    );
    const progressOptions = useMemo(
        () =>
            (options.progressPembangunans ?? []).filter(
                (row) =>
                    (!form.data.perumahan_id ||
                        row.perumahan_id === String(form.data.perumahan_id)) &&
                    (!form.data.detail_rumah_id ||
                        row.detail_rumah_id ===
                            String(form.data.detail_rumah_id)),
            ),
        [
            form.data.perumahan_id,
            form.data.detail_rumah_id,
            options.progressPembangunans,
        ],
    );
    const resetForm = () => {
        setEditing(null);
        form.reset();
        form.clearErrors();
        form.setData("tanggal", new Date().toISOString().slice(0, 10));
    };
    const submit = (event) => {
        event.preventDefault();
        const requestOptions = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: resetForm,
        };
        if (editing) {
            form.put(`${baseUrl}/${editing.id}`, requestOptions);
            return;
        }
        form.post(baseUrl, requestOptions);
    };
    const editRow = (row) => {
        setEditing(row);
        form.setData({
            tanggal: row.tanggal ?? "",
            perumahan_id: row.perumahan_id ?? "",
            detail_rumah_id: row.detail_rumah_id ?? "",
            tahapan_pembangunan_id: row.tahapan_pembangunan_id ?? "",
            site_schedule_id: row.site_schedule_id ?? "",
            progress_pembangunan_id: row.progress_pembangunan_id ?? "",
            hasil: row.hasil ?? "sesuai",
            item_pemeriksaan: row.item_pemeriksaan ?? "",
            temuan: row.temuan ?? "",
            tindakan_perbaikan: row.tindakan_perbaikan ?? "",
            target_selesai: row.target_selesai ?? "",
            status: row.status ?? "terbuka",
            foto: null,
        });
        window.scrollTo({ top: 0, behavior: "smooth" });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                {(permissions.canCreate || permissions.canUpdate) && (
                    <Form
                        collapsible
                        title={
                            editing
                                ? `Edit ${editing.kode_inspeksi}`
                                : "Input Pemeriksaan Kualitas"
                        }
                        description="Catat hasil pemeriksaan, defect, pekerjaan ulang, tindakan koreksi, dan bukti kondisi lapangan."
                        onSubmit={submit}
                        actions={
                            <>
                                {editing && permissions.canUpdate && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={resetForm}
                                    >
                                        <X size={14} /> Batal
                                    </Button>
                                )}
                                {((editing && permissions.canUpdate) ||
                                    (!editing && permissions.canCreate)) && (
                                    <Button type="submit">
                                        <ShieldCheck size={17} />{" "}
                                        {editing
                                            ? "Simpan Perubahan"
                                            : "Simpan Inspeksi"}
                                    </Button>
                                )}
                            </>
                        }
                    >
                        {Object.keys(form.errors).length > 0 && (
                            <div className="rounded-lg bg-red-50 p-4 text-sm font-bold text-red-700">
                                {Object.values(form.errors).map((error) => (
                                    <p key={error}>{error}</p>
                                ))}
                            </div>
                        )}
                        <div className="grid gap-4 md:grid-cols-4">
                            <Input
                                label="Tanggal"
                                type="date"
                                value={form.data.tanggal}
                                onChange={(event) =>
                                    form.setData("tanggal", event.target.value)
                                }
                            />
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Perumahan
                                </span>
                                <Dropdown
                                    label="Pilih Perumahan"
                                    value={form.data.perumahan_id}
                                    options={perumahans}
                                    onChange={(value) =>
                                        form.setData({
                                            ...form.data,
                                            perumahan_id: value,
                                            detail_rumah_id: "",
                                            tahapan_pembangunan_id: "",
                                            site_schedule_id: "",
                                            progress_pembangunan_id: "",
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Unit
                                </span>
                                <Dropdown
                                    label="Kawasan / Unit"
                                    value={form.data.detail_rumah_id}
                                    options={unitOptions}
                                    onChange={(value, selected) =>
                                        form.setData({
                                            ...form.data,
                                            detail_rumah_id: value,
                                            perumahan_id: resolveScopedValue(
                                                selected?.perumahan_id,
                                                form.data.perumahan_id,
                                            ),
                                            tahapan_pembangunan_id: "",
                                            site_schedule_id: "",
                                            progress_pembangunan_id: "",
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Tahapan
                                </span>
                                <Dropdown
                                    label={
                                        form.data.detail_rumah_id
                                            ? "Pilih Tahapan Rumah"
                                            : "Pilih Tahapan Kawasan"
                                    }
                                    value={form.data.tahapan_pembangunan_id}
                                    options={tahapanPembangunans}
                                    onChange={(value) =>
                                        form.setData(
                                            "tahapan_pembangunan_id",
                                            value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Jadwal Lapangan Terkait
                                </span>
                                <Dropdown
                                    label="Pilih Jadwal"
                                    value={form.data.site_schedule_id}
                                    options={scheduleOptions}
                                    onChange={(value, selected) =>
                                        form.setData({
                                            ...form.data,
                                            site_schedule_id: value,
                                            perumahan_id: resolveScopedValue(
                                                selected?.perumahan_id,
                                                form.data.perumahan_id,
                                            ),
                                            detail_rumah_id: resolveScopedValue(
                                                selected?.detail_rumah_id,
                                                form.data.detail_rumah_id,
                                            ),
                                            tahapan_pembangunan_id:
                                                resolveScopedValue(
                                                    selected?.tahapan_pembangunan_id,
                                                    form.data
                                                        .tahapan_pembangunan_id,
                                                ),
                                            item_pemeriksaan:
                                                form.data.item_pemeriksaan ||
                                                selected?.nama_pekerjaan ||
                                                "",
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Kemajuan Terkait
                                </span>
                                <Dropdown
                                    label="Pilih Kemajuan Approved"
                                    value={form.data.progress_pembangunan_id}
                                    options={progressOptions}
                                    onChange={(value, selected) =>
                                        form.setData({
                                            ...form.data,
                                            progress_pembangunan_id: value,
                                            site_schedule_id:
                                                resolveScopedValue(
                                                    selected?.site_schedule_id,
                                                    form.data.site_schedule_id,
                                                ),
                                            perumahan_id: resolveScopedValue(
                                                selected?.perumahan_id,
                                                form.data.perumahan_id,
                                            ),
                                            detail_rumah_id: resolveScopedValue(
                                                selected?.detail_rumah_id,
                                                form.data.detail_rumah_id,
                                            ),
                                            tahapan_pembangunan_id:
                                                resolveScopedValue(
                                                    selected?.tahapan_pembangunan_id,
                                                    form.data
                                                        .tahapan_pembangunan_id,
                                                ),
                                            item_pemeriksaan:
                                                form.data.item_pemeriksaan ||
                                                selected?.nama_progress ||
                                                "",
                                        })
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Hasil
                                </span>
                                <Dropdown
                                    value={form.data.hasil}
                                    options={[
                                        { value: "sesuai", label: "Sesuai" },
                                        {
                                            value: "defect",
                                            label: "Defect / Rusak",
                                        },
                                        {
                                            value: "perlu_perbaikan",
                                            label: "Perlu Perbaikan",
                                        },
                                    ]}
                                    onChange={(value) =>
                                        form.setData("hasil", value)
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <span className="text-sm font-extrabold">
                                    Status Tindak Lanjut
                                </span>
                                <Dropdown
                                    value={form.data.status}
                                    options={[
                                        { value: "terbuka", label: "Terbuka" },
                                        {
                                            value: "dalam_perbaikan",
                                            label: "Dalam Perbaikan",
                                        },
                                        { value: "selesai", label: "Selesai" },
                                    ]}
                                    onChange={(value) =>
                                        form.setData("status", value)
                                    }
                                />
                            </div>
                            <Input
                                label="Target Selesai"
                                type="date"
                                value={form.data.target_selesai}
                                onChange={(event) =>
                                    form.setData(
                                        "target_selesai",
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <Textarea
                            label="Item yang Diperiksa"
                            value={form.data.item_pemeriksaan}
                            onChange={(event) =>
                                form.setData(
                                    "item_pemeriksaan",
                                    event.target.value,
                                )
                            }
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <Textarea
                                label="Temuan / Kerusakan"
                                value={form.data.temuan}
                                onChange={(event) =>
                                    form.setData("temuan", event.target.value)
                                }
                            />
                            <Textarea
                                label="Tindakan Perbaikan"
                                value={form.data.tindakan_perbaikan}
                                onChange={(event) =>
                                    form.setData(
                                        "tindakan_perbaikan",
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Foto Bukti
                            </span>
                            <input
                                type="file"
                                accept="image/*"
                                className="min-h-11 rounded-lg border border-silver-deep/70 p-2"
                                onChange={(event) =>
                                    form.setData(
                                        "foto",
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </div>
                    </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="grid gap-3 p-5 lg:grid-cols-[1.2fr_1fr_1fr_auto]"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                baseUrl,
                                {
                                    search,
                                    perumahan_id: filterPerumahan,
                                    detail_rumah_id: filterUnit,
                                },
                                { preserveState: true, replace: true },
                            );
                        }}
                    >
                        <Input
                            label="Cari Inspeksi"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Perumahan
                            </span>
                            <Dropdown
                                value={filterPerumahan}
                                label="Semua Perumahan"
                                options={[
                                    { value: "", label: "Semua Perumahan" },
                                    ...perumahans,
                                ]}
                                onChange={(value) => {
                                    setFilterPerumahan(value);
                                    setFilterUnit("");
                                }}
                            />
                        </div>
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Unit</span>
                            <Dropdown
                                value={filterUnit}
                                label="Semua Unit"
                                options={[
                                    { value: "", label: "Semua Unit" },
                                    ...detailRumahs.filter(
                                        (row) =>
                                            !filterPerumahan ||
                                            row.perumahan_id ===
                                                String(filterPerumahan),
                                    ),
                                ]}
                                onChange={setFilterUnit}
                            />
                        </div>
                        <div className="flex items-end">
                            <Button className="w-full">
                                <Search size={16} /> Cari
                            </Button>
                        </div>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                <tr>
                                    {[
                                        "Tanggal",
                                        "Lokasi",
                                        "Pemeriksaan",
                                        "Temuan / Tindakan",
                                        "Status",
                                        "Persetujuan",
                                        "Audit",
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
                                            {row.tanggal}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.perumahan}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.unit} · {row.tahapan}
                                            </span>
                                        </td>
                                        <td className="max-w-sm px-5 py-4">
                                            <span className="font-bold">
                                                {row.hasil}
                                            </span>
                                            <br />
                                            {row.item_pemeriksaan}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.progress !== "-"
                                                    ? row.progress
                                                    : row.jadwal !== "-"
                                                      ? row.jadwal
                                                      : ""}
                                            </span>
                                        </td>
                                        <td className="max-w-sm px-5 py-4">
                                            {row.temuan || "-"}
                                            <br />
                                            <span className="text-xs text-ink-soft">
                                                {row.tindakan_perbaikan || "-"}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.status}
                                        </td>
                                        <td className="px-5 py-4 font-bold">
                                            {row.approval_status}
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
                                            <br />
                                            <span className="font-bold">
                                                Setujui:
                                            </span>{" "}
                                            {row.approved_by_name}
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setDetail(row)
                                                    }
                                                >
                                                    <Eye size={14} /> Detail
                                                </Button>
                                                {row.foto_url && (
                                                    <Button
                                                        as="a"
                                                        href={row.foto_url}
                                                        target="_blank"
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        Foto
                                                    </Button>
                                                )}
                                                {permissions.canUpdate &&
                                                    row.can_edit && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                editRow(row)
                                                            }
                                                        >
                                                            <Edit3 size={14} />{" "}
                                                            Ubah
                                                        </Button>
                                                    )}
                                                {row.can_approve &&
                                                    row.approval_status !==
                                                        "approved" && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/approve`,
                                                                )
                                                            }
                                                        >
                                                            <CheckCircle2
                                                                size={14}
                                                            />
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
                                                                    "Hapus inspeksi?",
                                                                ) &&
                                                                router.delete(
                                                                    `${baseUrl}/${row.id}`,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 size={14} />
                                                        </Button>
                                                    )}
                                                {permissions.canLock &&
                                                    row.can_lock && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/lock`,
                                                                )
                                                            }
                                                        >
                                                            <Lock size={14} />{" "}
                                                            Kunci
                                                        </Button>
                                                    )}
                                                {permissions.canUnlock &&
                                                    row.can_unlock &&
                                                    row.record_status ===
                                                        "locked" && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/unlock`,
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
                                            Belum ada pemeriksaan kualitas.
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
                open={Boolean(detail)}
                onClose={() => setDetail(null)}
                title={
                    detail
                        ? `Detail ${detail.kode_inspeksi}`
                        : "Detail Kontrol Kualitas"
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
                            <b>Lokasi:</b> {detail.perumahan} - {detail.unit} -{" "}
                            {detail.tahapan}
                        </p>
                        <p>
                            <b>Item:</b> {detail.item_pemeriksaan}
                        </p>
                        <p>
                            <b>Hasil:</b> {detail.hasil}
                        </p>
                        <p>
                            <b>Temuan:</b> {detail.temuan || "-"}
                        </p>
                        <p>
                            <b>Tindakan:</b> {detail.tindakan_perbaikan || "-"}
                        </p>
                        <p>
                            <b>Status:</b> {detail.status}
                        </p>
                    </div>
                )}
            </Modal>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Kontrol Kualitas"}>
        {page}
    </AdminLayout>
);
