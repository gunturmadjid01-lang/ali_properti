<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $pages = [
        'sales' => ['transactions', 'transaction-detail', 'payment-schedules', 'payments', 'handover', 'after-sales', 'reports'],
        'cash-installment' => ['schemes', 'scheme-detail', 'scheme-housing', 'scheme-steps', 'scheme-fees', 'scheme-requirements', 'scheme-documents', 'scheme-versions', 'scheme-history', 'scheme-reports', 'contracts', 'contract-detail', 'approvals', 'schedules', 'billings', 'arrears', 'payment-history', 'settlements', 'restructuring', 'cancellations', 'reports'],
        'developer-kpr' => ['products', 'product-detail', 'product-housing', 'financing-terms', 'margins', 'fees', 'requirements', 'documents', 'risk-approval', 'penalties', 'early-settlement', 'product-versions', 'product-history', 'product-reports', 'applications', 'application-detail', 'affordability-analysis', 'document-validation', 'internal-approval', 'contracts', 'schedules', 'receivables', 'arrears', 'payments', 'restructuring', 'cancellations', 'reports'],
        'bank-kpr' => ['applications', 'application-detail', 'document-validation', 'slik', 'appraisal', 'bank-decision', 'sp3k', 'contract-preparation', 'contract-schedule', 'contract-execution', 'disbursement', 'bank-change', 'rejections', 'reports'],
    ];

    public function up(): void
    {
        $names = [];
        foreach ($this->pages as $prefix => $pages) {
            foreach ($pages as $page) {
                foreach (['view', 'create', 'update', 'delete', 'submit', 'approve', 'reject', 'print', 'export'] as $action) {
                    $names[] = "{$prefix}.{$page}.{$action}";
                }
            }
        }$names = array_values(array_unique($names));
        foreach ($names as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }app(PermissionRegistrar::class)->forgetCachedPermissions();
        $manager = Role::query()->whereIn('name', ['manager', 'manajer_pimpro'])->get();
        foreach ($manager as $role) {
            $role->givePermissionTo(Permission::query()->whereIn('name', $names)->get());
        }app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (array_keys($this->pages) as $prefix) {
            Permission::where('name', 'like', $prefix.'.%.%')->delete();
        }app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
