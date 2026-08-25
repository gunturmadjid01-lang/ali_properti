<?php

use App\Models\DetailRumah;
use App\Models\MarketingCampaign;
use App\Models\MarketingLead;
use App\Models\MarketingLeadSource;
use App\Models\Perumahan;
use App\Models\PettyCashAccount;
use App\Models\User;
use App\Services\ApprovalWorkflowService;
use App\Services\HousingReservationService;
use App\Services\Marketing\MarketingLeadConversionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Smoke test ini memerlukan schema MySQL/MariaDB aplikasi.');
    }
});

it('runs qualified lead conversion through approved booking fee inside a rollback transaction', function () {
    $actor = User::query()->where('email', 'marketing@ptali.com')->firstOrFail();
    $perumahan = Perumahan::query()->firstOrFail();
    $unit = DetailRumah::query()->where('perumahan_id', $perumahan->id)->whereIn('status_penjualan', ['tersedia', 'available'])->firstOrFail();
    $pettyCash = PettyCashAccount::query()->where('assigned_user_id', $actor->id)->firstOrFail();
    $phone = 'SMOKE-'.Str::upper(Str::random(12));

    $lead = MarketingLead::query()->create([
        'lead_no' => 'SMOKE-'.Str::upper(Str::random(12)),
        'name' => 'CRM Smoke Test',
        'phone' => $phone,
        'ownership_type' => 'marketing',
        'source_channel' => 'canvassing',
        'marketing_id' => $actor->id,
        'perumahan_id' => $perumahan->id,
        'interest_level' => 'hot',
        'preferred_payment_method' => 'cash',
        'stage' => 'new',
        'qualification_status' => 'unqualified',
        'created_by' => $actor->id,
        'updated_by' => $actor->id,
    ]);

    expect(fn () => app(MarketingLeadConversionService::class)->convert($lead, $actor->id))
        ->toThrow(ValidationException::class);

    $lead->update([
        'stage' => 'qualified',
        'qualification_status' => 'qualified',
        'verification_status' => 'pending',
        'qualified_at' => now(),
        'qualified_by' => $actor->id,
    ]);
    expect(fn () => app(MarketingLeadConversionService::class)->convert($lead->fresh(), $actor->id))
        ->toThrow(ValidationException::class);
    $lead->update(['verification_status' => 'verified']);
    $customer = app(MarketingLeadConversionService::class)->convert($lead->fresh(), $actor->id);

    $this->actingAs($actor);
    $reservation = app(HousingReservationService::class)->create([
        'costumer_id' => $customer->id,
        'detail_rumah_id' => $unit->id,
        'payment_method' => 'cash',
        'payment_channel' => 'cash',
        'petty_cash_account_id' => $pettyCash->id,
        'payment_submitted_at' => now()->toDateString(),
        'booking_fee' => 1000000,
    ]);

    $reservation = app(HousingReservationService::class)->lock($reservation);
    $approval = app(ApprovalWorkflowService::class)->submitLocked($reservation, 'housing-reservation');
    if ($approval->status === 'pending') {
        app(ApprovalWorkflowService::class)->skipCurrentStep($approval, 'Smoke test alur cash tanpa verifikasi Keuangan.');
    }
    $reservation->refresh();

    expect($lead->fresh()->stage)->toBe('converted')
        ->and($lead->fresh()->converted_costumer_id)->toBe($customer->id)
        ->and($customer->source_marketing_lead_id)->toBe($lead->id)
        ->and($customer->customer_stage)->toBe('pre_reservation')
        ->and($reservation->record_status)->toBe('locked')
        ->and($reservation->payment_status)->toBe('paid')
        ->and($reservation->status)->toBe('active')
        ->and($reservation->paymentSchedule()->exists())->toBeTrue()
        ->and($reservation->receipts()->exists())->toBeTrue()
        ->and($reservation->customer->fresh()->customer_stage)->toBe('booking_fee_paid');
});

it('allows admin sales and supervisor roles to open their operational workspaces', function () {
    $perumahan = Perumahan::query()->firstOrFail();

    foreach ([
        ['role' => 'admin_sales', 'route' => 'admin.admin-sales.dashboard'],
        ['role' => 'supervisor_marketing', 'route' => 'admin.marketing.tools.show', 'parameters' => ['monitoring-aktivitas']],
        ['role' => 'owner', 'route' => 'admin.marketing.operasional.show', 'parameters' => ['pipeline']],
    ] as $case) {
        $user = User::query()->create([
            'name' => 'Smoke '.Str::headline($case['role']),
            'email' => 'smoke-'.Str::random(12).'@example.test',
            'phone' => '08'.random_int(1000000000, 9999999999),
            'password' => 'password',
            'has_login_access' => true,
        ]);
        $user->assignRole($case['role']);
        $user->perumahans()->sync([$perumahan->id]);

        $this->actingAs($user)
            ->get(route($case['route'], $case['parameters'] ?? []))
            ->assertOk();
    }
});

