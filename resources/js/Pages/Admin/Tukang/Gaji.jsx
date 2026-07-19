import { Head, Link, router, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    CheckCircle2,
    Edit3,
    LoaderCircle,
    Plus,
    Save,
    Trash2,
    X,
} from "lucide-react";
import { useState } from "react";
import {
    Button,
    CurrencyInput,
    Dropdown,
    Form,
    Input,
    TableActions,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { useResourcePermissions } from "../../../Utils/permissions";

const now = new Date();
const localToday = new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
    .toISOString()
    .slice(0, 10);

const emptyForm = {
    nominal: "",
    tanggal_berlaku: localToday,
    status: "aktif",
};

const rupiah = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
});

export default function Gaji({
    title,
    baseUrl,
    tukang,
    gajis = [],
    statusOptions = [],
}) {
    const [editing, setEditing] = useState(null);
    const form = useForm(emptyForm);
    const permissions = useResourcePermissions("tukang", baseUrl);

    const resetForm = () => {
        setEditing(null);
        form.setData(emptyForm);
        form.clearErrors();
    };

    const editGaji = (gaji) => {
        setEditing(gaji);
        form.setData({
            nominal: String(Math.trunc(Number(gaji.nominal))),
            tanggal_berlaku: gaji.tanggal_berlaku,
            status: gaji.status,
        });
        form.clearErrors();
        window.scrollTo({ top: 0, behavior: "smooth" });
    };

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: resetForm };
        editing
            ? form.put(`${baseUrl}/${editing.id}`, options)
            : form.post(baseUrl, options);
    };

    const activate = (gaji) => {
        if (
            !window.confirm(
                `Jadikan gaji ${rupiah.format(Number(gaji.nominal))} sebagai gaji aktif?`,
            )
        )
            return;
        router.post(
            `${baseUrl}/${gaji.id}/aktifkan`,
            {},
            { preserveScroll: true },
        );
    };

    const destroy = (gaji) => {
        if (!window.confirm("Hapus riwayat gaji ini?")) return;
        router.delete(`${baseUrl}/${gaji.id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${title} - ${tukang.nama}`} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <Button
                        as={Link}
                        href="/admin/tukang"
                        variant="outline"
                        size="sm"
                    >
                        <ArrowLeft size={16} /> Kembali ke Daftar Tukang
                    </Button>
                    <p className="mt-5 text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                        Riwayat Gaji Tukang
                    </p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">
                        {tukang.nama}
                    </h2>
                    <p className="mt-2 font-semibold text-ink-soft">
                        {tukang.posisi_label} · {tukang.alamat}
                    </p>
                </section>

                {permissions.canUpdate && (
                    <Form
                        collapsible
                        title={
                            editing ? "Ubah Gaji Tukang" : "Tambah Gaji Tukang"
                        }
                        description="Gaji berstatus aktif akan otomatis menjadi referensi utama. Gaji aktif sebelumnya akan dinonaktifkan."
                        onSubmit={submit}
                        actions={
                            <>
                                {editing && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={resetForm}
                                    >
                                        <X size={17} /> Batal Ubah
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? (
                                        <LoaderCircle
                                            className="animate-spin"
                                            size={17}
                                        />
                                    ) : editing ? (
                                        <Save size={17} />
                                    ) : (
                                        <Plus size={17} />
                                    )}
                                    {editing
                                        ? "Simpan Perubahan"
                                        : "Tambah Gaji"}
                                </Button>
                            </>
                        }
                    >
                        <div className="grid gap-4 md:grid-cols-3">
                            <CurrencyInput
                                label="Nominal Gaji"
                                value={form.data.nominal}
                                error={form.errors.nominal}
                                onChange={(value) =>
                                    form.setData("nominal", value)
                                }
                            />
                            <Input
                                label="Tanggal Berlaku"
                                type="date"
                                value={form.data.tanggal_berlaku}
                                error={form.errors.tanggal_berlaku}
                                onChange={(event) =>
                                    form.setData(
                                        "tanggal_berlaku",
                                        event.target.value,
                                    )
                                }
                            />
                            <div className="grid content-start gap-2">
                                <span className="text-sm font-extrabold">
                                    Status
                                </span>
                                <Dropdown
                                    value={form.data.status}
                                    options={statusOptions}
                                    searchable={false}
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
                    </Form>
                )}

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="border-b border-silver-deep/60 p-5 dark:border-white/10">
                        <h3 className="text-lg font-extrabold">Riwayat Gaji</h3>
                        <p className="mt-1 text-sm text-ink-soft">
                            Hanya gaji berstatus aktif yang dipakai sebagai
                            referensi.
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-silver-deep/60 text-sm dark:divide-white/10">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-[0.12em] text-ink-soft dark:bg-white/5 dark:text-white/50">
                                <tr>
                                    {[
                                        "Nominal",
                                        "Tanggal Berlaku",
                                        "Status",
                                        "Aksi",
                                    ].map((column) => (
                                        <th
                                            className="px-5 py-4 font-extrabold"
                                            key={column}
                                        >
                                            {column}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50 dark:divide-white/10">
                                {gajis.map((gaji) => (
                                    <tr key={gaji.id}>
                                        <td className="px-5 py-4 font-extrabold">
                                            {rupiah.format(
                                                Number(gaji.nominal),
                                            )}
                                        </td>
                                        <td className="px-5 py-4 font-semibold">
                                            {gaji.tanggal_berlaku_label}
                                        </td>
                                        <td className="px-5 py-4">
                                            <span
                                                className={`inline-flex rounded-full px-3 py-1 text-xs font-extrabold ${gaji.status === "aktif" ? "bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300" : "bg-silver text-ink-soft dark:bg-white/10 dark:text-white/55"}`}
                                            >
                                                {gaji.status_label}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <TableActions>
                                                {permissions.canUpdate &&
                                                    gaji.status !== "aktif" && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() =>
                                                                activate(gaji)
                                                            }
                                                        >
                                                            <CheckCircle2
                                                                size={15}
                                                            />{" "}
                                                            Aktifkan
                                                        </Button>
                                                    )}
                                                {permissions.canUpdate && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            editGaji(gaji)
                                                        }
                                                    >
                                                        <Edit3 size={15} /> Ubah
                                                    </Button>
                                                )}
                                                {permissions.canDelete && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            destroy(gaji)
                                                        }
                                                    >
                                                        <Trash2 size={15} />{" "}
                                                        Hapus
                                                    </Button>
                                                )}
                                            </TableActions>
                                        </td>
                                    </tr>
                                ))}
                                {gajis.length === 0 && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center font-bold text-ink-soft"
                                            colSpan={4}
                                        >
                                            Belum ada riwayat gaji.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

Gaji.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Gaji Tukang"}>
        {page}
    </AdminLayout>
);
