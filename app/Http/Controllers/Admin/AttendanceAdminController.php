<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\CabangPerusahaan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceAdminController extends Controller
{
    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->can('attendance.view') || $request->user()?->hasRole('super_admin'), 403);
    }

    public function index(Request $request): Response
    {
        $this->authorizeView($request);
        $search = trim((string) $request->query('search'));
        $dateFrom = Carbon::parse($request->query('date_from', today()->subDays(6)->toDateString()))->startOfDay();
        $dateTo = Carbon::parse($request->query('date_to', today()->toDateString()))->endOfDay();
        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }
        if ($dateFrom->diffInDays($dateTo) > 92) {
            $dateFrom = $dateTo->copy()->subDays(92)->startOfDay();
        }
        $branchId = $request->integer('branch_id') ?: null;
        $type = in_array($request->query('type'), ['check_in', 'check_out'], true) ? $request->query('type') : null;
        $radius = in_array($request->query('radius'), ['inside', 'outside'], true) ? $request->query('radius') : null;
        $timeStatus = in_array($request->query('time_status'), ['on_time', 'late', 'early_leave', 'late_leave'], true) ? $request->query('time_status') : null;

        $query = AttendanceRecord::query()
            ->whereDate('attendance_date', '>=', $dateFrom->toDateString())
            ->whereDate('attendance_date', '<=', $dateTo->toDateString())
            ->when($search, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('employee_number', 'like', "%{$search}%")))
            ->when($branchId, fn ($q) => $q->where('cabang_perusahaan_id', $branchId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($radius, fn ($q) => $q->where('is_within_radius', $radius === 'inside'))
            ->when($timeStatus, fn ($q) => $q->where('time_status', $timeStatus));

        $total = (clone $query)->count();
        $statistics = [
            ['label' => 'Total Catatan', 'value' => $total, 'tone' => 'slate'],
            ['label' => 'Pegawai Hadir', 'value' => (clone $query)->where('type', 'check_in')->distinct('user_id')->count('user_id'), 'tone' => 'blue'],
            ['label' => 'Tepat Waktu', 'value' => (clone $query)->where('time_status', 'on_time')->count(), 'tone' => 'emerald'],
            ['label' => 'Terlambat Masuk', 'value' => (clone $query)->where('time_status', 'late')->count(), 'tone' => 'amber'],
            ['label' => 'Di Luar Radius', 'value' => (clone $query)->where('is_within_radius', false)->count(), 'tone' => 'red'],
            ['label' => 'Pulang Tidak Wajar', 'value' => (clone $query)->whereIn('time_status', ['early_leave', 'late_leave'])->count(), 'tone' => 'violet'],
        ];

        $daily = (clone $query)->reorder()->selectRaw('attendance_date, SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as check_in_count, SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as check_out_count, SUM(CASE WHEN is_within_radius = 0 THEN 1 ELSE 0 END) as outside_count', ['check_in', 'check_out'])->groupBy('attendance_date')->orderBy('attendance_date')->get()->keyBy(fn ($row) => Carbon::parse($row->attendance_date)->toDateString());
        $chart = collect(range(0, $dateFrom->diffInDays($dateTo)))->map(function (int $offset) use ($dateFrom, $daily) {
            $date = $dateFrom->copy()->addDays($offset);
            $row = $daily->get($date->toDateString());

            return ['date' => $date->toDateString(), 'label' => $date->format('d M'), 'check_in' => (int) ($row?->check_in_count ?? 0), 'check_out' => (int) ($row?->check_out_count ?? 0), 'outside' => (int) ($row?->outside_count ?? 0)];
        })->values();

        $rows = (clone $query)->with(['user:id,name,employee_number', 'branch:id,nama_cabang'])
            ->latest('recorded_at')->paginate(20)->withQueryString()->through(fn (AttendanceRecord $r) => [
                'id' => $r->id, 'employee' => $r->user?->name, 'employee_number' => $r->user?->employee_number,
                'branch' => $r->branch?->nama_cabang, 'date' => $r->attendance_date->format('d/m/Y'),
                'time' => $r->recorded_at->format('H:i:s'), 'type' => $r->type, 'distance' => round((float) $r->distance_meters),
                'within_radius' => $r->is_within_radius, 'time_status' => $r->time_status,
            ]);

        return Inertia::render('Admin/Attendance/Index', [
            'rows' => $rows,
            'filters' => ['search' => $search, 'date_from' => $dateFrom->toDateString(), 'date_to' => $dateTo->toDateString(), 'branch_id' => $branchId ? (string) $branchId : '', 'type' => $type ?? '', 'radius' => $radius ?? '', 'time_status' => $timeStatus ?? ''],
            'filterOptions' => ['branches' => CabangPerusahaan::query()->finalized()->orderBy('nama_cabang')->get(['id', 'nama_cabang'])->map(fn ($b) => ['value' => (string) $b->id, 'label' => $b->nama_cabang])],
            'statistics' => $statistics, 'chart' => $chart,
            'canManageSettings' => $request->user()?->can('attendance.settings') || $request->user()?->hasRole('super_admin'),
        ]);
    }

    public function show(Request $request, AttendanceRecord $attendance): Response
    {
        $this->authorizeView($request);
        $attendance->load(['user:id,name,employee_number,job_title', 'branch:id,nama_cabang,address,latitude,longtitude,attendance_radius_meters']);

        return Inertia::render('Admin/Attendance/Show', ['record' => [
            'id' => $attendance->id, 'employee' => $attendance->user?->name, 'employee_number' => $attendance->user?->employee_number,
            'job_title' => $attendance->user?->job_title, 'branch' => $attendance->branch?->nama_cabang, 'branch_address' => $attendance->branch?->address,
            'date' => $attendance->attendance_date->format('d F Y'), 'time' => $attendance->recorded_at->format('H:i:s'), 'type' => $attendance->type,
            'latitude' => (float) $attendance->latitude, 'longitude' => (float) $attendance->longitude,
            'office_latitude' => (float) $attendance->branch?->latitude, 'office_longitude' => (float) $attendance->branch?->longtitude,
            'radius' => (int) $attendance->branch?->attendance_radius_meters, 'distance' => round((float) $attendance->distance_meters),
            'within_radius' => $attendance->is_within_radius, 'time_status' => $attendance->time_status,
            'accuracy' => $attendance->accuracy_meters, 'photo_url' => route('media', ['path' => $attendance->photo_path]),
            'approval_status' => $attendance->record_status,
        ]]);
    }
}
