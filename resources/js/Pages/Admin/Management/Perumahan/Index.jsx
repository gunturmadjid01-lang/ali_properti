import { Head, Link, router } from "@inertiajs/react";
import { PlusCircle } from "lucide-react";
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
    options,
}) {
    const permissions = useResourcePermissions("perumahan", baseUrl);

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">
                                Data Proyek
                            </p>
                            <h2 className="mt-1 text-2xl font-extrabold">
                                {title}
                            </h2>
                            <p className="mt-1 max-w-3xl text-sm leading-6 text-ink-soft dark:text-white/60">
                                {description}
                            </p>
                        </div>
                        {permissions.canCreate && (
                            <Button as={Link} href={createUrl}>
                                <PlusCircle size={18} /> Tambah Perumahan
                            </Button>
                        )}
                    </div>
                </section>

                <TableData
                    baseUrl={baseUrl}
                    title={title}
                    columns={columns}
                    rows={rows}
                    filters={filters}
                    options={options}
                    permissions={permissions}
                    onEdit={(row) => router.visit(row.edit_url)}
                    onDelete={(row) => requestService.destroy({ baseUrl, row })}
                    onLock={(row) => requestService.lock({ baseUrl, row })}
                    onUnlock={(row) => requestService.unlock({ baseUrl, row })}
                    onSearch={(search) =>
                        requestService.search({ baseUrl, search })
                    }
                />
            </div>
        </>
    );
}

Index.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Data Perumahan"}>
        {page}
    </AdminLayout>
);
