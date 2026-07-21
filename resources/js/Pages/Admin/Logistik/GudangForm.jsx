import { Head, router, useForm } from "@inertiajs/react";
import { ChevronLeft } from "lucide-react";
import { useEffect } from "react";
import {
    Button,
    Dropdown,
    Input,
    Textarea,
} from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";

export default function GudangForm({
    title,
    baseUrl,
    gudang,
    options = {},
}) {
    const isEditing = gudang?.id;
    const form = useForm({
        nama_gudang: gudang?.nama_gudang ?? "",
        cabang_id: gudang?.cabang_id ?? "",
        perumahan_id: gudang?.perumahan_id ?? "",
        penanggung_jawab: gudang?.penanggung_jawab ?? "",
        phone: gudang?.phone ?? "",
        alamat: gudang?.alamat ?? "",
        catatan: gudang?.catatan ?? "",
        status: gudang?.status ?? "aktif",
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        if (isEditing) {
            form.put(`/admin/gudang/${gudang.id}`, {
                onSuccess: () => {
                    router.visit(baseUrl);
                },
            });
        } else {
            form.post("/admin/gudang", {
                onSuccess: () => {
                    router.visit(baseUrl);
                },
            });
        }
    };

    return (
        <>
            <Head title={title} />
            <AdminLayout>
                <div className="px-6 py-4">
                    <div className="mb-6">
                        <Button
                            variant="outline"
                            size="sm"
                            as="button"
                            onClick={() => router.visit(baseUrl)}
                            className="mb-4"
                        >
                            <ChevronLeft size={17} /> Kembali
                        </Button>
                        <h1 className="text-3xl font-bold text-ink-darkest">
                            {title}
                        </h1>
                        <p className="mt-1 text-sm text-ink-soft">
                            {isEditing
                                ? `Edit data gudang ${gudang.kode_gudang}`
                                : "Tambahkan gudang baru ke sistem"}
                        </p>
                    </div>

                    <div className="rounded-lg border border-silver-deep/30 bg-white p-6">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid gap-6 md:grid-cols-2">
                                <Input
                                    label="Nama Gudang"
                                    placeholder="Contoh: Gudang Utama Jakarta"
                                    value={form.data.nama_gudang}
                                    onChange={(e) =>
                                        form.setData(
                                            "nama_gudang",
                                            e.target.value
                                        )
                                    }
                                    error={form.errors.nama_gudang}
                                    required
                                />

                                <Dropdown
                                    label="Cabang"
                                    options={options.cabangs || []}
                                    value={form.data.cabang_id}
                                    onChange={(value) =>
                                        form.setData("cabang_id", value)
                                    }
                                    error={form.errors.cabang_id}
                                />

                                <Dropdown
                                    label="Perumahan"
                                    options={options.perumahans || []}
                                    value={form.data.perumahan_id}
                                    onChange={(value) =>
                                        form.setData("perumahan_id", value)
                                    }
                                    error={form.errors.perumahan_id}
                                />

                                <Input
                                    label="Penanggung Jawab"
                                    placeholder="Nama PIC gudang"
                                    value={form.data.penanggung_jawab}
                                    onChange={(e) =>
                                        form.setData(
                                            "penanggung_jawab",
                                            e.target.value
                                        )
                                    }
                                    error={form.errors.penanggung_jawab}
                                />

                                <Input
                                    label="Nomor Telepon"
                                    placeholder="Contoh: 08123456789"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData("phone", e.target.value)
                                    }
                                    error={form.errors.phone}
                                />

                                <Dropdown
                                    label="Status"
                                    options={options.status || []}
                                    value={form.data.status}
                                    onChange={(value) =>
                                        form.setData("status", value)
                                    }
                                    error={form.errors.status}
                                    required
                                />
                            </div>

                            <div>
                                <Textarea
                                    label="Alamat"
                                    placeholder="Alamat lengkap gudang"
                                    value={form.data.alamat}
                                    onChange={(e) =>
                                        form.setData("alamat", e.target.value)
                                    }
                                    error={form.errors.alamat}
                                    rows={3}
                                />
                            </div>

                            <div>
                                <Textarea
                                    label="Catatan"
                                    placeholder="Catatan tambahan tentang gudang"
                                    value={form.data.catatan}
                                    onChange={(e) =>
                                        form.setData("catatan", e.target.value)
                                    }
                                    error={form.errors.catatan}
                                    rows={3}
                                />
                            </div>

                            <div className="flex gap-3 border-t border-silver-deep/30 pt-6">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {isEditing
                                        ? "Simpan Perubahan"
                                        : "Tambah Gudang"}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.visit(baseUrl)}
                                >
                                    Batal
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </AdminLayout>
        </>
    );
}
