<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsFieldOptions;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\SiteSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SiteScheduleController extends Controller
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

        return Inertia::render('Admin/SiteSchedule/Index', [
            'title' => 'Jadwal Lapangan',
            'baseUrl' => route('admin.site-schedule.index', absolute: false),
            'filters' => ['search' => $search, 'perumahan_id' => $perumahanId, 'detail_rumah_id' => $detailRumahId],
            'options' => $this->fieldOptions(),
            'rows' => SiteSchedule::query()
                ->with([
                    'perumahan:id,nama_perusahaan',
                    'detailRumah:id,kode_nlok,nomor_rumah',
                    'tahapanPembangunan:id,nama_tahapan',
                    'creator:id,name',
                    'updater:id,name',
                ])
                ->when($search !== '', fn (Builder $query) => $query
                    ->where('kode_jadwal', 'like', "%{$search}%")
                    ->orWhere('nama_pekerjaan', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%"))
                ->when($perumahanId, fn (Builder $query) => $query->where('perumahan_id', $perumahanId))
                ->when($detailRumahId, fn (Builder $query) => $query->where('detail_rumah_id', $detailRumahId))
                ->orderBy('tanggal_target')
                ->paginate(10)
                ->withQueryString()
                ->through(fn (SiteSchedule $row) => [
                    'id' => $row->id,
                    'kode_jadwal' => $row->kode_jadwal,
                    'perumahan_id' => (string) $row->perumahan_id,
                    'detail_rumah_id' => (string) ($row->detail_rumah_id ?? ''),
                    'tahapan_pembangunan_id' => (string) ($row->tahapan_pembangunan_id ?? ''),
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'unit' => $row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : 'Kawasan',
                    'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
                    'nama_pekerjaan' => $row->nama_pekerjaan,
                    'tanggal_mulai' => optional($row->tanggal_mulai)->format('Y-m-d'),
                    'tanggal_target' => optional($row->tanggal_target)->format('Y-m-d'),
                    'target_progress' => $row->target_progress,
                    'realisasi_progress' => $row->realisasi_progress,
                    'status' => $row->status,
                    'kendala' => $row->kendala,
                    'catatan' => $row->catatan,
                    'created_by_name' => $row->creator?->name ?? '-',
                    'updated_by_name' => $row->updater?->name ?? '-',
                    'terlambat' => $row->status !== 'selesai' && $row->tanggal_target?->isPast(),
                    'record_status' => $row->record_status ?? 'draft',
                    'can_lock' => ($row->record_status ?? 'draft') !== 'locked' && (bool) auth()->user()?->hasAnyRole(['pengawas', 'manajer_pimpro', 'owner', 'super_admin']),
                    'can_unlock' => (bool) auth()->user()?->hasAnyRole(['owner', 'super_admin']),
                    'can_edit' => ($row->record_status ?? 'draft') !== 'locked',
                    'can_delete' => ($row->record_status ?? 'draft') !== 'locked',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeFieldUser();
        $validated = $request->validate([
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'nama_pekerjaan' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_target' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'target_progress' => ['required', 'numeric', 'min:0', 'max:100'],
            'realisasi_progress' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:direncanakan,berjalan,terlambat,selesai,tertahan'],
            'kendala' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);

        SiteSchedule::query()->create([
            ...$validated,
            'kode_jadwal' => 'JDL-'.now()->format('Ymd-His').'-'.random_int(10, 99),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Jadwal lapangan berhasil dibuat.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeFieldUser();
        $row = SiteSchedule::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->delete();

        return back()->with('success', 'Jadwal lapangan berhasil dihapus.');
    }

    public function lock(string $id): RedirectResponse
    {
        $this->authorizeFieldUser();

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['owner', 'super_admin']), 403, 'Hanya owner yang dapat membuka lock jadwal.');

        return $this->traitUnlock($id);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeFieldUser();
        $row = SiteSchedule::query()->findOrFail($id);
        $this->abortIfLocked($row);
        $row->update([
            ...$this->validatedPayload($request),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Jadwal lapangan berhasil diperbarui.');
    }

    protected function modelClass(): string
    {
        return SiteSchedule::class;
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'nama_pekerjaan' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_target' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'target_progress' => ['required', 'numeric', 'min:0', 'max:100'],
            'realisasi_progress' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:direncanakan,berjalan,terlambat,selesai,tertahan'],
            'kendala' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
        ]);
    }
}
