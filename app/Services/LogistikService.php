<?php

namespace App\Services;

use App\Models\BarangMaterial;
use App\Models\DetailRumah;
use App\Models\StokMaterial;
use App\Models\MaterialStockLot;
use App\Models\MaterialStockLotAllocation;
use App\Models\TransaksiLogistik;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogistikService
{
    public function __construct(private readonly MaterialUnitConversionService $conversions) {}

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
                ? DetailRumah::query()->finalized()->findOrFail($payload['detail_rumah_id'])
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
                $barang = BarangMaterial::query()->finalized()->findOrFail($item['barang_material_id']);
                $inputQty = (float) $item['qty'];
                $inputPrice = (float) ($item['harga_satuan'] ?? $barang->harga_hpp);
                $normalized = $this->conversions->normalize($barang, $item['material_unit_id'] ?? null, $inputQty, $inputPrice);
                $qty = $normalized['quantity_base'];
                $harga = $normalized['unit_price_base'];
                $subtotal = $inputQty * $inputPrice;
                $total += $subtotal;

                $allocations = $this->mutasiStok($barang->id, $payload['jenis'], $qty, $payload['gudang_id'] ?? null, $harga);

                $transactionDetail = $transaksi->details()->create([
                    'barang_material_id' => $barang->id,
                    'qty' => $qty,
                    'input_qty' => $inputQty,
                    'input_unit_id' => $normalized['unit_id'],
                    'input_satuan' => $normalized['unit_symbol'],
                    'conversion_to_base' => $normalized['factor_to_base'],
                    'satuan' => $barang->satuan,
                    'harga_satuan' => $harga,
                    'subtotal' => $subtotal,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
                foreach ($allocations as $allocation) {
                    MaterialStockLotAllocation::query()->create([
                        'transaksi_logistik_detail_id' => $transactionDetail->id,
                        'material_stock_lot_id' => $allocation['lot_id'],
                        'qty' => $allocation['qty'],
                        'unit_cost' => $allocation['unit_cost'],
                        'amount' => $allocation['qty'] * $allocation['unit_cost'],
                    ]);
                }
            }

            $transaksi->update(['total_nominal' => $total]);

            return $transaksi;
        });
    }

    public function reverseTransaction(TransaksiLogistik $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $transaction = TransaksiLogistik::withTrashed()->with('details')->lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->trashed()) {
                return;
            }

            foreach ($transaction->details as $detail) {
                $stock = StokMaterial::query()->where([
                    'barang_material_id' => $detail->barang_material_id,
                    'gudang_id' => $transaction->gudang_id,
                    'cabang_id' => null,
                ])->lockForUpdate()->firstOrFail();
                $qty = (float) $detail->qty;
                $value = $qty * (float) $detail->harga_satuan;

                if ($transaction->jenis === TransaksiLogistik::JENIS_KELUAR) {
                    $newQty = (float) $stock->qty + $qty;
                    $newValue = (float) $stock->inventory_value + $value;
                    $stock->update(['qty' => $newQty, 'inventory_value' => $newValue, 'average_unit_cost' => $newQty > 0 ? $newValue / $newQty : 0]);
                    $allocations = MaterialStockLotAllocation::query()->where('transaksi_logistik_detail_id', $detail->id)->lockForUpdate()->get();
                    if ($allocations->isNotEmpty()) {
                        foreach ($allocations as $allocation) {
                            $lot = MaterialStockLot::query()->lockForUpdate()->findOrFail($allocation->material_stock_lot_id);
                            $lot->update(['qty_tersedia' => (float) $lot->qty_tersedia + (float) $allocation->qty, 'status' => 'tersedia']);
                        }
                    } else {
                        MaterialStockLot::query()->create([
                            'source_type' => TransaksiLogistik::class, 'source_id' => $transaction->id,
                            'barang_material_id' => $detail->barang_material_id, 'gudang_id' => $transaction->gudang_id,
                            'kode_lot' => 'REV-'.$transaction->id.'-'.$detail->id, 'tanggal_terima' => now()->toDateString(),
                            'qty_diterima' => $qty, 'qty_tersedia' => $qty, 'unit_cost' => (float) $detail->harga_satuan,
                            'total_cost' => $value, 'kondisi' => 'baik', 'status' => 'tersedia',
                        ]);
                    }
                } else {
                    if ((float) $stock->qty < $qty) {
                        throw ValidationException::withMessages(['unlock' => 'Transaksi masuk tidak dapat dibalik karena sebagian stok sudah dipakai.']);
                    }
                    $newQty = (float) $stock->qty - $qty;
                    $newValue = max(0, (float) $stock->inventory_value - $value);
                    $stock->update(['qty' => $newQty, 'inventory_value' => $newValue, 'average_unit_cost' => $newQty > 0 ? $newValue / $newQty : 0]);
                }
            }

            $transaction->delete();
        });
    }

    private function mutasiStok(int $barangId, string $jenis, float $qty, int|string|null $gudangId = null, float $unitCost = 0): array
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
            $oldQty = (float) $stok->qty;
            $oldValue = (float) $stok->inventory_value;
            $newQty = $oldQty + $qty;
            $newValue = $oldValue + ($qty * $unitCost);
            $stok->update([
                'qty' => $newQty,
                'inventory_value' => $newValue,
                'average_unit_cost' => $newQty > 0 ? $newValue / $newQty : 0,
            ]);

            return [];
        }

        $average = (float) $stok->average_unit_cost;
        $newQty = max(0, (float) $stok->qty - $qty);
        $newValue = max(0, (float) $stok->inventory_value - ($qty * $average));
        $stok->update(['qty' => $newQty, 'inventory_value' => $newValue, 'average_unit_cost' => $newQty > 0 ? $average : 0]);
        $remaining = $qty;
        $allocations = [];
        MaterialStockLot::query()->where('barang_material_id', $barangId)
            ->where('gudang_id', $gudangId ?: null)->where('qty_tersedia', '>', 0)
            ->orderBy('tanggal_terima')->orderBy('id')->lockForUpdate()->get()
            ->each(function (MaterialStockLot $lot) use (&$remaining, &$allocations): void {
                if ($remaining <= 0) {
                    return;
                }
                $taken = min($remaining, (float) $lot->qty_tersedia);
                $available = (float) $lot->qty_tersedia - $taken;
                $lot->update(['qty_tersedia' => $available, 'status' => $available > 0 ? 'tersedia' : 'habis']);
                $allocations[] = ['lot_id' => $lot->id, 'qty' => $taken, 'unit_cost' => (float) $lot->unit_cost];
                $remaining -= $taken;
            });

        return $allocations;
    }

    private function kodeTransaksi(): string
    {
        return 'LOG-'.now()->format('YmdHisv').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }
}
