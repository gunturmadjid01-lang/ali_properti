import { Head, router, useForm } from '@inertiajs/react';
import { Save, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button, Dropdown, Input } from '../../../../Components/UI';
import AdminLayout from '../../../../Layouts/AdminLayout';
import { useResourcePermissions } from '../../../../Utils/permissions';

const actionOrder = [
    'view',
    'create',
    'update',
    'delete',
    'manage',
    'unlock',
];

const actionLabels = {
    view: 'Buka',
    create: 'Tambah',
    update: 'Ubah',
    delete: 'Hapus',
    manage: 'Manage',
    unlock: 'Buka Kunci',
};

export default function Index({ title, description, baseUrl, options = {} }) {
    const pagePermissions = useResourcePermissions('roles', baseUrl);
    const roles = options.roles ?? [];
    const permissionMatrix = options.permissionMatrix ?? [];
    const matrixPermissionIds = useMemo(() => new Set(permissionMatrix.flatMap((group) => group.modules.flatMap((module) => module.permissions.map((permission) => permission.id)))), [permissionMatrix]);
    const [selectedRoleId, setSelectedRoleId] = useState(roles[0]?.value ?? '');
    const [activeGroup, setActiveGroup] = useState(permissionMatrix[0]?.key ?? '');
    const [search, setSearch] = useState('');
    const selectedRole = roles.find((role) => role.value === selectedRoleId);
    const nonMatrixPermissionIds = useMemo(() => (selectedRole?.permission_ids ?? []).filter((id) => !matrixPermissionIds.has(id)), [matrixPermissionIds, selectedRole]);
    const form = useForm({
        name: selectedRole?.name ?? '',
        permission_ids: selectedRole?.permission_ids ?? [],
    });

    const activeModules = useMemo(() => {
        const group = permissionMatrix.find((item) => item.key === activeGroup) ?? permissionMatrix[0];
        const keyword = search.trim().toLowerCase();

        if (!group) {
            return [];
        }

        return group.modules.filter((module) => !keyword || module.label.toLowerCase().includes(keyword));
    }, [activeGroup, permissionMatrix, search]);

    const selectRole = (roleId) => {
        const role = roles.find((item) => item.value === roleId);
        setSelectedRoleId(roleId);
        form.setData({
            name: role?.name ?? '',
            permission_ids: role?.permission_ids ?? [],
        });
        form.clearErrors();
    };

    const checked = (permissionId) => form.data.permission_ids.includes(permissionId);

    const togglePermission = (permissionId) => {
        form.setData('permission_ids', checked(permissionId)
            ? form.data.permission_ids.filter((id) => id !== permissionId)
            : [...form.data.permission_ids, permissionId]);
    };

    const toggleModule = (module) => {
        const moduleIds = module.permissions.map((permission) => permission.id);
        const allChecked = moduleIds.every((id) => form.data.permission_ids.includes(id));

        form.setData('permission_ids', allChecked
            ? form.data.permission_ids.filter((id) => !moduleIds.includes(id))
            : Array.from(new Set([...form.data.permission_ids, ...moduleIds])));
    };

    const toggleAction = (action) => {
        const ids = activeModules
            .flatMap((module) => module.permissions)
            .filter((permission) => permission.action === action)
            .map((permission) => permission.id);
        const allChecked = ids.length > 0 && ids.every((id) => form.data.permission_ids.includes(id));

        form.setData('permission_ids', allChecked
            ? form.data.permission_ids.filter((id) => !ids.includes(id))
            : Array.from(new Set([...form.data.permission_ids, ...ids])));
    };

    const submit = (event) => {
        event.preventDefault();
        if (!selectedRole || !pagePermissions.canUpdate) {
            return;
        }

        const mergedPermissionIds = Array.from(new Set([...nonMatrixPermissionIds, ...form.data.permission_ids.filter((id) => matrixPermissionIds.has(id))]));

        router.put(`${baseUrl}/${selectedRole.id}`, {
            name: selectedRole.name,
            permission_ids: mergedPermissionIds,
        }, {
            preserveScroll: true,
            onSuccess: () => form.setData('permission_ids', mergedPermissionIds),
        });
    };

    return (
        <>
            <Head title={title} />
            <div className="grid gap-6">
                <section className="rounded-lg border border-white/80 bg-white/78 p-6 shadow-soft dark:border-white/10 dark:bg-white/8">
                    <p className="text-xs font-extrabold uppercase tracking-[0.18em] text-ink-soft">Pengaturan</p>
                    <h2 className="mt-2 font-display text-3xl font-extrabold">{title}</h2>
                    <p className="mt-2 max-w-4xl leading-7 text-ink-soft dark:text-white/60">{description}</p>
                </section>

                <form className="grid gap-4 rounded-lg border border-white/80 bg-white/78 p-5 shadow-soft dark:border-white/10 dark:bg-white/8" onSubmit={submit}>
                    <div className="grid gap-4 lg:grid-cols-[320px_1fr_auto]">
                        <div className="grid gap-2">
                            <span className="text-sm font-extrabold">Peran</span>
                            <Dropdown label="Pilih Peran" value={selectedRoleId} options={roles} onChange={selectRole} />
                        </div>
                        <Input label="Cari Modul" value={search} onChange={(event) => setSearch(event.target.value)} icon={<Search size={16} />} />
                        {pagePermissions.canUpdate && (
                            <div className="flex items-end">
                                <Button type="submit" disabled={!selectedRole || form.processing}>
                                    <Save size={17} /> {form.processing ? 'Menyimpan...' : 'Simpan Hak Akses'}
                                </Button>
                            </div>
                        )}
                    </div>

                    {Object.keys(form.errors).length > 0 && <div className="rounded-lg bg-red-50 p-4 text-sm font-bold text-red-700">{Object.values(form.errors).map((error) => <p key={error}>{error}</p>)}</div>}

                    <div className="grid gap-4 lg:grid-cols-[220px_1fr]">
                        <div className="overflow-hidden rounded-lg border border-silver-deep/70 dark:border-white/10">
                            {permissionMatrix.map((group) => (
                                <button
                                    className={`block min-h-11 w-full border-b border-silver-deep/60 px-4 text-left text-sm font-extrabold last:border-b-0 dark:border-white/10 ${activeGroup === group.key ? 'bg-ink text-white dark:bg-white dark:text-graphite' : 'bg-white/70 text-ink-soft hover:bg-silver dark:bg-white/5 dark:text-white/70 dark:hover:bg-white/10'}`}
                                    type="button"
                                    key={group.key}
                                    onClick={() => setActiveGroup(group.key)}
                                >
                                    {group.label}
                                </button>
                            ))}
                        </div>

                        <div className="overflow-x-auto rounded-lg border border-silver-deep/70 dark:border-white/10">
                            <table className="w-full table-fixed text-sm">
                                <colgroup>
                                    <col className="w-auto" />
                                    {actionOrder.map((action) => (
                                        <col className="w-16" key={action} />
                                    ))}
                                </colgroup>
                                <thead className="bg-silver-soft text-xs uppercase tracking-wide text-ink-soft dark:bg-white/5 dark:text-white/50">
                                    <tr>
                                        <th className="px-3 py-3 text-left">Modul / Tabel</th>
                                        {actionOrder.map((action) => (
                                            <th className="px-1 py-3 text-center" key={action}>
                                                <button className="max-w-full break-words text-[11px] font-extrabold leading-tight hover:text-ink disabled:cursor-not-allowed disabled:opacity-50 dark:hover:text-white" disabled={!pagePermissions.canUpdate} type="button" onClick={() => toggleAction(action)}>
                                                    {actionLabels[action]}
                                                </button>
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-silver-deep/60 dark:divide-white/10">
                                    {activeModules.map((module) => {
                                        const byAction = Object.fromEntries(module.permissions.map((permission) => [permission.action, permission]));
                                        const allChecked = module.permissions.every((permission) => checked(permission.id));

                                        return (
                                            <tr key={module.key}>
                                                <td className="px-3 py-3 font-extrabold">
                                                    <label className="flex items-center gap-3">
                                                        <input checked={allChecked} className="h-4 w-4 accent-ink" disabled={!pagePermissions.canUpdate} type="checkbox" onChange={() => toggleModule(module)} />
                                                        {module.label}
                                                    </label>
                                                </td>
                                                {actionOrder.map((action) => {
                                                    const permission = byAction[action];
                                                    return (
                                                        <td className="px-1 py-3 text-center" key={`${module.key}-${action}`}>
                                                            {permission ? (
                                                                <input
                                                                    checked={checked(permission.id)}
                                                                    className="h-4 w-4 accent-ink"
                                                                    disabled={!pagePermissions.canUpdate}
                                                                    type="checkbox"
                                                                    title={permission.name}
                                                                    onChange={() => togglePermission(permission.id)}
                                                                />
                                                            ) : (
                                                                <span className="text-ink-soft/30">-</span>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        );
                                    })}
                                    {activeModules.length === 0 && (
                                        <tr>
                                            <td className="px-4 py-10 text-center font-bold text-ink-soft" colSpan={actionOrder.length + 1}>Modul tidak ditemukan.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

Index.layout = (page) => <AdminLayout title={page?.props?.title ?? 'Peran Hak Akses'}>{page}</AdminLayout>;
