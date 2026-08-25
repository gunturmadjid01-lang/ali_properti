<?php

use App\Models\QualityUpgradeContract;
use App\Models\QualityUpgradeAddendum;
use App\Models\QualityUpgradeHandover;
use App\Support\ApprovalResources;
use Illuminate\Support\Facades\Route;

it('registers the quality upgrade approval resource and lifecycle routes', function () {
    $resources = ApprovalResources::modules();

    expect($resources)->toHaveKey('quality-upgrade')
        ->and($resources['quality-upgrade']['model'])->toBe(QualityUpgradeContract::class)
        ->and($resources['quality-upgrade-addendum']['model'])->toBe(QualityUpgradeAddendum::class)
        ->and($resources['quality-upgrade-handover']['model'])->toBe(QualityUpgradeHandover::class)
        ->and(Route::has('admin.quality-upgrades.lock'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.unlock'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.review'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.progress.store'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.cancel'))->toBeTrue();
    expect(Route::has('admin.quality-upgrades.addenda.lock'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.addenda.unlock'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.addenda.review'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.addenda.print'))->toBeTrue();
    expect(Route::has('admin.quality-upgrades.handover.lock'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.handover.unlock'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.handover.review'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.defects.store'))->toBeTrue()
        ->and(Route::has('admin.quality-upgrades.defects.resolve'))->toBeTrue();
});

it('defines handover warranty and defect audit storage', function () {
    $migration = file_get_contents(database_path('migrations/2026_07_27_000004_create_quality_upgrade_handover_and_defects.php'));
    expect($migration)->toContain("Schema::create('quality_upgrade_handovers'")
        ->toContain("Schema::create('quality_upgrade_defects'")
        ->toContain("'warranty_end_date'")
        ->toContain("'customer_evidence_path'")
        ->toContain("'resolution_evidence_path'");
});

it('keeps auditable progress costing payment and material schema in the lifecycle migration', function () {
    $migration = file_get_contents(database_path('migrations/2026_07_27_000001_complete_quality_upgrade_lifecycle.php'));

    expect($migration)
        ->toContain("Schema::create('quality_upgrade_catalogs'")
        ->toContain("Schema::create('quality_upgrade_progresses'")
        ->toContain("Schema::create('quality_upgrade_addenda'")
        ->toContain("'actual_material_cost'")
        ->toContain("'actual_labor_cost'")
        ->toContain("'cancellation_reason'")
        ->toContain("'quality_upgrade_contract_item_id'")
        ->toContain("'material_usage_qu_contract_fk'");
});

it('keeps inventory and quality upgrade receivable accounts separate', function () {
    expect(\App\Models\ChartOfAccount::PERSEDIAAN_MATERIAL)->toBe('1-1300')
        ->and(\App\Models\ChartOfAccount::PIUTANG_PENAMBAHAN_MUTU)->toBe('1-1500')
        ->and(\App\Models\ChartOfAccount::PIUTANG_PENAMBAHAN_MUTU)->not->toBe(\App\Models\ChartOfAccount::PERSEDIAAN_MATERIAL);

    $repair = file_get_contents(database_path('migrations/2026_07_27_000003_repair_quality_upgrade_accounts_and_material_links.php'));
    expect($repair)->toContain("'Persediaan Material'")
        ->toContain("'Piutang Penambahan Mutu'")
        ->toContain("'quality_upgrade_contract_item_id'");
});
