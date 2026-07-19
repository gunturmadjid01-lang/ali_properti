<?php

use App\Http\Controllers\Admin\Management\RolePermission\RolePermissionController;

function permissionMatrix(): array
{
    $controller = app(RolePermissionController::class);
    $method = new ReflectionMethod($controller, 'permissionMatrix');

    return $method->invoke($controller);
}

test('tab permission tidak memiliki modul ganda lintas kategori', function () {
    $matrix = permissionMatrix();
    $keys = collect($matrix)->flatMap(fn (array $group) => collect($group['modules'])->pluck('key'));

    expect($keys->duplicates()->values()->all())->toBe([]);
});

test('permission utama berada pada kategori bisnis yang sesuai', function () {
    $matrix = collect(permissionMatrix())->keyBy('key');

    expect(collect($matrix['access']['modules'])->pluck('key'))->toContain('users', 'roles')
        ->and(collect($matrix['employees']['modules'])->pluck('key'))->toContain('attendance', 'payroll')
        ->and(collect($matrix['company-property']['modules'])->pluck('key'))->toContain('cabang', 'perumahan', 'detail-rumah', 'unit-ownership')
        ->and(collect($matrix['sales']['modules'])->pluck('key'))->toContain('sales-process')
        ->and(collect($matrix['contracts-spk']['modules'])->pluck('key'))->not->toContain('sales-process');
});
