<?php

namespace App\Services;

use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialPriceHistory;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseDetail;
use App\Models\MaterialPurchaseRequest;
use App\Models\MaterialRequest;
use App\Models\OperationalSetting;
use App\Models\Supplier;
use App\Models\TransaksiLogistik;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialPurchaseService
{
    public function __construct(
        private AppNotificationService $notifications,
        private AccountingService $accounting,
        private LogistikService $logistik,
        private MaterialWorkflowService $materialWorkflow,
        private MaterialUnitConversionService $unitConversions,
    ) {}

    public function createPurchase(
        array $payload,
        ?MaterialRequest $request = null,
        ?MaterialPurchaseRequest $purchaseRequest = null,
    ): MaterialPurchase {
        return DB::transaction(function () use ($payload, $request, $purchaseRequest) {
            $items = collect($payload['items'] ?? [])->filter(fn ($item) => (float) ($item['qty'] ?? 0) > 0)->values();
            $transactionDiscount = max(0, (float) ($payload['diskon_transaksi'] ?? 0));

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Minimal satu item pembelian harus diisi.']);
            }

            $supplier = ! empty($payload['supplier_id'])
                ? Supplier::query()->finalized()->find($payload['supplier_id'])
                : null;
            $gudang = $purchaseRequest?->gudang
                ?? ($request?->gudang ?? null)
                ?? (filled($payload['gudang_id'] ?? null) ? Gudang::query()->finalized()->find($payload['gudang_id']) : null);
            $purchaseCode = $payload['kode_pembelian'] ?? $this->nextPurchaseCode();

            if (MaterialPurchase::withTrashed()->where('kode_pembelian', $purchaseCode)->exists()) {
                $purchaseCode = $this->nextPurchaseCode();
            }

            $purchase = MaterialPurchase::query()->create([
                'kode_pembelian' => $purchaseCode,
                'tanggal' => $payload['tanggal'],
                'material_request_id' => $request?->id,
                'material_purchase_request_id' => $purchaseRequest?->id,
                'gudang_id' => $purchaseRequest?->gudang_id ?? $request?->gudang_id ?? $payload['gudang_id'] ?? null,
                'perumahan_id' => $gudang?->perumahan_id ?? $request?->perumahan_id ?? $payload['perumahan_id'] ?? null,
                'detail_rumah_id' => null,
                'tahapan_pembangunan_id' => null,
                'kelompok_hpp_id' => null,
                'supplier_id' => $supplier?->id,
                'supplier' => $supplier?->nama_supplier ?? $payload['supplier'] ?? null,
                'metode_pembayaran' => $payload['metode_pembayaran'] ?? 'tunai',
                'planned_master_bank_id' => $payload['planned_master_bank_id'] ?? null,
                'status' => $purchaseRequest ? MaterialPurchase::STATUS_APPROVED : MaterialPurchase::STATUS_MENUNGGU_APPROVAL,
                'keterangan' => $payload['keterangan'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $subtotal = 0;
            foreach ($items as $item) {
                $barang = BarangMaterial::query()->finalized()->findOrFail($item['barang_material_id']);
                $qty = (float) $item['qty'];
                $harga = (float) ($item['harga_satuan'] ?? $barang->harga_hpp);
                $normalized = $this->unitConversions->normalize($barang, $item['material_unit_id'] ?? null, $qty, $harga);
                $diskon = min(max(0, (float) ($item['diskon'] ?? 0)), $qty * $harga);
                $lineSubtotal = max(0, ($qty * $harga) - $diskon);
                $subtotal += $lineSubtotal;

                $purchase->details()->create([
                    'barang_material_id' => $barang->id,
                    'qty' => $qty,
                    'qty_base' => $normalized['quantity_base'],
                    'qty_diterima' => 0,
                    'qty_diterima_base' => 0,
                    'material_unit_id' => $normalized['unit_id'],
                    'satuan' => $normalized['unit_symbol'],
                    'conversion_to_base' => $normalized['factor_to_base'],
                    'harga_satuan' => $harga,
                    'harga_satuan_base' => $normalized['unit_price_base'],
                    'diskon' => $diskon,
                    'subtotal' => $lineSubtotal,
                ]);
            }

            $transactionDiscount = min($transactionDiscount, $subtotal);
            $purchase->update([
                'subtotal_nominal' => $subtotal,
                'diskon_transaksi' => $transactionDiscount,
                'total_nominal' => max(0, $subtotal - $transactionDiscount),
                'updated_by' => auth()->id(),
            ]);
            $request?->update(['status' => MaterialRequest::STATUS_DIPROSES, 'processed_by' => auth()->id(), 'processed_at' => now()]);
            $purchaseRequest?->update([
                'status' => MaterialPurchaseRequest::STATUS_DIPROSES,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            if (filter_var($payload['update_material_prices'] ?? false, FILTER_VALIDATE_BOOL)) {
                $this->syncMaterialPricesFromPurchase($purchase, $items, $transactionDiscount);
            }

            return $purchase;
        });
    }

    public function updatePurchase(MaterialPurchase $purchase, array $payload): MaterialPurchase
    {
        $purchase->loadMissing('details');

        if ($purchase->details->contains(fn (MaterialPurchaseDetail $detail) => $detail->inspection_status !== 'pending' || (float) $detail->qty_diterima > 0)) {
            throw ValidationException::withMessages([
                'purchase' => 'Pembelian yang sudah masuk pengecekan barang tidak dapat diedit.',
            ]);
        }

        return DB::transaction(function () use ($purchase, $payload) {
            $items = collect($payload['items'] ?? [])->filter(fn ($item) => (float) ($item['qty'] ?? 0) > 0)->values();
            $transactionDiscount = max(0, (float) ($payload['diskon_transaksi'] ?? 0));

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Minimal satu item pembelian harus diisi.']);
            }

            $supplier = ! empty($payload['supplier_id'])
                ? Supplier::query()->finalized()->find($payload['supplier_id'])
                : null;
            $gudang = filled($payload['gudang_id'] ?? null)
                ? Gudang::query()->finalized()->find($payload['gudang_id'])
                : $purchase->gudang;

            $purchase->update([
                'tanggal' => $payload['tanggal'],
                'gudang_id' => $payload['gudang_id'] ?? $purchase->gudang_id,
                'perumahan_id' => $gudang?->perumahan_id ?? $purchase->perumahan_id,
                'supplier_id' => $supplier?->id,
                'supplier' => $supplier?->nama_supplier ?? $payload['supplier'] ?? null,
                'metode_pembayaran' => $payload['metode_pembayaran'] ?? 'tunai',
                'planned_master_bank_id' => $payload['planned_master_bank_id'] ?? null,
                'keterangan' => $payload['keterangan'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            $purchase->details()->delete();
            $subtotal = 0;
            foreach ($items as $item) {
                $barang = BarangMaterial::query()->finalized()->findOrFail($item['barang_material_id']);
                $qty = (float) $item['qty'];
                $harga = (float) ($item['harga_satuan'] ?? $barang->harga_hpp);
                $normalized = $this->unitConversions->normalize($barang, $item['material_unit_id'] ?? null, $qty, $harga);
                $diskon = min(max(0, (float) ($item['diskon'] ?? 0)), $qty * $harga);
                $lineSubtotal = max(0, ($qty * $harga) - $diskon);
                $subtotal += $lineSubtotal;

                $purchase->details()->create([
                    'barang_material_id' => $barang->id,
                    'qty' => $qty,
                    'qty_base' => $normalized['quantity_base'],
                    'qty_diterima' => 0,
                    'qty_diterima_base' => 0,
                    'material_unit_id' => $normalized['unit_id'],
                    'satuan' => $normalized['unit_symbol'],
                    'conversion_to_base' => $normalized['factor_to_base'],
                    'harga_satuan' => $harga,
                    'harga_satuan_base' => $normalized['unit_price_base'],
                    'diskon' => $diskon,
                    'subtotal' => $lineSubtotal,
                    'inspection_status' => 'pending',
                ]);
            }

            $transactionDiscount = min($transactionDiscount, $subtotal);
            $purchase->update([
                'subtotal_nominal' => $subtotal,
                'diskon_transaksi' => $transactionDiscount,
                'total_nominal' => max(0, $subtotal - $transactionDiscount),
            ]);

            if (filter_var($payload['update_material_prices'] ?? false, FILTER_VALIDATE_BOOL)) {
                $this->syncMaterialPricesFromPurchase($purchase->fresh('details'), $items, $transactionDiscount);
            }

            return $purchase;
        });
    }

    public function approve(MaterialPurchase $purchase): void
    {
        abort_unless(
            in_array($purchase->status, [MaterialPurchase::STATUS_MENUNGGU_APPROVAL, MaterialPurchase::STATUS_MENUNGGU_MANAGER], true),
            422,
            'Pembelian tidak sedang menunggu approval.'
        );

        DB::transaction(function () use ($purchase) {
            $purchase->update([
                'status' => MaterialPurchase::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            if ($purchase->metode_pembayaran === 'hutang') {
                $this->accounting->recordSupplierBill($purchase->fresh(['perumahan']));
            }
        });

        $this->notifications->toRoles(
            ['owner', 'super_admin', 'keuangan', 'admin_keuangan'],
            'Pembelian telah disetujui',
            "Pembelian {$purchase->kode_pembelian} sudah approved dan menunggu barang masuk.",
            '/admin/pembelian-material'
        );
    }

    public function releaseFund(MaterialPurchase $purchase, int $paymentMasterBankId): void
    {
        abort_unless(in_array($purchase->status, [MaterialPurchase::STATUS_MENUNGGU_DANA, MaterialPurchase::STATUS_DANA_CAIR], true), 422, 'Pembelian belum bisa dicairkan.');

        DB::transaction(function () use ($purchase, $paymentMasterBankId) {
            $purchase->update([
                'status' => MaterialPurchase::STATUS_DANA_CAIR,
                'payment_master_bank_id' => $paymentMasterBankId,
                'fund_released_by' => auth()->id(),
                'fund_released_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            $freshPurchase = $purchase->fresh(['perumahan', 'paymentMasterBank']);

            if ($purchase->metode_pembayaran === 'hutang') {
                $this->accounting->recordSupplierPayment($freshPurchase);
            } else {
                $this->accounting->recordMaterialCashPurchase($freshPurchase);
            }
        });

        $this->notifications->toRoles(
            ['user_area_gudang', 'admin', 'keuangan', 'admin_keuangan'],
            'Dana pembelian sudah cair',
            "Pembelian {$purchase->kode_pembelian} bisa diproses dan dicek barang masuk.",
            '/admin/pembelian-material'
        );
    }

    protected function canReleaseFund(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return true;
        }

        if ($user->hasAnyRole(['owner', 'super_admin'])) {
            return true;
        }

        $managerCanRelease = OperationalSetting::query()
            ->where('key', 'manager_can_release_purchase_fund')
            ->value('value') === '1';

        return $managerCanRelease && $user->hasRole('manajer_pimpro');
    }

    protected function nextPurchaseCode(): string
    {
        $prefix = 'PB-'.now()->format('Ym').'-';
        $lastCode = MaterialPurchase::withTrashed()
            ->where('kode_pembelian', 'like', "{$prefix}%")
            ->orderByDesc('kode_pembelian')
            ->value('kode_pembelian');

        $nextNumber = $lastCode ? ((int) substr($lastCode, -4)) + 1 : 1;

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected function syncMaterialPricesFromPurchase(MaterialPurchase $purchase, $items, float $transactionDiscount): void
    {
        $purchase->loadMissing('details.barangMaterial');
        $grossTotal = (float) $items->sum(fn ($item) => (float) ($item['qty'] ?? 0) * (float) ($item['harga_satuan'] ?? 0));
        $netBeforeTxnDiscount = (float) $items->sum(function ($item): float {
            $qty = (float) ($item['qty'] ?? 0);
            $harga = (float) ($item['harga_satuan'] ?? 0);
            $diskon = min(max(0, (float) ($item['diskon'] ?? 0)), $qty * $harga);

            return max(0, ($qty * $harga) - $diskon);
        });
        $transactionDiscount = min(max(0, $transactionDiscount), $netBeforeTxnDiscount);

        foreach ($items as $item) {
            $barang = BarangMaterial::query()->lockForUpdate()->find($item['barang_material_id']);

            if (! $barang) {
                continue;
            }

            $qty = max(0.000001, (float) ($item['qty'] ?? 0));
            $harga = max(0, (float) ($item['harga_satuan'] ?? 0));
            $diskon = min(max(0, (float) ($item['diskon'] ?? 0)), $qty * $harga);
            $gross = $qty * $harga;
            $baseNet = max(0, $gross - $diskon);
            $txnShare = $grossTotal > 0 ? round(($gross / $grossTotal) * $transactionDiscount, 2) : 0;
            $effective = max(0, $baseNet - $txnShare);
            $normalized = $this->unitConversions->normalize($barang, $item['material_unit_id'] ?? null, $qty, $effective / $qty);
            $effectivePrice = round($normalized['unit_price_base'], 2);

            if (abs((float) $barang->harga_hpp - $effectivePrice) < 0.0001) {
                continue;
            }

            MaterialPriceHistory::query()->create([
                'barang_material_id' => $barang->id,
                'tanggal_berlaku' => $purchase->tanggal?->toDateString() ?? now()->toDateString(),
                'harga_satuan' => $effectivePrice,
                'supplier' => $purchase->supplier,
                'keterangan' => "Update dari pembelian {$purchase->kode_pembelian}",
                'status' => 'aktif',
                'created_by' => auth()->id(),
            ]);

            $barang->update(['harga_hpp' => $effectivePrice]);
        }
    }

    public function markPurchased(MaterialPurchase $purchase): void
    {
        abort_unless($purchase->status === MaterialPurchase::STATUS_DANA_CAIR, 422, 'Dana pembelian belum cair.');

        $purchase->update([
            'status' => MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN,
            'updated_by' => auth()->id(),
        ]);

        $this->notifications->toRole(
            'user_area_gudang',
            'Barang pembelian siap dicek',
            "Pembelian {$purchase->kode_pembelian} menunggu pengecekan barang masuk.",
            '/admin/pemeriksaan-barang-masuk'
        );
    }

    public function inspectItem(MaterialPurchase $purchase, MaterialPurchaseDetail $detail, array $payload): void
    {
        abort_unless(
            in_array($purchase->status, [
                MaterialPurchase::STATUS_APPROVED,
                MaterialPurchase::STATUS_DIBELI,
                MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN,
                'menunggu_pemeriksaan_gudang',
            ], true),
            422,
            'Pembelian ini belum approved atau tidak sedang menunggu pengecekan gudang.'
        );

        DB::transaction(function () use ($purchase, $detail, $payload) {
            $lockedDetail = MaterialPurchaseDetail::query()->lockForUpdate()->findOrFail($detail->id);

            if ($lockedDetail->inspection_status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Item ini sudah diperiksa dan tidak dapat diproses dua kali.',
                ]);
            }

            $qtyDiterima = (float) ($payload['qty_diterima'] ?? ($payload['status'] === 'sesuai' ? $lockedDetail->qty : 0));

            if ($qtyDiterima < 0 || $qtyDiterima > (float) $lockedDetail->qty) {
                throw ValidationException::withMessages([
                    'qty_diterima' => 'Qty diterima tidak boleh kurang dari 0 atau lebih besar dari qty pembelian.',
                ]);
            }

            $isAccepted = $payload['status'] === 'sesuai' && $qtyDiterima === (float) $lockedDetail->qty;
            $inspectionStatus = $isAccepted ? 'sesuai' : 'tidak_sesuai';
            $tanggalBarangMasuk = $payload['tanggal_barang_masuk'] ?? $purchase->tanggal_barang_masuk?->toDateString() ?? now()->toDateString();

            $purchase->update([
                'tanggal_barang_masuk' => $tanggalBarangMasuk,
                'status' => MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN,
                'updated_by' => auth()->id(),
            ]);

            $lockedDetail->update([
                'qty_diterima' => $qtyDiterima,
                'qty_diterima_base' => $qtyDiterima / max(1e-9, (float) $lockedDetail->conversion_to_base),
                'inspection_status' => $inspectionStatus,
                'inspection_note' => $payload['catatan'] ?? null,
                'checked_by' => auth()->id(),
                'checked_at' => now(),
            ]);

            if ($qtyDiterima > 0) {
                $this->logistik->simpanTransaksi([
                    'tanggal' => $purchase->fresh()->tanggal_barang_masuk?->toDateString() ?? $tanggalBarangMasuk,
                    'jenis' => TransaksiLogistik::JENIS_MASUK,
                    'gudang_id' => $purchase->gudang_id,
                    'perumahan_id' => null,
                    'detail_rumah_id' => null,
                    'tahapan_pembangunan_id' => null,
                    'kelompok_hpp_id' => null,
                    'keterangan' => "Penerimaan {$lockedDetail->barangMaterial?->nama_barang} dari {$purchase->kode_pembelian}",
                    'source_type' => MaterialPurchaseDetail::class,
                    'source_id' => $lockedDetail->id,
                    'items' => [[
                        'barang_material_id' => $lockedDetail->barang_material_id,
                        'qty' => $qtyDiterima,
                        'material_unit_id' => $lockedDetail->material_unit_id,
                        'satuan' => $lockedDetail->satuan,
                        'harga_satuan' => $lockedDetail->harga_satuan,
                    ]],
                ]);
            }

            $freshPurchase = $purchase->fresh('details', 'materialRequest');
            $pending = $freshPurchase->details->where('inspection_status', 'pending')->count();

            if ($pending === 0) {
                $totalOrdered = (float) $freshPurchase->details->sum('qty');
                $totalReceived = (float) $freshPurchase->details->sum('qty_diterima');
                $freshPurchase->update([
                    'status' => MaterialPurchase::STATUS_PENGECEKAN_SELESAI,
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                    'updated_by' => auth()->id(),
                ]);

                if ($freshPurchase->materialRequest && $totalReceived > 0) {
                    $this->materialWorkflow->tryIssueApprovedRequest($freshPurchase->materialRequest);
                }
            }
        });
    }

    public function receive(MaterialPurchase $purchase, array $payload): void
    {
        abort_unless(in_array($purchase->status, [MaterialPurchase::STATUS_DANA_CAIR, MaterialPurchase::STATUS_DIBELI, MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN, MaterialPurchase::STATUS_APPROVED], true), 422, 'Pembelian belum siap diterima logistik.');

        DB::transaction(function () use ($purchase, $payload) {
            $received = collect($payload['items'] ?? [])->keyBy('id');

            $items = [];

            foreach ($purchase->details as $detail) {
                $qtyDiterima = (float) ($received[$detail->id]['qty_diterima'] ?? $detail->qty);

                if ($qtyDiterima < 0 || $qtyDiterima > $detail->qty) {
                    throw ValidationException::withMessages(['items' => 'Qty diterima tidak valid.']);
                }

                $detail->update([
                    'qty_diterima' => $qtyDiterima,
                    'qty_diterima_base' => $qtyDiterima / max(1e-9, (float) $detail->conversion_to_base),
                ]);
                if ($qtyDiterima > 0) {
                    $items[] = [
                        'barang_material_id' => $detail->barang_material_id,
                        'qty' => $qtyDiterima,
                        'material_unit_id' => $detail->material_unit_id,
                        'satuan' => $detail->satuan,
                        'harga_satuan' => $detail->harga_satuan,
                    ];
                }
            }

            if ($items !== []) {
                $this->logistik->simpanTransaksi([
                    'tanggal' => $payload['tanggal_barang_masuk'] ?? $purchase->tanggal_barang_masuk?->toDateString() ?? now()->toDateString(),
                    'jenis' => TransaksiLogistik::JENIS_MASUK,
                    'gudang_id' => $purchase->gudang_id,
                    'perumahan_id' => null,
                    'detail_rumah_id' => null,
                    'tahapan_pembangunan_id' => null,
                    'kelompok_hpp_id' => null,
                    'keterangan' => "Penerimaan pembelian {$purchase->kode_pembelian}",
                    'source_type' => MaterialPurchase::class,
                    'source_id' => $purchase->id,
                    'items' => $items,
                ]);
            }

            $purchase->update([
                'tanggal_barang_masuk' => $payload['tanggal_barang_masuk'] ?? $purchase->tanggal_barang_masuk?->toDateString() ?? now()->toDateString(),
                'status' => MaterialPurchase::STATUS_PENGECEKAN_SELESAI,
                'received_by' => auth()->id(),
                'received_at' => now(),
                'receive_note' => $payload['receive_note'] ?? null,
                'updated_by' => auth()->id(),
            ]);

            if ($purchase->materialRequest) {
                $this->materialWorkflow->tryIssueApprovedRequest($purchase->materialRequest);
            }
        });
    }
}
