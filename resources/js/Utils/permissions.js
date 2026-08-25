import { usePage } from "@inertiajs/react";

const resourceFromUrl = (baseUrl = "") => {
    const pairs = [
        ["/management/employee", "employee"],
        ["/management/user", "users"],
        ["/management/role-permission", "roles"],
        ["/management/cabang-perusahaan", "cabang"],
        ["/management/perumahan", "perumahan"],
        ["/management/master-dokumen-customer", "dokumen-customer"],
        ["/management/dokumen-legalitas-rumah", "dokumen-legalitas"],
        ["/management/dokumen-legalitas", "dokumen-legalitas"],
        ["/management/master-bank", "master-bank"],
        ["/bank-kredit", "bank-credit-master"],
        ["/cabang-bank", "bank-branch"],
        ["/produk-kredit-bank", "bank-credit-product"],
        ["/kerja-sama-bank", "bank-housing-partnership"],
        ["/paket-persyaratan-dokumen", "bank-document-requirement"],
        ["/riwayat-kerja-sama-bank", "bank-partnership-history"],
        ["/management/tipe-post", "tipe-post"],
        ["/unit-rumah", "detail-rumah"],
    ];

    return pairs.find(([path]) => baseUrl.includes(path))?.[1] ?? null;
};

export function useResourcePermissions(permissionKey, baseUrl) {
    const user = usePage().props.auth?.user;
    const roles = user?.roles ?? [];
    const permissions = user?.permissions ?? [];
    const key = permissionKey ?? resourceFromUrl(baseUrl);
    const superUser = roles.includes("super_admin");

    const has = (name) => superUser || permissions.includes(name);
    const hasExact = (action) => {
        if (!key) {
            return true;
        }

        return has(`${key}.${action}`);
    };
    const can = (action) => {
        if (!key) {
            return true;
        }

        return has(`${key}.${action}`) || has(`${key}.manage`);
    };

    return {
        key,
        canView: can("view"),
        canCreate: can("create"),
        canUpdate: can("update"),
        canDelete: can("delete"),
        canManage: hasExact("manage"),
        canUnlock: can("unlock"),
        canViewExact: hasExact("view"),
        canCreateExact: hasExact("create"),
        canUpdateExact: hasExact("update"),
        canDeleteExact: hasExact("delete"),
        canUnlockExact: hasExact("unlock"),
    };
}
