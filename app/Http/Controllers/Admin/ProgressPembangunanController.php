<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\UsesApprovalSettings;
use App\Models\DetailRumah;
use App\Models\Perumahan;
use App\Models\ProgressPembangunan;
use App\Models\SiteSchedule;
use App\Models\TahapanPembangunan;
use Illuminate\Database\Eloquent\Model;
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
        $tahapanId = $request->query('tahapan_pembangunan_id');

        $rows = ProgressPembangunan::query()
            ->with([
                'detailRumah.perumahan:id,nama_perusahaan',
                'siteSchedule.perumahan:id,nama_perusahaan',
                'siteSchedule.detailRumah:id,kode_nlok,nomor_rumah',
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
            ->when($tahapanId, fn (Builder $query) => $query->where('tahapan_pembangunan_id', $tahapanId))
            ->latest('tanggal')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ProgressPembangunan $row) => [
                'id' => $row->id,
                'perumahan_id' => (string) ($row->detailRumah?->perumahan_id ?? $row->siteSchedule?->perumahan_id ?? ''),
                'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                'site_schedule_id' => (string) ($row->site_schedule_id ?? ''),
                'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                'nama_progress' => $row->nama_progress,
                'perumahan' => $row->detailRumah?->perumahan?->nama_perusahaan ?? $row->siteSchedule?->perumahan?->nama_perusahaan ?? '-',
                'unit' => $row->detailRumah
                    ? trim(($row->detailRumah->kode_nlok ?? '').' '.($row->detailRumah->nomor_rumah ?? ''))
                    : ($row->siteSchedule?->detailRumah
                        ? trim(($row->siteSchedule->detailRumah->kode_nlok ?? '').' '.($row->siteSchedule->detailRumah->nomor_rumah ?? ''))
                        : 'Kawasan'),
                'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
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
                'tahapan_pembangunan_id' => $tahapanId,
            ],
            'options' => [
                'perumahans' => Perumahan::query()
                    ->orderBy('nama_perusahaan')
                    ->get(['id', 'nama_perusahaan'])
                    ->map(fn (Perumahan $row) => ['value' => (string) $row->id, 'label' => $row->nama_perusahaan])
                    ->values(),
                'detailRumahs' => DetailRumah::query()
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
                'tahapanPembangunans' => app(\App\Services\TahapanOptionService::class)->forContext('unit'),
                'tahapanPembangunansUnit' => app(\App\Services\TahapanOptionService::class)->forContext('unit'),
                'tahapanPembangunansKawasan' => app(\App\Services\TahapanOptionService::class)->forContext('kawasan'),
                'siteSchedules' => SiteSchedule::query()
                    ->with('perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah')
                    ->whereNotNull('tahapan_pembangunan_id')
                    ->orderBy('tanggal_target')
                    ->get(['id', 'perumahan_id', 'detail_rumah_id', 'tahapan_pembangunan_id', 'nama_pekerjaan', 'target_progress', 'realisasi_progress', 'status'])
                    ->map(fn (SiteSchedule $row) => [
                        'value' => (string) $row->id,
                        'label' => ($row->detailRumah ? trim(($row->detailRumah->kode_nlok ?? '').' '.($row->detailRumah->nomor_rumah ?? '')) : 'Kawasan').': '.$row->nama_pekerjaan.' (target '.$row->target_progress.'%, realisasi '.$row->realisasi_progress.'%)',
                        'perumahan_id' => (string) $row->perumahan_id,
                        'detail_rumah_id' => (string) $row->detail_rumah_id,
                        'tahapan_pembangunan_id' => (string) $row->tahapan_pembangunan_id,
                        'nama_pekerjaan' => $row->nama_pekerjaan,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canCreateProgress(), 403, 'Hanya pengawas yang dapat menginput progress pembangunan.');
        $validated = $request->validate([
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['required', 'exists:tahapan_pembangunans,id'],
            'site_schedule_id' => ['required', 'exists:site_schedules,id'],
            'nama_progress' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['required', 'string'],
            'foto' => ['nullable', 'file', 'image', 'max:4096'],
        ]);

        $rumah = filled($validated['detail_rumah_id'] ?? null)
            ? DetailRumah::query()->findOrFail($validated['detail_rumah_id'])
            : null;
        $schedule = SiteSchedule::query()->with(['perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah'])->findOrFail($validated['site_schedule_id']);
        abort_unless((string) $schedule->tahapan_pembangunan_id === (string) $validated['tahapan_pembangunan_id'], 422, 'Tahapan progress harus sesuai dengan jadwal yang dipilih.');
        if (filled($validated['detail_rumah_id'] ?? null)) {
            abort_unless((string) $schedule->detail_rumah_id === (string) $validated['detail_rumah_id'], 422, 'Jadwal yang dipilih tidak sesuai dengan unit yang dipilih.');
        } else {
            abort_unless(blank($schedule->detail_rumah_id), 422, 'Untuk progress kawasan, pilih jadwal kawasan.');
        }
        $tahapan = TahapanPembangunan::query()->findOrFail($validated['tahapan_pembangunan_id']);
        $this->ensureTahapanCapacity($rumah?->id, $tahapan->id, (float) $validated['persentase']);

        $approvalRequired = $this->requiresApprovalFor('progress');

        $progress = ProgressPembangunan::query()->create([
            'detail_rumah_id' => $rumah?->id,
            'tahapan_pembangunan_id' => $tahapan->id,
            'site_schedule_id' => $schedule->id,
            'nama_progress' => $validated['nama_progress'],
            'tanggal' => $validated['tanggal'],
            'tahapan' => $tahapan->bobot_persen,
            'persentase' => $validated['persentase'],
            'persentase_total' => ((float) $validated['persentase'] / 100) * (float) $tahapan->bobot_persen,
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
            'tahapan_pembangunan_id' => ['required', 'exists:tahapan_pembangunans,id'],
            'site_schedule_id' => ['required', 'exists:site_schedules,id'],
            'nama_progress' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['required', 'string'],
            'foto' => ['nullable', 'file', 'image', 'max:4096'],
        ]);

        $rumah = filled($validated['detail_rumah_id'] ?? null)
            ? DetailRumah::query()->findOrFail($validated['detail_rumah_id'])
            : null;
        $schedule = SiteSchedule::query()->with(['perumahan:id,nama_perusahaan', 'detailRumah:id,kode_nlok,nomor_rumah'])->findOrFail($validated['site_schedule_id']);
        abort_unless((string) $schedule->tahapan_pembangunan_id === (string) $validated['tahapan_pembangunan_id'], 422, 'Tahapan progress harus sesuai dengan jadwal yang dipilih.');
        if (filled($validated['detail_rumah_id'] ?? null)) {
            abort_unless((string) $schedule->detail_rumah_id === (string) $validated['detail_rumah_id'], 422, 'Jadwal yang dipilih tidak sesuai dengan unit yang dipilih.');
        } else {
            abort_unless(blank($schedule->detail_rumah_id), 422, 'Untuk progress kawasan, pilih jadwal kawasan.');
        }
        $tahapan = TahapanPembangunan::query()->findOrFail($validated['tahapan_pembangunan_id']);
        $this->ensureTahapanCapacity($rumah?->id, $tahapan->id, (float) $validated['persentase'], $progress->id);
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

        $approvalRequired = $this->requiresApprovalFor('progress');

        $progress->update([
            'detail_rumah_id' => $rumah?->id,
            'tahapan_pembangunan_id' => $tahapan->id,
            'site_schedule_id' => $schedule->id,
            'nama_progress' => $validated['nama_progress'],
            'tanggal' => $validated['tanggal'],
            'tahapan' => $tahapan->bobot_persen,
            'persentase' => $validated['persentase'],
            'persentase_total' => ((float) $validated['persentase'] / 100) * (float) $tahapan->bobot_persen,
            'keterangan' => $validated['keterangan'],
            'foto' => $fotoPath,
            'approval_status' => $approvalRequired ? 'menunggu_approval_manager' : 'approved',
            'approved_by' => $approvalRequired ? null : auth()->id(),
            'approved_at' => $approvalRequired ? null : now(),
            'approved_note' => null,
            'updated_by' => auth()->id(),
        ]);

        if ($oldApprovalStatus === 'approved') {
            $this->recalculateRumahProgress(DetailRumah::query()->find($oldDetailRumahId));
            $this->syncSiteSchedulesFor($oldDetailRumahId, $oldTahapanPembangunanId, $oldSiteScheduleId, $oldNamaProgress);
        } elseif (! $approvalRequired) {
            $this->recalculateRumahProgress($progress->detailRumah);
            $this->syncSiteSchedules($progress->fresh());
        }

        return back()->with('success', $approvalRequired
            ? 'Progress pembangunan berhasil diperbarui dan menunggu approval manajer.'
            : 'Progress pembangunan berhasil diperbarui dan langsung aktif.');
    }

    public function destroy(string $id): RedirectResponse
    {
        abort_unless($this->canDeleteProgress(), 403, 'Hanya pengawas yang dapat menghapus progress pembangunan.');
        $progress = ProgressPembangunan::query()->findOrFail($id);
        $this->abortIfLocked($progress);

        if ($progress->foto) {
            Storage::disk('public')->delete($progress->foto);
        }

        $detailRumahId = $progress->detail_rumah_id;
        $tahapanPembangunanId = $progress->tahapan_pembangunan_id;
        $siteScheduleId = $progress->site_schedule_id;
        $namaProgress = $progress->nama_progress;
        $approvalStatus = $progress->approval_status;
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

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        $this->authorizeManageProgressLock();

        return $this->traitUnlock($id);
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
        $this->ensureTahapanCapacity(
            $progress->detail_rumah_id,
            $progress->tahapan_pembangunan_id,
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
        });

        return back()->with('success', 'Progress pembangunan berhasil disetujui.');
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
        $approvedQuery = ProgressPembangunan::query()
            ->where('detail_rumah_id', $detailRumahId)
            ->where('tahapan_pembangunan_id', $tahapanPembangunanId)
            ->where('approval_status', 'approved');

        $scheduleQuery = SiteSchedule::query();

        if (filled($siteScheduleId)) {
            $approvedQuery->where('site_schedule_id', $siteScheduleId);
            $scheduleQuery->whereKey($siteScheduleId);
        } else {
            $approvedQuery->whereNull('site_schedule_id')->where('nama_progress', $namaProgress);
            $scheduleQuery
                ->where('detail_rumah_id', $detailRumahId)
                ->where('tahapan_pembangunan_id', $tahapanPembangunanId)
                ->where('nama_pekerjaan', $namaProgress);
        }

        $approvedPercent = $approvedQuery->sum('persentase_total');

        $scheduleQuery->get()
            ->each(function (SiteSchedule $schedule) use ($approvedPercent): void {
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
}
