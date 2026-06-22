<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsFieldOptions;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\ProgressPembangunan;
use App\Models\SiteReport;
use App\Models\SiteSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteReportController extends Controller
{
    use BuildsFieldOptions, HandlesCrudLock {
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
                    'can_approve' => $row->approval_status !== 'approved' && $this->canApproveFieldData(),
                    'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && (bool) auth()->user()?->hasAnyRole(['pengawas', 'manajer_pimpro', 'owner', 'super_admin']),
                    'can_unlock' => (bool) auth()->user()?->hasAnyRole(['owner', 'super_admin']),
                    'can_edit' => ($row->record_status ?? 'draft') !== 'locked',
                    'can_delete' => ($row->record_status ?? 'draft') !== 'locked',
                    'record_status' => $row->record_status ?? 'draft',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeFieldUser();
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

        SiteReport::query()->create([
            ...$this->normalizePayload(collect($validated)->except('lampiran')->all()),
            'kode_laporan' => 'LAP-'.now()->format('Ymd-His').'-'.random_int(10, 99),
            'lampiran' => $request->file('lampiran')?->store('laporan-lapangan', 'public'),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Laporan lapangan berhasil dibuat dan menunggu review manager.');
    }

    public function approve(string $id): RedirectResponse
    {
        abort_unless($this->canApproveFieldData(), 403, 'Hanya manager atau owner yang dapat menyetujui laporan.');
        $row = SiteReport::query()->findOrFail($id);
        if ($row->approval_status === 'approved') {
            return back()->with('success', 'Laporan lapangan sudah disetujui sebelumnya.');
        }

        $row->update(['approval_status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);

        return back()->with('success', 'Laporan lapangan berhasil disetujui.');
    }

    public function lock(string $id): RedirectResponse
    {
        $this->authorizeFieldUser();

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['owner', 'super_admin']), 403, 'Hanya owner yang dapat membuka lock laporan.');

        return $this->traitUnlock($id);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeFieldUser();
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

        $row->update([
            ...$this->normalizePayload(collect($validated)->except('lampiran')->all()),
            'lampiran' => $lampiran,
            'approval_status' => 'menunggu_approval_manager',
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Laporan lapangan berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeFieldUser();
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

    private function options(): array
    {
        $options = $this->fieldOptions();
        $options['siteSchedules'] = SiteSchedule::query()
            ->whereNotNull('detail_rumah_id')
            ->orderBy('tanggal_target')
            ->get(['id', 'perumahan_id', 'detail_rumah_id', 'tahapan_pembangunan_id', 'nama_pekerjaan', 'target_progress', 'realisasi_progress'])
            ->map(fn (SiteSchedule $row) => [
                'value' => (string) $row->id,
                'label' => $row->nama_pekerjaan.' ('.$row->realisasi_progress.'/'.$row->target_progress.'%)',
                'perumahan_id' => (string) $row->perumahan_id,
                'detail_rumah_id' => (string) $row->detail_rumah_id,
                'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                'nama_pekerjaan' => $row->nama_pekerjaan,
            ])
            ->values();
        $options['progressPembangunans'] = ProgressPembangunan::query()
            ->with('detailRumah:id,perumahan_id')
            ->where('approval_status', 'approved')
            ->latest('tanggal')
            ->get(['id', 'detail_rumah_id', 'tahapan_pembangunan_id', 'site_schedule_id', 'nama_progress', 'persentase'])
            ->map(fn (ProgressPembangunan $row) => [
                'value' => (string) $row->id,
                'label' => $row->nama_progress.' - '.$row->persentase.'%',
                'perumahan_id' => (string) ($row->detailRumah?->perumahan_id ?? ''),
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