it('merges duplicate leads and recycles postponed leads with an audit-safe workflow', function () {
    $admin = User::query()->where('email', 'admin@ptali.com')->firstOrFail();
    $perumahan = Perumahan::query()->firstOrFail();
    $base = ['phone' => 'DUP-'.Str::random(12), 'ownership_type' => 'company', 'source_channel' => 'direct', 'perumahan_id' => $perumahan->id, 'interest_level' => 'warm', 'qualification_status' => 'unqualified', 'verification_status' => 'pending', 'created_by' => $admin->id, 'updated_by' => $admin->id];
    $target = MarketingLead::query()->create($base + ['lead_no' => 'TARGET-'.Str::random(8), 'name' => 'Lead Utama', 'stage' => 'nurturing']);
    $source = MarketingLead::query()->create($base + ['lead_no' => 'SOURCE-'.Str::random(8), 'name' => 'Lead Duplikat', 'stage' => 'new']);

    $this->actingAs($admin)->post(route('admin.admin-sales.leads.merge', $source), ['target_lead_id' => $target->id, 'reason' => 'Nomor telepon dan identitas prospek sama.'])->assertRedirect();
    expect($source->fresh()->merged_into_lead_id)->toBe($target->id)
        ->and($source->fresh()->verification_status)->toBe('duplicate')
        ->and($source->fresh()->do_not_contact)->toBeTrue();

    $postponed = MarketingLead::query()->create($base + ['lead_no' => 'RECYCLE-'.Str::random(8), 'name' => 'Lead Ditunda', 'stage' => 'postponed', 'recycle_at' => now()->subDay()]);
    $this->actingAs($admin)->post(route('admin.admin-sales.leads.recycle', $postponed), ['reason' => 'Prospek kembali siap membahas pembelian rumah.', 'next_action_at' => now()->addDay()->toDateTimeString()])->assertRedirect();
    expect($postponed->fresh()->stage)->toBe('nurturing')
        ->and($postponed->fresh()->recycle_count)->toBe(1)
        ->and($postponed->fresh()->next_action_at)->not->toBeNull();
});

it('enforces lead consent channels and keeps qualification behind its dedicated gate', function () {
    $marketing = User::query()->where('email', 'marketing@ptali.com')->firstOrFail();
    $perumahan = Perumahan::query()->firstOrFail();
    $lead = MarketingLead::query()->create([
        'lead_no' => 'CONSENT-'.Str::random(8),
        'name' => 'Lead Consent Test',
        'phone' => 'CONSENT-'.Str::random(12),
        'ownership_type' => 'marketing',
        'source_channel' => 'canvassing',
        'marketing_id' => $marketing->id,
        'perumahan_id' => $perumahan->id,
        'interest_level' => 'hot',
        'stage' => 'new',
        'qualification_status' => 'unqualified',
        'consent_status' => 'unknown',
        'created_by' => $marketing->id,
        'updated_by' => $marketing->id,
    ]);

    $this->actingAs($marketing)->post(route('admin.marketing.leads.consent', $lead), [
        'consent_status' => 'denied',
        'note' => 'Prospek meminta agar tidak dihubungi kembali.',
    ])->assertRedirect();
    expect($lead->fresh()->do_not_contact)->toBeTrue();

    $payload = [
        'marketing_lead_id' => $lead->id,
        'tanggal_follow_up' => now()->toDateString(),
        'metode_follow_up' => 'telephone',
        'status_serius' => true,
        'progress_kemampuan' => 'very_high',
        'result_code' => 'reservation_ready',
        'interest_level' => 'hot',
        'status' => 'selesai',
        'catatan' => 'Prospek siap melanjutkan pembicaraan.',
        'next_action' => 'Lengkapi proses kualifikasi.',
        'rencana_follow_up_at' => now()->addDay()->toDateString(),
    ];
    $this->post(route('admin.marketing.jejak-follow-up.store'), $payload)->assertStatus(422);

    $this->post(route('admin.marketing.leads.consent', $lead), [
        'consent_status' => 'granted',
        'consent_channels' => ['whatsapp'],
        'note' => 'Prospek menyetujui komunikasi hanya melalui WhatsApp.',
    ])->assertRedirect();
    $this->post(route('admin.marketing.jejak-follow-up.store'), $payload)->assertStatus(422);
    $this->post(route('admin.marketing.jejak-follow-up.store'), array_merge($payload, ['metode_follow_up' => 'whatsapp']))->assertRedirect();

    expect($lead->fresh()->stage)->toBe('nurturing')
        ->and($lead->fresh()->qualification_status)->toBe('unqualified')
        ->and($lead->fresh()->do_not_contact)->toBeFalse();
});

