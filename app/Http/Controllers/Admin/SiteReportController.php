<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsFieldOptions;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Concerns\UsesApprovalSettings;
use App\Http\Controllers\Controller;
use App\Models\ProgressPembangunan;
use App\Models\SiteReport;
use App\Models\SiteSchedule;
use App\Models\TahapanPembangunan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteReportController extends Controller
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

        return Inertia::render('Admin/SiteReport/Index', [
            'title' => 'Laporan Lapangan',
            'baseUrl' => route('admin.site-report.index', absolute: false),
            'filters' => ['search' => $search, 'perumahan_id' => $perumahanId, 'detail_rumah_id' => $detailRumahId],
            'options' => $this->options(),
            'permissions' => [
                'canCreate' => $this->canSiteReport('create'),
                'canUpdate' => $this->canSiteReport('update'),
                'canDelete' => $this->canSiteReport('delete'),
                'canLock' => $this->canSiteReport('update'),
                'canUnlock' => $this->canSiteReport('unlock') || $this->currentUserCanManageLockedRecords(),
            ],
            'rows' => SiteReport::query()
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
                    ->where('kode_laporan', 'like', "%{$search}%")
                    ->orWhere('pekerjaan_selesai', 'like', "%{$search}%")
                    ->orWhereHas('perumahan', fn (Builder $query) => $query->where('nama_perusahaan', 'like', "%{$search}%")))
                ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
                ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
                ->latest('tanggal')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (SiteReport $row) => [
                    'id' => $row->id,
                    'kode_laporan' => $row->kode_laporan,
                    'jenis_laporan' => $row->jenis_laporan,
                    'tanggal' => optional($row->tanggal)->format('Y-m-d'),
                    'periode_mulai' => optional($row->periode_mulai)->format('Y-m-d'),
                    'periode_selesai' => optional($row->periode_selesai)->format('Y-m-d'),
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
                    'cuaca' => $row->cuaca,
                    'jumlah_pekerja' => $row->jumlah_pekerja,
                    'kontraktor' => $row->kontraktor,
                    'pekerjaan_selesai' => $row->pekerjaan_selesai,
                    'pekerjaan_tertahan' => $row->pekerjaan_tertahan,
                    'kendala' => $row->kendala,
                    'koordinasi' => $row->koordinasi,
                    'rencana_berikutnya' => $row->rencana_berikutnya,
                    'petugas' => $row->creator?->name ?? '-',
                    'created_by_name' => $row->creator?->name ?? '-',
                    'updated_by_name' => $row->updater?->name ?? '-',
                    'approved_by_name' => $row->approvedBy?->name ?? '-',
                    'lampiran_url' => $row->lampiran ? route('media', ['path' => $row->lampiran], false) : null,
                    'approval_status' => $row->approval_status,
                    'can_approve' => ($row->record_status ?? 'draft') === 'locked' && $this->requiresApprovalFor('site-report') && $row->approval_status !== 'approved' && $this->canApproveFor('site-report'),
                    'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && $this->canSiteReport('update'),
                    'can_unlock' => $this->canSiteReport('unlock') || $this->currentUserCanManageLockedRecords(),
                    'can_edit' => ($row->record_status ?? 'draft') !== 'locked' && $this->canSiteReport('update'),
                    'can_delete' => ($row->record_status ?? 'draft') !== 'locked' && $this->canSiteReport('delete'),
                    'record_status' => $row->record_status ?? 'draft',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSiteReport('create');
        $validated = $request->validate([
            'jenis_laporan' => ['required', 'in:harian,mingguan'],
            'tanggal' => ['required', 'date'],
            'periode_mulai' => ['nullable', 'date'],
            'periode_selesai' => ['nullable', 'date', 'after_or_equal:periode_mulai'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'site_schedule_id' => ['nullable', 'exists:site_schedules,id'],
            'progress_pembangunan_id' => ['nullable', 'exists:progress_pembangunans,id'],
            'cuaca' => ['nullable', 'string', 'max:100'],
            'jumlah_pekerja' => ['required', 'integer', 'min:0'],
            'kontraktor' => ['nullable', 'string', 'max:255'],
            'pekerjaan_selesai' => ['required', 'string'],
            'pekerjaan_tertahan' => ['nullable', 'string'],
            'kendala' => ['nullable', 'string'],
            'koordinasi' => ['nullable', 'string'],
            'rencana_berikutnya' => ['nullable', 'string'],
            'lampiran' => ['nullable', 'file', 'max:6144'],
        ]);

        $approvalRequired = $this->requiresApprovalFor('site-report', 'create');

        SiteReport::query()->create([
            ...$this->normalizePayload(collect($validated)->except('lampiran')->all()),
            'kode_laporan' => 'LAP-'.now()->format('Ymd-His').'-'.random_int(10, 99),
            'lampiran' => $request->file('lampiran')?->store('laporan-lapangan', 'public'),
            'approval_status' => $approvalRequired ? 'menunggu_approval_manager' : 'approved',
            'approved_by' => $approvalRequired ? null : auth()->id(),
            'approved_at' => $approvalRequired ? null : now(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', $approvalRequired
            ? 'Laporan lapangan berhasil dibuat dan menunggu review manajer.'
            : 'Laporan lapangan berhasil disimpan dan langsung aktif.');
    }

    public function approve(string $id): RedirectResponse
    {
        abort_unless($this->requiresApprovalFor('site-report'), 422, 'Laporan ini tidak memerlukan approval.');
        abort_unless($this->canApproveFor('site-report'), 403, 'Anda tidak memiliki izin approval laporan.');
        $row = SiteReport::query()->findOrFail($id);
        abort_unless(($row->record_status ?? 'draft') === 'locked', 422, 'Laporan harus di-lock terlebih dahulu.');
        if ($row->approval_status === 'approved') {
            return back()->with('success', 'Laporan lapangan sudah disetujui sebelumnya.');
        }

        $row->update(['approval_status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);

        return back()->with('success', 'Laporan lapangan berhasil disetujui.');
    }

    public function lock(string $id): RedirectResponse
    {
        $this->authorizeSiteReport('update');

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        abort_unless($this->canSiteReport('unlock') || $this->currentUserCanManageLockedRecords(), 403, 'Hanya user yang diberi akses yang dapat membuka lock laporan.');

        return $this->traitUnlock($id);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeSiteReport('update');
        $row = SiteReport::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $validated = $this->validatedPayload($request);
        $lampiran = $row->lampiran;

        if ($request->hasFile('lampiran')) {
            if ($lampiran) {
                Storage::disk('public')->delete($lampiran);
            }
            $lampiran = $request->file('lampiran')->store('laporan-lapangan', 'public');
        }

        $approvalRequired = $this->requiresApprovalFor('site-report', 'update');

        $row->update([
            ...$this->normalizePayload(collect($validated)->except('lampiran')->all()),
            'lampiran' => $lampiran,
            'approval_status' => $approvalRequired ? 'menunggu_approval_manager' : 'approved',
            'approved_by' => $approvalRequired ? null : auth()->id(),
            'approved_at' => $approvalRequired ? null : now(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', $approvalRequired
            ? 'Laporan lapangan berhasil diperbarui dan menunggu review manajer.'
            : 'Laporan lapangan berhasil diperbarui dan langsung aktif.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeSiteReport('delete');
        $row = SiteReport::query()->findOrFail($id);
        $this->abortIfLocked($row);
        if ($row->lampiran) {
            Storage::disk('public')->delete($row->lampiran);
        }
        $row->delete();

        return back()->with('success', 'Laporan lapangan berhasil dihapus.');
    }

    protected function modelClass(): string
    {
        return SiteReport::class;
    }

    private function authorizeSiteReport(string $action): void
    {
        abort_unless($this->canSiteReport($action), 403, 'Anda tidak memiliki permission laporan lapangan.');
    }

    private function canSiteReport(string $action): bool
    {
        $user = auth()->user();

        return (bool) (
            $user?->hasRole('super_admin')
            || $user?->can("site-report.{$action}")
            || $user?->can('site-report.manage')
        );
    }

    private function options(): array
    {
        $options = $this->fieldOptions();
        $options['tahapanPembangunansUnit'] = TahapanPembangunan::query()
            ->where('status', 'aktif')
            ->where('konteks', 'unit')
            ->orderBy('urutan')
            ->get(['id', 'nama_tahapan', 'bobot_persen'])
            ->map(fn (TahapanPembangunan $row) => [
                'value' => (string) $row->id,
                'label' => $row->nama_tahapan.' ('.$row->bobot_persen.'%)',
            ])
            ->values();
        $options['tahapanPembangunansKawasan'] = TahapanPembangunan::query()
            ->where('status', 'aktif')
            ->where('konteks', 'kawasan')
            ->orderBy('urutan')
            ->get(['id', 'nama_tahapan', 'bobot_persen'])
            ->map(fn (TahapanPembangunan $row) => [
                'value' => (string) $row->id,
                'label' => $row->nama_tahapan.' ('.$row->bobot_persen.'%)',
            ])
            ->values();
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
            'jenis_laporan' => ['required', 'in:harian,mingguan'],
            'tanggal' => ['required', 'date'],
            'periode_mulai' => ['nullable', 'date'],
            'periode_selesai' => ['nullable', 'date', 'after_or_equal:periode_mulai'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'site_schedule_id' => ['nullable', 'exists:site_schedules,id'],
            'progress_pembangunan_id' => ['nullable', 'exists:progress_pembangunans,id'],
            'cuaca' => ['nullable', 'string', 'max:100'],
            'jumlah_pekerja' => ['required', 'integer', 'min:0'],
            'kontraktor' => ['nullable', 'string', 'max:255'],
            'pekerjaan_selesai' => ['required', 'string'],
            'pekerjaan_tertahan' => ['nullable', 'string'],
            'kendala' => ['nullable', 'string'],
            'koordinasi' => ['nullable', 'string'],
            'rencana_berikutnya' => ['nullable', 'string'],
            'lampiran' => ['nullable', 'file', 'max:6144'],
        ]);
    }
}
