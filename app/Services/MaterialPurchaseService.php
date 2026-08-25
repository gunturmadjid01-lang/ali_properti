<?php

namespace App\Services;

use App\Models\BarangMaterial;
use App\Models\Gudang;
use App\Models\MaterialPriceHistory;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseDetail;
use App\Models\MaterialPurchaseRequest;
use App\Models\MaterialPurchaseShipment;
use App\Models\MaterialPurchaseCostLine;
use App\Models\MaterialRequest;
use App\Models\MaterialStockLot;
use App\Models\MaterialSupplierClaim;
use App\Models\MaterialSupplierInvoice;
use App\Models\Journal;
use App\Models\OperationalSetting;
use App\Models\StokMaterial;
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
                ...$this->deliveryData($payload),
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
                'total_landed_cost' => max(0, $subtotal - $transactionDiscount) + $this->acquisitionCosts($payload),
                'updated_by' => auth()->id(),
            ]);
            $this->allocateAcquisitionCosts($purchase->fresh('details'));
            $this->syncShipmentAndCosts($purchase, $payload);
            $request?->update(['status' => MaterialRequest::STATUS_DIPROSES, 'processed_by' => auth()->id(), 'processed_at' => now()]);
            $purchaseRequest?->update([
                'status' => MaterialPurchaseRequest::STATUS_DIPROSES,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'updated_by' => auth()->id(),
            ]);

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
                ...$this->deliveryData($payload),
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
                'total_landed_cost' => max(0, $subtotal - $transactionDiscount) + $this->acquisitionCosts($payload),
            ]);
            $this->allocateAcquisitionCosts($purchase->fresh('details'));
            $this->syncShipmentAndCosts($purchase, $payload);

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

            $this->syncSupplierInvoice($purchase->fresh('details'));
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
                abort_unless($freshPurchase->supplierInvoice?->status === 'reconciled', 422, 'Tagihan supplier belum dapat dibayar sebelum pemeriksaan faktur dan fisik selesai.');
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

    private function deliveryData(array $payload): array
    {
        return [
            'nomor_faktur' => $payload['nomor_faktur'] ?? null,
            'tanggal_faktur' => $payload['tanggal_faktur'] ?? null,
            'nomor_surat_jalan' => $payload['nomor_surat_jalan'] ?? null,
            'nama_ekspedisi' => $payload['nama_ekspedisi'] ?? null,
            'nomor_kendaraan' => $payload['nomor_kendaraan'] ?? null,
            'biaya_ekspedisi' => max(0, (float) ($payload['biaya_ekspedisi'] ?? 0)),
            'upah_buruh_logistik' => max(0, (float) ($payload['upah_buruh_logistik'] ?? 0)),
            'biaya_lain_perolehan' => max(0, (float) ($payload['biaya_lain_perolehan'] ?? 0)),
            'metode_alokasi_biaya' => $payload['metode_alokasi_biaya'] ?? 'nilai',
        ];
    }

    private function acquisitionCosts(array $payload): float
    {
        return max(0, (float) ($payload['biaya_ekspedisi'] ?? 0))
            + max(0, (float) ($payload['upah_buruh_logistik'] ?? 0))
            + max(0, (float) ($payload['biaya_lain_perolehan'] ?? 0));
    }

    private function syncShipmentAndCosts(MaterialPurchase $purchase, array $payload): void
    {
        $shipment = MaterialPurchaseShipment::query()->updateOrCreate(
            ['material_purchase_id' => $purchase->id, 'shipment_no' => $purchase->kode_pembelian.'-KRM-01'],
            [
                'delivery_note_no' => $payload['nomor_surat_jalan'] ?? null,
                'expedition_provider' => $payload['nama_ekspedisi'] ?? null,
                'vehicle_no' => $payload['nomor_kendaraan'] ?? null,
                'freight_cost' => max(0, (float) ($payload['biaya_ekspedisi'] ?? 0)),
                'logistics_labor_cost' => max(0, (float) ($payload['upah_buruh_logistik'] ?? 0)),
                'other_cost' => max(0, (float) ($payload['biaya_lain_perolehan'] ?? 0)),
                'status' => 'direncanakan',
            ],
        );
        MaterialPurchaseCostLine::query()->where('material_purchase_id', $purchase->id)->delete();
        foreach ([
            'ekspedisi' => (float) ($payload['biaya_ekspedisi'] ?? 0),
            'buruh_logistik' => (float) ($payload['upah_buruh_logistik'] ?? 0),
            'lainnya' => (float) ($payload['biaya_lain_perolehan'] ?? 0),
        ] as $type => $amount) {
            if ($amount <= 0) {
                continue;
            }
            MaterialPurchaseCostLine::query()->create([
                'material_purchase_id' => $purchase->id,
                'material_purchase_shipment_id' => $shipment->id,
                'cost_type' => $type,
                'payee' => $type === 'ekspedisi' ? ($payload['nama_ekspedisi'] ?? null) : null,
                'amount' => $amount,
                'description' => $type === 'buruh_logistik' ? 'Upah muat, bongkar, atau angkut lokal' : null,
            ]);
        }
    }

    private function allocateAcquisitionCosts(MaterialPurchase $purchase): void
    {
        $purchase->loadMissing('details');
        $basisTotal = $purchase->metode_alokasi_biaya === 'kuantitas'
            ? (float) $purchase->details->sum('qty_base')
            : (float) $purchase->details->sum('subtotal');
        $basisTotal = max($basisTotal, 1e-9);

        foreach ($purchase->details as $detail) {
            $basis = $purchase->metode_alokasi_biaya === 'kuantitas' ? (float) $detail->qty_base : (float) $detail->subtotal;
            $ratio = $basis / $basisTotal;
            $transactionDiscount = (float) $purchase->diskon_transaksi * $ratio;
            $freight = (float) $purchase->biaya_ekspedisi * $ratio;
            $labor = (float) $purchase->upah_buruh_logistik * $ratio;
            $other = (float) $purchase->biaya_lain_perolehan * $ratio;
            $landedTotal = max(0, (float) $detail->subtotal - $transactionDiscount) + $freight + $labor + $other;
            $detail->update([
                'biaya_ekspedisi_alokasi' => $freight,
                'upah_buruh_alokasi' => $labor,
                'biaya_lain_alokasi' => $other,
                'landed_cost_total' => $landedTotal,
                'landed_unit_cost' => $landedTotal / max((float) $detail->qty_base, 1e-9),
            ]);
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

            $qtyFaktur = (float) ($payload['qty_faktur'] ?? $lockedDetail->qty);
            $qtyFisik = (float) ($payload['qty_fisik_tiba'] ?? $qtyFaktur);
            $qtyDiterima = (float) ($payload['qty_diterima'] ?? ($payload['status'] === 'sesuai' ? $qtyFisik : 0));
            $qtyCacat = (float) ($payload['qty_cacat'] ?? 0);
            $qtyDitolak = (float) ($payload['qty_ditolak'] ?? max(0, $qtyFisik - $qtyDiterima - $qtyCacat));
            $invoiceUnitPrice = (float) ($payload['invoice_unit_price'] ?? $lockedDetail->harga_satuan);

            if ($qtyFaktur < 0 || $qtyFisik < 0 || min($qtyDiterima, $qtyCacat, $qtyDitolak) < 0 || ($qtyDiterima + $qtyCacat + $qtyDitolak) > $qtyFisik + 0.0001) {
                throw ValidationException::withMessages([
                    'qty_diterima' => 'Rincian fisik tidak valid: total diterima, cacat, dan ditolak tidak boleh melebihi fisik tiba.',
                ]);
            }

            $isAccepted = $payload['status'] === 'sesuai' && abs($qtyDiterima - (float) $lockedDetail->qty) < 0.0001 && $qtyCacat <= 0;
            $inspectionStatus = $isAccepted ? 'sesuai' : 'tidak_sesuai';
            $tanggalBarangMasuk = $payload['tanggal_barang_masuk'] ?? $purchase->tanggal_barang_masuk?->toDateString() ?? now()->toDateString();
            $priceVariance = $invoiceUnitPrice - (float) $lockedDetail->harga_satuan;
            $priceVariancePercent = (float) $lockedDetail->harga_satuan > 0 ? ($priceVariance / (float) $lockedDetail->harga_satuan) * 100 : 0;
            $varianceLimit = (float) (OperationalSetting::query()->where('key', 'material_price_variance_limit_percent')->value('value') ?? 10);
            $acceptedBase = $qtyDiterima / max(1e-9, (float) $lockedDetail->conversion_to_base);
            $actualMaterialCost = max(0, ($qtyDiterima * $invoiceUnitPrice) - ((float) $lockedDetail->diskon * ($qtyDiterima / max((float) $lockedDetail->qty, 1e-9))));
            $actualLandedTotal = $actualMaterialCost + (float) $lockedDetail->biaya_ekspedisi_alokasi + (float) $lockedDetail->upah_buruh_alokasi + (float) $lockedDetail->biaya_lain_alokasi;

            $purchase->update([
                'tanggal_barang_masuk' => $tanggalBarangMasuk,
                'status' => MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN,
                'updated_by' => auth()->id(),
            ]);

            $lockedDetail->update([
                'qty_diterima' => $qtyDiterima,
                'qty_diterima_base' => $qtyDiterima / max(1e-9, (float) $lockedDetail->conversion_to_base),
                'invoice_unit_price' => $invoiceUnitPrice,
                'price_variance' => $priceVariance,
                'price_variance_percent' => $priceVariancePercent,
                'price_variance_requires_approval' => abs($priceVariancePercent) > $varianceLimit,
                'qty_faktur' => $qtyFaktur,
                'qty_fisik_tiba' => $qtyFisik,
                'qty_diterima_baik' => $qtyDiterima,
                'qty_cacat' => $qtyCacat,
                'qty_ditolak' => $qtyDitolak,
                'qty_kurang' => max(0, (float) $lockedDetail->qty - $qtyFisik),
                'qty_lebih' => max(0, $qtyFisik - (float) $lockedDetail->qty),
                'kondisi_fisik' => $payload['kondisi_fisik'] ?? ($qtyCacat > 0 ? 'cacat' : 'baik'),
                'status_selisih' => abs($qtyFaktur - (float) $lockedDetail->qty) < 0.0001 && abs($qtyFisik - $qtyFaktur) < 0.0001 ? 'sesuai' : 'selisih',
                'inspection_status' => $inspectionStatus,
                'inspection_note' => $payload['catatan'] ?? null,
                'alasan_selisih' => $payload['alasan_selisih'] ?? null,
                'landed_cost_total' => $actualLandedTotal,
                'landed_unit_cost' => $acceptedBase > 0 ? $actualLandedTotal / $acceptedBase : 0,
                'checked_by' => auth()->id(),
                'checked_at' => now(),
            ]);

            foreach ([
                'kekurangan' => max(0, (float) $lockedDetail->qty - $qtyFisik),
                'cacat' => $qtyCacat,
                'ditolak' => $qtyDitolak,
            ] as $claimType => $claimQty) {
                if ($claimQty <= 0) {
                    continue;
                }
                MaterialSupplierClaim::query()->create([
                    'material_purchase_id' => $purchase->id,
                    'material_purchase_detail_id' => $lockedDetail->id,
                    'claim_no' => 'CLM-'.now()->format('YmdHisv').'-'.$lockedDetail->id.'-'.$claimType,
                    'claim_type' => $claimType,
                    'qty' => $claimQty,
                    'amount' => $claimQty * $invoiceUnitPrice,
                    'notes' => $payload['alasan_selisih'] ?? $payload['catatan'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }

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
                        'harga_satuan' => (float) $lockedDetail->fresh()->landed_unit_cost * max(1e-9, (float) $lockedDetail->conversion_to_base),
                    ]],
                ]);

                $qtyBase = (float) $lockedDetail->fresh()->qty_diterima_base;
                $unitCost = (float) $lockedDetail->landed_unit_cost;
                MaterialStockLot::query()->create([
                    'material_purchase_id' => $purchase->id,
                    'material_purchase_detail_id' => $lockedDetail->id,
                    'barang_material_id' => $lockedDetail->barang_material_id,
                    'gudang_id' => $purchase->gudang_id,
                    'kode_lot' => $purchase->kode_pembelian.'-'.$lockedDetail->id,
                    'tanggal_terima' => $tanggalBarangMasuk,
                    'qty_diterima' => $qtyBase,
                    'qty_tersedia' => $qtyBase,
                    'unit_cost' => $unitCost,
                    'total_cost' => $qtyBase * $unitCost,
                    'kondisi' => $payload['kondisi_fisik'] ?? 'baik',
                ]);
                $stock = StokMaterial::query()->where('barang_material_id', $lockedDetail->barang_material_id)
                    ->where('gudang_id', $purchase->gudang_id)->whereNull('cabang_id')->lockForUpdate()->firstOrFail();
                $average = (float) $stock->average_unit_cost;
                $barang = $lockedDetail->barangMaterial()->lockForUpdate()->firstOrFail();
                if (abs((float) $barang->harga_hpp - $average) > 0.0001) {
                    MaterialPriceHistory::query()->create([
                        'barang_material_id' => $barang->id,
                        'tanggal_berlaku' => $tanggalBarangMasuk,
                        'harga_satuan' => $average,
                        'supplier' => $purchase->supplier,
                        'keterangan' => "Rata-rata bergerak setelah penerimaan {$purchase->kode_pembelian}; termasuk ekspedisi dan buruh logistik",
                        'status' => 'aktif',
                        'created_by' => auth()->id(),
                    ]);
                    $barang->update(['harga_hpp' => $average]);
                }
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
                MaterialPurchaseShipment::query()->where('material_purchase_id', $freshPurchase->id)->update([
                    'arrived_at' => now(),
                    'status' => 'diterima_dan_diperiksa',
                ]);

                $invoice = $this->syncSupplierInvoice($freshPurchase->fresh(['details', 'supplierClaims']));
                if ($freshPurchase->metode_pembayaran === 'hutang' && $invoice->payable_amount > 0) {
                    $this->accounting->recordSupplierBill($freshPurchase->fresh(['perumahan', 'supplierInvoice']));
                }

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

    public function reverseForUnlock(MaterialPurchase $purchase): MaterialPurchase
    {
        return DB::transaction(function () use ($purchase): MaterialPurchase {
            $purchase = MaterialPurchase::query()->with(['details', 'materialPurchaseRequest'])->lockForUpdate()->findOrFail($purchase->id);
            if ($purchase->fund_released_at) {
                throw ValidationException::withMessages(['unlock' => 'Pembelian tidak dapat di-unlock karena dana atau pembayaran supplier sudah diproses. Lakukan reversal pembayaran terlebih dahulu.']);
            }
            if (MaterialSupplierClaim::query()->where('material_purchase_id', $purchase->id)->whereNotIn('status', ['diajukan', 'draft'])->exists()) {
                throw ValidationException::withMessages(['unlock' => 'Pembelian tidak dapat di-unlock karena sudah memiliki klaim supplier yang diproses.']);
            }

            foreach ($purchase->details as $detail) {
                $lot = MaterialStockLot::query()->where('material_purchase_detail_id', $detail->id)->lockForUpdate()->first();
                if ($lot && (float) $lot->qty_tersedia + 0.0001 < (float) $lot->qty_diterima) {
                    throw ValidationException::withMessages(['unlock' => "Pembelian tidak dapat di-unlock karena lot {$lot->kode_lot} sudah dipakai."]);
                }
            }

            $detailIds = $purchase->details->pluck('id');
            TransaksiLogistik::query()->where(function ($query) use ($purchase, $detailIds): void {
                $query->where(fn ($q) => $q->where('source_type', MaterialPurchase::class)->where('source_id', $purchase->id))
                    ->orWhere(fn ($q) => $q->where('source_type', MaterialPurchaseDetail::class)->whereIn('source_id', $detailIds));
            })->lockForUpdate()->get()->each(fn (TransaksiLogistik $transaction) => $this->logistik->reverseTransaction($transaction));

            MaterialStockLot::query()->where('material_purchase_id', $purchase->id)->delete();
            MaterialSupplierClaim::query()->where('material_purchase_id', $purchase->id)->delete();
            MaterialSupplierInvoice::query()->where('material_purchase_id', $purchase->id)->delete();
            Journal::query()->where('source_type', MaterialPurchase::class)->where('source_id', $purchase->id)->get()->each(function (Journal $journal): void {
                $journal->details()->delete();
                $journal->delete();
            });
            MaterialPriceHistory::query()->where('keterangan', 'like', '%'.$purchase->kode_pembelian.'%')->delete();

            foreach ($purchase->details as $detail) {
                $stock = StokMaterial::query()->where('barang_material_id', $detail->barang_material_id)->where('gudang_id', $purchase->gudang_id)->whereNull('cabang_id')->first();
                if ($stock) {
                    $detail->barangMaterial()->update(['harga_hpp' => (float) $stock->average_unit_cost]);
                }
                $detail->update([
                    'qty_faktur' => null, 'qty_fisik_tiba' => null, 'qty_diterima' => 0, 'qty_diterima_base' => 0,
                    'qty_diterima_baik' => 0, 'qty_cacat' => 0, 'qty_ditolak' => 0, 'qty_kurang' => 0, 'qty_lebih' => 0,
                    'invoice_unit_price' => null, 'price_variance' => 0, 'price_variance_percent' => 0,
                    'price_variance_requires_approval' => false, 'inspection_status' => 'pending', 'kondisi_fisik' => null,
                    'status_selisih' => 'belum_diperiksa', 'inspection_note' => null, 'alasan_selisih' => null,
                    'checked_by' => null, 'checked_at' => null,
                ]);
            }
            $purchase->materialPurchaseRequest?->update(['status' => MaterialPurchaseRequest::STATUS_DISETUJUI, 'processed_by' => null, 'processed_at' => null]);
            $purchase->update([
                'status' => MaterialPurchase::STATUS_MENUNGGU_APPROVAL, 'tanggal_barang_masuk' => null,
                'approved_by' => null, 'approved_at' => null, 'received_by' => null, 'received_at' => null, 'receive_note' => null,
            ]);

            return $purchase->fresh();
        });
    }

    public function syncSupplierInvoice(MaterialPurchase $purchase): MaterialSupplierInvoice
    {
        $purchase->loadMissing(['details', 'supplierClaims']);
        $allInspected = $purchase->details->isNotEmpty() && $purchase->details->every(fn (MaterialPurchaseDetail $detail) => $detail->inspection_status !== 'pending');
        $gross = (float) $purchase->details->sum(function (MaterialPurchaseDetail $detail): float {
            $qty = $detail->qty_faktur === null ? (float) $detail->qty : (float) $detail->qty_faktur;
            $price = $detail->invoice_unit_price === null ? (float) $detail->harga_satuan : (float) $detail->invoice_unit_price;
            $discount = min((float) $detail->diskon, $qty * $price);

            return max(0, ($qty * $price) - $discount);
        });
        $claim = (float) $purchase->supplierClaims->whereIn('status', ['draft', 'diajukan'])->sum('amount');
        $accepted = (float) $purchase->details->sum(fn (MaterialPurchaseDetail $detail): float => (float) $detail->qty_diterima * (float) ($detail->invoice_unit_price ?? $detail->harga_satuan));
        $payable = max(0, $gross - $claim);

        return MaterialSupplierInvoice::query()->updateOrCreate(
            ['material_purchase_id' => $purchase->id],
            [
                'supplier_id' => $purchase->supplier_id,
                'invoice_no' => $purchase->nomor_faktur,
                'invoice_date' => $purchase->tanggal_faktur,
                'gross_amount' => $gross,
                'accepted_amount' => $accepted,
                'claim_amount' => $claim,
                'payable_amount' => $payable,
                'outstanding_amount' => max(0, $payable - (float) ($purchase->supplierInvoice?->paid_amount ?? 0)),
                'status' => $allInspected ? 'reconciled' : 'pending_inspection',
            ],
        );
    }
}
