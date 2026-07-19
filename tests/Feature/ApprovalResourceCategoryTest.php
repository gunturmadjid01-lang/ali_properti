<?php

use App\Support\ApprovalResources;

test('semua modul approval memiliki kategori daftar yang jelas', function () {
    foreach (ApprovalResources::modules() as $moduleKey => $module) {
        $category = ApprovalResources::category($moduleKey);

        expect($category['key'])->not->toBe('other', "Modul {$moduleKey} belum memiliki kategori.")
            ->and($category['label'])->not->toBeEmpty();
    }
});

test('kategori approval membedakan data bisnis utama', function () {
    expect(ApprovalResources::category('spr')['label'])->toBe('Penjualan & Pembiayaan')
        ->and(ApprovalResources::category('material-purchase')['label'])->toBe('Material & Logistik')
        ->and(ApprovalResources::category('employee-payroll')['label'])->toBe('Kepegawaian');
});
