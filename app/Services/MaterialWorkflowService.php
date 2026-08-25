<?php

namespace App\Services;

use App\Models\BarangMaterial;
use App\Models\MaterialRequest;
use App\Models\MaterialConditionStock;
use App\Models\MaterialReturn;
use App\Models\MaterialUsage;
use App\Models\MaterialRequestDetail;
use App\Models\SiteMaterialStock;
use App\Models\StokMaterial;
use App\Models\TransaksiLogistik;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaterialWorkflowService
{
    public function __construct(
        private readonly LogistikService $logistik,
        private readonly AppNotificationService $notifications,
        private readonly MaterialUnitConversionService $unitConversions,
    ) {
    }

    public function approveGudang(MaterialRequest $materialRequest, ?string $note = null): MaterialRequest
    {
        return DB::transaction(function () use ($materialRequest, $note) {
            $request = MaterialRequest::query()->with(['details.barangMaterial'])->lockForUpdate()->findOrFail($materialRequest->id);

            if (! $request->approved_at_gudang) {
                $request->update([
                    'approved_by_gudang' => auth()->id(),
                    'approved_at_gudang' => now(),
                    'approval_note' => $note,
                    'status' => $request->approved_at_owner
                        ? MaterialRequest::STATUS_DIPROSES
                        : MaterialRequest::STATUS_MENUNGGU_OWNER,
                ]);
            }

            try {
                return $this->issueWhenComplete($request);
            } catch (ValidationException) {
                $request->update(['status' => MaterialRequest::STATUS_MENUNGGU_STOK]);

                return $request->fresh();
            }
        });
    }

    public function approveOwner(MaterialRequest $materialRequest, ?string $note = null): MaterialRequest
    {
        return DB::transaction(function () use ($materialRequest, $note) {
            $request = MaterialRequest::query()->with(['details.barangMaterial'])->lockForUpdate()->findOrFail($materialRequest->id);

            if (! $request->approved_at_owner) {
                $request->update([
                    'approved_by_owner' => auth()->id(),
                    'approved_at_owner' => now(),
                    'owner_approval_note' => $note,
                    'status' => $request->approved_at_gudang
                        ? MaterialRequest::STATUS_DIPROSES
                        : MaterialRequest::STATUS_DIAJUKAN,
                ]);
            }

            try {
                return $this->issueWhenComplete($request);
            } catch (ValidationException) {
                $request->update(['status' => MaterialRequest::STATUS_MENUNGGU_STOK]);

                return $request->fresh();
            }
        });
    }

    public function recordUsage(array $payload, ?UploadedFile $photo = null): MaterialUsage
    {
        return DB::transaction(function () use ($payload, $photo) {
            $usage = MaterialUsage::query()->create([
                'kode_pemakaian' => $this->code('PAKAI'),
                'tanggal' => $payload['tanggal'],
                'perumahan_id' => $payload['perumahan_id'],
                'detail_rumah_id' => $payload['detail_rumah_id'] ?? null,
                'tahapan_pembangunan_id' => $payload['tahapan_pembangunan_id'] ?? null,
                'progress_pembangunan_id' => $payload['progress_pembangunan_id'] ?? null,
                'material_request_id' => $payload['material_request_id'] ?? null,
                'quality_upgrade_contract_id' => $payload['quality_upgrade_contract_id'] ?? null,
                'quality_upgrade_contract_item_id' => $payload['quality_upgrade_contract_item_id'] ?? null,
                'keterangan' => $payload['keterangan'] ?? null,
                'foto' => $photo?->store('pemakaian-material', 'public'),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($payload['items'] as $item) {
                $siteStock = SiteMaterialStock::query()->with('barangMaterial')->lockForUpdate()->findOrFail($item['site_material_stock_id']);
                $normalized = $this->unitConversions->normalize($siteStock->barangMaterial, $item['material_unit_id'] ?? null, (float) $item['qty']);
                $qty = $normalized['quantity_base'];

                if ($siteStock->perumahan_id !== (int) $payload['perumahan_id']) {
                    throw ValidationException::withMessages(['items' => 'Stok lokasi tidak sesuai dengan perumahan yang dipilih.']);
                }

                if (! empty($payload['detail_rumah_id']) && $siteStock->detail_rumah_id !== (int) $payload['detail_rumah_id']) {
                    throw ValidationException::withMessages(['items' => 'Stok lokasi tidak sesuai dengan unit yang dipilih.']);
                }

                if ($siteStock->qty_available < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Sisa material di lokasi tidak cukup. Tersedia {$siteStock->qty_available}.",
                    ]);
                }

                $usage->details()->create([
                    'site_material_stock_id' => $siteStock->id,
                    'barang_material_id' => $siteStock->barang_material_id,
                    'detail_rumah_hpp_item_id' => $item['detail_rumah_hpp_item_id'] ?? null,
                    'qty' => $qty,
                    'input_qty' => (float) $item['qty'],
                    'input_unit_id' => $normalized['unit_id'],
                    'conversion_to_base' => $normalized['factor_to_base'],
                    'satuan' => $normalized['unit_symbol'],
                    'unit_cost_snapshot' => (float) $siteStock->average_unit_cost,
                    'subtotal_snapshot' => $qty * (float) $siteStock->average_unit_cost,
                ]);

            }

            return $usage;
        });
    }

    public function recordUsageFromRequest(MaterialRequest $materialRequest, array $payload = [], ?UploadedFile $photo = null): MaterialUsage
    {
        return DB::transaction(function () use ($materialRequest, $payload, $photo) {
            $request = MaterialRequest::query()
                ->with(['details.barangMaterial'])
                ->lockForUpdate()
                ->findOrFail($materialRequest->id);

            if (! $request->issued_at && $request->approved_at_gudang && $request->approved_at_owner) {
                $this->issueWhenComplete($request);
                $request->refresh();
            }

            if (! $request->issued_at) {
                throw ValidationException::withMessages([
                    'material_request_ids' => "Permintaan {$request->kode_request} belum siap dipakai karena barang belum keluar dari gudang.",
                ]);
            }

            return $this->recordUsage([
                'tanggal' => $payload['tanggal'] ?? now()->toDateString(),
                'perumahan_id' => $payload['perumahan_id'] ?? $request->perumahan_id,
                'detail_rumah_id' => $payload['detail_rumah_id'] ?? $request->detail_rumah_id,
                'tahapan_pembangunan_id' => $payload['tahapan_pembangunan_id'] ?? $request->tahapan_pembangunan_id,
                'progress_pembangunan_id' => $payload['progress_pembangunan_id'] ?? null,
                'quality_upgrade_contract_id' => $payload['quality_upgrade_contract_id'] ?? null,
                'quality_upgrade_contract_item_id' => $payload['quality_upgrade_contract_item_id'] ?? null,
                'material_request_id' => $request->id,
                'keterangan' => $payload['keterangan'] ?? "Pemakaian material dari {$request->kode_request}",
                'items' => $request->details->map(fn (MaterialRequestDetail $detail) => [
                    'site_material_stock_id' => $this->resolveSiteMaterialStockId($request, $detail),
                    'qty' => (float) ($detail->qty_issued > 0 ? $detail->qty_issued : $detail->qty),
                    'satuan' => $detail->satuan,
                ])->all(),
            ], $photo);
        });
    }

    public function updateUsage(MaterialUsage $usage, array $payload, ?UploadedFile $photo = null): MaterialUsage
    {
        return DB::transaction(function () use ($usage, $payload, $photo) {
            $usage = MaterialUsage::query()->with('details.siteMaterialStock')->lockForUpdate()->findOrFail($usage->id);

            if ($usage->stock_posted_at) {
                $this->reverseUsage($usage);
                $usage->refresh();
            }

            $usage->details()->delete();

            $usage->update([
                'tanggal' => $payload['tanggal'],
                'perumahan_id' => $payload['perumahan_id'],
                'detail_rumah_id' => $payload['detail_rumah_id'] ?? null,
                'tahapan_pembangunan_id' => $payload['tahapan_pembangunan_id'] ?? null,
                'progress_pembangunan_id' => $payload['progress_pembangunan_id'] ?? null,
                'keterangan' => $payload['keterangan'] ?? null,
                'foto' => $photo?->store('pemakaian-material', 'public') ?? $usage->foto,
                'updated_by' => auth()->id(),
            ]);

            foreach ($payload['items'] as $item) {
                $siteStock = SiteMaterialStock::query()->with('barangMaterial')->lockForUpdate()->findOrFail($item['site_material_stock_id']);
                $normalized = $this->unitConversions->normalize($siteStock->barangMaterial, $item['material_unit_id'] ?? null, (float) $item['qty']);
                $qty = $normalized['quantity_base'];

                if ($siteStock->perumahan_id !== (int) $payload['perumahan_id']) {
                    throw ValidationException::withMessages(['items' => 'Stok lokasi tidak sesuai dengan perumahan yang dipilih.']);
                }

                if (! empty($payload['detail_rumah_id']) && $siteStock->detail_rumah_id !== (int) $payload['detail_rumah_id']) {
                    throw ValidationException::withMessages(['items' => 'Stok lokasi tidak sesuai dengan unit yang dipilih.']);
                }

                if ($siteStock->qty_available < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Sisa material di lokasi tidak cukup. Tersedia {$siteStock->qty_available}.",
                    ]);
                }

                $usage->details()->create([
                    'site_material_stock_id' => $siteStock->id,
                    'barang_material_id' => $siteStock->barang_material_id,
                    'detail_rumah_hpp_item_id' => $item['detail_rumah_hpp_item_id'] ?? null,
                    'qty' => $qty,
                    'input_qty' => (float) $item['qty'],
                    'input_unit_id' => $normalized['unit_id'],
                    'conversion_to_base' => $normalized['factor_to_base'],
                    'satuan' => $normalized['unit_symbol'],
                    'unit_cost_snapshot' => (float) $siteStock->average_unit_cost,
                    'subtotal_snapshot' => $qty * (float) $siteStock->average_unit_cost,
                ]);

            }

            return $usage->fresh('details');
        });
    }

    public function submitReturn(array $payload): MaterialReturn
    {
        return DB::transaction(function () use ($payload) {
            $return = MaterialReturn::query()->create([
                'kode_pengembalian' => $this->code('RET'),
                'tanggal' => $payload['tanggal'],
                'gudang_id' => $payload['gudang_id'],
                'perumahan_id' => $payload['perumahan_id'],
                'detail_rumah_id' => $payload['detail_rumah_id'] ?? null,
                'tahapan_pembangunan_id' => $payload['tahapan_pembangunan_id'] ?? null,
                'keterangan' => $payload['keterangan'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($payload['items'] as $item) {
                $siteStock = SiteMaterialStock::query()->with('barangMaterial')->lockForUpdate()->findOrFail($item['site_material_stock_id']);
                $qty = (float) $item['qty'];

                if ($siteStock->gudang_id !== (int) $payload['gudang_id']) {
                    throw ValidationException::withMessages(['items' => 'Material harus dikembalikan ke gudang asalnya.']);
                }

                if ($siteStock->qty_available < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Sisa material di lokasi tidak cukup. Tersedia {$siteStock->qty_available}.",
                    ]);
                }

                $price = (float) ($siteStock->average_unit_cost ?: $siteStock->barangMaterial?->harga_hpp ?? 0);
                $condition = $item['condition_status'] ?? 'utuh';
                $disposition = match ($condition) {
                    'utuh', 'layak_pakai' => 'normal',
                    'cacat_dapat_diperbaiki', 'cacat' => 'quarantine',
                    'hilang' => 'loss',
                    default => 'scrap',
                };
                $return->details()->create([
                    'site_material_stock_id' => $siteStock->id,
                    'barang_material_id' => $siteStock->barang_material_id,
                    'qty' => $qty,
                    'qty_normal' => $disposition === 'normal' ? $qty : 0,
                    'qty_quarantine' => $disposition === 'quarantine' ? $qty : 0,
                    'qty_scrap' => $disposition === 'scrap' ? $qty : 0,
                    'qty_lost' => $disposition === 'loss' ? $qty : 0,
                    'satuan' => $siteStock->barangMaterial?->satuan ?? '-',
                    'condition_status' => $condition,
                    'stock_disposition' => $disposition,
                    'condition_note' => $item['condition_note'] ?? null,
                    'harga_satuan' => $price,
                    'subtotal' => $qty * $price,
                ]);

                $siteStock->increment('qty_reserved_return', $qty);
                $siteStock->decrement('qty_available', $qty);
            }

            $this->notifications->toRoles(
                ['user_area_gudang', 'owner', 'super_admin'],
                'Pengembalian material baru',
                "Pengembalian {$return->kode_pengembalian} menunggu pemeriksaan gudang.",
                '/admin/pengembalian-material'
            );

            return $return;
        });
    }

    public function receiveReturn(MaterialReturn $materialReturn, ?string $note = null): MaterialReturn
    {
        return DB::transaction(function () use ($materialReturn, $note) {
            $return = MaterialReturn::query()->with(['details.barangMaterial', 'details.siteMaterialStock'])->lockForUpdate()->findOrFail($materialReturn->id);

            if ($return->received_at || $return->transaksi_logistik_id) {
                return $return;
            }

            $items = $return->details->where('qty_normal', '>', 0)->map(fn ($detail) => [
                'barang_material_id' => $detail->barang_material_id,
                'qty' => $detail->qty_normal,
                'satuan' => $detail->satuan,
                'harga_satuan' => $detail->harga_satuan,
            ])->all();

            foreach ($return->details as $detail) {
                $siteStock = SiteMaterialStock::query()->lockForUpdate()->findOrFail($detail->site_material_stock_id);

                if ($siteStock->qty_reserved_return < $detail->qty) {
                    throw ValidationException::withMessages(['items' => 'Reservasi material pengembalian tidak lagi mencukupi.']);
                }
            }

            $transaction = $items->isNotEmpty() ? $this->logistik->simpanTransaksi([
                'tanggal' => $return->tanggal->format('Y-m-d'),
                'jenis' => TransaksiLogistik::JENIS_MASUK,
                'gudang_id' => $return->gudang_id,
                'perumahan_id' => $return->perumahan_id,
                'detail_rumah_id' => $return->detail_rumah_id,
                'tahapan_pembangunan_id' => $return->tahapan_pembangunan_id,
                'kelompok_hpp_id' => $return->details->first()?->siteMaterialStock?->kelompok_hpp_id,
                'keterangan' => "Pengembalian material {$return->kode_pengembalian}",
                'source_type' => MaterialReturn::class,
                'source_id' => $return->id,
                'reverse_hpp' => true,
                'items' => $items,
            ]) : null;

            foreach ($return->details as $detail) {
                $siteStock = SiteMaterialStock::query()->lockForUpdate()->findOrFail($detail->site_material_stock_id);
                $siteStock->increment('qty_returned', $detail->qty);
                $siteStock->decrement('qty_reserved_return', $detail->qty);

                foreach (['quarantine' => $detail->qty_quarantine, 'scrap' => $detail->qty_scrap, 'loss' => $detail->qty_lost] as $bucket => $qty) {
                    if ((float) $qty <= 0) {
                        continue;
                    }
                    $conditionStock = MaterialConditionStock::query()->firstOrCreate([
                        'barang_material_id' => $detail->barang_material_id,
                        'gudang_id' => $return->gudang_id,
                        'condition_bucket' => $bucket,
                    ]);
                    $conditionStock->update([
                        'qty' => (float) $conditionStock->qty + (float) $qty,
                        'unit_cost' => (float) $detail->harga_satuan,
                        'inventory_value' => (float) $conditionStock->inventory_value + ((float) $qty * (float) $detail->harga_satuan),
                    ]);
                }
            }

            $return->update([
                'status' => MaterialReturn::STATUS_DITERIMA,
                'receive_note' => $note,
                'received_by' => auth()->id(),
                'received_at' => now(),
                'transaksi_logistik_id' => $transaction?->id,
                'updated_by' => auth()->id(),
            ]);

            return $return;
        });
    }

    public function rejectReturn(MaterialReturn $materialReturn, ?string $note = null): MaterialReturn
    {
        return DB::transaction(function () use ($materialReturn, $note) {
            $return = MaterialReturn::query()->with('details')->lockForUpdate()->findOrFail($materialReturn->id);

            if ($return->status !== MaterialReturn::STATUS_DIAJUKAN) {
                return $return;
            }

            foreach ($return->details as $detail) {
                $siteStock = SiteMaterialStock::query()->lockForUpdate()->findOrFail($detail->site_material_stock_id);
                $siteStock->decrement('qty_reserved_return', $detail->qty);
                $siteStock->increment('qty_available', $detail->qty);
            }

            $return->update([
                'status' => MaterialReturn::STATUS_DITOLAK,
                'receive_note' => $note,
                'updated_by' => auth()->id(),
            ]);

            return $return;
        });
    }

    public function reverseReturnForUnlock(MaterialReturn $materialReturn): MaterialReturn
    {
        return DB::transaction(function () use ($materialReturn): MaterialReturn {
            $return = MaterialReturn::query()->with(['details.siteMaterialStock'])->lockForUpdate()->findOrFail($materialReturn->id);
            if ($return->status === MaterialReturn::STATUS_DITERIMA) {
                $transaction = TransaksiLogistik::query()->whereKey($return->transaksi_logistik_id)->first();
                if ($transaction) {
                    $this->logistik->reverseTransaction($transaction);
                }
                foreach ($return->details as $detail) {
                    foreach (['quarantine' => $detail->qty_quarantine, 'scrap' => $detail->qty_scrap, 'loss' => $detail->qty_lost] as $bucket => $qty) {
                        if ((float) $qty <= 0) {
                            continue;
                        }
                        $condition = MaterialConditionStock::query()->where(['barang_material_id' => $detail->barang_material_id, 'gudang_id' => $return->gudang_id, 'condition_bucket' => $bucket])->lockForUpdate()->firstOrFail();
                        if ((float) $condition->qty + 0.0001 < (float) $qty) {
                            throw ValidationException::withMessages(['unlock' => "Retur tidak dapat di-unlock karena stok kondisi {$bucket} sudah ditindaklanjuti."]);
                        }
                        $condition->update(['qty' => (float) $condition->qty - (float) $qty, 'inventory_value' => max(0, (float) $condition->inventory_value - ((float) $qty * (float) $detail->harga_satuan))]);
                    }
                    $site = SiteMaterialStock::query()->lockForUpdate()->findOrFail($detail->site_material_stock_id);
                    $site->update(['qty_returned' => max(0, (float) $site->qty_returned - (float) $detail->qty), 'qty_available' => (float) $site->qty_available + (float) $detail->qty]);
                }
            } elseif ($return->status === MaterialReturn::STATUS_DIAJUKAN) {
                foreach ($return->details as $detail) {
                    $site = SiteMaterialStock::query()->lockForUpdate()->findOrFail($detail->site_material_stock_id);
                    $site->update(['qty_reserved_return' => max(0, (float) $site->qty_reserved_return - (float) $detail->qty), 'qty_available' => (float) $site->qty_available + (float) $detail->qty]);
                }
            }
            $return->update(['status' => 'draft', 'received_by' => null, 'received_at' => null, 'receive_note' => null, 'transaksi_logistik_id' => null]);

            return $return->fresh();
        });
    }

    public function reserveReturnAfterApproval(MaterialReturn $materialReturn): MaterialReturn
    {
        return DB::transaction(function () use ($materialReturn): MaterialReturn {
            $return = MaterialReturn::query()->with('details')->lockForUpdate()->findOrFail($materialReturn->id);
            if ($return->status === MaterialReturn::STATUS_DIAJUKAN) {
                return $return;
            }

            foreach ($return->details as $detail) {
                $site = SiteMaterialStock::query()->lockForUpdate()->findOrFail($detail->site_material_stock_id);
                if ((float) $site->qty_available < (float) $detail->qty) {
                    throw ValidationException::withMessages(['items' => 'Stok lokasi tidak cukup untuk mengaktifkan kembali pengembalian material.']);
                }
                $site->update([
                    'qty_reserved_return' => (float) $site->qty_reserved_return + (float) $detail->qty,
                    'qty_available' => (float) $site->qty_available - (float) $detail->qty,
                ]);
            }
            $return->update(['status' => MaterialReturn::STATUS_DIAJUKAN, 'updated_by' => auth()->id()]);

            return $return->fresh();
        });
    }

    public function tryIssueApprovedRequest(MaterialRequest $materialRequest): MaterialRequest
    {
        return DB::transaction(function () use ($materialRequest) {
            $request = MaterialRequest::query()
                ->with(['details.barangMaterial'])
                ->lockForUpdate()
                ->findOrFail($materialRequest->id);

            if ($request->issued_at) {
                return $request;
            }

            try {
                return $this->issueWhenComplete($request);
            } catch (ValidationException) {
                $request->update(['status' => MaterialRequest::STATUS_MENUNGGU_STOK]);

                return $request->fresh();
            }
        });
    }

    public function issueApprovedRequest(MaterialRequest $materialRequest): MaterialRequest
    {
        return DB::transaction(function () use ($materialRequest) {
            $request = MaterialRequest::query()
                ->with(['details.barangMaterial'])
                ->lockForUpdate()
                ->findOrFail($materialRequest->id);

            return $this->issueWhenComplete($request);
        });
    }

    public function reverseIssuedRequest(MaterialRequest $materialRequest): MaterialRequest
    {
        return DB::transaction(function () use ($materialRequest): MaterialRequest {
            $request = MaterialRequest::query()->with(['details', 'transaksiLogistik.details'])->lockForUpdate()->findOrFail($materialRequest->id);
            if ($request->transaksi_logistik_id) {
                MaterialUsage::query()->with('details.siteMaterialStock')
                    ->where('material_request_id', $request->id)
                    ->lockForUpdate()->get()
                    ->each(function (MaterialUsage $usage): void {
                        $this->reverseUsage($usage);
                        $usage->delete();
                    });

                foreach ($request->details as $detail) {
                    $siteStock = SiteMaterialStock::query()
                    ->where('gudang_id', $request->gudang_id)
                    ->where('perumahan_id', $request->perumahan_id)
                    ->where('barang_material_id', $detail->barang_material_id)
                    ->when($request->detail_rumah_id, fn ($query) => $query->where('detail_rumah_id', $request->detail_rumah_id), fn ($query) => $query->whereNull('detail_rumah_id'))
                    ->when($request->tahapan_pembangunan_id, fn ($query) => $query->where('tahapan_pembangunan_id', $request->tahapan_pembangunan_id), fn ($query) => $query->whereNull('tahapan_pembangunan_id'))
                    ->lockForUpdate()->firstOrFail();
                $issued = (float) ($detail->qty_issued ?: $detail->qty);
                if ((float) $siteStock->qty_available < $issued) {
                    throw ValidationException::withMessages(['unlock' => 'Unlock dibatalkan karena material sudah dipakai atau dikembalikan oleh transaksi lain yang tidak memiliki relasi permintaan.']);
                }
                $siteStock->update([
                    'qty_received' => max(0, (float) $siteStock->qty_received - $issued),
                    'qty_available' => (float) $siteStock->qty_available - $issued,
                ]);
                    $detail->update(['qty_issued' => 0]);
                }

                $this->logistik->reverseTransaction($request->transaksiLogistik);
            }
            $request->update([
                'status' => MaterialRequest::STATUS_DIAJUKAN,
                'approved_by_gudang' => null,
                'approved_at_gudang' => null,
                'approval_note' => null,
                'approved_by_owner' => null,
                'approved_at_owner' => null,
                'owner_approval_note' => null,
                'processed_by' => null,
                'processed_at' => null,
                'issued_by' => null,
                'issued_at' => null,
                'transaksi_logistik_id' => null,
            ]);

            return $request->fresh();
        });
    }

    public function postUsage(MaterialUsage $materialUsage): MaterialUsage
    {
        return DB::transaction(function () use ($materialUsage): MaterialUsage {
            $usage = MaterialUsage::query()->with('details.siteMaterialStock')->lockForUpdate()->findOrFail($materialUsage->id);
            if ($usage->stock_posted_at) {
                return $usage;
            }
            foreach ($usage->details as $detail) {
                $stock = SiteMaterialStock::query()->lockForUpdate()->findOrFail($detail->site_material_stock_id);
                if ((float) $stock->qty_available < (float) $detail->qty) {
                    throw ValidationException::withMessages(['items' => 'Stok lokasi tidak cukup untuk memfinalisasi pemakaian material.']);
                }
                $stock->increment('qty_used', $detail->qty);
                $stock->decrement('qty_available', $detail->qty);
            }
            $usage->update(['stock_posted_at' => now()]);

            return $usage->fresh();
        });
    }

    public function reverseUsage(MaterialUsage $materialUsage): MaterialUsage
    {
        return DB::transaction(function () use ($materialUsage): MaterialUsage {
            $usage = MaterialUsage::query()->with('details.siteMaterialStock')->lockForUpdate()->findOrFail($materialUsage->id);
            if (! $usage->stock_posted_at) {
                return $usage;
            }
            app(MaterialHppRealizationService::class)->removeForUsage($usage);
            foreach ($usage->details as $detail) {
                $detail->siteMaterialStock()->increment('qty_available', $detail->qty);
                $detail->siteMaterialStock()->decrement('qty_used', $detail->qty);
            }
            $usage->update(['stock_posted_at' => null]);

            return $usage->fresh();
        });
    }

    private function issueWhenComplete(MaterialRequest $request): MaterialRequest
    {
        if ($request->transaksi_logistik_id) {
            return $request->fresh();
        }

        if (! $request->gudang_id) {
            throw ValidationException::withMessages(['gudang_id' => 'Gudang wajib dipilih sebelum material dapat dikeluarkan.']);
        }

        $items = $request->details->map(function ($detail) use ($request) {
            $average = (float) (StokMaterial::query()->where('gudang_id', $request->gudang_id)
                ->where('barang_material_id', $detail->barang_material_id)->value('average_unit_cost') ?? 0);
            return ['barang_material_id' => $detail->barang_material_id, 'qty' => $detail->qty, 'satuan' => $detail->satuan, 'harga_satuan' => $average];
        })->all();

        $shortages = [];

        foreach ($request->details as $detail) {
            $stock = StokMaterial::query()
                ->where('gudang_id', $request->gudang_id)
                ->where('barang_material_id', $detail->barang_material_id)
                ->value('qty') ?? 0;

            if ((float) $stock < (float) $detail->qty) {
                $shortages[] = sprintf(
                    '%s kurang %s %s (tersedia %s, diminta %s)',
                    $detail->barangMaterial?->nama_barang ?? 'Material',
                    number_format(((float) $detail->qty) - ((float) $stock), 2, ',', '.'),
                    $detail->satuan,
                    number_format((float) $stock, 2, ',', '.'),
                    number_format((float) $detail->qty, 2, ',', '.')
                );
            }
        }

        if ($shortages !== []) {
            throw ValidationException::withMessages([
                'items' => 'Stok gudang belum cukup: '.implode('; ', $shortages).'.',
            ]);
        }

        $transaction = $this->logistik->simpanTransaksi([
            'tanggal' => now()->toDateString(),
            'jenis' => TransaksiLogistik::JENIS_KELUAR,
            'gudang_id' => $request->gudang_id,
            'perumahan_id' => $request->perumahan_id,
            'detail_rumah_id' => $request->detail_rumah_id,
            'tahapan_pembangunan_id' => $request->tahapan_pembangunan_id,
            'kelompok_hpp_id' => $request->kelompok_hpp_id,
            'keterangan' => "Pengeluaran otomatis dari {$request->kode_request}",
            'source_type' => MaterialRequest::class,
            'source_id' => $request->id,
            'items' => $items,
        ]);

        foreach ($request->details as $detail) {
            $issuedUnitCost = (float) (collect($items)->firstWhere('barang_material_id', $detail->barang_material_id)['harga_satuan'] ?? 0);
            $siteStock = SiteMaterialStock::query()->firstOrCreate([
                'gudang_id' => $request->gudang_id,
                'perumahan_id' => $request->perumahan_id,
                'detail_rumah_id' => $request->detail_rumah_id,
                'tahapan_pembangunan_id' => $request->tahapan_pembangunan_id,
                'kelompok_hpp_id' => $request->kelompok_hpp_id,
                'barang_material_id' => $detail->barang_material_id,
            ]);

            $oldQty = (float) $siteStock->qty_available;
            $newQty = $oldQty + (float) $detail->qty;
            $average = $newQty > 0
                ? (($oldQty * (float) $siteStock->average_unit_cost) + ((float) $detail->qty * $issuedUnitCost)) / $newQty
                : 0;
            $siteStock->update([
                'qty_received' => (float) $siteStock->qty_received + (float) $detail->qty,
                'qty_available' => $newQty,
                'average_unit_cost' => $average,
            ]);
            $detail->update(['qty_issued' => $detail->qty]);
        }

        $request->update([
                'status' => MaterialRequest::STATUS_SELESAI,
            'processed_by' => auth()->id(),
            'processed_at' => now(),
            'issued_by' => auth()->id(),
            'issued_at' => now(),
                'transaksi_logistik_id' => $transaction->id,
                'updated_by' => auth()->id(),
            ]);

        $this->notifications->toRoles(
            ['pengawas', 'user_area_gudang', 'owner', 'super_admin'],
            'Material keluar dari gudang',
            "Permintaan {$request->kode_request} telah disetujui lengkap dan stok gudang sudah dikurangi.",
            '/admin/permintaan-barang'
        );

        return $request->fresh();
    }

    private function resolveSiteMaterialStockId(MaterialRequest $request, MaterialRequestDetail $detail): int
    {
        $query = SiteMaterialStock::query()
            ->where('gudang_id', $request->gudang_id)
            ->where('perumahan_id', $request->perumahan_id)
            ->where('barang_material_id', $detail->barang_material_id);

        if (filled($request->detail_rumah_id)) {
            $query->where('detail_rumah_id', $request->detail_rumah_id);
        } else {
            $query->whereNull('detail_rumah_id');
        }

        if (filled($request->tahapan_pembangunan_id)) {
            $query->where('tahapan_pembangunan_id', $request->tahapan_pembangunan_id);
        } else {
            $query->whereNull('tahapan_pembangunan_id');
        }

        $siteStockId = $query->lockForUpdate()->value('id');

        if (! $siteStockId) {
            throw ValidationException::withMessages([
                'material_request_ids' => 'Stok lokasi untuk material '.$detail->barangMaterial?->nama_barang.' belum ditemukan.',
            ]);
        }

        return (int) $siteStockId;
    }

    private function code(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd-His').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }
}
