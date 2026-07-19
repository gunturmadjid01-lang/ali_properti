import { Head, Link, router } from "@inertiajs/react";
import { Edit3, PlusCircle, Search } from "lucide-react";
import { useState } from "react";
import { Button, Dropdown, Input } from "../../../Components/UI";
import AdminLayout from "../../../Layouts/AdminLayout";
import { FinanceChart } from "../../../Components/Finance/FinanceChart";
const rp = (v) =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(v || 0));
export default function Index({ title, baseUrl, createUrl, filters, rows }) {
    const [q, setQ] = useState(filters.search ?? "");
    const [s, setS] = useState(filters.status ?? "all");
    return (
        <>
            <Head title={title} />
            <div className="grid gap-5">
                <section className="flex items-center justify-between rounded-2xl bg-ink p-6 text-white">
                    <div>
                        <p className="text-xs font-black uppercase tracking-widest text-champagne">
                            Master Penggajian
                        </p>
                        <h1 className="mt-2 text-3xl font-black">{title}</h1>
                        <p className="mt-2 text-sm text-white/60">
                            Sumber gaji pokok dan tunjangan aktif transaksi
                            penggajian.
                        </p>
                    </div>
                    <Button as={Link} href={createUrl}>
                        <PlusCircle size={17} /> Tambah Gaji
                    </Button>
                </section>
                <FinanceChart
                    title="Komposisi Gaji Aktif"
                    subtitle="Gaji pokok dan tunjangan pegawai pada hasil pencarian yang tampil."
                    items={rows.data.map((row) => ({
                        label: row.employee_name,
                        value: row.basic_salary,
                        secondary: row.fixed_allowance,
                    }))}
                />
                <section className="overflow-hidden rounded-2xl border bg-white dark:border-white/10 dark:bg-white/5">
                    <form
                        className="grid gap-3 border-b p-4 md:grid-cols-[1fr_220px_auto] md:items-end"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(
                                baseUrl,
                                { search: q, status: s },
                                { preserveState: true },
                            );
                        }}
                    >
                        <Input
                            label="Cari pegawai"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                        />
                        <div>
                            <span className="mb-2 block text-sm font-bold">
                                Status
                            </span>
                            <Dropdown
                                value={s}
                                onChange={setS}
                                options={[
                                    { value: "all", label: "Semua" },
                                    { value: "active", label: "Aktif" },
                                    { value: "inactive", label: "Nonaktif" },
                                ]}
                            />
                        </div>
                        <Button>
                            <Search size={16} /> Cari
                        </Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-silver-soft text-left text-xs uppercase">
                                <tr>
                                    <th className="p-4">Pegawai</th>
                                    <th className="p-4">Gaji Aktif</th>
                                    <th className="p-4">Masa Berlaku</th>
                                    <th className="p-4">Status</th>
                                    <th className="p-4"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {rows.data.map((r) => (
                                    <tr key={r.id}>
                                        <td className="p-4">
                                            <b>{r.employee_name}</b>
                                            <div className="text-xs text-ink-soft">
                                                {[
                                                    r.employee_number,
                                                    r.job_title,
                                                    r.branch,
                                                ]
                                                    .filter(Boolean)
                                                    .join(" · ")}
                                            </div>
                                        </td>
                                        <td className="p-4">
                                            <b>{rp(r.total_salary)}</b>
                                            <div className="text-xs">
                                                Pokok {rp(r.basic_salary)} +
                                                tunjangan{" "}
                                                {rp(r.fixed_allowance)}
                                            </div>
                                        </td>
                                        <td className="p-4">
                                            {r.effective_from} —{" "}
                                            {r.effective_until ?? "seterusnya"}
                                        </td>
                                        <td className="p-4">
                                            {r.is_active ? "Aktif" : "Nonaktif"}
                                        </td>
                                        <td className="p-4 text-right">
                                            <Button
                                                as={Link}
                                                href={r.edit_url}
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Edit3 size={14} /> Edit
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {!rows.data.length && (
                                    <tr>
                                        <td
                                            colSpan="5"
                                            className="p-10 text-center"
                                        >
                                            Belum ada daftar gaji.
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
Index.layout = (p) => <AdminLayout title={p?.props?.title}>{p}</AdminLayout>;
