<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsFieldOptions;
use App\Http\Controllers\Controller;
use App\Models\AssetMaintenanceLog;
use App\Models\AssetUsageLog;
use App\Models\AssetUsageRequest;
use App\Models\OfficeAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssetInventoryController extends Controller
{
    use BuildsFieldOptions;

    public function index(Request $request): Response
    {
        $this->authorizeViewer();
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('Admin/AssetInventory/Index', [
            'title' => 'Inventaris Aset',
            'description' => 'Kelola aset kantor/proyek, pengajuan pemakaian, hour meter, odometer, BBM, dan servis.',
            'baseUrl' => route('admin.asset-inventory.index', absolute: false),
            'filters' => ['search' => $search],
            'options' => $this->options(),
            'permissions' => [
                'canManageAssets' => $this->canManageAssets(),
                'canApprove' => $this->canApprove(),
                'canUseAsset' => $this->canUseAsset(),
            ],
            'assets' => OfficeAsset::query()
                ->with('penanggungJawab:id,name')
                ->when($search !== '', fn (Builder $query) => $query->where('kode_aset', 'like', "%{$search}%")->orWhere('nama_aset', 'like', "%{$search}%"))
                ->latest('id')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (OfficeAsset $asset) => $this->assetRow($asset)),
            'requests' => AssetUsageRequest::query()
                ->with(['asset:id,kode_aset,nama_aset,tipe_aset,status', 'perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah', 'requester:id,name', 'approvedBy:id,name'])
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (AssetUsageRequest $row) => $this->requestRow($row)),
            'usageLogs' => AssetUsageLog::query()
                ->with(['asset:id,kode_aset,nama_aset,tipe_aset', 'user:id,name'])
                ->latest('mulai_pakai')
                ->limit(30)
                ->get()
                ->map(fn (AssetUsageLog $row) => $this->usageRow($row)),
            'maintenanceLogs' => AssetMaintenanceLog::query()
                ->with('asset:id,kode_aset,nama_aset')
                ->latest('tanggal_servis')
                ->limit(30)
                ->get()
                ->map(fn (AssetMaintenanceLog $row) => $this->maintenanceRow($row)),
        ]);
    }

    public function storeAsset(Request $request): RedirectResponse
    {
        abort_unless($this->canManageAssets(), 403, 'Hanya admin/owner yang dapat mengelola aset.');
        $validated = $this->assetPayload($request);

        OfficeAsset::query()->create([
            ...$validated,
            'kode_aset' => 'AST-'.now()->format('ymd-His').'-'.random_int(10, 99),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Aset berhasil ditambahkan.');
    }

    public function updateAsset(Request $request, string $id): RedirectResponse
    {
        abort_unless($this->canManageAssets(), 403, 'Hanya admin/owner yang dapat mengelola aset.');
        $asset = OfficeAsset::query()->findOrFail($id);
        abort_if(($asset->record_status ?? 'draft') === 'locked' && ! $this->canUnlock(), 422, 'Aset sudah locked.');
        $asset->update([...$this->assetPayload($request), 'updated_by' => auth()->id()]);

        return back()->with('success', 'Aset berhasil diperbarui.');
    }

    public function requestAsset(Request $request): RedirectResponse
    {
        abort_unless($this->canUseAsset(), 403, 'Hanya pengawas/admin/manajer/owner yang dapat mengajukan alat.');
        $validated = $request->validate([
            'office_asset_id' => ['required', 'exists:office_assets,id'],
            'perumahan_id' => ['nullable', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'nama_peminjam' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai_estimasi' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'tujuan_pemakaian' => ['required', 'string'],
            'lokasi_pemakaian' => ['nullable', 'string', 'max:255'],
        ]);

        AssetUsageRequest::query()->create([
            ...$validated,
            'kode_pengajuan' => 'PJA-'.now()->format('ymd-His').'-'.random_int(10, 99),
            'requested_by' => auth()->id(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Pengajuan alat berhasil dibuat.');
    }

    public function approveRequest(string $id): RedirectResponse
    {
        abort_unless($this->canApprove(), 403, 'Hanya admin, manajer, atau owner yang dapat approve.');
        $request = AssetUsageRequest::query()->with('asset')->findOrFail($id);
        abort_if($request->status !== 'diajukan', 422, 'Pengajuan ini sudah diproses.');
        abort_if($request->asset?->status !== 'tersedia', 422, 'Aset tidak tersedia.');

        $request->update([
            'status' => 'disetujui',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Pengajuan alat berhasil disetujui.');
    }

    public function issueRequest(string $id): RedirectResponse
    {
        abort_unless($this->canManageAssets(), 403, 'Hanya admin/owner yang dapat menyerahkan aset.');
        $request = AssetUsageRequest::query()->with('asset')->findOrFail($id);
        abort_unless($request->status === 'disetujui', 422, 'Pengajuan harus disetujui lebih dulu.');

        DB::transaction(function () use ($request): void {
            $request->update(['status' => 'dipakai', 'updated_by' => auth()->id()]);
            $request->asset?->update([
                'status' => 'dipakai',
                'lokasi_sekarang' => $request->lokasi_pemakaian,
                'penanggung_jawab_id' => $request->requested_by,
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Aset berhasil diserahkan untuk dipakai.');
    }

    public function storeUsage(Request $request): RedirectResponse
    {
        abort_unless($this->canUseAsset(), 403, 'Tidak dapat mencatat pemakaian aset.');
        $validated = $request->validate([
            'office_asset_id' => ['required', 'exists:office_assets,id'],
            'asset_usage_request_id' => ['nullable', 'exists:asset_usage_requests,id'],
            'mulai_pakai' => ['required', 'date'],
            'selesai_pakai' => ['nullable', 'date', 'after_or_equal:mulai_pakai'],
            'hour_meter_awal' => ['nullable', 'numeric', 'min:0'],
            'hour_meter_akhir' => ['nullable', 'numeric', 'min:0'],
            'odometer_awal' => ['nullable', 'numeric', 'min:0'],
            'odometer_akhir' => ['nullable', 'numeric', 'min:0'],
            'bbm_liter' => ['nullable', 'numeric', 'min:0'],
            'biaya_bbm' => ['nullable', 'numeric', 'min:0'],
            'operator' => ['nullable', 'string', 'max:255'],
            'kondisi_sebelum' => ['nullable', 'string', 'max:255'],
            'kondisi_sesudah' => ['nullable', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'pekerjaan' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated): void {
            $payload = $this->usagePayload($validated);
            AssetUsageLog::query()->create([
                ...$payload,
                'kode_log' => 'LOG-'.now()->format('ymd-His').'-'.random_int(10, 99),
                'used_by' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $asset = OfficeAsset::query()->find($payload['office_asset_id']);
            $asset?->update([
                'hour_meter_terakhir' => max((float) $asset->hour_meter_terakhir, (float) ($payload['hour_meter_akhir'] ?? 0)),
                'odometer_terakhir' => max((float) $asset->odometer_terakhir, (float) ($payload['odometer_akhir'] ?? 0)),
                'lokasi_sekarang' => $payload['lokasi'] ?: $asset->lokasi_sekarang,
                'kondisi' => $payload['kondisi_sesudah'] ?: $asset->kondisi,
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Log pemakaian aset berhasil dicatat.');
    }

    public function returnRequest(string $id): RedirectResponse
    {
        abort_unless($this->canManageAssets(), 403, 'Hanya admin/owner yang dapat menerima pengembalian aset.');
        $request = AssetUsageRequest::query()->with('asset')->findOrFail($id);

        DB::transaction(function () use ($request): void {
            $request->update(['status' => 'selesai', 'updated_by' => auth()->id()]);
            $request->asset?->update([
                'status' => 'tersedia',
                'penanggung_jawab_id' => null,
                'updated_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Aset berhasil dikembalikan.');
    }

    public function storeMaintenance(Request $request): RedirectResponse
    {
        abort_unless($this->canManageAssets(), 403, 'Hanya admin/owner yang dapat mencatat servis.');
        $validated = $request->validate([
            'office_asset_id' => ['required', 'exists:office_assets,id'],
            'tanggal_servis' => ['required', 'date'],
            'jenis_servis' => ['required', 'string', 'max:100'],
            'hour_meter' => ['nullable', 'numeric', 'min:0'],
            'odometer' => ['nullable', 'numeric', 'min:0'],
            'pekerjaan_servis' => ['required', 'string'],
            'sparepart' => ['nullable', 'string'],
            'biaya' => ['nullable', 'numeric', 'min:0'],
            'teknisi' => ['nullable', 'string', 'max:255'],
            'jadwal_berikutnya' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:100'],
        ]);

        AssetMaintenanceLog::query()->create([
            ...$validated,
            'kode_servis' => 'SRV-'.now()->format('ymd-His').'-'.random_int(10, 99),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Catatan servis berhasil disimpan.');
    }

    protected function assetPayload(Request $request): array
    {
        return $request->validate([
            'nama_aset' => ['required', 'string', 'max:255'],
            'kategori' => ['required', 'string', 'max:100'],
            'tipe_aset' => ['required', Rule::in(['alat_kecil', 'alat_proyek', 'kendaraan', 'alat_berat'])],
            'nomor_seri' => ['nullable', 'string', 'max:255'],
            'plat_nomor' => ['nullable', 'string', 'max:50'],
            'lokasi_sekarang' => ['nullable', 'string', 'max:255'],
            'kondisi' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['tersedia', 'dipakai', 'servis', 'rusak', 'hilang'])],
            'nilai_aset' => ['nullable', 'numeric', 'min:0'],
            'hour_meter_terakhir' => ['nullable', 'numeric', 'min:0'],
            'odometer_terakhir' => ['nullable', 'numeric', 'min:0'],
            'penanggung_jawab_id' => ['nullable', 'exists:users,id'],
            'catatan' => ['nullable', 'string'],
        ]);
    }

    protected function usagePayload(array $validated): array
    {
        $mulai = strtotime((string) $validated['mulai_pakai']);
        $selesai = filled($validated['selesai_pakai'] ?? null) ? strtotime((string) $validated['selesai_pakai']) : null;

        return [
            ...$validated,
            'asset_usage_request_id' => filled($validated['asset_usage_request_id'] ?? null) ? $validated['asset_usage_request_id'] : null,
            'durasi_jam' => $selesai && $mulai ? max(0, round(($selesai - $mulai) / 3600, 2)) : 0,
        ];
    }

    protected function options(): array
    {
        $fieldOptions = $this->fieldOptions();

        return [
            ...$fieldOptions,
            'assets' => OfficeAsset::query()->orderBy('nama_aset')->get(['id', 'kode_aset', 'nama_aset', 'tipe_aset', 'status'])
                ->map(fn (OfficeAsset $asset) => ['value' => (string) $asset->id, 'label' => $asset->kode_aset.' - '.$asset->nama_aset, 'status' => $asset->status, 'tipe_aset' => $asset->tipe_aset])->values(),
            'approvedRequests' => AssetUsageRequest::query()->whereIn('status', ['disetujui', 'dipakai'])->with('asset:id,kode_aset,nama_aset')->latest('id')->get()
                ->map(fn (AssetUsageRequest $row) => ['value' => (string) $row->id, 'label' => $row->kode_pengajuan.' - '.$row->asset?->nama_aset, 'office_asset_id' => (string) $row->office_asset_id, 'lokasi' => $row->lokasi_pemakaian])->values(),
            'users' => User::query()->orderBy('name')->get(['id', 'name'])->map(fn (User $user) => ['value' => (string) $user->id, 'label' => $user->name])->values(),
            'assetTypes' => $this->simpleOptions(['alat_kecil', 'alat_proyek', 'kendaraan', 'alat_berat']),
            'assetStatuses' => $this->simpleOptions(['tersedia', 'dipakai', 'servis', 'rusak', 'hilang']),
            'requestStatuses' => $this->simpleOptions(['diajukan', 'disetujui', 'dipakai', 'selesai', 'ditolak']),
        ];
    }

    protected function assetRow(OfficeAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'kode_aset' => $asset->kode_aset,
            'nama_aset' => $asset->nama_aset,
            'kategori' => $asset->kategori,
            'tipe_aset' => $asset->tipe_aset,
            'nomor_seri' => $asset->nomor_seri,
            'plat_nomor' => $asset->plat_nomor,
            'lokasi_sekarang' => $asset->lokasi_sekarang,
            'kondisi' => $asset->kondisi,
            'status' => $asset->status,
            'nilai_aset' => $asset->nilai_aset,
            'hour_meter_terakhir' => $asset->hour_meter_terakhir,
            'odometer_terakhir' => $asset->odometer_terakhir,
            'penanggung_jawab_id' => (string) ($asset->penanggung_jawab_id ?? ''),
            'penanggung_jawab' => $asset->penanggungJawab?->name ?? '-',
            'catatan' => $asset->catatan,
        ];
    }

    protected function requestRow(AssetUsageRequest $row): array
    {
        return [
            'id' => $row->id,
            'kode_pengajuan' => $row->kode_pengajuan,
            'office_asset_id' => (string) $row->office_asset_id,
            'aset' => $row->asset?->nama_aset ?? '-',
            'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
            'unit' => $row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : '-',
            'tanggal_mulai' => optional($row->tanggal_mulai)->format('Y-m-d'),
            'tanggal_selesai_estimasi' => optional($row->tanggal_selesai_estimasi)->format('Y-m-d'),
            'nama_peminjam' => $row->nama_peminjam ?: $row->requester?->name ?? '-',
            'tujuan_pemakaian' => $row->tujuan_pemakaian,
            'lokasi_pemakaian' => $row->lokasi_pemakaian,
            'status' => $row->status,
            'requested_by' => $row->requester?->name ?? '-',
            'approved_by' => $row->approvedBy?->name ?? '-',
        ];
    }

    protected function usageRow(AssetUsageLog $row): array
    {
        return [
            'id' => $row->id,
            'kode_log' => $row->kode_log,
            'aset' => $row->asset?->nama_aset ?? '-',
            'used_by' => $row->user?->name ?? '-',
            'mulai_pakai' => optional($row->mulai_pakai)->format('Y-m-d H:i'),
            'selesai_pakai' => optional($row->selesai_pakai)->format('Y-m-d H:i'),
            'durasi_jam' => $row->durasi_jam,
            'hm' => $row->hour_meter_awal.' - '.$row->hour_meter_akhir,
            'km' => $row->odometer_awal.' - '.$row->odometer_akhir,
            'bbm_liter' => $row->bbm_liter,
            'biaya_bbm' => $row->biaya_bbm,
            'operator' => $row->operator,
            'lokasi' => $row->lokasi,
            'pekerjaan' => $row->pekerjaan,
        ];
    }

    protected function maintenanceRow(AssetMaintenanceLog $row): array
    {
        return [
            'id' => $row->id,
            'kode_servis' => $row->kode_servis,
            'aset' => $row->asset?->nama_aset ?? '-',
            'tanggal_servis' => optional($row->tanggal_servis)->format('Y-m-d'),
            'jenis_servis' => $row->jenis_servis,
            'hm' => $row->hour_meter,
            'km' => $row->odometer,
            'pekerjaan_servis' => $row->pekerjaan_servis,
            'biaya' => $row->biaya,
            'teknisi' => $row->teknisi,
            'jadwal_berikutnya' => optional($row->jadwal_berikutnya)->format('Y-m-d'),
            'status' => $row->status,
        ];
    }

    protected function simpleOptions(array $values): array
    {
        return collect($values)->map(fn (string $value) => ['value' => $value, 'label' => ucwords(str_replace('_', ' ', $value))])->values()->all();
    }

    protected function authorizeViewer(): void
    {
        abort_unless(
            auth()->user()?->can('asset-inventory.view')
            || auth()->user()?->can('asset-inventory.request')
            || auth()->user()?->can('asset-inventory.usage')
            || auth()->user()?->hasAnyRole(['owner', 'super_admin']),
            403,
        );
    }

    protected function canManageAssets(): bool
    {
        return (bool) auth()->user()?->can('asset-inventory.create')
            || auth()->user()?->can('asset-inventory.update')
            || auth()->user()?->can('asset-inventory.delete')
            || auth()->user()?->hasAnyRole(['owner', 'super_admin']);
    }

    protected function canApprove(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['admin', 'manajer_pimpro', 'owner', 'super_admin']);
    }

    protected function canUseAsset(): bool
    {
        return (bool) auth()->user()?->can('asset-inventory.request')
            || auth()->user()?->can('asset-inventory.usage')
            || auth()->user()?->hasAnyRole(['owner', 'super_admin']);
    }

    protected function canUnlock(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['owner', 'super_admin']);
    }
}
