<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsFieldOptions;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\UsesApprovalSettings;
use App\Http\Controllers\Controller;
use App\Models\FieldDefect;
use App\Models\ProgressPembangunan;
use App\Models\QualityInspection;
use App\Models\SiteSchedule;
use App\Services\TahapanOptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class QualityInspectionController extends Controller
{
    use BuildsFieldOptions, HandlesCrudLock, UsesApprovalSettings {
        HandlesCrudLock::lock as protected traitLock;
        HandlesCrudLock::unlock as protected traitUnlock;
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perumahanId = $request->query('perumahan_id');
        $detailRumahId = $request->query('detail_rumah_id');

        return Inertia::render('Admin/QualityInspection/Index', [
            'title' => 'Kontrol Kualitas',
            'baseUrl' => route('admin.quality-inspection.index', absolute: false),
            'permissions' => [
                'canCreate' => $this->canQualityInspection('create'),
                'canUpdate' => $this->canQualityInspection('update'),
                'canDelete' => $this->canQualityInspection('delete'),
                'canLock' => $this->canQualityInspection('update'),
                'canUnlock' => $this->canQualityInspection('unlock') || $this->currentUserCanManageLockedRecords(),
            ],
            'filters' => ['search' => $search, 'perumahan_id' => $perumahanId, 'detail_rumah_id' => $detailRumahId],
            'options' => $this->options(),
            'rows' => QualityInspection::query()
                ->with([
                    'perumahan:id,nama_perusahaan',
                    'detailRumah:id,kode_nlok,nomor_rumah',
                    'tahapanPembangunan:id,nama_tahapan',
                    'siteSchedule:id,kode_jadwal,nama_pekerjaan',
                    'progressPembangunan:id,nama_progress',
                    'creator:id,name',
                    'updater:id,name',
                    'approvedBy:id,name',
                ])
                ->when($search !== '', fn (Builder $query) => $query
                    ->where('kode_inspeksi', 'like', "%{$search}%")
                    ->orWhere('item_pemeriksaan', 'like', "%{$search}%")
                    ->orWhere('temuan', 'like', "%{$search}%"))
                ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
                ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
                ->latest('tanggal')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (QualityInspection $row) => [
                    'id' => $row->id,
                    'kode_inspeksi' => $row->kode_inspeksi,
                    'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                    'perumahan_id' => (string) $row->perumahan_id,
                    'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                    'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                    'site_schedule_id' => (string) ($row->site_schedule_id ?? ''),
                    'progress_pembangunan_id' => (string) ($row->progress_pembangunan_id ?? ''),
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'unit' => $row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : 'Kawasan',
                    'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
                    'jadwal' => $row->siteSchedule?->nama_pekerjaan ?? '-',
                    'progress' => $row->progressPembangunan?->nama_progress ?? '-',
                    'hasil' => $row->hasil,
                    'item_pemeriksaan' => $row->item_pemeriksaan,
                    'temuan' => $row->temuan,
                    'tindakan_perbaikan' => $row->tindakan_perbaikan,
                    'target_selesai' => optional($row->target_selesai)->format('Y-m-d'),
                    'status' => $row->status,
                    'foto_url' => $row->foto ? route('media', ['path' => $row->foto], false) : null,
                    'approval_status' => $row->approval_status,
                    'created_by_name' => $row->creator?->name ?? '-',
                    'updated_by_name' => $row->updater?->name ?? '-',
                    'approved_by_name' => $row->approvedBy?->name ?? '-',
                    'can_approve' => ($row->record_status ?? 'draft') === 'locked' && $this->requiresApprovalFor('quality-inspection') && $row->approval_status !== 'approved' && $this->canApproveFor('quality-inspection'),
                    'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && $this->canQualityInspection('update'),
                    'can_unlock' => $this->canQualityInspection('unlock') || $this->currentUserCanManageLockedRecords(),
                    'can_edit' => ($row->record_status ?? 'draft') !== 'locked' && $this->canQualityInspection('update'),
                    'can_delete' => ($row->record_status ?? 'draft') !== 'locked' && $this->canQualityInspection('delete'),
                    'record_status' => $row->record_status ?? 'draft',
                ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorizeQualityInspection('create');

        return Inertia::render('Admin/QualityInspection/Index', [
            'title' => 'Buat Kontrol Kualitas',
            'baseUrl' => route('admin.quality-inspection.index', absolute: false),
            'formPage' => true,
            'options' => $this->options(),
            'permissions' => ['canCreate' => true, 'canUpdate' => false],
        ]);
    }

    public function edit(string $id): Response
    {
        $this->authorizeQualityInspection('update');
        $row = QualityInspection::query()->findOrFail($id);
        $this->abortIfLocked($row);

        return Inertia::render('Admin/QualityInspection/Index', [
            'title' => 'Edit Kontrol Kualitas',
            'baseUrl' => route('admin.quality-inspection.index', absolute: false),
            'formPage' => true,
            'editingRow' => $this->formRow($row),
            'options' => $this->options(),
            'permissions' => ['canCreate' => false, 'canUpdate' => true],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeQualityInspection('create');
        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'site_schedule_id' => ['nullable', 'exists:site_schedules,id'],
            'progress_pembangunan_id' => ['nullable', 'exists:progress_pembangunans,id'],
            'hasil' => ['required', 'in:sesuai,defect,perlu_perbaikan'],
            'item_pemeriksaan' => ['required', 'string'],
            'temuan' => ['nullable', 'string'],
            'tindakan_perbaikan' => ['nullable', 'string'],
            'target_selesai' => ['nullable', 'date'],
            'status' => ['required', 'in:terbuka,dalam_perbaikan,selesai'],
            'foto' => ['nullable', 'image', 'max:4096'],
        ]);

        $approvalRequired = $this->requiresApprovalFor('quality-inspection', 'create');

        $inspection = QualityInspection::query()->create([
            ...$this->normalizePayload(collect($validated)->except('foto')->all()),
            'kode_inspeksi' => 'QC-'.now()->format('Ymd-His').'-'.random_int(10, 99),
            'foto' => $request->file('foto')?->store('kontrol-kualitas', 'public'),
            'approval_status' => $approvalRequired ? 'menunggu_approval_manager' : 'approved',
            'approved_by' => $approvalRequired ? null : auth()->id(),
            'approved_at' => $approvalRequired ? null : now(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        if (! $approvalRequired) {
            $this->syncDefectFromInspection($inspection);
        }

        return redirect()->route('admin.quality-inspection.index')->with('success', $approvalRequired
            ? 'Hasil kontrol kualitas berhasil disimpan dan menunggu approval.'
            : 'Hasil kontrol kualitas berhasil disimpan dan langsung aktif.');
    }

    public function approve(string $id): RedirectResponse
    {
        abort_unless($this->requiresApprovalFor('quality-inspection'), 422, 'Kontrol kualitas ini tidak memerlukan approval.');
        abort_unless($this->canApproveFor('quality-inspection'), 403, 'Anda tidak memiliki izin approval inspeksi.');
        $row = QualityInspection::query()->findOrFail($id);
        abort_unless(($row->record_status ?? 'draft') === 'locked', 422, 'Kontrol kualitas harus di-lock terlebih dahulu.');
        if ($row->approval_status === 'approved') {
            return back()->with('success', 'Kontrol kualitas sudah disetujui sebelumnya.');
        }

        $row->update(['approval_status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        $this->syncDefectFromInspection($row->fresh());

        return back()->with('success', 'Kontrol kualitas berhasil disetujui.');
    }

    public function lock(string $id): RedirectResponse
    {
        $this->authorizeQualityInspection('update');

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        abort_unless($this->canQualityInspection('unlock') || $this->currentUserCanManageLockedRecords(), 403, 'Hanya user yang diberi akses yang dapat membuka lock inspeksi.');

        return $this->traitUnlock($id);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeQualityInspection('update');
        $row = QualityInspection::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $validated = $this->validatedPayload($request);
        $foto = $row->foto;

        if ($request->hasFile('foto')) {
            if ($foto) {
                Storage::disk('public')->delete($foto);
            }
            $foto = $request->file('foto')->store('kontrol-kualitas', 'public');
        }

        $approvalRequired = $this->requiresApprovalFor('quality-inspection', 'update');

        $row->update([
            ...$this->normalizePayload(collect($validated)->except('foto')->all()),
            'foto' => $foto,
            'approval_status' => $approvalRequired ? 'menunggu_approval_manager' : 'approved',
            'approved_by' => $approvalRequired ? null : auth()->id(),
            'approved_at' => $approvalRequired ? null : now(),
            'updated_by' => auth()->id(),
        ]);
        if (! $approvalRequired) {
            $this->syncDefectFromInspection($row->fresh());
        }

        return redirect()->route('admin.quality-inspection.index')->with('success', $approvalRequired
            ? 'Kontrol kualitas berhasil diperbarui dan menunggu approval.'
            : 'Kontrol kualitas berhasil diperbarui dan langsung aktif.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeQualityInspection('delete');
        $row = QualityInspection::query()->findOrFail($id);
        $this->abortIfLocked($row);
        if ($row->foto) {
            Storage::disk('public')->delete($row->foto);
        }
        $row->delete();

        return back()->with('success', 'Data kontrol kualitas berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return QualityInspection::class;
    }

    private function authorizeQualityInspection(string $action): void
    {
        abort_unless($this->canQualityInspection($action), 403, 'Anda tidak memiliki permission kontrol kualitas.');
    }

    private function canQualityInspection(string $action): bool
    {
        $user = auth()->user();

        return (bool) (
            $user?->hasRole('super_admin')
            || $user?->can("quality-inspection.{$action}")
            || $user?->can('quality-inspection.manage')
        );
    }

    private function formRow(QualityInspection $row): array
    {
        return [
            'id' => $row->id,
            'kode_inspeksi' => $row->kode_inspeksi,
            'tanggal' => optional($row->tanggal)->format('Y-m-d'),
            'perumahan_id' => (string) $row->perumahan_id,
            'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
            'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
            'site_schedule_id' => (string) ($row->site_schedule_id ?? ''),
            'progress_pembangunan_id' => (string) ($row->progress_pembangunan_id ?? ''),
            'hasil' => $row->hasil,
            'item_pemeriksaan' => $row->item_pemeriksaan,
            'temuan' => $row->temuan,
            'tindakan_perbaikan' => $row->tindakan_perbaikan,
            'target_selesai' => optional($row->target_selesai)->format('Y-m-d'),
            'status' => $row->status,
        ];
    }

    protected function syncDefectFromInspection(QualityInspection $inspection): void
    {
        if (! in_array($inspection->hasil, ['defect', 'perlu_perbaikan'], true)) {
            return;
        }

        FieldDefect::query()->updateOrCreate(
            ['quality_inspection_id' => $inspection->id],
            [
                'kode_defect' => FieldDefect::query()->where('quality_inspection_id', $inspection->id)->value('kode_defect') ?: 'DEF-'.now()->format('ymd-His').'-'.random_int(10, 99),
                'tanggal' => $inspection->tanggal,
                'perumahan_id' => $inspection->perumahan_id,
                'detail_rumah_id' => $inspection->detail_rumah_id,
                'tahapan_pembangunan_id' => $inspection->tahapan_pembangunan_id,
                'progress_pembangunan_id' => $inspection->progress_pembangunan_id,
                'kategori' => 'pekerjaan',
                'prioritas' => $inspection->hasil === 'defect' ? 'high' : 'medium',
                'temuan' => $inspection->temuan ?: $inspection->item_pemeriksaan,
                'instruksi_perbaikan' => $inspection->tindakan_perbaikan,
                'target_selesai' => $inspection->target_selesai,
                'status' => match ($inspection->status) {
                    'selesai' => 'selesai',
                    'dalam_perbaikan' => 'dalam_perbaikan',
                    default => 'open',
                },
                'foto' => $inspection->foto,
                'approval_status' => $inspection->approval_status,
                'approved_by' => $inspection->approved_by,
                'approved_at' => $inspection->approved_at,
                'created_by' => $inspection->created_by,
                'updated_by' => auth()->id(),
            ],
        );
    }

    private function options(): array
    {
        $options = $this->fieldOptions();
        $options['tahapanPembangunansUnit'] = app(TahapanOptionService::class)->forContext('unit');
        $options['tahapanPembangunansKawasan'] = app(TahapanOptionService::class)->forContext('kawasan');
        $options['siteSchedules'] = SiteSchedule::query()
            ->with(['perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah'])
            ->orderBy('tanggal_target')
            ->get(['id', 'perumahan_id', 'detail_rumah_id', 'tahapan_pembangunan_id', 'nama_pekerjaan', 'target_progress', 'realisasi_progress'])
            ->map(fn (SiteSchedule $row) => [
                'value' => (string) $row->id,
                'label' => ($row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : 'Kawasan').': '.$row->nama_pekerjaan.' ('.$row->realisasi_progress.'/'.$row->target_progress.'%)',
                'perumahan_id' => (string) $row->perumahan_id,
                'detail_rumah_id' => (string) $row->detail_rumah_id,
                'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                'nama_pekerjaan' => $row->nama_pekerjaan,
            ])
            ->values();
        $options['progressPembangunans'] = ProgressPembangunan::query()
            ->with([
                'detailRumah:id,perumahan_id',
                'siteSchedule:id,perumahan_id,detail_rumah_id,tahapan_pembangunan_id,nama_pekerjaan',
            ])
            ->where('approval_status', 'approved')
            ->latest('tanggal')
            ->get(['id', 'detail_rumah_id', 'tahapan_pembangunan_id', 'site_schedule_id', 'nama_progress', 'persentase'])
            ->map(fn (ProgressPembangunan $row) => [
                'value' => (string) $row->id,
                'label' => $row->nama_progress.' - '.$row->persentase.'%',
                'perumahan_id' => (string) ($row->detailRumah?->perumahan_id ?? $row->siteSchedule?->perumahan_id ?? ''),
                'detail_rumah_id' => (string) $row->detail_rumah_id,
                'tahapan_pembangunan_id' => (string) $row->tahapan_pembangunan_id,
                'site_schedule_id' => (string) ($row->site_schedule_id ?? ''),
                'nama_progress' => $row->nama_progress,
            ])
            ->values();

        return $options;
    }

    private function normalizePayload(array $payload): array
    {
        foreach (['detail_rumah_id', 'tahapan_pembangunan_id', 'site_schedule_id', 'progress_pembangunan_id'] as $key) {
            if (array_key_exists($key, $payload) && blank($payload[$key])) {
                $payload[$key] = null;
            }
        }

        if (! empty($payload['progress_pembangunan_id'])) {
            $progress = ProgressPembangunan::query()->with('detailRumah:id,perumahan_id')->find($payload['progress_pembangunan_id']);
            if ($progress) {
                $payload['detail_rumah_id'] = $progress->detail_rumah_id;
                $payload['perumahan_id'] = $progress->detailRumah?->perumahan_id ?? $payload['perumahan_id'];
                $payload['tahapan_pembangunan_id'] = $progress->tahapan_pembangunan_id;
                $payload['site_schedule_id'] = $progress->site_schedule_id ?: $payload['site_schedule_id'];
            }
        } elseif (! empty($payload['site_schedule_id'])) {
            $schedule = SiteSchedule::query()->find($payload['site_schedule_id']);
            if ($schedule) {
                $payload['perumahan_id'] = $schedule->perumahan_id;
                $payload['detail_rumah_id'] = $schedule->detail_rumah_id;
                $payload['tahapan_pembangunan_id'] = $schedule->tahapan_pembangunan_id;
            }
        }

        return $payload;
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'site_schedule_id' => ['nullable', 'exists:site_schedules,id'],
            'progress_pembangunan_id' => ['nullable', 'exists:progress_pembangunans,id'],
            'hasil' => ['required', 'in:sesuai,defect,perlu_perbaikan'],
            'item_pemeriksaan' => ['required', 'string'],
            'temuan' => ['nullable', 'string'],
            'tindakan_perbaikan' => ['nullable', 'string'],
            'target_selesai' => ['nullable', 'date'],
            'status' => ['required', 'in:terbuka,dalam_perbaikan,selesai'],
            'foto' => ['nullable', 'image', 'max:4096'],
        ]);
    }
}
