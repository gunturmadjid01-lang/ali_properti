import { usePage } from '@inertiajs/react';

const resourceFromUrl = (baseUrl = '') => {
    const pairs = [
        ['/management/user', 'users'],
        ['/management/role-permission', 'roles'],
        ['/management/cabang-perusahaan', 'cabang'],
        ['/management/perumahan', 'perumahan'],
        ['/management/master-dokumen-customer', 'dokumen-customer'],
        ['/management/dokumen-legalitas-rumah', 'dokumen-legalitas'],
        ['/management/dokumen-legalitas', 'dokumen-legalitas'],
        ['/management/master-bank', 'master-bank'],
        ['/management/bank-kredit', 'master-bank'],
        ['/management/tipe-post', 'tipe-post'],
        ['/management/kelompok-hpp', 'kelompok-hpp'],
        ['/unit-rumah', 'detail-rumah'],
    ];

    return pairs.find(([path]) => baseUrl.includes(path))?.[1] ?? null;
};

export function useResourcePermissions(permissionKey, baseUrl) {
    const user = usePage().props.auth?.user;
    const roles = user?.roles ?? [];
    const permissions = user?.permissions ?? [];
    const key = permissionKey ?? resourceFromUrl(baseUrl);
    const superUser = roles.includes('super_admin');

    const has = (name) => superUser || permissions.includes(name);
    const can = (action) => {
        if (!key) {
            return true;
        }

        return has(`${key}.${action}`) || has(`${key}.manage`);
    };

    return {
        key,
        canView: can('view'),
        canCreate: can('create'),
        canUpdate: can('update'),
        canDelete: can('delete'),
        canUnlock: can('unlock'),
    };
}
