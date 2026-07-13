import { Head, Link, router } from "@inertiajs/react";
import { PlusCircle } from "lucide-react";
import { Button } from "../../../../Components/UI";
import { useResourcePermissions } from "../../../../Utils/permissions";
import ManagementTableAccordion from "./ManagementTableAccordion";
import { createResourceRequest } from "../services/createResourceRequest";

const requestService = createResourceRequest();

export default function SeparatedManagementIndex({
    title,
    description,
    baseUrl,
    createUrl,
    permissionKey,
    columns,
    rows,
    filters,
}) {
    const permissions = useResourcePermissions(permissionKey, baseUrl);

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-2xl border border-white/80 bg-white/80 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-black uppercase tracking-[0.15em] text-gold-deep">Master Data</p>
                            <h1 className="mt-1 text-2xl font-black">{title}</h1>
                            <p className="mt-1 max-w-3xl text-sm leading-6 text-ink-soft dark:text-white/60">{description}</p>
                        </div>
                        {permissions.canCreate && (
                            <Button as={Link} href={createUrl}>
                                <PlusCircle size={18} /> Tambah Baru
                            </Button>
                        )}
                    </div>
                </section>

                <ManagementTableAccordion
                    title={title}
                    columns={columns}
                    rows={rows}
                    filters={filters}
                    permissions={permissions}
                    onEdit={(row) => router.visit(row.edit_url ?? `${baseUrl}/${row.id}/edit`)}
                    onDelete={(row) => requestService.destroy({ baseUrl, row })}
                    onLock={(row) => requestService.lock({ baseUrl, row })}
                    onUnlock={(row) => requestService.unlock({ baseUrl, row })}
                    onSearch={(search) => requestService.search({ baseUrl, search })}
                />
            </div>
        </>
    );
}
