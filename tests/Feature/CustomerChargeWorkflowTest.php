<?php

use App\Models\ApprovalSetting;
use App\Models\CabangPerusahaan;
use App\Models\ChartOfAccount;
use App\Models\Costumer;
use App\Models\CustomerCharge;
use App\Models\CustomerReceipt;
use App\Models\DetailRumah;
use App\Models\Journal;
use App\Models\PaymentSchedule;
use App\Models\Perumahan;
use App\Models\SalesTransaction;
use App\Models\Spr;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\CustomerChargeService;
use App\Services\CustomerReceivableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function customerChargeTransaction(string $suffix): SalesTransaction
{
    $branch = CabangPerusahaan::create(['kode_cabang' => 'CB-CHG-'.$suffix, 'nama_cabang' => 'Cabang '.$suffix, 'address' => '-', 'phone' => '-', 'emaiil' => "{$suffix}@test.local", 'manager_name' => 'Manager', 'status' => 'aktif']);
    $housing = Perumahan::create(['cabang_id' => $branch->id, 'nama_perusahaan' => 'Perumahan '.$suffix, 'alamat' => '-', 'luas_lahan' => 1000, 'jumlah_unit' => 1, 'tanggal_mulai' => '2026-01-01', 'status' => 'aktif']);
    $unit = DetailRumah::create(['perumahan_id' => $housing->id, 'kode_nlok' => 'A', 'nomor_rumah' => '01', 'tipe_rumah' => '36', 'luas_tanah' => 72, 'status' => 'aktif']);
    $customer = Costumer::create(['kode_costumer' => 'CST-CHG-'.$suffix, 'perumahan_id' => $housing->id, 'nama' => 'Customer '.$suffix, 'jenis_kelamin' => 'L', 'jenis_identitas' => 'KTP', 'no_identitas' => 'NIK-'.$suffix, 'status_perkawinan' => 'menikah', 'alamat' => '-']);

    $spr = Spr::create(['kode_spr' => 'SPR-CHG-'.$suffix, 'costumer_id' => $customer->id, 'detail_rumah_id' => $unit->id, 'tanggal_spr' => '2026-07-16', 'metode_pembayaran' => 'kpr_bank', 'harga_jual' => 300000000, 'nilai_pengajuan_akhir' => 300000000, 'booking_fee' => 5000000, 'uang_muka' => 25000000, 'status' => Spr::STATUS_DISETUJUI]);

    return SalesTransaction::create(['spr_id' => $spr->id, 'transaction_no' => 'TRX-CHG-'.$suffix, 'costumer_id' => $customer->id, 'perumahan_id' => $housing->id, 'detail_rumah_id' => $unit->id, 'payment_method' => 'kpr_bank', 'sale_price_snapshot' => 300000000, 'party_snapshot' => ['customer_name' => $customer->nama], 'payment_snapshot' => ['method' => 'kpr_bank'], 'status' => 'active', 'approved_at' => now()]);
}

function seedCustomerChargeAccounts(): void
{
    foreach ([[ChartOfAccount::KAS_BANK, 'Kas Bank', 'aset', 'debit'], [ChartOfAccount::PIUTANG_CUSTOMER, 'Piutang Customer', 'aset', 'debit'], [ChartOfAccount::PENDAPATAN_ADMIN, 'Pendapatan Administrasi', 'pendapatan', 'kredit']] as [$code, $name, $category, $normal]) {
        ChartOfAccount::updateOrCreate(['kode_akun' => $code], ['nama_akun' => $name, 'kategori' => $category, 'posisi_normal' => $normal, 'status' => 'aktif']);
    }
}

function lockedCustomerCharge(SalesTransaction $transaction, User $user, string $type = 'additional_charge'): CustomerCharge
{
    return CustomerCharge::create(['charge_no' => 'CHG-TEST-'.uniqid(), 'sales_transaction_id' => $transaction->id, 'charge_type' => $type, 'category' => 'biaya_akad', 'description' => 'Biaya akad tambahan', 'amount' => 5000000, 'charge_date' => '2026-07-16', 'due_date' => '2026-08-16', 'status' => 'draft', 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $user->id, 'created_by' => $user->id]);
}

test('auto approval tagihan tambahan membentuk invoice dan jurnal hanya sekali', function () {
    seedCustomerChargeAccounts();
    $user = User::factory()->create(['phone' => '081244440001']);
    $this->actingAs($user);
    ApprovalSetting::where('module_key', 'customer-charge')->firstOrFail()->update(['requires_approval' => false, 'approval_stages' => 0, 'approval_steps' => []]);
    $charge = lockedCustomerCharge(customerChargeTransaction('AUTO'), $user);

    $approval = app(ApprovalWorkflowService::class)->submitLocked($charge, 'customer-charge');
    app(CustomerChargeService::class)->approve($charge->fresh());

    expect($approval->status)->toBe('approved')->and($charge->fresh()->status)->toBe('posted')
        ->and(PaymentSchedule::where('source_type', CustomerCharge::class)->where('source_id', $charge->id)->count())->toBe(1)
        ->and(Journal::where('source_type', CustomerCharge::class)->where('source_id', $charge->id)->where('type', 'customer_charge')->count())->toBe(1);
});

