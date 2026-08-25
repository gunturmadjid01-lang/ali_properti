<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BuildsFieldOptions;
use App\Http\Controllers\Controller;
use App\Models\DetailRumah;
use App\Models\FieldDefect;
use App\Models\InternalHandover;
use App\Models\MaterialUsage;
use App\Models\ProgressPembangunan;
use App\Models\QualityInspection;
use App\Models\SafetyReport;
use App\Models\SiteManpowerLog;
use App\Models\SiteReport;
use App\Models\SiteSchedule;
use App\Models\WorkChangeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FieldWorkspaceController extends Controller
{
    use BuildsFieldOptions;

    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->hasAnyRole(['pengawas', 'manajer_pimpro', 'owner', 'super_admin']) || $request->user()?->can('field-supervision.view'), 403);
        $perumahanId = $request->integer('perumahan_id') ?: null;
        $unitId = $request->integer('detail_rumah_id') ?: null;
        $scheduleId = $request->integer('site_schedule_id') ?: null;
        $unit = $unitId ? DetailRumah::query()->with('perumahan:id,nama_perusahaan')->find($unitId) : null;
        if ($unit && $perumahanId && (int) $unit->perumahan_id !== $perumahanId) abort(422, 'Unit tidak berada pada perumahan yang dipilih.');
        $scope = fn (Builder $query) => $query
            ->when($perumahanId, fn (Builder $q, int $id) => $q->where('perumahan_id', $id))
            ->when($unitId, fn (Builder $q, int $id) => $q->where('detail_rumah_id', $id));
        $progressScope = fn (Builder $query) => $query
            ->when($perumahanId && ! $unitId, fn (Builder $q) => $q->whereHas('detailRumah', fn (Builder $unitQuery) => $unitQuery->where('perumahan_id', $perumahanId)))
            ->when($unitId, fn (Builder $q, int $id) => $q->where('detail_rumah_id', $id));

        $cards = [
            ['key' => 'schedule', 'label' => 'Jadwal Aktif', 'count' => SiteSchedule::query()->tap($scope)->where('status', '!=', 'selesai')->count(), 'href' => '/admin/jadwal-lapangan'],
            ['key' => 'progress', 'label' => 'Progress Disetujui', 'count' => ProgressPembangunan::query()->tap($progressScope)->where('approval_status', 'approved')->count(), 'href' => '/admin/progress-pembangunan'],
            ['key' => 'report', 'label' => 'Laporan Lapangan', 'count' => SiteReport::query()->tap($scope)->count(), 'href' => '/admin/laporan-lapangan'],
            ['key' => 'material', 'label' => 'Pemakaian Material', 'count' => MaterialUsage::query()->tap($scope)->count(), 'href' => '/admin/pemakaian-material'],
            ['key' => 'manpower', 'label' => 'Tenaga & Alat', 'count' => SiteManpowerLog::query()->tap($scope)->count(), 'href' => '/admin/pengawasan/tenaga-kerja-alat'],
            ['key' => 'quality', 'label' => 'Kontrol Kualitas', 'count' => QualityInspection::query()->tap($scope)->count(), 'href' => '/admin/kontrol-kualitas'],
            ['key' => 'defect', 'label' => 'Defect Terbuka', 'count' => FieldDefect::query()->tap($scope)->whereNotIn('status', ['selesai', 'closed'])->count(), 'href' => '/admin/pengawasan/defect'],
            ['key' => 'safety', 'label' => 'Temuan K3 Aktif', 'count' => SafetyReport::query()->tap($scope)->whereNotIn('status', ['selesai', 'closed'])->count(), 'href' => '/admin/pengawasan/k3'],
            ['key' => 'change', 'label' => 'Perubahan Pekerjaan', 'count' => WorkChangeRequest::query()->tap($scope)->count(), 'href' => '/admin/pengawasan/perubahan-pekerjaan'],
            ['key' => 'handover', 'label' => 'Serah Terima Internal', 'count' => InternalHandover::query()->tap($scope)->count(), 'href' => '/admin/pengawasan/serah-terima-internal'],
        ];
        $options = $this->fieldOptions();
        $options['siteSchedules'] = SiteSchedule::query()->with('detailRumah:id,kode_nlok,nomor_rumah')
            ->when($perumahanId, fn (Builder $q, int $id) => $q->where('perumahan_id', $id))
            ->when($unitId, fn (Builder $q, int $id) => $q->where('detail_rumah_id', $id))
            ->orderBy('tanggal_target')->get()->map(fn (SiteSchedule $row) => ['value' => (string) $row->id, 'label' => ($row->detailRumah?->display_label ?? 'Kawasan').' - '.$row->nama_pekerjaan, 'perumahan_id' => (string) $row->perumahan_id, 'detail_rumah_id' => (string) ($row->detail_rumah_id ?? '')]);

        return Inertia::render('Admin/FieldWorkspace/Index', [
            'title' => 'Workspace Harian Pengawas', 'options' => $options, 'cards' => $cards,
            'context' => ['perumahan_id' => (string) ($perumahanId ?? ''), 'detail_rumah_id' => (string) ($unitId ?? ''), 'site_schedule_id' => (string) ($scheduleId ?? '')],
            'unit' => $unit ? ['label' => $unit->display_label, 'perumahan' => $unit->perumahan?->nama_perusahaan, 'progress' => (float) $unit->progress_terakhir, 'status' => $unit->status_pembangunan] : null,
        ]);
    }
}
