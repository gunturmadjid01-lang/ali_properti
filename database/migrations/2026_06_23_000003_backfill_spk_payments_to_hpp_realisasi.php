<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $kelompokIds = DB::table('kelompok_hpps')
            ->whereIn('nama_hpp', ['Biaya Subkontraktor', 'Jalan Kawasan', 'Biaya Pematangan Lahan'])
            ->pluck('id', 'nama_hpp');

        DB::table('spk_kontraktor_payments as payment')
            ->join('spk_kontraktors as spk', 'spk.id', '=', 'payment.spk_kontraktor_id')
            ->whereNull('payment.deleted_at')
            ->whereNull('spk.deleted_at')
            ->where('payment.status', '!=', 'menunggu_pengajuan')
            ->select([
                'payment.id',
                'payment.termin_ke',
                'payment.nominal',
                'payment.requested_by',
                'payment.requested_at',
                'payment.tanggal_jatuh_tempo',
                'payment.created_at',
                'spk.nomor_spk',
                'spk.jenis_pekerjaan',
                'spk.perumahan_id',
                'spk.detail_rumah_id',
            ])
            ->orderBy('payment.id')
            ->each(function ($payment) use ($kelompokIds): void {
                $exists = DB::table('hpp_realisasis')
                    ->where('sumber_type', 'App\\Models\\SpkKontraktorPayment')
                    ->where('sumber_id', $payment->id)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists || ! $payment->perumahan_id) {
                    return;
                }

                $kelompokName = match ($payment->jenis_pekerjaan) {
                    'jalan' => 'Jalan Kawasan',
                    'pembukaan_lahan' => 'Biaya Pematangan Lahan',
                    default => 'Biaya Subkontraktor',
                };
                $now = now();

                DB::table('hpp_realisasis')->insert([
                    'target_type' => $payment->detail_rumah_id ? 'App\\Models\\DetailRumah' : 'App\\Models\\Perumahan',
                    'target_id' => $payment->detail_rumah_id ?: $payment->perumahan_id,
                    'perumahan_id' => $payment->perumahan_id,
                    'detail_rumah_id' => $payment->detail_rumah_id,
                    'tahapan_pembangunan_id' => null,
                    'kelompok_hpp_id' => $kelompokIds[$kelompokName] ?? null,
                    'sumber_type' => 'App\\Models\\SpkKontraktorPayment',
                    'sumber_id' => $payment->id,
                    'tanggal' => $payment->requested_at
                        ? substr((string) $payment->requested_at, 0, 10)
                        : ($payment->tanggal_jatuh_tempo ?: substr((string) $payment->created_at, 0, 10)),
                    'nominal' => $payment->nominal,
                    'keterangan' => "Realisasi HPP termin {$payment->termin_ke} SPK {$payment->nomor_spk}",
                    'user_id' => $payment->requested_by,
                    'created_by' => $payment->requested_by,
                    'updated_by' => $payment->requested_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('hpp_realisasis')
            ->where('sumber_type', 'App\\Models\\SpkKontraktorPayment')
            ->delete();
    }
};