it('requires an audited decision before saving a direct duplicate lead', function () {
    $marketing = User::query()->where('email', 'marketing@ptali.com')->firstOrFail();
    $source = MarketingLeadSource::query()->first() ?? MarketingLeadSource::query()->create([
        'kode_sumber' => 'SMOKE-'.Str::random(8), 'nama_sumber' => 'Sumber Smoke Test',
        'kategori' => 'direct', 'status' => 'aktif', 'record_status' => 'locked',
        'created_by' => $marketing->id, 'updated_by' => $marketing->id,
    ]);
    $phone = 'DUPCHECK-'.Str::random(10);
    $existing = MarketingLead::query()->create([
        'lead_no' => 'EXISTING-'.Str::random(8), 'name' => 'Prospek Lama', 'phone' => $phone,
        'ownership_type' => 'marketing', 'source_channel' => 'canvassing', 'marketing_id' => $marketing->id,
        'lead_source_id' => $source->id, 'interest_level' => 'warm', 'stage' => 'new',
        'qualification_status' => 'unqualified', 'created_by' => $marketing->id, 'updated_by' => $marketing->id,
    ]);
    $payload = [
        'name' => 'Prospek Berbeda', 'phone' => $phone, 'lead_source_id' => $source->id,
        'source_channel' => 'direct', 'consent_status' => 'unknown', 'interest_level' => 'cold',
    ];

    $this->actingAs($marketing)->getJson(route('admin.marketing.leads.check-duplicates', ['phone' => $phone]))
        ->assertOk()->assertJsonPath('duplicates.0.id', $existing->id);
    $this->post(route('admin.marketing.leads.store'), $payload)
        ->assertSessionHasErrors('duplicate_override_reason');
    $this->post(route('admin.marketing.leads.store'), $payload + [
        'duplicate_acknowledged_id' => $existing->id,
        'duplicate_override_reason' => 'Nama sama tetapi sudah dikonfirmasi sebagai anggota keluarga berbeda.',
    ])->assertRedirect();

    $created = MarketingLead::query()->where('name', 'Prospek Berbeda')->latest('id')->firstOrFail();
    expect($created->possible_duplicate_lead_id)->toBe($existing->id)
        ->and($created->duplicate_override_reason)->toContain('anggota keluarga berbeda')
        ->and($created->duplicate_checked_by)->toBe($marketing->id);
});

it('edits lead property interest and carries it into the customer record', function () {
    $marketing = User::query()->where('email', 'marketing@ptali.com')->firstOrFail();
    $perumahan = Perumahan::query()->firstOrFail();
    $unit = DetailRumah::query()->where('perumahan_id', $perumahan->id)->whereIn('status_penjualan', ['tersedia', 'available'])->firstOrFail();
    $source = MarketingLeadSource::query()->first() ?? MarketingLeadSource::query()->create(['kode_sumber' => 'EDIT-'.Str::random(8), 'nama_sumber' => 'Sumber Edit Test', 'status' => 'aktif', 'record_status' => 'locked']);
    $campaign = MarketingCampaign::query()->create(['perumahan_id' => $perumahan->id, 'kode_campaign' => 'CMP-'.Str::random(8), 'nama_campaign' => 'Campaign Test', 'kanal' => 'digital', 'tanggal_mulai' => now()->toDateString(), 'status' => 'aktif', 'record_status' => 'locked']);
    $lead = MarketingLead::query()->create(['lead_no' => 'EDIT-'.Str::random(8), 'name' => 'Lead Edit Properti', 'phone' => 'EDIT-'.Str::random(10), 'ownership_type' => 'marketing', 'source_channel' => 'direct', 'lead_source_id' => $source->id, 'marketing_id' => $marketing->id, 'interest_level' => 'hot', 'stage' => 'new', 'qualification_status' => 'unqualified']);

    $this->actingAs($marketing)->put(route('admin.marketing.leads.update', $lead), [
        'name' => $lead->name, 'phone' => $lead->phone, 'lead_source_id' => $source->id,
        'source_channel' => 'direct', 'consent_status' => 'unknown', 'interest_level' => 'hot',
        'preferred_payment_method' => 'cash', 'perumahan_id' => $perumahan->id,
        'unit_type_interest' => $unit->tipe_rumah, 'detail_rumah_id' => $unit->id,
        'marketing_campaign_id' => $campaign->id,
    ])->assertRedirect(route('admin.marketing.leads.show', $lead));

    $lead->refresh();
    expect($lead->detail_rumah_id)->toBe($unit->id)
        ->and($lead->unit_type_interest)->toBe($unit->tipe_rumah)
        ->and($lead->cabang_perusahaan_id)->toBe($perumahan->cabang_id)
        ->and($lead->marketing_campaign_id)->toBe($campaign->id);
});
