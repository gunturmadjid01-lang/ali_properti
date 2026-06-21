<?php

namespace App\Services;

use App\Models\BarangMaterial;
use App\Models\DetailRumah;
use App\Models\HppRealisasi;
use App\Models\Perumahan;
use App\Models\StokMaterial;
use App\Models\TransaksiLogistik;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogistikService
{
    public function simpanTransaksi(array $payload): TransaksiLogistik
    {
        return DB::transaction(function () use ($payload) {
            $items = collect($payload['items'] ?? [])
                ->filter(fn (array $item) => (float) ($item['qty'] ?? 0) > 0)
                ->values();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Minimal satu material harus diisi.']);
            }

            $detailRumah = ! empty($payload['detail_rumah_id'])
                ? DetailRumah::query()->findOrFail($payload['detail_rumah_id'])
                : null;

            $perumahanId = $detailRumah?->perumahan_id ?? $payload['perumahan_id'] ?? null;

            if ($payload['jenis'] === TransaksiLogistik::JENIS_KELUAR && ! $perumahanId) {
                throw ValidationException::withMessages(['perumahan_id' => 'Pilih perumahan atau detail rumah.']);
            }

            $total = 0;
            $transaksi = TransaksiLogistik::query()->create([
                'kode_transaksi' => $payload['kode_transaksi'] ?? $this->kodeTransaksi(),
                'gudang_id' => $payload['gudang_id'] ?? null,
                'tanggal' => $payload['tanggal'],
                'jenis' => $payload['jenis'],
                'perumahan_id' => $perumahanId,
                'detail_rumah_id' => $detailRumah?->id,
                'tahapan_pembangunan_id' => $payload['tahapan_pembangunan_id'] ?? null,
                'kelompok_hpp_id' => $payload['kelompok_hpp_id'] ?? null,
                'total_nominal' => 0,
                'keterangan' => $payload['keterangan'] ?? null,
                'source_type' => $payload['source_type'] ?? null,
                'source_id' => $payload['source_id'] ?? null,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $barang = BarangMaterial::query()->findOrFail($item['barang_material_id']);
                $qty = (float) $item['qty'];
                $harga = (float) ($item['harga_satuan'] ?? $barang->harga_hpp);
                $subtotal = $qty * $harga;
                $total += $subtotal;

                $this->mutasiStok($barang->id, $payload['jenis'], $qty, $payload['gudang_id'] ?? null);

                $transaksi->details()->create([
                    'barang_material_id' => $barang->id,
                    'qty' => $qty,
                    'satuan' => $item['satuan'] ?? $barang->satuan,
                    'harga_satuan' => $harga,
                    'subtotal' => $subtotal,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
            }

            $transaksi->update(['total_nominal' => $total]);

            if ($payload['jenis'] === TransaksiLogistik::JENIS_KELUAR) {
                $this->createHppRealisasi(
                    target: $detailRumah ?? Perumahan::query()->findOrFail($perumahanId),
                    perumahanId: $perumahanId,
                    detailRumahId: $detailRumah?->id,
                    tahapanPembangunanId: $payload['tahapan_pembangunan_id'] ?? null,
                    kelompokHppId: $payload['kelompok_hpp_id'] ?? null,
                    sumber: $transaksi,
                    tanggal: $payload['tanggal'],
                    nominal: $total,
                    keterangan: $payload['keterangan'] ?? 'Realisasi otomatis dari transaksi logistik',
                );

                if ($detailRumah !== null) {
                    $this->createHppRealisasi(
                        target: Perumahan::query()->findOrFail($perumahanId),
                        perumahanId: $perumahanId,
                        detailRumahId: null,
                        tahapanPembangunanId: $payload['tahapan_pembangunan_id'] ?? null,
                        kelompokHppId: $payload['kelompok_hpp_id'] ?? null,
                        sumber: $transaksi,
                        tanggal: $payload['tanggal'],
                        nominal: $total,
                        keterangan: $payload['keterangan'] ?? 'Realisasi otomatis dari transaksi logistik',
                    );
                }
            }

            if (
                $payload['jenis'] === TransaksiLogistik::JENIS_MASUK
                && ($payload['reverse_hpp'] ?? false)
                && $perumahanId
            ) {
                $this->createHppRealisasi(
                    target: $detailRumah ?? Perumahan::query()->findOrFail($perumahanId),
                    perumahanId: $perumahanId,
                    detailRumahId: $detailRumah?->id,
                    tahapanPembangunanId: $payload['tahapan_pembangunan_id'] ?? null,
                    kelompokHppId: $payload['kelompok_hpp_id'] ?? null,
                    sumber: $transaksi,
                    tanggal: $payload['tanggal'],
                    nominal: -$total,
                    keterangan: $payload['keterangan'] ?? 'Pengurang realisasi HPP dari pengembalian material',
                );

                if ($detailRumah !== null) {
                    $this->createHppRealisasi(
                        target: Perumahan::query()->findOrFail($perumahanId),
                        perumahanId: $perumahanId,
                        detailRumahId: null,
                        tahapanPembangunanId: $payload['tahapan_pembangunan_id'] ?? null,
                        kelompokHppId: $payload['kelompok_hpp_id'] ?? null,
                        sumber: $transaksi,
                        tanggal: $payload['tanggal'],
                        nominal: -$total,
                        keterangan: $payload['keterangan'] ?? 'Pengurang realisasi HPP dari pengembalian material',
                    );
                }
            }

            return $transaksi;
        });
    }

    private function createHppRealisasi(
        object $target,
        int $perumahanId,
        ?int $detailRumahId,
        ?int $tahapanPembangunanId,
        ?int $kelompokHppId,
        TransaksiLogistik $sumber,
        string $tanggal,
        float $nominal,
        string $keterangan,
    ): HppRealisasi {
        return HppRealisasi::query()->create([
            'target_type' => $target::class,
            'target_id' => $target->getKey(),
            'perumahan_id' => $perumahanId,
            'detail_rumah_id' => $detailRumahId,
            'tahapan_pembangunan_id' => $tahapanPembangunanId,
            'kelompok_hpp_id' => $kelompokHppId,
            'sumber_type' => TransaksiLogistik::class,
            'sumber_id' => $sumber->id,
            'tanggal' => $tanggal,
            'nominal' => $nominal,
            'keterangan' => $keterangan,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    private function mutasiStok(int $barangId, string $jenis, float $qty, int|string|null $gudangId = null): void
    {
        $attributes = ['barang_material_id' => $barangId, 'gudang_id' => $gudangId ?: null, 'cabang_id' => null];
        $stok = StokMaterial::query()->where($attributes)->lockForUpdate()->first();

        if (! $stok) {
            $stok = StokMaterial::query()->create([...$attributes, 'qty' => 0]);
        }

        if ($jenis === TransaksiLogistik::JENIS_KELUAR && $stok->qty < $qty) {
            throw ValidationException::withMessages([
                'items' => "Stok material tidak cukup. Stok tersedia {$stok->qty}.",
            ]);
        }

        if ($jenis === TransaksiLogistik::JENIS_MASUK) {
            $stok->increment('qty', $qty);
            return;
        }

        $stok->decrement('qty', $qty);
    }

    private function kodeTransaksi(): string
    {
        return 'LOG-'.now()->format('YmdHisv').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }
}
