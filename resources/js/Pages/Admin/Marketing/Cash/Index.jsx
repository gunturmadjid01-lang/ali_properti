import { Head, router } from "@inertiajs/react";
import {
    CreditCard,
    Eye,
    Lock,
    PlusCircle,
    Search,
    ShieldCheck,
    Unlock,
} from "lucide-react";
import { useState } from "react";
import Pagination from "../../../../Components/Pagination";
import { Button, Input, TableActions } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";

const money = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;

export default function Index({
    title,
    description,
    baseUrl,
    rows = { data: [], links: [] },
    filters = {},
    permissions = {},
}) {
    const [search, setSearch] = useState(filters.search ?? "");
    const filter = (event) => {
        event.preventDefault();
        router.get(baseUrl, { search }, { preserveState: true, replace: true });
    };
    const action = (message, url) =>
        window.confirm(message) &&
        router.post(url, {}, { preserveScroll: true });

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">
                        Marketing / Transaksi Penjualan
                    </p>
                    <div className="mt-2 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                        <div>
                            <h1 className="text-3xl font-extrabold">{title}</h1>
                            <p className="mt-2 max-w-3xl text-ink-soft">
                                {description}
                            </p>
                        </div>
                        {permissions.canCreate && (
                            <Button
                                type="button"
                                onClick={() =>
                                    router.visit(`${baseUrl}/create`)
                                }
                            >
                                <PlusCircle size={17} /> Buat Transaksi
                            </Button>
                        )}
                    </div>
                </section>
                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <form
                        className="flex flex-col gap-3 p-5 md:flex-row md:items-end md:justify-between"
                        onSubmit={filter}
                    >
                        <Input
                            className="md:max-w-md"
                            icon={<Search size={17} />}
                            label="Cari Transaksi Cash"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <Button type="submit">
                            <Search size={17} /> Cari
                        </Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-xs">
                            <thead className="bg-silver-soft/80 text-left uppercase tracking-[0.12em] text-ink-soft">
                                <tr>
                                    {[
                                        "Kode",
                                        "SPR",
                                        "Customer",
                                        "Unit",
                                        "Harga",
                                        "Dibayar",
                                        "Sisa",
                                        "Status",
                                        "Kunci",
                                        "Aksi",
                                    ].map((label) => (
                                        <th className="px-4 py-3" key={label}>
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {rows.data.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-4 py-3 font-bold">
                                            {row.kode_cash}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.kode_spr}
                                        </td>
                                        <td className="px-4 py-3 font-semibold">
                                            {row.customer}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.unit}
                                        </td>
                                        <td className="px-4 py-3">
                                            {money(row.harga_rumah)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {money(row.total_dibayar)}
                                        </td>
                                        <td className="px-4 py-3">
                                            {money(row.sisa_tagihan)}
                                        </td>
                                        <td className="px-4 py-3 font-bold">
                                            {row.status_label}
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.record_status_label}
                                        </td>
                                        <td className="px-4 py-3">
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
                                                    <Eye size={15} /> Detail
                                                </Button>
                                                {row.can_update && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.visit(
                                                                `${baseUrl}/${row.id}/payments/create`,
                                                            )
                                                        }
                                                    >
                                                        <CreditCard size={15} />{" "}
                                                        Bayar
                                                    </Button>
                                                )}
                                                {row.can_lock && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            action(
                                                                `Lock ${row.kode_cash}?`,
                                                                `${baseUrl}/${row.id}/lock`,
                                                            )
                                                        }
                                                    >
                                                        <Lock size={15} /> Kunci
                                                    </Button>
                                                )}
                                                {row.can_unlock && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            action(
                                                                `Buka lock ${row.kode_cash}?`,
                                                                `${baseUrl}/${row.id}/unlock`,
                                                            )
                                                        }
                                                    >
                                                        <Unlock size={15} />{" "}
                                                        Unlock
                                                    </Button>
                                                )}
                                                {row.can_handover && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            action(
                                                                `Tandai serah terima ${row.kode_cash}?`,
                                                                `${baseUrl}/${row.id}/handover`,
                                                            )
                                                        }
                                                    >
                                                        <ShieldCheck
                                                            size={15}
                                                        />{" "}
                                                        Serah Terima
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
                                            colSpan={10}
                                        >
                                            Belum ada transaksi cash.
                                        </td>
                                    </tr>
                                )}
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
    <AdminLayout title={page?.props?.title ?? "Transaksi Cash"}>
        {page}
    </AdminLayout>
);
