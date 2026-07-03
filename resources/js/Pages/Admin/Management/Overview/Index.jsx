import { Head, useForm } from '@inertiajs/react';
import { PlusCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';
import { useResourcePermissions } from '../../../../Utils/permissions';
import CabangPerusahaanForm from '../CabangPerusahaan/Form';
import cabangPerusahaanRequest from '../CabangPerusahaan/request';
import ManagementTableAccordion from '../Components/ManagementTableAccordion';
import DokumenLegalitasForm from '../DokumenLegalitas/Form';
import dokumenLegalitasRequest from '../DokumenLegalitas/request';
import DokumenLegalitasRumahForm from '../DokumenLegalitasRumah/Form';
import dokumenLegalitasRumahRequest from '../DokumenLegalitasRumah/request';
import KelompokHppForm from '../KelompokHpp/Form';
import kelompokHppRequest from '../KelompokHpp/request';
import MasterBankForm from '../MasterBank/Form';
import masterBankRequest from '../MasterBank/request';
import PerumahanForm from '../Perumahan/Form';
import perumahanRequest from '../Perumahan/request';
import RolePermissionForm from '../RolePermission/Form';
import rolePermissionRequest from '../RolePermission/request';
import TipePostForm from '../TipePost/Form';
import tipePostRequest from '../TipePost/request';
import UserForm from '../User/Form';
import userRequest from '../User/request';

const sectionResources = {
    'cabang-perusahaan': { FormComponent: CabangPerusahaanForm, requestService: cabangPerusahaanRequest },
    perumahan: { FormComponent: PerumahanForm, requestService: perumahanRequest },
    'master-bank': { FormComponent: MasterBankForm, requestService: masterBankRequest },
    'dokumen-legalitas': { FormComponent: DokumenLegalitasForm, requestService: dokumenLegalitasRequest },
    'dokumen-legalitas-rumah': { FormComponent: DokumenLegalitasRumahForm, requestService: dokumenLegalitasRumahRequest },
    'kelompok-hpp': { FormComponent: KelompokHppForm, requestService: kelompokHppRequest },
    'tipe-post': { FormComponent: TipePostForm, requestService: tipePostRequest },
    user: { FormComponent: UserForm, requestService: userRequest },
    'role-permission': { FormComponent: RolePermissionForm, requestService: rolePermissionRequest },
};

const sectionPermissionKeys = {
    'cabang-perusahaan': 'cabang',
    perumahan: 'perumahan',
    'master-bank': 'master-bank',
    'dokumen-legalitas': 'dokumen-legalitas',
    'dokumen-legalitas-rumah': 'dokumen-legalitas',
    'kelompok-hpp': 'kelompok-hpp',
    'tipe-post': 'tipe-post',
    user: 'users',
    'role-permission': 'roles',
};

function defaultValues(fields) {
    return fields.reduce((values, field) => {
        values[field.name] = field.type === 'checkboxes' ? [] : (field.defaultValue ?? '');
        return values;
    }, {});
}

function valuesFromRow(fields, row) {
    return fields.reduce((values, field) => {
        if (field.type === 'checkboxes') {
            values[field.name] = Array.isArray(row[field.name]) ? row[field.name] : [];
            return values;
        }

        values[field.name] = row[field.name] ?? field.defaultValue ?? '';
        return values;
    }, {});
}

function ManagementSection({ section, overviewUrl }) {
    const { FormComponent, requestService } = sectionResources[section.key];
    const defaults = useMemo(() => defaultValues(section.fields), [section.fields]);
    const form = useForm(defaults);
    const [selected, setSelected] = useState(null);
    const [formOpen, setFormOpen] = useState(false);
    const resourcePermissions = useResourcePermissions(sectionPermissionKeys[section.key], section.baseUrl);
    const permissions = section.readOnly
        ? { ...resourcePermissions, canCreate: false, canUpdate: false, canDelete: false, canUnlock: false }
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

        setSelected(row);
        form.clearErrors();
        form.setData(valuesFromRow(section.fields, row));
        setFormOpen(true);
    };

    const submit = (event) => {
        event.preventDefault();

        requestService.submit({
            form,
            baseUrl: section.baseUrl,
            selected,
            onSuccess: closeForm,
            onError: () => setFormOpen(true),
        });
    };

    return (
        <section className="overflow-hidden rounded-2xl border border-white/80 bg-white/78 shadow-soft dark:border-white/10 dark:bg-white/8">
            <div className="flex flex-col gap-4 border-b border-silver-deep/50 p-5 md:flex-row md:items-start md:justify-between dark:border-white/10">
                <div>
                    <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">
                        Section
                    </p>
                    <h2 className="mt-1 text-xl font-extrabold text-ink dark:text-white">
                        {section.title}
                    </h2>
                    {section.description && (
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-ink-soft dark:text-white/62">
                            {section.description}
                        </p>
                    )}
                </div>
                {permissions.canCreate && (
                    <Button type="button" variant="outline" onClick={openCreate}>
                        <PlusCircle size={18} />
                        Data Baru
                    </Button>
                )}
            </div>

            <div className="grid gap-6 p-5">
                <ManagementTableAccordion
                    title={section.title}
                    columns={section.columns}
                    rows={section.rows}
                    filters={section.filters}
                    defaultOpen={section.defaultOpen}
                    permissions={permissions}
                    onEdit={editRow}
                    onDelete={(row) => permissions.canDelete && requestService.destroy({ baseUrl: section.baseUrl, row, label: section.title })}
                    onSearch={(search) => requestService.search({
                        baseUrl: overviewUrl,
                        search,
                        searchKey: section.searchKey,
                    })}
                />

                {(permissions.canCreate || permissions.canUpdate) && (
                    <FormComponent
                        open={formOpen}
                        title={section.title}
                        fields={section.fields}
                        options={section.options}
                        form={form}
                        selected={selected}
                        onSubmit={submit}
                        onClose={closeForm}
                    />
                )}
            </div>
        </section>
    );
}

export default function Index({ title, description, overviewUrl, sections }) {
    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-soft">
                        Master Data Center
                    </p>
                    <h1 className="mt-1 text-xl font-extrabold text-ink dark:text-white">
                        {title}
                    </h1>
                    <p className="mt-1 max-w-4xl text-sm leading-6 text-ink-soft dark:text-white/65">
                        {description}
                    </p>
                </section>

                <div className="grid gap-6">
                    {sections.map((section) => (
                        <ManagementSection
                            key={section.key}
                            section={section}
                            overviewUrl={overviewUrl}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Admin'}>{page}</AdminLayout>;
