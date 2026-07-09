<?php

namespace App\Services;

use App\Models\BarangMaterial;
use App\Models\MaterialRequest;
use App\Models\MaterialReturn;
use App\Models\MaterialUsage;
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
        private readonly ProgressRealizationService $progressRealization,
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
                'keterangan' => $payload['keterangan'] ?? null,
                'foto' => $photo?->store('pemakaian-material', 'public'),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($payload['items'] as $item) {
                $siteStock = SiteMaterialStock::query()->lockForUpdate()->findOrFail($item['site_material_stock_id']);
                $qty = (float) $item['qty'];

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
                    'qty' => $qty,
                    'satuan' => $siteStock->barangMaterial?->satuan ?? $item['satuan'] ?? '-',
                ]);

                $siteStock->increment('qty_used', $qty);
                $siteStock->decrement('qty_available', $qty);
            }

            return $usage;
        });
    }

    public function updateUsage(MaterialUsage $usage, array $payload, ?UploadedFile $photo = null): MaterialUsage
    {
        return DB::transaction(function () use ($usage, $payload, $photo) {
            $usage = MaterialUsage::query()->with('details.siteMaterialStock')->lockForUpdate()->findOrFail($usage->id);

            foreach ($usage->details as $detail) {
                $detail->siteMaterialStock()->increment('qty_available', $detail->qty);
                $detail->siteMaterialStock()->decrement('qty_used', $detail->qty);
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
                $siteStock = SiteMaterialStock::query()->lockForUpdate()->findOrFail($item['site_material_stock_id']);
                $qty = (float) $item['qty'];

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
                    'qty' => $qty,
                    'satuan' => $siteStock->barangMaterial?->satuan ?? $item['satuan'] ?? '-',
                ]);

                $siteStock->increment('qty_used', $qty);
                $siteStock->decrement('qty_available', $qty);
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

                $price = (float) ($siteStock->barangMaterial?->harga_hpp ?? 0);
                $return->details()->create([
                    'site_material_stock_id' => $siteStock->id,
                    'barang_material_id' => $siteStock->barang_material_id,
                    'qty' => $qty,
                    'satuan' => $siteStock->barangMaterial?->satuan ?? '-',
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

            $items = $return->details->map(fn ($detail) => [
                'barang_material_id' => $detail->barang_material_id,
                'qty' => $detail->qty,
                'satuan' => $detail->satuan,
                'harga_satuan' => $detail->harga_satuan,
            ])->all();

            foreach ($return->details as $detail) {
                $siteStock = SiteMaterialStock::query()->lockForUpdate()->findOrFail($detail->site_material_stock_id);

                if ($siteStock->qty_reserved_return < $detail->qty) {
                    throw ValidationException::withMessages(['items' => 'Reservasi material pengembalian tidak lagi mencukupi.']);
                }
            }

            $transaction = $this->logistik->simpanTransaksi([
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
            ]);

            foreach ($return->details as $detail) {
                $siteStock = SiteMaterialStock::query()->lockForUpdate()->findOrFail($detail->site_material_stock_id);
                $siteStock->increment('qty_returned', $detail->qty);
                $siteStock->decrement('qty_reserved_return', $detail->qty);
            }

            $return->update([
                'status' => MaterialReturn::STATUS_DITERIMA,
                'receive_note' => $note,
                'received_by' => auth()->id(),
                'received_at' => now(),
                'transaksi_logistik_id' => $transaction->id,
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

    public function tryIssueApprovedRequest(MaterialRequest $materialRequest): MaterialRequest
    {
        return DB::transaction(function () use ($materialRequest) {
            $request = MaterialRequest::query()
                ->with(['details.barangMaterial'])
                ->lockForUpdate()
                ->findOrFail($materialRequest->id);

            if (! $request->approved_at_gudang || ! $request->approved_at_owner || $request->issued_at) {
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

    private function issueWhenComplete(MaterialRequest $request): MaterialRequest
    {
        if (! $request->approved_at_gudang || ! $request->approved_at_owner || $request->transaksi_logistik_id) {
            return $request->fresh();
        }

        if (! $request->gudang_id) {
            throw ValidationException::withMessages(['gudang_id' => 'Gudang wajib dipilih sebelum material dapat dikeluarkan.']);
        }

        $items = $request->details->map(fn ($detail) => [
            'barang_material_id' => $detail->barang_material_id,
            'qty' => $detail->qty,
            'satuan' => $detail->satuan,
            'harga_satuan' => $detail->barangMaterial?->harga_hpp ?? 0,
        ])->all();

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
            $siteStock = SiteMaterialStock::query()->firstOrCreate([
                'gudang_id' => $request->gudang_id,
                'perumahan_id' => $request->perumahan_id,
                'detail_rumah_id' => $request->detail_rumah_id,
                'tahapan_pembangunan_id' => $request->tahapan_pembangunan_id,
                'kelompok_hpp_id' => $request->kelompok_hpp_id,
                'barang_material_id' => $detail->barang_material_id,
            ]);

            $siteStock->increment('qty_received', $detail->qty);
            $siteStock->increment('qty_available', $detail->qty);
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

        if ((float) ($request->progress_diakui ?? 0) > 0) {
            $progress = $this->progressRealization->recordFromSource($request->fresh(), [
                'detail_rumah_id' => $request->detail_rumah_id,
                'tahapan_pembangunan_id' => $request->tahapan_pembangunan_id,
                'site_schedule_id' => $request->site_schedule_id,
                'nama_progress' => $request->tahapanPembangunan?->nama_tahapan ?? 'Pengambilan Material',
                'tanggal' => now()->toDateString(),
                'persentase' => (float) $request->progress_diakui,
                'keterangan' => "Progress otomatis dari pengambilan material {$request->kode_request}",
                'source_label' => "Material {$request->kode_request}",
            ]);

            if ($progress) {
                $request->update(['progress_pembangunan_id' => $progress->id]);
            }
        }

        $this->notifications->toRoles(
            ['pengawas', 'user_area_gudang', 'owner', 'super_admin'],
            'Material keluar dari gudang',
            "Permintaan {$request->kode_request} telah disetujui lengkap dan stok gudang sudah dikurangi.",
            '/admin/permintaan-barang'
        );

        return $request->fresh();
    }

    private function code(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd-His').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }
}
