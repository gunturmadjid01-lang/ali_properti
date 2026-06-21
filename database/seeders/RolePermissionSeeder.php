<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',

            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.manage',
            'approval.view',
            'approval.manage',
            'approval.settings',

            'cabang.view',
            'cabang.create',
            'cabang.update',
            'cabang.delete',
            'perumahan.view',
            'perumahan.create',
            'perumahan.update',
            'perumahan.delete',
            'detail-rumah.view',
            'detail-rumah.create',
            'detail-rumah.update',
            'detail-rumah.delete',

            'customer.view',
            'customer.create',
            'customer.update',
            'customer.delete',
            'customer.follow-up',
            'booking.manage',

            'dokumen-legalitas.view',
            'dokumen-legalitas.create',
            'dokumen-legalitas.update',
            'dokumen-legalitas.delete',
            'dokumen-customer.view',
            'dokumen-customer.create',
            'dokumen-customer.update',

            'progress.view',
            'progress.create',
            'progress.update',
            'progress.delete',

            'keuangan.view',
            'keuangan.create',
            'keuangan.update',
            'keuangan.delete',
            'hpp.view',
            'hpp.create',
            'hpp.update',
            'hpp.delete',
            'laporan.view',
            'laporan.export',

            'master-bank.manage',
            'tipe-post.manage',
            'kelompok-hpp.manage',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $rolePermissions = [
            'super_admin' => $permissions,
            'owner' => $permissions,
            'admin' => [
                'dashboard.view',
                'approval.view',
                'cabang.view',
                'perumahan.view',
                'perumahan.create',
                'perumahan.update',
                'detail-rumah.view',
                'detail-rumah.create',
                'detail-rumah.update',
                'customer.view',
                'customer.create',
                'customer.update',
                'dokumen-legalitas.view',
                'dokumen-legalitas.create',
                'dokumen-legalitas.update',
                'dokumen-customer.view',
                'dokumen-customer.create',
                'dokumen-customer.update',
                'progress.view',
                'keuangan.view',
                'hpp.view',
                'laporan.view',
                'master-bank.manage',
                'tipe-post.manage',
                'kelompok-hpp.manage',
            ],
            'manajer_pimpro' => [
                'dashboard.view',
                'approval.view',
                'approval.manage',
                'cabang.view',
                'perumahan.view',
                'perumahan.create',
                'perumahan.update',
                'detail-rumah.view',
                'detail-rumah.create',
                'detail-rumah.update',
                'customer.view',
                'customer.update',
                'dokumen-legalitas.view',
                'dokumen-customer.view',
                'progress.view',
                'progress.create',
                'progress.update',
                'keuangan.view',
                'hpp.view',
                'laporan.view',
            ],
            'marketing' => [
                'dashboard.view',
                'perumahan.view',
                'detail-rumah.view',
                'customer.view',
                'customer.create',
                'customer.update',
                'customer.follow-up',
                'booking.manage',
                'dokumen-customer.view',
                'dokumen-customer.create',
                'dokumen-customer.update',
                'progress.view',
                'laporan.view',
            ],
            'supervisor_marketing' => [
                'dashboard.view',
                'perumahan.view',
                'detail-rumah.view',
                'customer.view',
                'customer.create',
                'customer.update',
                'customer.follow-up',
                'booking.manage',
                'dokumen-customer.view',
                'dokumen-customer.create',
                'dokumen-customer.update',
                'progress.view',
                'laporan.view',
            ],
            'area_marketing' => [
                'dashboard.view',
                'perumahan.view',
                'detail-rumah.view',
                'customer.view',
                'customer.create',
                'customer.update',
                'customer.follow-up',
                'booking.manage',
                'dokumen-customer.view',
                'dokumen-customer.create',
                'dokumen-customer.update',
                'progress.view',
            ],
            'admin_konsumen' => [
                'dashboard.view',
                'customer.view',
                'customer.create',
                'customer.update',
                'customer.delete',
                'dokumen-customer.view',
                'dokumen-customer.create',
                'dokumen-customer.update',
                'booking.manage',
            ],
            'keuangan' => [
                'dashboard.view',
                'customer.view',
                'perumahan.view',
                'detail-rumah.view',
                'keuangan.view',
                'keuangan.create',
                'keuangan.update',
                'keuangan.delete',
                'hpp.view',
                'hpp.create',
                'hpp.update',
                'hpp.delete',
                'master-bank.manage',
                'tipe-post.manage',
                'kelompok-hpp.manage',
                'laporan.view',
                'laporan.export',
            ],
            'user_area_gudang' => [
                'dashboard.view',
                'perumahan.view',
                'detail-rumah.view',
                'hpp.view',
                'laporan.view',
            ],
            'teknik' => [
                'dashboard.view',
                'perumahan.view',
                'detail-rumah.view',
                'detail-rumah.update',
                'dokumen-legalitas.view',
                'progress.view',
                'progress.create',
                'progress.update',
                'progress.delete',
                'hpp.view',
                'laporan.view',
            ],
            'pengawas' => [
                'dashboard.view',
                'perumahan.view',
                'detail-rumah.view',
                'progress.view',
                'progress.create',
                'progress.update',
                'laporan.view',
            ],
            'bag_legal' => [
                'dashboard.view',
                'perumahan.view',
                'dokumen-legalitas.view',
                'dokumen-legalitas.create',
                'dokumen-legalitas.update',
                'dokumen-legalitas.delete',
                'laporan.view',
            ],
            'admin_kpr' => [
                'dashboard.view',
                'perumahan.view',
                'detail-rumah.view',
                'customer.view',
                'dokumen-customer.view',
                'laporan.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $rolePermissionNames) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', 'web')
                    ->whereIn('name', $rolePermissionNames)
                    ->get(),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
