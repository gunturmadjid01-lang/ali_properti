<?php

namespace Database\Seeders;

use App\Models\BankCreditProduct;
use App\Models\CashInstallmentScheme;
use App\Models\Costumer;
use App\Models\DetailRumah;
use App\Models\DeveloperKprProduct;
use App\Models\Spr;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SprTestingSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::query()->where('email', 'marketing@ptali.com')->first()
            ?? User::query()->where('email', 'admin@ptali.com')->first()
            ?? User::query()->first();
        $cashSchemes = CashInstallmentScheme::query()->where('status', 'aktif')->orderBy('id')->get();
        $developerProducts = DeveloperKprProduct::query()->where('status', 'aktif')->orderBy('id')->get();
        $bankProducts = BankCreditProduct::query()
            ->with(['bank', 'branch'])
            ->where('status', 'aktif')
            ->orderBy('id')
            ->get();
        if (! $creator || $cashSchemes->isEmpty() || $developerProducts->isEmpty() || $bankProducts->isEmpty()) {
            throw new RuntimeException('Dependency SPR testing belum lengkap. Jalankan DatabaseSeeder agar user, customer, unit, dan master pembayaran tersedia.');
        }

        $methods = ['cash', 'cash_bertahap', 'kpr_bank', 'kpr_developer'];
        $created = 0;
        $updated = 0;
        // Customer pada SPR yang sudah dikunci tidak boleh dipakai ulang oleh draft lain.
        $usedCustomerIds = Spr::query()
            ->where('kode_spr', 'like', 'SPR-TEST-%')
            ->where('record_status', '!=', 'draft')
            ->pluck('costumer_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $usedUnitIds = Spr::query()
            ->where('kode_spr', 'like', 'SPR-TEST-%')
            ->where('record_status', '!=', 'draft')
            ->pluck('detail_rumah_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $availableCustomers = Costumer::query()
            ->where('created_by', $creator->id)
            ->whereNotIn('id', $usedCustomerIds)
            ->orderBy('id')
            ->get();
        $pairs = DetailRumah::query()
            ->with('perumahan')
            ->where('status', 'aktif')
            ->where('status_penjualan', 'tersedia')
            ->whereNull('booking_spr_id')
            ->whereNotIn('id', $usedUnitIds)
            ->orderBy('perumahan_id')
            ->orderBy('id')
            ->get()
            ->map(function (DetailRumah $unit) use ($availableCustomers) {
                $customer = $availableCustomers->first(
                    fn (Costumer $candidate) => (int) $candidate->perumahan_id === (int) $unit->perumahan_id
                );

                if (! $customer) {
                    return null;
                }

                $availableCustomers->forget($availableCustomers->search($customer));

                return [$unit, $customer];
            })
            ->filter()
            ->take(20)
            ->values();

        if ($pairs->count() < 20) {
            throw new RuntimeException('Dibutuhkan minimal 20 pasangan customer dan unit pada perumahan yang sama untuk data SPR testing.');
        }

        for ($index = 0; $index < 20; $index++) {
            $code = 'SPR-TEST-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $existing = Spr::withTrashed()->where('kode_spr', $code)->first();
            [$unit, $customer] = $pairs[$index];

            if ($existing) {
                // Pertahankan progress approval yang sudah dilakukan oleh tester.
                if ($existing->trashed()) {
                    $existing->restore();
                }

                if ($existing->record_status === 'draft'
                    && ((int) $existing->costumer_id !== (int) $customer->id
                        || (int) $existing->detail_rumah_id !== (int) $unit->id)) {
                    $existing->update([
                        'costumer_id' => $customer->id,
                        'detail_rumah_id' => $unit->id,
                    ]);
                    $updated++;
                }

                continue;
            }

            $method = $methods[$index % count($methods)];
            $price = (float) $unit->harga_jual;
            $bookingFee = $method === 'cash_bertahap' ? 5000000 : 0;
            $downPayment = in_array($method, ['cash_bertahap', 'kpr_bank', 'kpr_developer'], true)
                ? round($price * 0.20)
                : 0;
            $financing = in_array($method, ['kpr_bank', 'kpr_developer'], true)
                ? max(0, $price - $downPayment)
                : 0;
            $cashScheme = $method === 'cash_bertahap' ? $cashSchemes[$index % $cashSchemes->count()] : null;
            $developerProduct = $method === 'kpr_developer' ? $developerProducts[$index % $developerProducts->count()] : null;
            $bankProduct = $method === 'kpr_bank' ? $bankProducts[$index % $bankProducts->count()] : null;
            $installments = $cashScheme ? max(1, (int) $cashScheme->installment_count) : null;
            $date = now()->subDays(20 - $index)->toDateString();
            $snapshotMaster = $cashScheme ?? $developerProduct ?? $bankProduct;

            Spr::query()->create([
                'kode_spr' => $code,
                'costumer_id' => $customer->id,
                'detail_rumah_id' => $unit->id,
                'created_by' => $creator->id,
                'tanggal_spr' => $date,
                'booking_expires_at' => now()->addDays(30),
                'metode_pembayaran' => $method,
                'bank_kredit_id' => $bankProduct?->bank_kredit_id,
                'bank_branch_id' => $bankProduct?->bank_branch_id,
                'bank_credit_product_id' => $bankProduct?->id,
                'cash_installment_scheme_id' => $cashScheme?->id,
                'developer_kpr_product_id' => $developerProduct?->id,
                'payment_configuration_snapshot' => [
                    'type' => match ($method) {
                        'cash_bertahap' => 'cash_installment_scheme',
                        'kpr_bank' => 'bank_credit_product',
                        'kpr_developer' => 'developer_kpr_product',
                        default => 'cash',
                    },
                    'master' => $snapshotMaster?->toArray(),
                    'captured_at' => now()->toIso8601String(),
                    'pricing' => [
                        'unit_price' => $price,
                        'additional_price' => 0,
                        'final_price' => $price,
                        'booking_fee' => $bookingFee,
                        'down_payment' => $downPayment,
                        'financing_amount' => $financing,
                    ],
                ],
                'kpr_tenor_bulan' => $method === 'kpr_bank'
                    ? min(180, (int) $bankProduct->maximum_tenor_months)
                    : ($method === 'kpr_developer' ? min(36, (int) $developerProduct->maximum_tenor_months) : null),
                'kpr_bunga_tahunan' => $method === 'kpr_bank'
                    ? (float) $bankProduct->indicative_interest_margin
                    : ($method === 'kpr_developer' ? (float) $developerProduct->annual_margin : null),
                'harga_jual' => $price,
                'booking_fee' => $bookingFee,
                'booking_fee_includes_dp' => $method === 'cash_bertahap',
                'tanggal_pembayaran_booking_fee' => $bookingFee > 0 ? $date : null,
                'uang_muka' => $downPayment,
                'uang_muka_jumlah_pembayaran' => $downPayment > 0 ? 1 : null,
                'tanggal_jatuh_tempo_dp' => $downPayment > 0 ? now()->addDays(14)->toDateString() : null,
                'tanggal_jatuh_tempo_angsuran' => $cashScheme ? now()->addMonth()->toDateString() : null,
                'nilai_pengajuan_kpr' => $financing,
                'total_penambahan_tanah' => 0,
                'total_penambahan_lain_lain' => 0,
                'total_penambahan' => 0,
                'nilai_pengajuan_akhir' => $price,
                'jumlah_termin' => $installments,
                'nominal_termin' => $installments ? round(max(0, $price - $bookingFee - $downPayment) / $installments) : null,
                'tanggal_jatuh_tempo_termin' => $installments ? now()->addMonth()->toDateString() : null,
                'status' => Spr::STATUS_DRAFT,
                'record_status' => 'draft',
                'catatan' => 'Data uji '.$method.'; login sebagai marketing@ptali.com lalu Lock untuk memulai approval sesuai Setting Approval.',
            ]);
            $created++;
        }

        $this->command?->info("{$created} draft SPR baru dibuat dan {$updated} draft lama diperbarui dengan customer berbeda (target total: 20). Progress approval tetap aman.");
    }
}
