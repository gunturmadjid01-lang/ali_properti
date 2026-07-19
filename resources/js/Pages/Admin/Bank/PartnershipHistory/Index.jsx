import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import Pagination from "../../../../Components/Pagination";
import { Button, Input } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
export default function Index({ title, baseUrl, rows, filters = {} }) {
    const [search, setSearch] = useState(filters.search ?? "");
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-black uppercase tracking-wider text-ink-soft">
                        Master Kredit Bank
                    </p>
                    <h1 className="mt-1 text-2xl font-black">
                        Riwayat / Versi Kerja Sama
                    </h1>
                    <p className="mt-1 text-sm text-ink-soft">
                        Arsip bersifat baca-saja. Perubahan kerja sama tidak
                        menimpa versi sebelumnya.
                    </p>
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
                                    { preserveState: true },
                                );
                            }}
                        >
                            <Input
                                label="Cari perjanjian"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                            <Button>Cari</Button>
                        </form>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y text-sm">
                            <thead>
                                <tr>
                                    {[
                                        "Versi",
                                        "Nomor Perjanjian",
                                        "Nama Perjanjian",
                                        "Bank",
                                        "Perumahan",
                                        "Periode",
                                        "Status",
                                        "Dibuat",
                                    ].map((x) => (
                                        <th
                                            className="px-4 py-3 text-left text-xs font-black uppercase"
                                            key={x}
                                        >
                                            {x}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="px-4 py-3 font-black">
                                            v{r.version_number}
                                        </td>
                                        <td className="px-4 py-3">
                                            {r.agreement_number}
                                        </td>
                                        <td className="px-4 py-3 font-bold">
                                            {r.agreement_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {r.bank_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {r.housing_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {r.effective_from} —{" "}
                                            {r.effective_until || "seterusnya"}
                                        </td>
                                        <td className="px-4 py-3">
                                            {r.status}
                                        </td>
                                        <td className="px-4 py-3">
                                            {r.created_at}
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
    <AdminLayout title={page?.props?.title ?? "Riwayat / Versi Kerja Sama"}>
        {page}
    </AdminLayout>
);
