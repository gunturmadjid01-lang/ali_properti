<?php

use App\Models\CabangPerusahaan;
use App\Models\ChartOfAccount;
use App\Models\Costumer;
use App\Models\CustomerRefund;
use App\Models\DetailRumah;
use App\Models\PaymentSchedule;
use App\Models\Perumahan;
use App\Models\SalesResolutionRequest;
use App\Models\Spr;
use App\Services\CustomerRefundService;
use App\Services\SalesPaymentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function refundWorkflowTransaction()
{
    $branch = CabangPerusahaan::create(['kode_cabang' => 'CB-RFD', 'nama_cabang' => 'Cabang Refund', 'address' => '-', 'phone' => '-', 'emaiil' => 'refund@test.local', 'manager_name' => 'Manager', 'status' => 'aktif']);
    $housing = Perumahan::create(['cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan Refund', 'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 1, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif']);
    $unit = DetailRumah::create(['perumahan_id' => $housing->id, 'kode_nlok' => 'R', 'nomor_rumah' => '01', 'tipe_rumah' => '36', 'luas_tanah' => 72, 'status' => 'aktif']);
    $customer = Costumer::create(['kode_costumer' => 'CST-RFD', 'perumahan_id' => $housing->id, 'nama' => 'Customer Refund', 'jenis_kelamin' => 'L', 'jenis_identitas' => 'KTP', 'no_identitas' => 'NIK-RFD', 'status_perkawinan' => 'belum_menikah', 'alamat' => '-']);
    $spr = Spr::create(['kode_spr' => 'SPR-RFD', 'costumer_id' => $customer->id, 'detail_rumah_id' => $unit->id, 'tanggal_spr' => '2026-01-02', 'metode_pembayaran' => 'cash_bertahap', 'harga_jual' => 300000000, 'booking_fee' => 5000000, 'uang_muka' => 25000000, 'jumlah_termin' => 12, 'tanggal_jatuh_tempo_dp' => '2026-01-10', 'tanggal_jatuh_tempo_angsuran' => '2026-02-01', 'status' => Spr::STATUS_DISETUJUI]);
    return app(SalesPaymentWorkflowService::class)->processApprovedSpr($spr);
}

beforeEach(function () {
    foreach ([[ChartOfAccount::PIUTANG_CUSTOMER, 'Piutang Customer', 'aset', 'debit'], [ChartOfAccount::UANG_MUKA_CUSTOMER, 'Uang Muka Customer', 'liabilitas', 'kredit'], [ChartOfAccount::KAS_BANK, 'Kas Bank', 'aset', 'debit'], [ChartOfAccount::PENDAPATAN_ADMIN, 'Pendapatan Administrasi', 'pendapatan', 'kredit']] as [$code, $name, $category, $normal]) ChartOfAccount::updateOrCreate(['kode_akun' => $code], ['nama_akun' => $name, 'kategori' => $category, 'posisi_normal' => $normal, 'status' => 'aktif']);
});

test('refund hanya memakai booking fee dan uang muka yang sudah dibayar serta postingnya idempoten', function () {
    $transaction = refundWorkflowTransaction();
    $dp = PaymentSchedule::where('sales_transaction_id', $transaction->id)->where('type', 'down_payment')->firstOrFail();
    $dp->update(['paid_amount' => 25000000, 'status' => 'lunas']);
    $resolution = SalesResolutionRequest::create(['request_no' => 'PJG/RFD/1', 'sales_transaction_id' => $transaction->id, 'spr_id' => $transaction->spr_id, 'action' => 'close_lost', 'failure_category' => 'customer_cancel', 'failure_reason' => 'Customer membatalkan', 'financial_treatment' => 'refund', 'status' => 'approved', 'record_status' => 'locked', 'applied_at' => now()]);
    $refund = app(CustomerRefundService::class)->ensureDraftForResolution($resolution);
    $refund->update(['refund_date' => '2026-02-10', 'refund_amount' => 24000000, 'penalty_amount' => 1000000]);

    app(CustomerRefundService::class)->approve($refund);
    app(CustomerRefundService::class)->approve($refund->fresh());

    expect($refund->fresh()->status)->toBe('posted')->and((float) $dp->fresh()->paid_amount)->toBe(1000000.0)
        ->and($refund->items()->count())->toBe(1)->and($refund->journal()->count())->toBe(1)
        ->and($transaction->fresh()->status)->toBe('cancelled');
});

test('penutupan tanpa dana masuk menghasilkan draft refund nol dan tidak dapat diposting', function () {
    $transaction = refundWorkflowTransaction();
    $resolution = SalesResolutionRequest::create(['request_no' => 'PJG/RFD/2', 'sales_transaction_id' => $transaction->id, 'spr_id' => $transaction->spr_id, 'action' => 'close_lost', 'failure_category' => 'customer_cancel', 'failure_reason' => 'Customer membatalkan', 'financial_treatment' => 'refund', 'status' => 'approved', 'record_status' => 'locked', 'applied_at' => now()]);
    $refund = app(CustomerRefundService::class)->ensureDraftForResolution($resolution);
    expect((float) $refund->eligible_amount)->toBe(0.0);
    expect(fn () => app(CustomerRefundService::class)->approve($refund))->toThrow(\Illuminate\Validation\ValidationException::class);
});
