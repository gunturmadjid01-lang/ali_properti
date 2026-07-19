import { Head, Link, router } from "@inertiajs/react";
import { BriefcaseBusiness, PlusCircle, ShieldCheck, UserRoundCog, UsersRound, Warehouse } from "lucide-react";
import { Button } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { useResourcePermissions } from "../../../../Utils/permissions";
import requestService from "./request";
import TableData from "./TableData";

export default function Index({
    title,
    description,
    baseUrl,
    createUrl,
    columns,
    rows,
    filters,
    section,
    tabs = [],
    statistics = [],
}) {
    const permissions = useResourcePermissions("users", baseUrl);

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="admin-page-hero rounded-xl border p-5 md:p-6">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">
                                Data Akun & Kepegawaian
                            </p>
                            <h1 className="mt-1 text-2xl font-extrabold">{title}</h1>
                            <p className="mt-1 max-w-3xl text-sm leading-6 text-ink-soft dark:text-white/60">
                                {description}
                            </p>
                        </div>
                        {permissions.canCreate && (
                            <Button as={Link} href={createUrl}>
                                <PlusCircle size={18} /> {section === "pegawai" ? "Tambah Pegawai" : `Tambah ${tabs.find((tab) => tab.key === section)?.label ?? "User"}`}
                            </Button>
                        )}
                    </div>
                </section>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    {statistics.map((item, index) => {
                        const icons = [UsersRound, ShieldCheck, UserRoundCog, BriefcaseBusiness, Warehouse];
                        const Icon = icons[index % icons.length];
                        const tones = { blue: "bg-blue-50 text-blue-700", green: "bg-emerald-50 text-emerald-700", violet: "bg-violet-50 text-violet-700", amber: "bg-amber-50 text-amber-700", slate: "bg-slate-100 text-slate-700" };
                        return <div className="rounded-xl border border-white/80 bg-white/80 p-4 shadow-soft dark:border-white/10 dark:bg-white/8" key={item.label}><div className="flex items-center justify-between gap-3"><div><p className="text-[11px] font-black uppercase tracking-[0.1em] text-ink-soft">{item.label}</p><p className="mt-2 text-3xl font-black">{item.value}</p></div><span className={`rounded-xl p-3 ${tones[item.tone] ?? tones.blue}`}><Icon size={20} /></span></div></div>;
                    })}
                </section>

                <nav className="flex gap-2 overflow-x-auto rounded-xl border border-white/80 bg-white/80 p-3 shadow-soft dark:border-white/10 dark:bg-white/8" aria-label="Kelompok data user dan pegawai">
                    {tabs.map((tab) => <Button as={Link} href={tab.url} className="shrink-0" key={tab.key} variant={tab.key === section ? "dark" : "ghost"}>{tab.label}</Button>)}
                </nav>

                <TableData
                    baseUrl={baseUrl}
                    title={title}
                    columns={columns}
                    rows={rows}
                    filters={filters}
                    permissions={permissions}
                    onEdit={(row) => router.visit(row.edit_url)}
                    onDelete={(row) => requestService.destroy({ baseUrl, row })}
                    onLock={(row) => requestService.lock({ baseUrl, row })}
                    onUnlock={(row) => requestService.unlock({ baseUrl, row })}
                    onSearch={(search) => router.get(baseUrl, { search, section }, { preserveState: true, preserveScroll: true })}
                />
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Manajemen Pengguna"}>
        {page}
    </AdminLayout>
);
