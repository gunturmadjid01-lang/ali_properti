import { Head, Link, router } from "@inertiajs/react";
import { Button, TableActions } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
export default function Index({ title, baseUrl, rows }) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <header className="flex items-center justify-between rounded-xl bg-white/85 p-5 shadow-soft dark:border dark:border-white/10 dark:bg-[#171c23]">
                    <div>
                        <p className="text-xs font-black uppercase text-ink-soft">
                            Aturan Dokumen Kredit
                        </p>
                        <h1 className="text-2xl font-black">{title}</h1>
                        <p className="text-sm text-ink-soft">
                            Susun jenis berkas yang wajib dipenuhi untuk proses,
                            bank, produk kredit, dan kerja sama yang dipilih.
                        </p>
                    </div>
                    <Button as={Link} href={`${baseUrl}/tambah`}>
                        Tambah Paket
                    </Button>
                </header>
                <section className="overflow-hidden rounded-xl bg-white/85 shadow-soft dark:border dark:border-white/10 dark:bg-[#171c23]">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b text-left">
                                <th className="p-4">Kode / Paket</th>
                                <th>Cakupan Proses</th>
                                <th>Dokumen</th>
                                <th>Status Persetujuan</th>
                                <th className="p-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.data.map((row) => (
                                <tr className="border-b" key={row.id}>
                                    <td className="p-4">
                                        <b>{row.code}</b>
                                        <p>{row.name}</p>
                                    </td>
                                    <td>{row.types}</td>
                                    <td>{row.items_count} dokumen</td>
                                    <td>
                                        {row.approval_status ||
                                            row.record_status}
                                        {row.approval_stage &&
                                            ` · Tahap ${row.approval_stage}`}
                                    </td>
                                    <td className="p-4">
                                        <TableActions>
                                            {row.record_status !== "locked" && (
                                                <Button
                                                    as={Link}
                                                    size="sm"
                                                    variant="outline"
                                                    href={`${baseUrl}/${row.id}/edit`}
                                                >
                                                    Ubah Panduan
                                                </Button>
                                            )}
                                            {row.record_status !== "locked" && (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        router.post(
                                                            `${baseUrl}/${row.id}/lock`,
                                                        )
                                                    }
                                                >
                                                    Finalisasi
                                                </Button>
                                            )}
                                            {row.record_status === "locked" &&
                                                row.approval_status ===
                                                    "pending" && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.post(
                                                                `${baseUrl}/${row.id}/unlock`,
                                                            )
                                                        }
                                                    >
                                                        Buka Kunci
                                                    </Button>
                                                )}
                                            {row.can_review && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(
                                                                `${baseUrl}/${row.id}/review/approve`,
                                                            )
                                                        }
                                                    >
                                                        Setujui
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="danger"
                                                        onClick={() => {
                                                            const note =
                                                                prompt(
                                                                    "Alasan penolakan",
                                                                );
                                                            if (note)
                                                                router.post(
                                                                    `${baseUrl}/${row.id}/review/reject`,
                                                                    { note },
                                                                );
                                                        }}
                                                    >
                                                        Tolak
                                                    </Button>
                                                </>
                                            )}
                                        </TableActions>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}
Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Paket Dokumen"}>
        {page}
    </AdminLayout>
);
