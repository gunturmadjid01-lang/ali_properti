import { Head, router } from "@inertiajs/react";
import {
    Landmark,
    Search,
    TrendingDown,
    TrendingUp,
    WalletCards,
} from "lucide-react";
import { useState } from "react";
import { Button, Dropdown, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { FinanceChart } from "../../../Components/Finance/FinanceChart";

const money = (value) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(value ?? 0));

export default function Index({
    title,
    baseUrl,
    bankOptions = [],
    selectedBankId,
    summaries = [],
    transactions = [],
    filters = {},
}) {
    const [bankId, setBankId] = useState(selectedBankId ?? "");
    const [search, setSearch] = useState(filters.search ?? "");
    const selectedSummary = summaries.find(
        (row) => String(row.id) === String(bankId),
    );

    const applyFilter = (event) => {
        event?.preventDefault();
        router.get(
            baseUrl,
            { bank_id: bankId, search },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex items-center gap-3">
                        <div className="grid h-11 w-11 place-items-center rounded-lg bg-ink text-white dark:bg-white dark:text-ink">
                            <Landmark size={21} />
                        </div>
                        <div>
                            <h2 className="text-2xl font-extrabold">{title}</h2>
                            <p className="text-sm text-ink-soft">
                                Saldo dihitung otomatis dari seluruh pemasukan
                                dan pengeluaran per rekening.
                            </p>
                        </div>
                    </div>
                    <form
                        className="mt-5 grid gap-3 md:grid-cols-[1fr_1fr_auto]"
                        onSubmit={applyFilter}
                    >
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">
                                Rekening
                            </span>
                            <Dropdown
                                value={bankId}
                                label="Pilih Rekening"
                                options={bankOptions}
                                onChange={(value) => setBankId(value)}
                            />
                        </div>
                        <Input
                            label="Cari Transaksi"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <div className="flex items-end">
                            <Button type="submit">
                                <Search size={17} /> Tampilkan
                            </Button>
                        </div>
                    </form>
                </section>

                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/10 p-5">
                        <TrendingUp className="text-emerald-600" />
                        <p className="mt-3 text-xs font-bold uppercase text-ink-soft">
                            Total Pemasukan
                        </p>
                        <p className="mt-1 text-xl font-extrabold">
                            {money(selectedSummary?.pemasukan)}
                        </p>
                    </div>
                    <div className="rounded-lg border border-red-500/20 bg-red-500/10 p-5">
                        <TrendingDown className="text-red-600" />
                        <p className="mt-3 text-xs font-bold uppercase text-ink-soft">
                            Total Pengeluaran
                        </p>
                        <p className="mt-1 text-xl font-extrabold">
                            {money(selectedSummary?.pengeluaran)}
                        </p>
                    </div>
                    <div className="rounded-lg border border-gold/30 bg-gold/10 p-5">
                        <WalletCards className="text-gold-deep" />
                        <p className="mt-3 text-xs font-bold uppercase text-ink-soft">
                            Saldo Rekening
                        </p>
                        <p className="mt-1 text-xl font-extrabold">
                            {money(selectedSummary?.saldo)}
                        </p>
                    </div>
                </div>
                <FinanceChart
                    title="Pergerakan Saldo Rekening"
                    subtitle="Mengikuti rekening, pencarian, dan perumahan aktif."
                    items={transactions.map((row) => ({
                        label: row.tanggal,
                        value: Math.abs(Number(row.saldo)),
                        tone:
                            Number(row.saldo) < 0
                                ? "bg-red-500"
                                : "bg-emerald-500",
                    }))}
                />

                <section className="overflow-hidden rounded-lg border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft/80 text-left text-xs uppercase tracking-wider text-ink-soft">
                                <tr>
                                    {[
                                        "Tanggal",
                                        "Tipe Transaksi",
                                        "Keterangan",
                                        "Input Oleh",
                                        "Pemasukan",
                                        "Pengeluaran",
                                        "Saldo Berjalan",
                                    ].map((label) => (
                                        <th className="px-5 py-4" key={label}>
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-silver-deep/50">
                                {transactions.map((row) => (
                                    <tr key={row.id}>
                                        <td className="px-5 py-4 font-bold">
                                            {row.tanggal}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.tipe_post}
                                        </td>
                                        <td className="min-w-72 px-5 py-4">
                                            {row.keterangan}
                                        </td>
                                        <td className="px-5 py-4">
                                            {row.input_oleh}
                                        </td>
                                        <td className="px-5 py-4 font-bold text-emerald-600">
                                            {row.pemasukan
                                                ? money(row.pemasukan)
                                                : "-"}
                                        </td>
                                        <td className="px-5 py-4 font-bold text-red-600">
                                            {row.pengeluaran
                                                ? money(row.pengeluaran)
                                                : "-"}
                                        </td>
                                        <td className="px-5 py-4 font-extrabold">
                                            {money(row.saldo)}
                                        </td>
                                    </tr>
                                ))}
                                {!transactions.length && (
                                    <tr>
                                        <td
                                            className="px-5 py-10 text-center font-bold text-ink-soft"
                                            colSpan="7"
                                        >
                                            Belum ada transaksi pada rekening
                                            ini.
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

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Mutasi & Saldo Rekening"}>
        {page}
    </AdminLayout>
);
