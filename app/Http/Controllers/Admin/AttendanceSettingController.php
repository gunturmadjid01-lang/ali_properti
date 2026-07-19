<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Models\CabangPerusahaan;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can('attendance.settings') || $request->user()?->hasRole('super_admin'), 403);
    }

    public function index(Request $request): Response
    {
        $this->authorizeManage($request);
        return Inertia::render('Admin/Attendance/Settings', [
            'branches' => CabangPerusahaan::query()->finalized()->where('status', 'aktif')->orderBy('nama_cabang')->get(['id', 'nama_cabang'])->map(fn ($b) => ['value' => (string) $b->id, 'label' => $b->nama_cabang]),
            'settings' => AttendanceSetting::query()->with('branch:id,nama_cabang')->orderBy('id')->get()->map(fn ($s) => [
                'id' => $s->id, 'branch_id' => (string) $s->cabang_perusahaan_id, 'branch' => $s->branch?->nama_cabang,
                'check_in_time' => substr($s->check_in_time, 0, 5), 'check_out_time' => substr($s->check_out_time, 0, 5),
                'late_tolerance_minutes' => $s->late_tolerance_minutes, 'checkout_tolerance_minutes' => $s->checkout_tolerance_minutes, 'work_days' => $s->work_days ?? [1,2,3,4,5,6],
                'is_active' => $s->is_active, 'record_status' => $s->record_status,
            ]),
        ]);
    }

    public function store(Request $request, ApprovalWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'cabang_perusahaan_id' => ['required', 'exists:cabang_perusahaans,id'], 'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i', 'after:check_in_time'], 'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:180'], 'checkout_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'work_days' => ['required', 'array', 'min:1'], 'work_days.*' => ['integer', 'between:1,7'], 'is_active' => ['boolean'],
        ]);
        $setting = AttendanceSetting::query()->updateOrCreate(['cabang_perusahaan_id' => $data['cabang_perusahaan_id']], [
            ...$data, 'record_status' => 'locked', 'locked_at' => now(), 'locked_by' => $request->user()->id,
        ]);
        $workflow->cancelPendingLock($setting);
        $workflow->submitLocked($setting, 'attendance-setting');
        return back()->with('success', 'Pengaturan jam absensi berhasil disimpan dan diajukan sesuai Setting Approval.');
    }
}
