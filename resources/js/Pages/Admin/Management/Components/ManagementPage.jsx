import { Head, router, useForm } from "@inertiajs/react";
import { PlusCircle } from "lucide-react";
import { useMemo, useState } from "react";
import { Button } from "../../../../Components/UI";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { useResourcePermissions } from "../../../../Utils/permissions";

function defaultValues(fields) {
    return fields.reduce((values, field) => {
        values[field.name] =
            field.type === "checkboxes"
                ? []
                : field.type === "checkbox"
                  ? Boolean(field.defaultValue)
                  : (field.defaultValue ?? "");
        return values;
    }, {});
}

function valuesFromRow(fields, row) {
    return fields.reduce((values, field) => {
        values[field.name] =
            row[field.name] ??
            (field.type === "checkboxes"
                ? []
                : field.type === "checkbox"
                  ? false
                  : "");
        return values;
    }, {});
}

function ManagementPageContent({
    title,
    description,
    baseUrl,
    permissionKey,
    readOnly = false,
    columns,
    fields,
    rows,
    filters,
    options,
    TableComponent,
    FormComponent,
    requestService,
    separateFormPages = false,
}) {
    const defaults = useMemo(() => defaultValues(fields), [fields]);
    const form = useForm(defaults);
    const [selected, setSelected] = useState(null);
    const [formOpen, setFormOpen] = useState(false);
    const resourcePermissions = useResourcePermissions(permissionKey, baseUrl);
    const permissions = readOnly
        ? {
              ...resourcePermissions,
              canCreate: false,
              canUpdate: false,
              canDelete: false,
              canUnlock: false,
          }
        : resourcePermissions;

    const resetForm = () => {
        setSelected(null);
        form.clearErrors();
        form.setData(defaults);
    };

    const openCreate = () => {
        if (!permissions.canCreate) {
            return;
        }

        if (separateFormPages) { router.visit(`${baseUrl}/create`); return; }
        resetForm();
        setFormOpen(true);
    };

    const closeForm = () => {
        resetForm();
        setFormOpen(false);
    };

    const editRow = (row) => {
        if (!permissions.canUpdate) {
            return;
        }

        if (separateFormPages) { router.visit(`${baseUrl}/${row.id}/edit`); return; }
        setSelected(row);
        form.clearErrors();
        form.setData(valuesFromRow(fields, row));
        setFormOpen(true);
    };

    const submit = (event) => {
        event.preventDefault();
        if (
            (selected && !permissions.canUpdate) ||
            (!selected && !permissions.canCreate)
        ) {
            return;
        }

        requestService.submit({
            form,
            baseUrl,
            selected,
            onSuccess: closeForm,
            onError: () => setFormOpen(true),
        });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="admin-page-hero rounded-xl border p-5 md:p-6">
                    <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">
                                Master Data
                            </p>
                            <h2 className="mt-1 text-xl font-extrabold">
                                {title}
                            </h2>
                            <p className="mt-1 max-w-2xl text-sm leading-6 text-ink-soft dark:text-white/60">
                                {description}
                            </p>
                        </div>
                        {permissions.canCreate && (
                            <Button type="button" onClick={openCreate}>
                                <PlusCircle size={18} /> Tambah Baru
                            </Button>
                        )}
                    </div>
                </section>

                <TableComponent
                    baseUrl={baseUrl}
                    title={title}
                    columns={columns}
                    rows={rows}
                    filters={filters}
                    options={options}
                    permissions={permissions}
                    onEdit={editRow}
                    onDetail={separateFormPages ? (row) => router.visit(`${baseUrl}/${row.id}`) : undefined}
                    onDelete={(row) =>
                        permissions.canDelete &&
                        requestService.destroy({ baseUrl, row })
                    }
                    onLock={(row) => requestService.lock?.({ baseUrl, row })}
                    onUnlock={(row) =>
                        permissions.canUnlock &&
                        requestService.unlock?.({ baseUrl, row })
                    }
                    onSearch={(search) =>
                        requestService.search({ baseUrl, search })
                    }
                />

                {!separateFormPages && (permissions.canCreate || permissions.canUpdate) && (
                    <FormComponent
                        open={formOpen}
                        title={title}
                        fields={fields}
                        options={options}
                        form={form}
                        selected={selected}
                        onSubmit={submit}
                        onClose={closeForm}
                    />
                )}
            </div>
        </>
    );
}

export default function ManagementPage(props) {
    const { TableComponent, FormComponent, requestService } = props;

    return (
        <ManagementPageContent
            {...props}
            TableComponent={TableComponent}
            FormComponent={FormComponent}
            requestService={requestService}
        />
    );
}

ManagementPage.layout = (page) => (
    <AdminLayout title={page?.props?.title ?? "Admin"}>{page}</AdminLayout>
);
