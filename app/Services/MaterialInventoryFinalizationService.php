<?php

namespace App\Services;

use App\Models\MaterialOpeningBalance;
use App\Models\MaterialStockLot;
use App\Models\MaterialStockOpname;
use App\Models\StokMaterial;
use App\Models\TransaksiLogistik;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialInventoryFinalizationService
{
    public function __construct(private readonly LogistikService $logistik) {}

    public function postOpeningBalance(MaterialOpeningBalance $balance): void
    {
        DB::transaction(function () use ($balance): void {
            $balance = MaterialOpeningBalance::query()->lockForUpdate()->findOrFail($balance->id);
            if ($balance->stock_posted_at || (float) $balance->qty <= 0) {
                return;
            }
            $stock = StokMaterial::query()->firstOrCreate(
                ['gudang_id' => $balance->gudang_id, 'barang_material_id' => $balance->barang_material_id, 'cabang_id' => null],
                ['qty' => 0, 'average_unit_cost' => 0, 'inventory_value' => 0],
            );
            $qty = (float) $balance->qty;
            $value = $qty * (float) $balance->harga_satuan;
            $newQty = (float) $stock->qty + $qty;
            $newValue = (float) $stock->inventory_value + $value;
            $stock->update(['qty' => $newQty, 'inventory_value' => $newValue, 'average_unit_cost' => $newQty > 0 ? $newValue / $newQty : 0]);
            MaterialStockLot::query()->updateOrCreate(
                ['source_type' => MaterialOpeningBalance::class, 'source_id' => $balance->id],
                ['barang_material_id' => $balance->barang_material_id, 'gudang_id' => $balance->gudang_id, 'kode_lot' => 'OPEN-'.$balance->id,
                    'tanggal_terima' => $balance->tanggal_saldo, 'qty_diterima' => $qty, 'qty_tersedia' => $qty,
                    'unit_cost' => (float) $balance->harga_satuan, 'total_cost' => $value, 'kondisi' => 'baik', 'status' => 'tersedia'],
            );
            $balance->update(['stock_posted_at' => now()]);
            $balance->barangMaterial()->update(['harga_hpp' => $newQty > 0 ? $newValue / $newQty : 0]);
        });
    }

    public function reverseOpeningBalance(MaterialOpeningBalance $balance): void
    {
        DB::transaction(function () use ($balance): void {
            $balance = MaterialOpeningBalance::query()->lockForUpdate()->findOrFail($balance->id);
            if (! $balance->stock_posted_at) {
                return;
            }
            $lot = MaterialStockLot::query()->where(['source_type' => MaterialOpeningBalance::class, 'source_id' => $balance->id])->lockForUpdate()->first();
            if ($lot && (float) $lot->qty_tersedia + 0.0001 < (float) $lot->qty_diterima) {
                throw ValidationException::withMessages(['unlock' => 'Saldo awal tidak dapat di-unlock karena sebagian lot sudah dipakai.']);
            }
            $stock = StokMaterial::query()->where(['gudang_id' => $balance->gudang_id, 'barang_material_id' => $balance->barang_material_id, 'cabang_id' => null])->lockForUpdate()->firstOrFail();
            if ((float) $stock->qty + 0.0001 < (float) $balance->qty) {
                throw ValidationException::withMessages(['unlock' => 'Saldo awal tidak dapat dibalik karena stoknya sudah dipakai.']);
            }
            $newQty = (float) $stock->qty - (float) $balance->qty;
            $newValue = max(0, (float) $stock->inventory_value - (float) $balance->total_nilai);
            $average = $newQty > 0 ? $newValue / $newQty : 0;
            $stock->update(['qty' => $newQty, 'inventory_value' => $newValue, 'average_unit_cost' => $average]);
            $lot?->delete();
            $balance->update(['stock_posted_at' => null]);
            $balance->barangMaterial()->update(['harga_hpp' => $average]);
        });
    }

    public function postStockOpname(MaterialStockOpname $opname): void
    {
        DB::transaction(function () use ($opname): void {
            $opname = MaterialStockOpname::query()->with('details.barangMaterial')->lockForUpdate()->findOrFail($opname->id);
            if ($opname->stock_posted_at) {
                return;
            }
            foreach ([TransaksiLogistik::JENIS_MASUK, TransaksiLogistik::JENIS_KELUAR] as $type) {
                $items = $opname->details->filter(fn ($detail) => $type === TransaksiLogistik::JENIS_MASUK ? (float) $detail->masuk > 0 : (float) $detail->keluar > 0)
                    ->map(fn ($detail) => ['barang_material_id' => $detail->barang_material_id, 'qty' => $type === TransaksiLogistik::JENIS_MASUK ? $detail->masuk : $detail->keluar, 'satuan' => $detail->barangMaterial?->satuan, 'harga_satuan' => $detail->unit_cost_snapshot])->values()->all();
                if ($items !== []) {
                    $this->logistik->simpanTransaksi(['tanggal' => $opname->tanggal->toDateString(), 'jenis' => $type, 'gudang_id' => $opname->gudang_id,
                        'keterangan' => 'Stock Opname '.$opname->kode_opname, 'source_type' => MaterialStockOpname::class, 'source_id' => $opname->id, 'items' => $items]);
                }
            }
            $opname->update(['stock_posted_at' => now()]);
        });
    }

    public function reverseStockOpname(MaterialStockOpname $opname): void
    {
        DB::transaction(function () use ($opname): void {
            $opname = MaterialStockOpname::query()->lockForUpdate()->findOrFail($opname->id);
            if (! $opname->stock_posted_at) {
                return;
            }
            TransaksiLogistik::query()->where(['source_type' => MaterialStockOpname::class, 'source_id' => $opname->id])->lockForUpdate()->get()
                ->each(fn (TransaksiLogistik $transaction) => $this->logistik->reverseTransaction($transaction));
            $opname->update(['stock_posted_at' => null]);
        });
    }
}
