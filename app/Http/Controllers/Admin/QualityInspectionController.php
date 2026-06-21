<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsFieldOptions;
use App\Http\Controllers\Concerns\HandlesCrudLock;
use App\Http\Controllers\Controller;
use App\Models\QualityInspection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class QualityInspectionController extends Controller
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

        return Inertia::render('Admin/QualityInspection/Index', [
            'title' => 'Kontrol Kualitas',
            'baseUrl' => route('admin.quality-inspection.index', absolute: false),
            'filters' => ['search' => $search, 'perumahan_id' => $perumahanId, 'detail_rumah_id' => $detailRumahId],
            'options' => $this->fieldOptions(),
            'rows' => QualityInspection::query()
                ->with([
                    'perumahan:id,nama_perusahaan',
                    'detailRumah:id,kode_nlok,nomor_rumah',
                    'tahapanPembangunan:id,nama_tahapan',
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
                    'perumahan' => $row->perumahan?->nama_perusahaan ?? '-',
                    'unit' => $row->detailRumah ? trim($row->detailRumah->kode_nlok.' '.$row->detailRumah->nomor_rumah) : 'Kawasan',
                    'tahapan' => $row->tahapanPembangunan?->nama_tahapan ?? '-',
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
                    'can_approve' => $this->canApproveFieldData(),
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
            'tanggal' => ['required', 'date'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
            'hasil' => ['required', 'in:sesuai,defect,perlu_perbaikan'],
            'item_pemeriksaan' => ['required', 'string'],
            'temuan' => ['nullable', 'string'],
            'tindakan_perbaikan' => ['nullable', 'string'],
            'target_selesai' => ['nullable', 'date'],
            'status' => ['required', 'in:terbuka,dalam_perbaikan,selesai'],
            'foto' => ['nullable', 'image', 'max:4096'],
        ]);

        QualityInspection::query()->create([
            ...collect($validated)->except('foto')->all(),
            'kode_inspeksi' => 'QC-'.now()->format('Ymd-His').'-'.random_int(10, 99),
            'foto' => $request->file('foto')?->store('kontrol-kualitas', 'public'),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Hasil kontrol kualitas berhasil disimpan.');
    }

    public function approve(string $id): RedirectResponse
    {
        abort_unless($this->canApproveFieldData(), 403, 'Hanya manager atau owner yang dapat menyetujui inspeksi.');
        $row = QualityInspection::query()->findOrFail($id);
        $row->update(['approval_status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);

        return back()->with('success', 'Kontrol kualitas berhasil disetujui.');
    }

    public function lock(string $id): RedirectResponse
    {
        $this->authorizeFieldUser();

        return $this->traitLock($id);
    }

    public function unlock(string $id): RedirectResponse
    {
        abort_unless(auth()->user()?->hasAnyRole(['owner', 'super_admin']), 403, 'Hanya owner yang dapat membuka lock inspeksi.');

        return $this->traitUnlock($id);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->authorizeFieldUser();
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

        $row->update([
            ...collect($validated)->except('foto')->all(),
            'foto' => $foto,
            'approval_status' => 'menunggu_approval_manager',
            'approved_by' => null,
            'approved_at' => null,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Kontrol kualitas berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->authorizeFieldUser();
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

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date'],
            'perumahan_id' => ['required', 'exists:perumahans,id'],
            'detail_rumah_id' => ['nullable', 'exists:detail_rumahs,id'],
            'tahapan_pembangunan_id' => ['nullable', 'exists:tahapan_pembangunans,id'],
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
