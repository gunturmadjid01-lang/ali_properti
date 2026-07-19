<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Requests\Admin\Approval\UpdateApprovalSettingsRequest;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Validator;

test('flash response dikirim sebagai nilai yang dapat dibaca modal frontend', function () {
    $request = Request::create('/admin/approval/settings');
    $session = new Store('approval-test', new ArraySessionHandler(120));
    $request->setLaravelSession($session);
    $session->flash('success', 'Setting approval berhasil disimpan.');

    $shared = app(HandleInertiaRequests::class)->share($request);

    expect($shared['flash']['success'])->toBe('Setting approval berhasil disimpan.')
        ->and($shared['flash']['error'])->toBeNull()
        ->and($shared['flash']['id'])->toBeString();
});

test('role tahap approval yang kosong menghasilkan pesan validasi yang jelas', function () {
    $data = [
        'settings' => [[
            'module_key' => 'spr',
            'module_label' => 'Pengajuan SPR',
            'action' => 'lock',
            'requires_approval' => true,
            'approval_stages' => 1,
            'approval_steps' => [['step' => 1, 'role_ids' => []]],
        ]],
    ];
    $request = UpdateApprovalSettingsRequest::create('/admin/approval/settings', 'PUT', $data);
    $request->setContainer(app());
    $validator = Validator::make($data, $request->rules(), $request->messages());

    expect($validator->errors()->first('settings.0.approval_steps.0.role_ids'))
        ->toBe('Role penanggung jawab tahap ini wajib dipilih.');
});
