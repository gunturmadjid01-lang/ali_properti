<?php

use App\Models\ApprovalRequest;
use App\Models\ApprovalSetting;
use App\Models\CabangPerusahaan;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\SalesProcessStep;
use App\Models\SalesResolutionRequest;
use App\Models\SalesTransaction;
use App\Models\Spr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function resolutionFixture(): array
{
    $branch = CabangPerusahaan::create(['kode_cabang' => 'CB-RS', 'nama_cabang' => 'Cabang Resolution', 'address' => '-', 'phone' => '-', 'emaiil' => 'rs@test.local', 'manager_name' => 'Manager', 'status' => 'aktif']);
    $housing = Perumahan::create(['cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan Resolution', 'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 1, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif']);
    $unit = DetailRumah::create(['perumahan_id' => $housing->id, 'kode_nlok' => 'A', 'nomor_rumah' => '1', 'tipe_rumah' => '36', 'luas_tanah' => 72, 'harga_jual' => 300000000, 'status' => 'aktif', 'status_penjualan' => 'booking']);
    $user = User::factory()->create(['phone' => '081200009991']);
    $user->givePermissionTo([Permission::findOrCreate('booking.view'), Permission::findOrCreate('booking.update'), Permission::findOrCreate('booking.manage')]);
    $customer = Costumer::create(['kode_costumer' => 'C-RS', 'created_by' => $user->id, 'perumahan_id' => $housing->id, 'nama' => 'Customer Resolution', 'jenis_kelamin' => 'laki-laki', 'jenis_identitas' => 'ktp', 'no_identitas' => 'RS-1', 'status_perkawinan' => 'belum_menikah', 'alamat' => '-']);
    $spr = Spr::create(['kode_spr' => 'SPR/RS/1', 'costumer_id' => $customer->id, 'detail_rumah_id' => $unit->id, 'created_by' => $user->id, 'tanggal_spr' => '2026-07-16', 'metode_pembayaran' => 'kpr_bank', 'harga_jual' => 300000000, 'nilai_pengajuan_akhir' => 300000000, 'status' => Spr::STATUS_DISETUJUI, 'record_status' => 'locked']);
    $transaction = SalesTransaction::create(['transaction_no' => 'TRX/RS/1', 'spr_id' => $spr->id, 'costumer_id' => $customer->id, 'perumahan_id' => $housing->id, 'detail_rumah_id' => $unit->id, 'marketing_user_id' => $user->id, 'payment_method' => 'kpr_bank', 'sale_price_snapshot' => 300000000, 'party_snapshot' => [], 'payment_snapshot' => [], 'status' => 'active', 'approved_at' => now()]);
    $step = SalesProcessStep::create(['sales_transaction_id' => $transaction->id, 'code' => 'slik', 'sequence' => 1, 'label' => 'SLIK', 'category' => 'bank', 'status' => 'in_progress', 'record_status' => 'locked']);

    return compact('user', 'transaction', 'step', 'spr');
}

test('penanganan gagal auto approve membuka ulang tahap secara idempoten', function () {
    ['user' => $user,'transaction' => $transaction,'step' => $step] = resolutionFixture();
    ApprovalSetting::create(['module_key' => 'sales-resolution-request', 'module_label' => 'Penanganan Proses Penjualan Gagal', 'action' => 'lock', 'requires_approval' => false, 'approval_stages' => 0, 'approver_role_ids' => [], 'approval_steps' => [], 'is_active' => true]);

    $this->actingAs($user)->post(route('admin.sales-resolutions.store'), ['sales_transaction_id' => $transaction->id, 'action' => 'retry_stage', 'failed_stage' => 'slik', 'failure_category' => 'data_customer', 'failure_reason' => 'Data perlu diperbaiki', 'restart_stage' => 'slik', 'financial_treatment' => 'review_required'])->assertSessionHasNoErrors();
    $resolution = SalesResolutionRequest::sole();
    $this->actingAs($user)->post(route('admin.sales-resolutions.lock', $resolution))->assertSessionHasNoErrors();

    $resolution->refresh();
    expect($resolution->status)->toBe('approved')->and($resolution->applied_at)->not->toBeNull()
        ->and($step->fresh()->status)->toBe('available')->and($step->fresh()->record_status)->toBe('draft')
        ->and(ApprovalRequest::sole()->status)->toBe(ApprovalRequest::STATUS_APPROVED);
});
