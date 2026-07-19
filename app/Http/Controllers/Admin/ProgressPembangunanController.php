<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\UsesApprovalSettings;
use App\Http\Controllers\Controller;
use App\Models\DetailRumah;
use App\Models\DetailRumahHppItem;
use App\Models\HppRealisasi;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestDetail;
use App\Models\MaterialUsage;
use App\Models\Perumahan;
use App\Models\ProgressPembangunan;
use App\Models\SiteMaterialStock;
use App\Models\SiteReport;
use App\Models\SiteSchedule;
use App\Models\TahapanPembangunan;
use App\Services\MaterialHppRealizationService;
use App\Services\MaterialWorkflowService;
use App\Services\TahapanOptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProgressPembangunanController extends Controller
{
    use HandlesCrudLock, UsesApprovalSettings {
        lock as protected traitLock;
        unlock as protected traitUnlock;
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perumahanId = $request->query('perumahan_id');
        $detailRumahId = $request->query('detail_rumah_id');

        $rows = ProgressPembangunan::query()
            ->with([
                'detailRumah.perumahan:id,nama_perusahaan',
                'siteSchedule.perumahan:id,nama_perusahaan',
                'siteSchedule.detailRumah:id,kode_nlok,nomor_rumah',
                'siteSchedule.spkKontraktor.items',
                'tahapanPembangunan:id,nama_tahapan',
                'user:id,name',
                'creator:id,name',
                'updater:id,name',
                'approvedBy:id,name',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('nama_progress', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%")
                        ->orWhereHas('detailRumah', function (Builder $query) use ($search) {
                            $query->where('kode_nlok', 'like', "%{$search}%")
                                ->orWhere('nomor_rumah', 'like', "%{$search}%")
                                ->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%"));
                        })
                        ->orWhereHas('siteSchedule', function (Builder $query) use ($search) {
                            $query->where('nama_pekerjaan', 'like', "%{$search}%")
                                ->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%"));
                        });
                });
            })
            ->when($perumahanId, fn (Builder $query) => $query->where(function (Builder $query) use ($perumahanId) {
                $query->whereHas('detailRumah', fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
                    ->orWhereHas('siteSchedule', fn (Builder $query) => $query->where('perumahan_id', $perumahanId));
            }))
            ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
            ->latest('tanggal')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ProgressPembangunan $row) => [
                'id' => $row->id,
                'perumahan_id' => (string) ($row->detailRumah?->perumahan_id ?? $row->siteSchedule?->perumahan_id ?? ''),
                'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                'schedule_item_key' => (string) ($row->schedule_item_key ?? ''),
                'site_schedule_id' => (string) ($row->site_schedule_id ?? ''),
                'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                'nama_progress' => $row->nama_progress,
                'perumahan' => $row->detailRumah?->perumahan?->nama_perusahaan ?? $row->siteSchedule?->perumahan?->nama_perusahaan ?? '-',
                'unit' => $row->detailRumah
                    ? trim(($row->detailRumah->kode_nlok ?? '').' '.($row->detailRumah->nomor_rumah ?? ''))
                    : ($row->siteSchedule?->detailRumah
                        ? trim(($row->siteSchedule->detailRumah->kode_nlok ?? '').' '.($row->siteSchedule->detailRumah->nomor_rumah ?? ''))
                        : 'Kawasan'),
                'tahapan' => trim(($row->schedule_stage_name ? $row->schedule_stage_name.' - ' : '').($row->schedule_item_name ?? $row->tahapanPembangunan?->nama_tahapan ?? '-')),
                'persentase' => $row->persentase,
                'persentase_total' => $row->persentase_total,
                'source_label' => $row->source_label ?? 'Input Manual',
                'approval_status' => $row->approval_status,
                'approval_label' => $this->approvalLabel($row->approval_status),
                'foto_url' => $row->foto ? route('media', ['path' => $row->foto], false) : null,
                'keterangan' => $row->keterangan,
                'input_oleh' => $row->user?->name ?? '-',
                'created_by_name' => $row->creator?->name ?? $row->user?->name ?? '-',
                'updated_by_name' => $row->updater?->name ?? '-',
                'approved_by' => $row->approvedBy?->name ?? '-',
                'can_approve' => ($row->record_status ?? 'draft') === 'locked' && $this->requiresApprovalFor('progress') && $row->approval_status !== 'approved' && $this->canApproveFor('progress'),
                'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && $this->canLock(),
                'can_unlock' => $this->canManageDraftRows(),
                'can_edit' => ($row->record_status ?? 'draft') !== 'locked' && $this->canUpdateProgress(),
                'can_delete' => ($row->record_status ?? 'draft') !== 'locked' && $this->canDeleteProgress(),
                'record_status' => $row->record_status ?? 'draft',
            ]);

        return Inertia::render('Admin/ProgressPembangunan/Index', [
            'title' => 'Progress Pembangunan',
            'description' => 'Pengawas menginput progress lapangan dengan bukti foto, lalu manajer menyetujui sebelum progress dihitung ke unit rumah.',
            'baseUrl' => route('admin.progress-pembangunan.index', absolute: false),
            'permissions' => [
                'canCreate' => $this->canCreateProgress(),
                'canUpdate' => $this->canUpdateProgress(),
                'canDelete' => $this->canDeleteProgress(),
                'canApprove' => $this->canApproveFor('progress'),
                'canLock' => $this->canLock(),
                'canUnlock' => $this->canManageDraftRows(),
            ],
            'rows' => $rows,
            'filters' => [
                'search' => $search,
                'perumahan_id' => $perumahanId,
                'detail_rumah_id' => $detailRumahId,
            ],
            'options' => [
                'perumahans' => Perumahan::query()->finalized()
                    ->orderBy('nama_perusahaan')
                    ->get(['id', 'nama_perusahaan'])
                    ->map(fn (Perumahan $row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])
                    ->values(),
                'detailRumahs' => DetailRumah::query()->finalized()
                    ->with('perumahan:id,nama_perusahaan')
                    ->orderBy('kode_nlok')
                    ->orderBy('nomor_rumah')
                    ->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])
                    ->map(fn (DetailRumah $row) => [
                        'value' => (string) $row->id,
                        'label' => "{$row->perumahan?->nama_perusahaan} - {$row->kode_nlok} {$row->nomor_rumah}",
                        'perumahan_id' => (string) $row->perumahan_id,
                    ])
                    ->values(),
                'siteSchedules' => $this->siteScheduleOptions(),
                'hppTahapanOptions' => app(TahapanOptionService::class)->forContext('unit'),
                'approvedMaterialRequests' => $this->approvedMaterialRequestOptions($perumahanId, $detailRumahId),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $perumahanId = $request->query('perumahan_id');
        $detailRumahId = $request->query('detail_rumah_id');

        return Inertia::render('Admin/ProgressPembangunan/Create', [
            'title' => 'Tambah Progress Pembangunan',
            'description' => 'Pengawas menginput progress lapangan dengan bukti foto dan bisa langsung memilih permintaan material approved yang terkait.',
            'baseUrl' => route('admin.progress-pembangunan.store', absolute: false),
            'indexUrl' => route('admin.progress-pembangunan.index', absolute: false),
            'permissions' => [
                'canCreate' => $this->canCreateProgress(),
            ],
            'options' => [
                'perumahans' => Perumahan::query()->finalized()
                    ->orderBy('nama_perusahaan')
                    ->get(['id', 'nama_perusahaan'])
                    ->map(fn (Perumahan $row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])
                    ->values(),
                'detailRumahs' => DetailRumah::query()->finalized()
                    ->with('perumahan:id,nama_perusahaan')
                    ->orderBy('kode_nlok')
                    ->orderBy('nomor_rumah')
                    ->get(['id', 'perumahan_id', 'kode_nlok', 'nomor_rumah'])
                    ->map(fn (DetailRumah $row) => [
                        'value' => (string) $row->id,
                        'label' => "{$row->perumahan?->nama_perusahaan} - {$row->kode_nlok} {$row->nomor_rumah}",
                        'perumahan_id' => (string) $row->perumahan_id,
                    ])
                    ->values(),
                'siteSchedules' => $this->siteScheduleOptions($perumahanId, $detailRumahId),
                'hppTahapanOptions' => app(TahapanOptionService::class)->forContext('unit'),
                'approvedMaterialRequests' => $this->approvedMaterialRequestOptions($perumahanId, $detailRumahId),
                'hppItems' => $this->hppItemOptions(),
            ],
        ]);
    }

    public function show(string $id): Response
    {
        $progress = ProgressPembangunan::query()
            ->with([
                'detailRumah.perumahan:id,nama_perusahaan',
                'siteSchedule.perumahan:id,nama_perusahaan',
                'siteSchedule.detailRumah:id,kode_nlok,nomor_rumah',
                'user:id,name',
                'creator:id,name',
                'updater:id,name',
                'approvedBy:id,name',
                'siteReports',
                'materialUsages.details.barangMaterial:id,kode_barang,nama_barang,satuan,harga_hpp',
                'materialUsages.details.detailRumahHppItem.tahapanPembangunan:id,nama_tahapan',
                'materialUsages.details.detailRumahHppItem.barangMaterial:id,kode_barang,nama_barang,satuan',
            ])
            ->findOrFail($id);

        $materialRequests = MaterialRequest::query()
            ->with(['details.barangMaterial:id,kode_barang,nama_barang,satuan,harga_hpp'])
            ->where('progress_pembangunan_id', $progress->id)
            ->latest('tanggal')
            ->get()
            ->map(fn (MaterialRequest $request) => [
                'id' => $request->id,
                'kode_request' => $request->kode_request,
                'tanggal' => optional($request->tanggal)->format('Y-m-d'),
                'issued_at' => optional($request->issued_at)->format('Y-m-d H:i'),
                'status' => $request->status,
                'items' => $request->details->map(fn ($detail) => [
                    'material' => $detail->barangMaterial?->nama_barang ?? '-',
                    'kode' => $detail->barangMaterial?->kode_barang ?? '-',
                    'qty' => (float) ($detail->qty_issued > 0 ? $detail->qty_issued : $detail->qty),
                    'satuan' => $detail->satuan ?: ($detail->barangMaterial?->satuan ?? '-'),
                ])->values(),
            ])->values();

        $siteReport = $progress->siteReports->first();

        return Inertia::render('Admin/ProgressPembangunan/Show', [
            'title' => 'Detail Progress Pembangunan',
            'indexUrl' => route('admin.progress-pembangunan.index', absolute: false),
            'progress' => [
                'id' => $progress->id,
                'tanggal' => optional($progress->tanggal)->format('Y-m-d'),
                'nama_progress' => $progress->nama_progress,
                'perumahan' => $progress->detailRumah?->perumahan?->nama_perusahaan ?? $progress->siteSchedule?->perumahan?->nama_perusahaan ?? '-',
                'unit' => $progress->detailRumah
                    ? trim(($progress->detailRumah->kode_nlok ?? '').' '.($progress->detailRumah->nomor_rumah ?? ''))
                    : ($progress->siteSchedule?->detailRumah ? trim(($progress->siteSchedule->detailRumah->kode_nlok ?? '').' '.($progress->siteSchedule->detailRumah->nomor_rumah ?? '')) : 'Kawasan'),
                'jadwal' => $progress->siteSchedule?->kode_jadwal.' - '.$progress->siteSchedule?->nama_pekerjaan,
                'tahap_jadwal' => $progress->schedule_stage_name,
                'item_pekerjaan' => $progress->schedule_item_name,
                'target_item' => (float) $progress->schedule_item_target,
                'progress' => (float) $progress->persentase,
                'kontribusi_total' => (float) $progress->persentase_total,
                'approval_label' => $this->approvalLabel($progress->approval_status),
                'record_status' => $progress->record_status ?? 'draft',
                'keterangan' => $progress->keterangan,
                'foto_url' => $progress->foto ? route('media', ['path' => $progress->foto], false) : null,
                'input_oleh' => $progress->user?->name ?? '-',
                'created_by_name' => $progress->creator?->name ?? $progress->user?->name ?? '-',
                'updated_by_name' => $progress->updater?->name ?? '-',
                'approved_by' => $progress->approvedBy?->name ?? '-',
            ],
            'siteReport' => $siteReport ? [
                'kode_laporan' => $siteReport->kode_laporan,
                'tanggal' => optional($siteReport->tanggal)->format('Y-m-d'),
                'cuaca' => $siteReport->cuaca,
                'jumlah_pekerja' => $siteReport->jumlah_pekerja,
                'kontraktor' => $siteReport->kontraktor,
                'pekerjaan_selesai' => $siteReport->pekerjaan_selesai,
                'pekerjaan_tertahan' => $siteReport->pekerjaan_tertahan,
                'kendala' => $siteReport->kendala,
                'koordinasi' => $siteReport->koordinasi,
                'rencana_berikutnya' => $siteReport->rencana_berikutnya,
                'lampiran_url' => $siteReport->lampiran ? route('media', ['path' => $siteReport->lampiran], false) : null,
            ] : null,
            'materialRequests' => $materialRequests,
            'materialUsages' => $progress->materialUsages->map(fn (MaterialUsage $usage) => [
                'id' => $usage->id,
                'kode_pemakaian' => $usage->kode_pemakaian,
                'tanggal' => optional($usage->tanggal)->format('Y-m-d'),
                'keterangan' => $usage->keterangan,
                'items' => $usage->details->map(fn ($detail) => [
                    'material' => $detail->barangMaterial?->nama_barang ?? '-',
                    'kode' => $detail->barangMaterial?->kode_barang ?? '-',
                    'qty' => (float) $detail->qty,
                    'satuan' => $detail->satuan,
                    'hpp_item' => trim(($detail->detailRumahHppItem?->tahapanPembangunan?->nama_tahapan ? $detail->detailRumahHppItem->tahapanPembangunan->nama_tahapan.' - ' : '').($detail->detailRumahHppItem?->nama_pekerjaan ?: $detail->detailRumahHppItem?->barangMaterial?->nama_barang ?? '-')),
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canCreateProgress(), 403, 'Hanya pengawas yang dapat menginput progress pembangunan.');
        $validated = $request->validate([
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'site_schedule_id' => ['required', 'exists:site_schedules,id'],
            'schedule_stage_key' => ['required', 'string'],
            'schedule_item_key' => ['required', 'string'],
            'tanggal' => ['required', 'date'],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['required', 'string'],
            'foto' => ['nullable', 'file', 'image', 'max:4096'],
            'site_report' => ['nullable', 'array'],
            'site_report.cuaca' => ['nullable', 'string', 'max:255'],
            'site_report.jumlah_pekerja' => ['nullable', 'integer', 'min:0'],
            'site_report.kontraktor' => ['nullable', 'string', 'max:255'],
            'site_report.pekerjaan_selesai' => ['nullable', 'string'],
            'site_report.pekerjaan_tertahan' => ['nullable', 'string'],
            'site_report.kendala' => ['nullable', 'string'],
            'site_report.koordinasi' => ['nullable', 'string'],
            'site_report.rencana_berikutnya' => ['nullable', 'string'],
            'site_report.lampiran' => ['nullable', 'file', 'max:4096'],
            'material_request_ids' => ['nullable', 'array'],
            'material_request_ids.*' => ['exists:material_requests,id'],
            'material_usage_items' => ['nullable', 'array'],
            'material_usage_items.*.material_request_id' => ['required_with:material_usage_items', 'exists:material_requests,id'],
            'material_usage_items.*.material_request_detail_id' => ['required_with:material_usage_items', 'exists:material_request_details,id'],
            'material_usage_items.*.barang_material_id' => ['required_with:material_usage_items', 'exists:barang_materials,id'],
            'material_usage_items.*.detail_rumah_hpp_item_id' => ['required_with:material_usage_items', 'exists:detail_rumah_hpp_items,id'],
            'material_usage_items.*.qty' => ['required_with:material_usage_items', 'numeric', 'min:0'],
            'material_usage_items.*.satuan' => ['nullable', 'string', 'max:255'],
        ]);

        $rumah = filled($validated['detail_rumah_id'] ?? null)
            ? DetailRumah::query()->finalized()->findOrFail($validated['detail_rumah_id'])
            : null;
        $schedule = SiteSchedule::query()->with(['perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah', 'spkKontraktor.items'])->findOrFail($validated['site_schedule_id']);
        $scheduleStage = $this->scheduleStageByKey($schedule, $validated['schedule_stage_key']);
        abort_unless($scheduleStage !== null, 422, 'Tahap jadwal kerja tidak ditemukan pada jadwal yang dipilih.');
        $scheduleItem = $this->scheduleItemByKey($schedule, $validated['schedule_stage_key'], $validated['schedule_item_key']);
        abort_unless($scheduleItem !== null, 422, 'Item jadwal kerja tidak ditemukan pada jadwal yang dipilih.');
        if (filled($validated['detail_rumah_id'] ?? null)) {
            abort_unless((string) $schedule->detail_rumah_id === (string) $validated['detail_rumah_id'], 422, 'Jadwal yang dipilih tidak sesuai dengan unit yang dipilih.');
        } else {
            abort_unless(blank($schedule->detail_rumah_id), 422, 'Untuk progress kawasan, pilih jadwal kawasan.');
        }
        $this->ensureScheduleItemCapacity($schedule->id, $validated['schedule_stage_key'], $validated['schedule_item_key'], (float) $validated['persentase']);

        $approvalRequired = $this->requiresApprovalFor('progress', 'create');

        $progress = DB::transaction(function () use ($request, $validated, $rumah, $schedule, $scheduleStage, $scheduleItem, $approvalRequired) {
            $progress = ProgressPembangunan::query()->create([
                'detail_rumah_id' => $rumah?->id,
                'tahapan_pembangunan_id' => null,
                'site_schedule_id' => $schedule->id,
                'schedule_stage_key' => $scheduleStage['key'],
                'schedule_stage_name' => $scheduleStage['name'],
                'schedule_stage_target' => $scheduleStage['target'],
                'schedule_item_key' => $scheduleItem['key'],
                'schedule_item_name' => $scheduleItem['name'],
                'schedule_item_target' => $scheduleItem['target'],
                'nama_progress' => $scheduleItem['name'],
                'tanggal' => $validated['tanggal'],
                'tahapan' => $scheduleItem['target'],
                'persentase' => $validated['persentase'],
                'persentase_total' => ((float) $validated['persentase'] / 100) * (float) $scheduleItem['target'],
                'keterangan' => $validated['keterangan'],
                'foto' => $request->file('foto')?->store('progress-pembangunan', 'public'),
                'approval_status' => $approvalRequired ? 'menunggu_approval_manager' : 'approved',
                'approved_by' => $approvalRequired ? null : auth()->id(),
                'approved_at' => $approvalRequired ? null : now(),
                'users_id' => auth()->id(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if (! $approvalRequired) {
                $this->recalculateRumahProgress($rumah);
                $this->syncSiteSchedules($progress);
            }

            $this->replaceMaterialUsagesFromRequests($progress, $validated['material_request_ids'] ?? [], $validated['material_usage_items'] ?? [], $rumah?->perumahan_id ?? $schedule->perumahan_id);
            $this->upsertSiteReportForProgress($progress, $validated['site_report'] ?? [], $approvalRequired, $request);

            return $progress;
        });

        return back()->with('success', $approvalRequired
            ? 'Progress pembangunan berhasil diajukan dan menunggu approval manajer.'
            : 'Progress pembangunan berhasil disimpan dan langsung aktif.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        abort_unless($this->canUpdateProgress(), 403, 'Hanya pengawas yang dapat mengubah progress pembangunan.');

        $progress = ProgressPembangunan::query()->findOrFail($id);
        $this->abortIfLocked($progress);

        $validated = $request->validate([
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'site_schedule_id' => ['required', 'exists:site_schedules,id'],
            'schedule_stage_key' => ['required', 'string'],
            'schedule_item_key' => ['required', 'string'],
            'tanggal' => ['required', 'date'],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['required', 'string'],
            'foto' => ['nullable', 'file', 'image', 'max:4096'],
            'site_report' => ['nullable', 'array'],
            'site_report.cuaca' => ['nullable', 'string', 'max:255'],
            'site_report.jumlah_pekerja' => ['nullable', 'integer', 'min:0'],
            'site_report.kontraktor' => ['nullable', 'string', 'max:255'],
            'site_report.pekerjaan_selesai' => ['nullable', 'string'],
            'site_report.pekerjaan_tertahan' => ['nullable', 'string'],
            'site_report.kendala' => ['nullable', 'string'],
            'site_report.koordinasi' => ['nullable', 'string'],
            'site_report.rencana_berikutnya' => ['nullable', 'string'],
            'site_report.lampiran' => ['nullable', 'file', 'max:4096'],
            'material_request_ids' => ['nullable', 'array'],
            'material_request_ids.*' => ['exists:material_requests,id'],
            'material_usage_items' => ['nullable', 'array'],
            'material_usage_items.*.material_request_id' => ['required_with:material_usage_items', 'exists:material_requests,id'],
            'material_usage_items.*.material_request_detail_id' => ['required_with:material_usage_items', 'exists:material_request_details,id'],
            'material_usage_items.*.barang_material_id' => ['required_with:material_usage_items', 'exists:barang_materials,id'],
            'material_usage_items.*.detail_rumah_hpp_item_id' => ['required_with:material_usage_items', 'exists:detail_rumah_hpp_items,id'],
            'material_usage_items.*.qty' => ['required_with:material_usage_items', 'numeric', 'min:0'],
            'material_usage_items.*.satuan' => ['nullable', 'string', 'max:255'],
        ]);

        $rumah = filled($validated['detail_rumah_id'] ?? null)
            ? DetailRumah::query()->finalized()->findOrFail($validated['detail_rumah_id'])
            : null;
        $schedule = SiteSchedule::query()->with(['perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah', 'spkKontraktor.items'])->findOrFail($validated['site_schedule_id']);
        $scheduleStage = $this->scheduleStageByKey($schedule, $validated['schedule_stage_key']);
        abort_unless($scheduleStage !== null, 422, 'Tahap jadwal kerja tidak ditemukan pada jadwal yang dipilih.');
        $scheduleItem = $this->scheduleItemByKey($schedule, $validated['schedule_stage_key'], $validated['schedule_item_key']);
        abort_unless($scheduleItem !== null, 422, 'Item jadwal kerja tidak ditemukan pada jadwal yang dipilih.');
        if (filled($validated['detail_rumah_id'] ?? null)) {
            abort_unless((string) $schedule->detail_rumah_id === (string) $validated['detail_rumah_id'], 422, 'Jadwal yang dipilih tidak sesuai dengan unit yang dipilih.');
        } else {
            abort_unless(blank($schedule->detail_rumah_id), 422, 'Untuk progress kawasan, pilih jadwal kawasan.');
        }
        $this->ensureScheduleItemCapacity($schedule->id, $validated['schedule_stage_key'], $validated['schedule_item_key'], (float) $validated['persentase'], $progress->id);
        $fotoPath = $progress->foto;
        $oldDetailRumahId = $progress->detail_rumah_id;
        $oldTahapanPembangunanId = $progress->tahapan_pembangunan_id;
        $oldSiteScheduleId = $progress->site_schedule_id;
        $oldNamaProgress = $progress->nama_progress;
        $oldApprovalStatus = $progress->approval_status;

        if ($request->hasFile('foto')) {
            if ($fotoPath) {
                Storage::disk('public')->delete($fotoPath);
            }

            $fotoPath = $request->file('foto')->store('progress-pembangunan', 'public');
        }

        $approvalRequired = $this->requiresApprovalFor('progress', 'update');

        DB::transaction(function () use (
            $progress,
            $rumah,
            $schedule,
            $scheduleStage,
            $scheduleItem,
            $validated,
            $fotoPath,
            $approvalRequired,
            $oldApprovalStatus,
            $oldDetailRumahId,
            $oldTahapanPembangunanId,
            $oldSiteScheduleId,
            $oldNamaProgress,
        ): void {
            $progress->update([
                'detail_rumah_id' => $rumah?->id,
                'tahapan_pembangunan_id' => null,
                'site_schedule_id' => $schedule->id,
                'schedule_stage_key' => $scheduleStage['key'],
                'schedule_stage_name' => $scheduleStage['name'],
                'schedule_stage_target' => $scheduleStage['target'],
                'schedule_item_key' => $scheduleItem['key'],
                'schedule_item_name' => $scheduleItem['name'],
                'schedule_item_target' => $scheduleItem['target'],
                'nama_progress' => $scheduleItem['name'],
                'tanggal' => $validated['tanggal'],
                'tahapan' => $scheduleItem['target'],
                'persentase' => $validated['persentase'],
                'persentase_total' => ((float) $validated['persentase'] / 100) * (float) $scheduleItem['target'],
                'keterangan' => $validated['keterangan'],
                'foto' => $fotoPath,
                'approval_status' => $approvalRequired ? 'menunggu_approval_manager' : 'approved',
                'approved_by' => $approvalRequired ? null : auth()->id(),
                'approved_at' => $approvalRequired ? null : now(),
                'approved_note' => null,
                'updated_by' => auth()->id(),
            ]);

            $this->replaceMaterialUsagesFromRequests($progress->fresh(['detailRumah', 'siteSchedule']), $validated['material_request_ids'] ?? [], $validated['material_usage_items'] ?? [], $rumah?->perumahan_id ?? $schedule->perumahan_id);
            $this->upsertSiteReportForProgress($progress->fresh(['detailRumah', 'siteSchedule']), $validated['site_report'] ?? [], $approvalRequired, request());

            if ($oldApprovalStatus === 'approved') {
                $this->recalculateRumahProgress(DetailRumah::query()->find($oldDetailRumahId));
                $this->syncSiteSchedulesFor($oldDetailRumahId, $oldTahapanPembangunanId, $oldSiteScheduleId, $oldNamaProgress);
            } elseif (! $approvalRequired) {
                $this->recalculateRumahProgress($progress->detailRumah);
                $this->syncSiteSchedules($progress->fresh());
            }
        });

        return back()->with('success', $approvalRequired
            ? 'Progress pembangunan berhasil diperbarui dan menunggu approval manajer.'
            : 'Progress pembangunan berhasil diperbarui dan langsung aktif.');
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless($this->canDeleteProgress(), 403, 'Hanya pengawas yang dapat menghapus progress pembangunan.');
        $progress = ProgressPembangunan::query()->with('materialUsages.details.siteMaterialStock')->findOrFail($id);
        $this->abortIfLocked($progress);

        if ($progress->foto) {
            Storage::disk('public')->delete($progress->foto);
        }

        $detailRumahId = $progress->detail_rumah_id;
        $tahapanPembangunanId = $progress->tahapan_pembangunan_id;
        $siteScheduleId = $progress->site_schedule_id;
        $namaProgress = $progress->nama_progress;
        $approvalStatus = $progress->approval_status;

        $usageService = app(MaterialHppRealizationService::class);
        foreach ($progress->materialUsages as $usage) {
            $usageService->removeForUsage($usage);

            foreach ($usage->details as $detail) {
                $detail->siteMaterialStock()->increment('qty_available', $detail->qty);
                $detail->siteMaterialStock()->decrement('qty_used', $detail->qty);
            }

            $usage->delete();
        }

        MaterialRequest::query()
            ->where('progress_pembangunan_id', $progress->id)
            ->update([
                'progress_pembangunan_id' => null,
                'updated_by' => auth()->id(),
            ]);

        SiteReport::query()
            ->where('progress_pembangunan_id', $progress->id)
            ->delete();

        $progress->delete();

        if ($approvalStatus === 'approved') {
            $this->recalculateRumahProgress(DetailRumah::query()->find($detailRumahId));
            $this->syncSiteSchedulesFor($detailRumahId, $tahapanPembangunanId, $siteScheduleId, $namaProgress);
        }

        return back()->with('success', 'Progress pembangunan berhasil dihapus.');
    }

    public function lock(string $id): RedirectResponse
    {
        abort_unless(auth()->check(), 403, 'Silakan login untuk mengunci progress.');

        $response = $this->traitLock($id);

        SiteReport::query()
            ->where('progress_pembangunan_id', $id)
            ->update([
                'record_status' => 'locked',
                'locked_at' => now(),
                'locked_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

        return $response;
    }

    public function unlock(string $id): RedirectResponse
    {
        $this->authorizeManageProgressLock();

        $response = $this->traitUnlock($id);

        SiteReport::query()
            ->where('progress_pembangunan_id', $id)
            ->update([
                'record_status' => 'draft',
                'locked_at' => null,
                'locked_by' => null,
                'updated_by' => auth()->id(),
            ]);

        return $response;
    }

    public function approve(string $id): RedirectResponse
    {
        abort_unless($this->requiresApprovalFor('progress'), 422, 'Progress ini tidak memerlukan approval.');
        abort_unless($this->canApproveFor('progress'), 403, 'Anda tidak memiliki izin approval progress.');

        $progress = ProgressPembangunan::query()->with(['detailRumah', 'siteSchedule'])->findOrFail($id);
        abort_unless(($progress->record_status ?? 'draft') === 'locked', 422, 'Progress harus di-lock terlebih dahulu.');

        if ($progress->approval_status === 'approved') {
            return back()->with('success', 'Progress pembangunan sudah disetujui sebelumnya.');
        }
        $this->ensureScheduleItemCapacity(
            $progress->site_schedule_id,
            $progress->schedule_stage_key,
            $progress->schedule_item_key,
            (float) $progress->persentase,
            $progress->id,
        );

        DB::transaction(function () use ($progress) {
            $progress->update([
                'approval_status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            $this->recalculateRumahProgress($progress->detailRumah);
            $this->syncSiteSchedules($progress);
            $this->syncSiteReportApproval($progress);
        });

        return back()->with('success', 'Progress pembangunan berhasil disetujui.');
    }

    private function upsertSiteReportForProgress(ProgressPembangunan $progress, array $payload, bool $approvalRequired, Request $request): void
    {
        $progress->loadMissing(['detailRumah', 'siteSchedule']);

        $existing = SiteReport::query()
            ->where('progress_pembangunan_id', $progress->id)
            ->first();

        $lampiran = $existing?->lampiran;
        if ($request->hasFile('site_report.lampiran')) {
            if ($lampiran) {
                Storage::disk('public')->delete($lampiran);
            }

            $lampiran = $request->file('site_report.lampiran')->store('laporan-lapangan', 'public');
        }

        $reportApprovalStatus = $approvalRequired ? 'menunggu_approval_manager' : 'approved';

        SiteReport::query()->updateOrCreate(
            ['progress_pembangunan_id' => $progress->id],
            [
                'kode_laporan' => $existing?->kode_laporan ?? $this->generateSiteReportCode(),
                'jenis_laporan' => 'progress_pembangunan',
                'tanggal' => $progress->tanggal?->toDateString() ?? now()->toDateString(),
                'periode_mulai' => null,
                'periode_selesai' => null,
                'perumahan_id' => $progress->detailRumah?->perumahan_id ?? $progress->siteSchedule?->perumahan_id,
                'detail_rumah_id' => $progress->detail_rumah_id,
                'tahapan_pembangunan_id' => null,
                'site_schedule_id' => $progress->site_schedule_id,
                'cuaca' => $payload['cuaca'] ?? null,
                'jumlah_pekerja' => (int) ($payload['jumlah_pekerja'] ?? 0),
                'kontraktor' => $payload['kontraktor'] ?? null,
                'pekerjaan_selesai' => filled($payload['pekerjaan_selesai'] ?? null)
                    ? $payload['pekerjaan_selesai']
                    : ($progress->keterangan ?: $progress->nama_progress),
                'pekerjaan_tertahan' => $payload['pekerjaan_tertahan'] ?? null,
                'kendala' => $payload['kendala'] ?? null,
                'koordinasi' => $payload['koordinasi'] ?? null,
                'rencana_berikutnya' => $payload['rencana_berikutnya'] ?? null,
                'lampiran' => $lampiran,
                'approval_status' => $reportApprovalStatus,
                'approved_by' => $approvalRequired ? null : auth()->id(),
                'approved_at' => $approvalRequired ? null : now(),
                'record_status' => $progress->record_status ?? 'draft',
                'locked_at' => $progress->locked_at,
                'locked_by' => $progress->locked_by,
                'created_by' => $existing?->created_by ?? auth()->id(),
                'updated_by' => auth()->id(),
            ],
        );
    }

    private function syncSiteReportApproval(ProgressPembangunan $progress): void
    {
        SiteReport::query()
            ->where('progress_pembangunan_id', $progress->id)
            ->update([
                'approval_status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'updated_by' => auth()->id(),
            ]);
    }

    private function generateSiteReportCode(): string
    {
        $prefix = 'LL-'.now()->format('Ymd').'-';
        $count = SiteReport::withTrashed()
            ->where('kode_laporan', 'like', $prefix.'%')
            ->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    protected function recalculateRumahProgress(?DetailRumah $rumah): void
    {
        if (! $rumah) {
            return;
        }

        $progressTotal = ProgressPembangunan::query()
            ->where('detail_rumah_id', $rumah->id)
            ->where('approval_status', 'approved')
            ->sum('persentase_total');

        $rumah->update([
            'progress_terakhir' => min(100, $progressTotal),
            'status_pembangunan' => $progressTotal >= 100 ? 'selesai' : 'sedang_dibangun',
            'updated_by' => auth()->id(),
        ]);
    }

    protected function syncSiteSchedules(ProgressPembangunan $progress): void
    {
        $this->syncSiteSchedulesFor($progress->detail_rumah_id, $progress->tahapan_pembangunan_id, $progress->site_schedule_id, $progress->nama_progress);
    }

    protected function syncSiteSchedulesFor(mixed $detailRumahId, mixed $tahapanPembangunanId, mixed $siteScheduleId = null, ?string $namaProgress = null): void
    {
        $scheduleQuery = SiteSchedule::query();

        if (filled($siteScheduleId)) {
            $scheduleQuery->whereKey($siteScheduleId);
        } else {
            $scheduleQuery
                ->where('detail_rumah_id', $detailRumahId)
                ->where('tahapan_pembangunan_id', $tahapanPembangunanId)
                ->where('nama_pekerjaan', $namaProgress);
        }

        $scheduleQuery->get()
            ->each(function (SiteSchedule $schedule): void {
                $approvedProgress = ProgressPembangunan::query()
                    ->where('site_schedule_id', $schedule->id)
                    ->where('approval_status', 'approved')
                    ->get(['id', 'schedule_item_key', 'schedule_item_target', 'persentase']);

                $approvedPercent = $approvedProgress->sum(function (ProgressPembangunan $progress) use ($schedule): float {
                    return ((float) $progress->persentase / 100) * $this->scheduleTargetForItem($schedule, $progress->schedule_item_key, (float) $progress->schedule_item_target);
                });

                $realisasi = min(100, (float) $approvedPercent);
                $target = (float) ($schedule->target_progress ?? 100);
                $status = $this->scheduleStatus($schedule, $realisasi, $target);

                $schedule->update([
                    'realisasi_progress' => $realisasi,
                    'status' => $status,
                    'updated_by' => auth()->id(),
                ]);
            });
    }

    protected function scheduleStatus(SiteSchedule $schedule, float $realisasi, float $target): string
    {
        if ($realisasi >= $target) {
            return 'selesai';
        }

        if (($schedule->status ?? null) === 'tertahan') {
            return 'tertahan';
        }

        if ($schedule->tanggal_target?->isPast()) {
            return 'terlambat';
        }

        return $realisasi > 0 ? 'berjalan' : 'direncanakan';
    }

    protected function authorizePengawasOnly(): void
    {
        abort_unless($this->canCreateProgress(), 403, 'Hanya pengawas yang dapat menginput progress pembangunan.');
    }

    protected function canApprove(): bool
    {
        return $this->canApproveFor('progress');
    }

    protected function canManageDraftRows(): bool
    {
        return (bool) auth()->user()?->can('progress.unlock');
    }

    protected function authorizeManageProgressLock(): void
    {
        abort_unless(auth()->user()?->can('progress.unlock'), 403, 'Hanya user yang diberi akses yang dapat mengunci progress.');
    }

    protected function canCreateProgress(): bool
    {
        return (bool) auth()->user()?->can('progress.create');
    }

    protected function canUpdateProgress(): bool
    {
        return (bool) auth()->user()?->can('progress.update');
    }

    protected function canDeleteProgress(): bool
    {
        return (bool) auth()->user()?->can('progress.delete');
    }

    protected function canLock(): bool
    {
        return (bool) auth()->check();
    }

    protected function ensureTahapanCapacity(int|string|null $detailRumahId, int|string $tahapanPembangunanId, float $incomingPercent, ?int $ignoreId = null): void
    {
        $tahapan = TahapanPembangunan::query()->findOrFail($tahapanPembangunanId);

        $currentApproved = ProgressPembangunan::query()
            ->when($detailRumahId === null, fn ($query) => $query->whereNull('detail_rumah_id'), fn ($query) => $query->where('detail_rumah_id', $detailRumahId))
            ->where('tahapan_pembangunan_id', $tahapanPembangunanId)
            ->where('approval_status', 'approved')
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->sum('persentase');

        $nextTotal = $currentApproved + $incomingPercent;

        if ($currentApproved >= 100) {
            throw ValidationException::withMessages([
                'tahapan_pembangunan_id' => 'Tahapan '.$tahapan->nama_tahapan.' sudah selesai, progress baru tidak bisa ditambahkan.',
            ]);
        }

        if ($nextTotal > 100) {
            throw ValidationException::withMessages([
                'persentase' => 'Total progress tahapan '.$tahapan->nama_tahapan.' tidak boleh melebihi 100%. Sisa yang tersedia hanya '.number_format(max(0, 100 - $currentApproved), 2, ',', '.').'%'.'',
            ]);
        }
    }

    protected function modelClass(): string
    {
        return ProgressPembangunan::class;
    }

    protected function approvalLabel(?string $status): string
    {
        return match ($status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Menunggu Approval',
        };
    }

    private function siteScheduleOptions(?string $perumahanId = null, ?string $detailRumahId = null)
    {
        return SiteSchedule::query()
            ->with('perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah', 'spkKontraktor.items')
            ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
            ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
            ->orderBy('tanggal_target')
            ->get(['id', 'perumahan_id', 'detail_rumah_id', 'spk_kontraktor_id', 'tahapan_pembangunan_id', 'spk_plan_json', 'nama_pekerjaan', 'target_progress', 'realisasi_progress', 'status'])
            ->map(fn (SiteSchedule $row) => $this->siteScheduleOptionPayload($row))
            ->values();
    }

    private function siteScheduleOptionPayload(SiteSchedule $row): array
    {
        $location = $row->detailRumah ? trim(($row->detailRumah->kode_nlok ?? '').' '.($row->detailRumah->nomor_rumah ?? '')) : 'Kawasan';
        $stages = $this->scheduleStages($row);

        return [
            'value' => (string) $row->id,
            'label' => $location.' - '.$row->nama_pekerjaan.' (realisasi '.$row->realisasi_progress.'%)',
            'perumahan_id' => (string) $row->perumahan_id,
            'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
            'nama_pekerjaan' => $row->nama_pekerjaan,
            'stages' => $stages,
        ];
    }

    private function scheduleStages(SiteSchedule $schedule): array
    {
        if (blank($schedule->spk_plan_json)) {
            $stageKey = 'stage:'.$schedule->id;
            $stageName = $this->stripStagePrefix($schedule->nama_pekerjaan);

            return [[
                'value' => $stageKey,
                'label' => $stageName.' (target '.(float) $schedule->target_progress.'%)',
                'key' => $stageKey,
                'name' => $stageName,
                'target' => (float) $schedule->target_progress,
                'items' => [[
                    'value' => 'item:'.$schedule->id,
                    'label' => $stageName.' (target '.(float) $schedule->target_progress.'%)',
                    'key' => 'item:'.$schedule->id,
                    'name' => $stageName,
                    'target' => (float) $schedule->target_progress,
                ]],
            ]];
        }

        return collect($schedule->spk_plan_json)
            ->map(function (array $planRow, int $index) use ($schedule) {
                $stageName = $this->stripStagePrefix((string) ($planRow['nama_tahap_pekerjaan'] ?? 'Tahap '.($index + 1)));
                $stageKey = (string) ($planRow['key'] ?? 'stage:'.$schedule->id.':'.$this->stageNameKey($stageName));
                $stageTarget = (float) ($planRow['target_progress'] ?? 0);
                $rawItems = collect($planRow['items'] ?? []);
                if ($rawItems->isEmpty() && $schedule->spkKontraktor) {
                    $stageKeyForMatch = $this->stageNameKey($stageName);
                    $rawItems = $schedule->spkKontraktor->items
                        ->filter(fn ($item) => $this->stageNameKey((string) $item->nama_tahap_pekerjaan) === $stageKeyForMatch)
                        ->sortBy('urutan')
                        ->map(fn ($item) => [
                            'id' => $item->id,
                            'nama_pekerjaan' => $item->nama_pekerjaan,
                            'harga_satuan' => $item->harga_satuan,
                            'total' => $item->total,
                        ])
                        ->values();
                }

                $stageTotal = max(0, (float) ($planRow['group_total'] ?? $rawItems->sum(fn ($item) => (float) ($item['total'] ?? $item['harga_satuan'] ?? 0))));
                $fallbackCount = max(1, $rawItems->count());

                return [
                    'value' => $stageKey,
                    'label' => $stageName.' (target '.$stageTarget.'%)',
                    'key' => $stageKey,
                    'name' => $stageName,
                    'target' => $stageTarget,
                    'items' => $rawItems->isEmpty()
                        ? [[
                            'value' => $stageKey.':item:1',
                            'label' => $stageName.' (target '.$stageTarget.'%)',
                            'key' => $stageKey.':item:1',
                            'name' => $stageName,
                            'target' => $stageTarget,
                        ]]
                        : $rawItems->values()->map(function (array $workItem, int $itemIndex) use ($schedule, $stageKey, $stageTarget, $stageTotal, $fallbackCount) {
                            $itemName = $this->stripStagePrefix((string) ($workItem['nama_pekerjaan'] ?? 'Item '.($itemIndex + 1)));
                            $itemValue = (float) ($workItem['total'] ?? $workItem['harga_satuan'] ?? 0);
                            $itemTarget = $stageTotal > 0
                                ? round(($itemValue / $stageTotal) * $stageTarget, 2)
                                : round($stageTarget / $fallbackCount, 2);
                            $itemKey = 'item:'.$schedule->id.':'.$stageKey.':'.$this->stageNameKey($itemName).':'.$itemIndex;

                            return [
                                'value' => $itemKey,
                                'label' => $itemName.' (target '.$itemTarget.'%)',
                                'key' => $itemKey,
                                'name' => $itemName,
                                'target' => $itemTarget,
                            ];
                        })->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function scheduleStageByKey(SiteSchedule $schedule, ?string $key): ?array
    {
        if (blank($key)) {
            return null;
        }

        return collect($this->scheduleStages($schedule))
            ->first(fn (array $stage) => (string) $stage['key'] === (string) $key);
    }

    private function scheduleItemByKey(SiteSchedule $schedule, ?string $stageKey, ?string $itemKey): ?array
    {
        $stage = $this->scheduleStageByKey($schedule, $stageKey);

        if (! $stage || blank($itemKey)) {
            return null;
        }

        return collect($stage['items'] ?? [])
            ->first(fn (array $item) => (string) $item['key'] === (string) $itemKey);
    }

    private function scheduleTargetForItem(SiteSchedule $schedule, ?string $key, float $fallback = 0): float
    {
        $item = collect($this->scheduleStages($schedule))
            ->flatMap(fn (array $stage) => $stage['items'] ?? [])
            ->first(fn (array $item) => (string) $item['key'] === (string) $key);

        return (float) ($item['target'] ?? $fallback);
    }

    private function ensureScheduleItemCapacity(int|string|null $siteScheduleId, ?string $scheduleStageKey, ?string $scheduleItemKey, float $incomingPercent, ?int $ignoreId = null): void
    {
        $currentApproved = ProgressPembangunan::query()
            ->where('site_schedule_id', $siteScheduleId)
            ->where('schedule_stage_key', $scheduleStageKey)
            ->where('schedule_item_key', $scheduleItemKey)
            ->where('approval_status', 'approved')
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->sum('persentase');

        $nextTotal = $currentApproved + $incomingPercent;

        if ($currentApproved >= 100) {
            throw ValidationException::withMessages([
                'schedule_item_key' => 'Item jadwal kerja ini sudah selesai, progress baru tidak bisa ditambahkan.',
            ]);
        }

        if ($nextTotal > 100) {
            throw ValidationException::withMessages([
                'persentase' => 'Total progress item jadwal tidak boleh melebihi 100%. Sisa yang tersedia hanya '.number_format(max(0, 100 - $currentApproved), 2, ',', '.').'%',
            ]);
        }
    }

    private function stageScopeKey(mixed $perumahanId = null, mixed $detailRumahId = null): string
    {
        return $detailRumahId ? 'unit:'.$detailRumahId : 'perumahan:'.$perumahanId;
    }

    private function stageNameKey(string $name): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper($this->stripStagePrefix($name)));
    }

    private function stripStagePrefix(?string $name): string
    {
        $value = trim((string) $name);
        $value = preg_replace('/^\s*[IVXLCDM]+\s*[\.\-\)]\s*/i', '', $value) ?? $value;
        $value = preg_replace('/^PEK\.?\s*/i', 'PEK ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function replaceMaterialUsagesFromRequests(ProgressPembangunan $progress, array $materialRequestIds, array $usageItems, int|string|null $perumahanId = null): void
    {
        $usageService = app(MaterialHppRealizationService::class);

        MaterialRequest::query()
            ->where('progress_pembangunan_id', $progress->id)
            ->update([
                'progress_pembangunan_id' => null,
                'updated_by' => auth()->id(),
            ]);

        $existingUsages = MaterialUsage::query()
            ->with('details.siteMaterialStock')
            ->where('progress_pembangunan_id', $progress->id)
            ->get();

        foreach ($existingUsages as $usage) {
            $usageService->removeForUsage($usage);

            foreach ($usage->details as $detail) {
                $detail->siteMaterialStock()->increment('qty_available', $detail->qty);
                $detail->siteMaterialStock()->decrement('qty_used', $detail->qty);
            }

            $usage->delete();
        }

        if ($materialRequestIds === []) {
            return;
        }

        $workflow = app(MaterialWorkflowService::class);

        $requests = MaterialRequest::query()
            ->whereNotNull('approved_at_gudang')
            ->whereNotNull('approved_at_owner')
            ->whereNotNull('issued_at')
            ->whereKey($materialRequestIds)
            ->get();

        if ($requests->count() !== count($materialRequestIds)) {
            throw ValidationException::withMessages([
                'material_request_ids' => 'Sebagian permintaan material belum siap dipakai atau sudah tidak tersedia.',
            ]);
        }

        $occupiedRequest = $requests
            ->first(fn (MaterialRequest $request) => filled($request->progress_pembangunan_id) && (int) $request->progress_pembangunan_id !== (int) $progress->id);

        if ($occupiedRequest) {
            throw ValidationException::withMessages([
                'material_request_ids' => "Permintaan {$occupiedRequest->kode_request} sudah dipakai pada progress lain.",
            ]);
        }

        $invalidRequest = $requests->first(function (MaterialRequest $request) use ($progress, $perumahanId) {
            $expectedPerumahanId = (string) ($perumahanId ?? $progress->detailRumah?->perumahan_id ?? $progress->siteSchedule?->perumahan_id ?? '');

            if ((string) ($request->perumahan_id ?? '') !== $expectedPerumahanId) {
                return true;
            }

            if ((string) ($request->detail_rumah_id ?? '') !== (string) ($progress->detail_rumah_id ?? '')) {
                return true;
            }

            return false;
        });

        if ($invalidRequest) {
            throw ValidationException::withMessages([
                'material_request_ids' => "Permintaan {$invalidRequest->kode_request} tidak sesuai dengan perumahan atau unit progress yang dipilih.",
            ]);
        }

        $items = collect($usageItems)
            ->filter(fn (array $item) => (float) ($item['qty'] ?? 0) > 0)
            ->values();

        if ($items->isEmpty()) {
            foreach ($requests as $request) {
                $request->update([
                    'progress_pembangunan_id' => $progress->id,
                    'updated_by' => auth()->id(),
                ]);
            }

            return;
        }

        $requestDetailIds = $items->pluck('material_request_detail_id')->map(fn ($id) => (int) $id)->unique()->values();
        $requestDetails = MaterialRequestDetail::query()
            ->with(['materialRequest', 'barangMaterial:id,nama_barang,harga_hpp,satuan'])
            ->whereIn('id', $requestDetailIds)
            ->get()
            ->keyBy('id');

        $requestIds = $requests->pluck('id')->map(fn ($id) => (int) $id)->all();
        $hppItemIds = $items->pluck('detail_rumah_hpp_item_id')->map(fn ($id) => (int) $id)->unique()->values();
        $hppItems = DetailRumahHppItem::query()
            ->with('detailRumahHpp:id,detail_rumah_id')
            ->whereIn('id', $hppItemIds)
            ->get()
            ->keyBy('id');

        $payloadItems = $items->map(function (array $item) use ($requestDetails, $requestIds, $hppItems, $progress) {
            $detail = $requestDetails->get((int) $item['material_request_detail_id']);
            if (! $detail || ! in_array((int) $detail->material_request_id, $requestIds, true)) {
                throw ValidationException::withMessages([
                    'material_usage_items' => 'Item pemakaian tidak sesuai dengan permintaan material yang dipilih.',
                ]);
            }

            $hppItem = $hppItems->get((int) $item['detail_rumah_hpp_item_id']);
            if (! $hppItem || (string) ($hppItem->detailRumahHpp?->detail_rumah_id ?? '') !== (string) ($progress->detail_rumah_id ?? '')) {
                throw ValidationException::withMessages([
                    'material_usage_items' => 'Item HPP/RAB yang dipilih tidak sesuai dengan unit progress.',
                ]);
            }

            $qty = (float) ($item['qty'] ?? 0);
            $availableQty = (float) ($detail->qty_issued > 0 ? $detail->qty_issued : $detail->qty);
            if ($qty > $availableQty) {
                throw ValidationException::withMessages([
                    'material_usage_items' => 'Jumlah pemakaian '.$detail->barangMaterial?->nama_barang.' tidak boleh melebihi jumlah material yang keluar.',
                ]);
            }

            return [
                'site_material_stock_id' => $this->resolveSiteMaterialStockIdForRequestDetail($detail),
                'detail_rumah_hpp_item_id' => $hppItem->id,
                'qty' => $qty,
                'satuan' => $item['satuan'] ?? $detail->satuan ?? $detail->barangMaterial?->satuan ?? '-',
            ];
        })->values()->all();

        $usage = $workflow->recordUsage([
            'tanggal' => $progress->tanggal?->toDateString() ?? now()->toDateString(),
            'perumahan_id' => $perumahanId ?? $progress->detailRumah?->perumahan_id ?? $progress->siteSchedule?->perumahan_id,
            'detail_rumah_id' => $progress->detail_rumah_id,
            'progress_pembangunan_id' => $progress->id,
            'keterangan' => 'Pemakaian dari progress '.$progress->nama_progress,
            'items' => $payloadItems,
        ]);

        $usageService->syncFromUsage($usage);

        foreach ($requests as $request) {
            $request->update([
                'progress_pembangunan_id' => $progress->id,
                'updated_by' => auth()->id(),
            ]);
        }
    }

    private function resolveSiteMaterialStockIdForRequestDetail(MaterialRequestDetail $detail): int
    {
        $request = $detail->materialRequest;

        $query = SiteMaterialStock::query()
            ->where('gudang_id', $request->gudang_id)
            ->where('perumahan_id', $request->perumahan_id)
            ->where('barang_material_id', $detail->barang_material_id);

        filled($request->detail_rumah_id)
            ? $query->where('detail_rumah_id', $request->detail_rumah_id)
            : $query->whereNull('detail_rumah_id');

        filled($request->tahapan_pembangunan_id)
            ? $query->where('tahapan_pembangunan_id', $request->tahapan_pembangunan_id)
            : $query->whereNull('tahapan_pembangunan_id');

        $siteStockId = $query->lockForUpdate()->value('id');

        if (! $siteStockId) {
            throw ValidationException::withMessages([
                'material_usage_items' => 'Stok lokasi untuk '.$detail->barangMaterial?->nama_barang.' belum ditemukan.',
            ]);
        }

        return (int) $siteStockId;
    }

    private function approvedMaterialRequestOptions(?string $perumahanId = null, ?string $detailRumahId = null)
    {
        return MaterialRequest::query()
            ->with([
                'detailRumah.perumahan:id,nama_perusahaan',
                'detailRumah:id,perumahan_id,kode_nlok,nomor_rumah',
                'details.barangMaterial:id,kode_barang,nama_barang,satuan,harga_hpp',
                'tahapanPembangunan:id,nama_tahapan',
            ])
            ->whereNotNull('approved_at_gudang')
            ->whereNotNull('approved_at_owner')
            ->whereNotNull('issued_at')
            ->whereNull('progress_pembangunan_id')
            ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
            ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
            ->latest('tanggal')
            ->get(['id', 'kode_request', 'perumahan_id', 'detail_rumah_id', 'tahapan_pembangunan_id', 'gudang_id', 'tanggal', 'issued_at'])
            ->map(fn (MaterialRequest $row) => [
                'value' => (string) $row->id,
                'label' => $row->kode_request.' - '.($row->detailRumah ? trim(($row->detailRumah->kode_nlok ?? '').' '.($row->detailRumah->nomor_rumah ?? '')) : 'Kawasan'),
                'perumahan_id' => (string) ($row->perumahan_id ?? ''),
                'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                'gudang_id' => (string) ($row->gudang_id ?? ''),
                'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                'issued_at' => optional($row->issued_at)->format('Y-m-d H:i'),
                'perumahan_label' => $row->detailRumah ? ($row->detailRumah->perumahan?->nama_perusahaan ?? '-') : 'Kawasan',
                'unit_label' => $row->detailRumah ? trim(($row->detailRumah->kode_nlok ?? '').' '.($row->detailRumah->nomor_rumah ?? '')) : 'Kawasan',
                'tahapan_label' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
                'items_text' => $row->details->map(fn ($detail) => ($detail->barangMaterial?->nama_barang ?? '-').' x'.number_format((float) ($detail->qty_issued > 0 ? $detail->qty_issued : $detail->qty), 2, ',', '.'))->join(', '),
                'details' => $row->details->map(fn ($detail) => [
                    'id' => (string) $detail->id,
                    'material_request_id' => (string) $row->id,
                    'barang_material_id' => (string) $detail->barang_material_id,
                    'kode_barang' => $detail->barangMaterial?->kode_barang ?? '',
                    'nama_barang' => $detail->barangMaterial?->nama_barang ?? '-',
                    'qty_request' => (float) $detail->qty,
                    'qty_issued' => (float) ($detail->qty_issued > 0 ? $detail->qty_issued : $detail->qty),
                    'satuan' => $detail->satuan ?: ($detail->barangMaterial?->satuan ?? '-'),
                    'harga_hpp' => (float) ($detail->barangMaterial?->harga_hpp ?? 0),
                ])->values(),
            ])
            ->values();
    }

    private function hppItemOptions()
    {
        $realisasiByItem = HppRealisasi::query()
            ->whereNotNull('detail_rumah_hpp_item_id')
            ->select('detail_rumah_hpp_item_id', DB::raw('SUM(nominal) as total_realisasi'))
            ->groupBy('detail_rumah_hpp_item_id')
            ->pluck('total_realisasi', 'detail_rumah_hpp_item_id');

        return DetailRumahHppItem::query()
            ->with([
                'detailRumahHpp:id,detail_rumah_id',
                'tahapanPembangunan:id,nama_tahapan',
                'barangMaterial:id,kode_barang,nama_barang,satuan',
            ])
            ->orderBy('urutan')
            ->get()
            ->map(function (DetailRumahHppItem $item) use ($realisasiByItem) {
                $rab = (float) ($item->jumlah_rab ?? 0);
                $realisasi = (float) ($realisasiByItem[$item->id] ?? 0);
                $sisa = max(0, $rab - $realisasi);

                return [
                    'value' => (string) $item->id,
                    'label' => trim(($item->tahapanPembangunan?->nama_tahapan ? $item->tahapanPembangunan->nama_tahapan.' - ' : '').($item->nama_pekerjaan ?: $item->barangMaterial?->nama_barang)),
                    'detail_rumah_id' => (string) ($item->detailRumahHpp?->detail_rumah_id ?? ''),
                    'barang_material_id' => (string) ($item->barang_material_id ?? ''),
                    'nama_pekerjaan' => $item->nama_pekerjaan,
                    'nama_material' => $item->barangMaterial?->nama_barang ?? '-',
                    'satuan' => $item->satuan ?: ($item->barangMaterial?->satuan ?? '-'),
                    'harga_satuan' => (float) $item->harga_satuan,
                    'jumlah_rab' => $rab,
                    'realisasi' => $realisasi,
                    'sisa_anggaran' => $sisa,
                    'progress' => $rab > 0 ? min(100, round(($realisasi / $rab) * 100, 2)) : 0,
                ];
            })
            ->values();
    }
}