test('role tahap aktif saja yang dapat menyetujui talangan customer', function () {
    seedCustomerChargeAccounts();
    $role = Role::findOrCreate('approver_customer_charge', 'web');
    $creator = User::factory()->create(['phone' => '081244440002']);
    $approver = User::factory()->create(['phone' => '081244440003']);
    $approver->assignRole($role);
    ApprovalSetting::where('module_key', 'customer-charge')->firstOrFail()->update(['requires_approval' => true, 'approval_stages' => 1, 'approver_role_ids' => [$role->id], 'approval_steps' => [['step' => 1, 'role_ids' => [$role->id]]]]);
    $charge = lockedCustomerCharge(customerChargeTransaction('ROLE'), $creator, 'customer_advance');
    $this->actingAs($creator);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($charge, 'customer-charge');
    expect(app(ApprovalWorkflowService::class)->canReview($approval))->toBeFalse();
    $this->actingAs($approver);
    expect(app(ApprovalWorkflowService::class)->canReview($approval))->toBeTrue();
    app(ApprovalWorkflowService::class)->approve($approval);
    expect($charge->fresh()->status)->toBe('posted')->and($approval->fresh()->status)->toBe('approved');
});

test('reversal membentuk jurnal pembalik dan membatalkan invoice secara idempoten', function () {
    seedCustomerChargeAccounts();
    $user = User::factory()->create(['phone' => '081244440004']);
    $this->actingAs($user);
    ApprovalSetting::whereIn('module_key', ['customer-charge', 'customer-charge-reversal'])->get()->each->update(['requires_approval' => false, 'approval_stages' => 0, 'approval_steps' => []]);
    $charge = lockedCustomerCharge(customerChargeTransaction('REV'), $user);
    app(ApprovalWorkflowService::class)->submitLocked($charge, 'customer-charge');
    $charge->update(['reversal_reason' => 'Kesalahan nominal', 'reversal_status' => 'pending_approval']);
    app(ApprovalWorkflowService::class)->submitLocked($charge->fresh(), 'customer-charge-reversal');
    app(CustomerChargeService::class)->reverse($charge->fresh());

    expect($charge->fresh()->status)->toBe('reversed')->and((float) $charge->invoice()->first()->amount)->toBe(0.0)
        ->and(Journal::where('source_type', CustomerCharge::class)->where('source_id', $charge->id)->count())->toBe(2);
});

test('tagihan yang sudah dibayar tidak dapat direversal', function () {
    seedCustomerChargeAccounts();
    $user = User::factory()->create(['phone' => '081244440005']);
    $this->actingAs($user);
    ApprovalSetting::where('module_key', 'customer-charge')->firstOrFail()->update(['requires_approval' => false, 'approval_stages' => 0, 'approval_steps' => []]);
    $charge = lockedCustomerCharge(customerChargeTransaction('PAID'), $user);
    app(ApprovalWorkflowService::class)->submitLocked($charge, 'customer-charge');
    $charge->invoice()->update(['paid_amount' => 1000000, 'status' => 'sebagian']);

    expect(fn () => app(CustomerChargeService::class)->reverse($charge->fresh()))->toThrow(ValidationException::class);
});

test('satu invoice menerima pembayaran sebagian berkali kali dan setiap pembayaran tetap terlacak', function () {
    seedCustomerChargeAccounts();
    $user = User::factory()->create(['phone' => '081244440006']);
    $this->actingAs($user);
    ApprovalSetting::where('module_key', 'customer-charge')->firstOrFail()->update(['requires_approval' => false, 'approval_stages' => 0, 'approval_steps' => []]);
    $charge = lockedCustomerCharge(customerChargeTransaction('PARTIAL'), $user);
    app(ApprovalWorkflowService::class)->submitLocked($charge, 'customer-charge');
    $invoice = $charge->invoice()->firstOrFail();

    foreach ([['RCV-PARTIAL-1', '2026-07-20', 2000000], ['RCV-PARTIAL-2', '2026-07-27', 1000000]] as [$number, $date, $amount]) {
        $receipt = CustomerReceipt::create(['receipt_no' => $number, 'sales_transaction_id' => $charge->sales_transaction_id, 'payment_date' => $date, 'amount' => $amount, 'payment_method' => 'transfer', 'status' => 'draft', 'record_status' => 'locked', 'created_by' => $user->id]);
        $receipt->allocations()->create(['payment_schedule_id' => $invoice->id, 'amount' => $amount, 'allocation_type' => 'invoice']);
        app(CustomerReceivableService::class)->approveReceipt($receipt);
    }

    expect((float) $invoice->fresh()->paid_amount)->toBe(3000000.0)->and($invoice->fresh()->status)->toBe('sebagian')
        ->and($invoice->allocations()->count())->toBe(2)
        ->and($invoice->allocations()->with('receipt')->get()->pluck('receipt.payment_date')->filter()->count())->toBe(2);
});
