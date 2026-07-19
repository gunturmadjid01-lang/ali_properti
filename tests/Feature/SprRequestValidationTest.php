<?php

use App\Http\Requests\Admin\Marketing\SaveSprRequest;
use Illuminate\Support\Facades\Validator;

function sprRequestValidator(array $data)
{
    $request = SaveSprRequest::create('/admin/marketing/spr', 'POST', $data);
    $request->setContainer(app());

    return Validator::make($data, $request->rules(), $request->messages(), $request->attributes());
}

test('validasi SPR cash tidak mewajibkan master pembiayaan atau daftar berkas kosong', function () {
    $errors = sprRequestValidator(['metode_pembayaran' => 'cash'])->errors();

    expect($errors->has('bank_kredit_id'))->toBeFalse()
        ->and($errors->has('bank_branch_id'))->toBeFalse()
        ->and($errors->has('bank_credit_product_id'))->toBeFalse()
        ->and($errors->has('cash_installment_scheme_id'))->toBeFalse()
        ->and($errors->has('developer_kpr_product_id'))->toBeFalse()
        ->and($errors->has('uang_muka'))->toBeFalse()
        ->and($errors->has('nilai_pengajuan_kpr'))->toBeFalse()
        ->and($errors->has('berkas'))->toBeFalse();
});

test('validasi SPR Cash Bertahap otomatis mengikuti metode pembayaran', function () {
    $errors = sprRequestValidator(['metode_pembayaran' => 'cash_bertahap'])->errors();

    expect($errors->has('cash_installment_scheme_id'))->toBeTrue()
        ->and($errors->has('booking_fee'))->toBeTrue()
        ->and($errors->has('uang_muka'))->toBeTrue()
        ->and($errors->has('tanggal_jatuh_tempo_angsuran'))->toBeTrue()
        ->and($errors->has('bank_kredit_id'))->toBeFalse()
        ->and($errors->has('developer_kpr_product_id'))->toBeFalse();
});

test('validasi SPR KPR Bank mewajibkan seluruh pilihan produk dan nominal pembiayaan', function () {
    $errors = sprRequestValidator(['metode_pembayaran' => 'kpr_bank'])->errors();

    expect($errors->has('bank_kredit_id'))->toBeTrue()
        ->and($errors->has('bank_branch_id'))->toBeTrue()
        ->and($errors->has('bank_credit_product_id'))->toBeTrue()
        ->and($errors->has('kpr_tenor_bulan'))->toBeTrue()
        ->and($errors->has('uang_muka'))->toBeTrue()
        ->and($errors->has('nilai_pengajuan_kpr'))->toBeTrue()
        ->and($errors->has('cash_installment_scheme_id'))->toBeFalse();
});

test('validasi SPR KPR Developer mewajibkan produk tenor DP dan pembiayaan', function () {
    $errors = sprRequestValidator(['metode_pembayaran' => 'kpr_developer'])->errors();

    expect($errors->has('developer_kpr_product_id'))->toBeTrue()
        ->and($errors->has('kpr_tenor_bulan'))->toBeTrue()
        ->and($errors->has('uang_muka'))->toBeTrue()
        ->and($errors->has('nilai_pengajuan_kpr'))->toBeTrue()
        ->and($errors->has('bank_kredit_id'))->toBeFalse()
        ->and($errors->has('cash_installment_scheme_id'))->toBeFalse();
});
