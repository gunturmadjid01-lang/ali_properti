<?php

namespace App\Services;

use App\Models\BarangMaterial;
use App\Models\MaterialPurchase;
use App\Models\MaterialPurchaseDetail;
use App\Models\MaterialPurchaseRequest;
use App\Models\MaterialRequest;
use App\Models\OperationalSetting;
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
    )
    {
    }

    public function createPurchase(
        array $payload,
        ?MaterialRequest $request = null,
        ?MaterialPurchaseRequest $purchaseRequest = null,
    ): MaterialPurchase
    {
        return DB::transaction(function () use ($payload, $request, $purchaseRequest) {
            if ($purchaseRequest && $purchaseRequest->status !== MaterialPurchaseRequest::STATUS_DIAJUKAN) {
                throw ValidationException::withMessages([
                    'material_purchase_request_id' => 'Permintaan pembelian ini sudah diproses.',
                ]);
            }

            $items = collect($payload['items'] ?? [])->filter(fn ($item) => (float) ($item['qty'] ?? 0) > 0)->values();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Minimal satu item pembelian harus diisi.']);
            }

            $purchase = MaterialPurchase::query()->create([
                'kode_pembelian' => 'PB-'.now()->format('YmdHis'),
                'tanggal' => $payload['tanggal'],
                'material_request_id' => $request?->id,
                'material_purchase_request_id' => $purchaseRequest?->id,
                'gudang_id' => $purchaseRequest?->gudang_id ?? $request?->gudang_id ?? $payload['gudang_id'] ?? null,
                'perumahan_id' => null,
                'detail_rumah_id' => null,
                'tahapan_pembangunan_id' => null,
                'kelompok_hpp_id' => null,
                'supplier' => $payload['supplier'] ?? null,
                'metode_pembayaran' => $payload['metode_pembayaran'] ?? 'tunai',
                'planned_master_bank_id' => $payload['planned_master_bank_id'] ?? null,
                'status' => MaterialPurchase::STATUS_MENUNGGU_MANAGER,
                'keterangan' => $payload['keterangan'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $total = 0;
            foreach ($items as $item) {
                $barang = BarangMaterial::query()->findOrFail($item['barang_material_id']);
                $qty = (float) $item['qty'];
                $harga = (float) ($item['harga_satuan'] ?? $barang->harga_hpp);
                $subtotal = $qty * $harga;
                $total += $subtotal;

                $purchase->details()->create([
                    'barang_material_id' => $barang->id,
                    'qty' => $qty,
                    'qty_diterima' => 0,
                    'satuan' => $item['satuan'] ?? $barang->satuan,
                    'harga_satuan' => $harga,
                    'subtotal' => $subtotal,
                ]);
            }

            $purchase->update(['total_nominal' => $total, 'updated_by' => auth()->id()]);
            $request?->update(['status' => MaterialRequest::STATUS_DIPROSES, 'processed_by' => auth()->id(), 'processed_at' => now()]);
            $purchaseRequest?->update([
                'status' => MaterialPurchaseRequest::STATUS_DIPROSES,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            $this->notifications->toRoles(
                ['manajer_pimpro', 'owner', 'super_admin'],
                'Pembelian barang menunggu approval',
                "Pembelian {$purchase->kode_pembelian} membutuhkan approval manager.",
                '/admin/pembelian-material'
            );

            return $purchase;
        });
    }

    public function approve(MaterialPurchase $purchase): void
    {
        abort_unless($purchase->status === MaterialPurchase::STATUS_MENUNGGU_MANAGER, 422, 'Pembelian tidak sedang menunggu approval manager.');

        DB::transaction(function () use ($purchase) {
            $purchase->update([
                'status' => MaterialPurchase::STATUS_MENUNGGU_DANA,
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
            "Pembelian {$purchase->kode_pembelian} menunggu pencairan dana.",
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
                MaterialPurchase::STATUS_DIBELI,
                MaterialPurchase::STATUS_MENUNGGU_PEMERIKSAAN,
            ], true),
            422,
            'Pembelian ini tidak sedang menunggu pemeriksaan gudang.'
        );

        DB::transaction(function () use ($purchase, $detail, $payload) {
            $lockedDetail = MaterialPurchaseDetail::query()->lockForUpdate()->findOrFail($detail->id);

            if ($lockedDetail->inspection_status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Item ini sudah diperiksa dan tidak dapat diproses dua kali.',
                ]);
            }

            $isAccepted = $payload['status'] === 'sesuai';
            $lockedDetail->update([
                'qty_diterima' => $isAccepted ? $lockedDetail->qty : 0,
                'inspection_status' => $payload['status'],
                'inspection_note' => $payload['catatan'] ?? null,
                'checked_by' => auth()->id(),
                'checked_at' => now(),
            ]);

            if ($isAccepted) {
                $this->logistik->simpanTransaksi([
                    'tanggal' => now()->toDateString(),
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
                        'qty' => $lockedDetail->qty,
                        'satuan' => $lockedDetail->satuan,
                        'harga_satuan' => $lockedDetail->harga_satuan,
                    ]],
                ]);
            }

            $freshPurchase = $purchase->fresh('details', 'materialPurchaseRequest', 'materialRequest');
            $pending = $freshPurchase->details->where('inspection_status', 'pending')->count();
            $accepted = $freshPurchase->details->where('inspection_status', 'sesuai')->count();

            if ($pending === 0) {
                $status = $accepted === $freshPurchase->details->count()
                    ? MaterialPurchase::STATUS_DITERIMA
                    : ($accepted > 0 ? MaterialPurchase::STATUS_DITERIMA_SEBAGIAN : MaterialPurchase::STATUS_DITOLAK_GUDANG);

                $freshPurchase->update([
                    'status' => $status,
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                    'updated_by' => auth()->id(),
                ]);

                if ($freshPurchase->materialPurchaseRequest) {
                    $freshPurchase->materialPurchaseRequest->update([
                        'status' => $accepted > 0
                            ? ($accepted === $freshPurchase->details->count()
                                ? MaterialPurchaseRequest::STATUS_SELESAI
                                : MaterialPurchaseRequest::STATUS_SELESAI_SEBAGIAN)
                            : MaterialPurchaseRequest::STATUS_DITOLAK,
                        'updated_by' => auth()->id(),
                    ]);
                }

                if ($freshPurchase->materialRequest && $accepted > 0) {
                    $this->materialWorkflow->tryIssueApprovedRequest($freshPurchase->materialRequest);
                }
            }
        });
    }

    public function receive(MaterialPurchase $purchase, array $payload): void
    {
        abort_unless(in_array($purchase->status, [MaterialPurchase::STATUS_DANA_CAIR, MaterialPurchase::STATUS_DIBELI], true), 422, 'Pembelian belum siap diterima logistik.');

        DB::transaction(function () use ($purchase, $payload) {
            $received = collect($payload['items'] ?? [])->keyBy('id');

            $items = [];

            foreach ($purchase->details as $detail) {
                $qtyDiterima = (float) ($received[$detail->id]['qty_diterima'] ?? $detail->qty);

                if ($qtyDiterima < 0 || $qtyDiterima > $detail->qty) {
                    throw ValidationException::withMessages(['items' => 'Qty diterima tidak valid.']);
                }

                $detail->update(['qty_diterima' => $qtyDiterima]);
                if ($qtyDiterima > 0) {
                    $items[] = [
                        'barang_material_id' => $detail->barang_material_id,
                        'qty' => $qtyDiterima,
                        'satuan' => $detail->satuan,
                        'harga_satuan' => $detail->harga_satuan,
                    ];
                }
            }

            if ($items !== []) {
                $this->logistik->simpanTransaksi([
                    'tanggal' => now()->toDateString(),
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
                'status' => MaterialPurchase::STATUS_DITERIMA,
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
